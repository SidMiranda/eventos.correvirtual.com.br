<?php

namespace App\Http\Controllers\Admin;

use App\Models\Event;
use App\Support\ImagensDoEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends AdminController
{
    public function index()
    {
        $events = Event::where('organizer_id', $this->organizerId())
            ->withCount(['modalities', 'kits', 'subscriptions'])
            ->orderByDesc('event_date')
            ->paginate(15);

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.events.create');
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $evento = new Event($dados);
        $evento->organizer_id = $this->organizerId();
        $evento->slug = $this->slugUnico($dados['title']);
        $evento->save();

        // Depois do save: o caminho da imagem no R2 usa o id do evento, que só
        // existe depois de gravar.
        $this->guardarImagens($request, $evento);

        return redirect()
            ->route('admin.eventos.index')
            ->with('sucesso', "Evento \"{$evento->title}\" criado.");
    }

    public function edit(int $id)
    {
        $event = $this->buscarDoOrganizador($id);

        return view('admin.events.edit', compact('event'));
    }

    public function update(Request $request, int $id)
    {
        $event = $this->buscarDoOrganizador($id);
        $dados = $this->validar($request);

        // O slug só é refeito se o título mudou — links já divulgados de um
        // evento existente não podem quebrar porque alguém corrigiu um acento.
        if ($dados['title'] !== $event->title) {
            $event->slug = $this->slugUnico($dados['title'], $event->id);
        }

        $event->fill($dados)->save();

        $this->guardarImagens($request, $event);

        return redirect()
            ->route('admin.eventos.index')
            ->with('sucesso', "Evento \"{$event->title}\" atualizado.");
    }

    public function destroy(int $id)
    {
        $event = $this->buscarDoOrganizador($id);

        // Apagar um evento derruba em cascata modalidades, kits e inscrições —
        // inclusive inscrição paga. Se alguém já se inscreveu, o caminho é
        // desativar (some do site público, o histórico continua de pé).
        if ($event->subscriptions()->exists()) {
            return back()->withErrors([
                'evento' => 'Este evento já tem inscrições e por isso não pode ser apagado. Desative-o para tirá-lo do ar sem perder o histórico.',
            ]);
        }

        $titulo = $event->title;

        // As imagens saem junto: o caminho no bucket é derivado do id do evento,
        // então um evento futuro com o mesmo id herdaria a imagem deste.
        ImagensDoEvento::apagar($event);

        $event->delete();

        return redirect()
            ->route('admin.eventos.index')
            ->with('sucesso', "Evento \"{$titulo}\" apagado.");
    }

    /**
     * Busca o evento JÁ filtrando pelo organizador do usuário.
     *
     * 404 e não 403 de propósito: um organizador não deve nem descobrir que o
     * evento de outro existe. Nunca buscar por ID solto e conferir depois —
     * é assim que nasce o vazamento entre clientes (ver BUG-005).
     */
    private function buscarDoOrganizador(int $id): Event
    {
        return Event::where('id', $id)
            ->where('organizer_id', $this->organizerId())
            ->firstOrFail();
    }

    /**
     * Sobe para o R2 o que veio no formulário.
     *
     * `banner_url` deixou de guardar um nome de arquivo digitado e passou a ser
     * só a marca de "este evento tem imagem" — o caminho é derivado do id do
     * organizador e do evento (ver ImagensDoEvento). Enquanto houver evento
     * antigo com o nome de arquivo lá dentro, o valor continua valendo como
     * marca; o que importa é estar preenchido.
     */
    private function guardarImagens(Request $request, Event $event): void
    {
        $subiuAlguma = false;

        if ($request->hasFile('banner')) {
            ImagensDoEvento::salvarBanner($event, $request->file('banner'));
            $subiuAlguma = true;
        }

        if ($request->hasFile('card')) {
            ImagensDoEvento::salvarCard($event, $request->file('card'));
            $subiuAlguma = true;
        }

        // A imagem que vai no cartão do WhatsApp é derivada da arte, não é um
        // terceiro campo do formulário — pedir mais uma imagem ao organizador
        // só para isso seria trabalho dele para resolver um problema nosso.
        // O card tem preferência por ser o cartaz que já circula na home.
        $arte = $request->file('card') ?? $request->file('banner');

        if ($arte) {
            ImagensDoEvento::gerarOg($event, file_get_contents($arte->getRealPath()));
        }

        if ($subiuAlguma && !$event->banner_url) {
            $event->banner_url = 'banner.jpg';
            $event->save();
        }
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            // Opcional: nem todo evento tem a programação fechada na hora do
            // cadastro. Sem cronograma, o bloco some da página.
            'schedule' => ['nullable', 'string', 'max:5000'],
            // O texto do bloco "Inscrição": o que a inscrição inclui, a
            // retirada do kit, a troca de tamanho. Também opcional.
            'registration_info' => ['nullable', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'registration_deadline' => ['required', 'date', 'before_or_equal:event_date'],
            'banner' => ImagensDoEvento::regraDeValidacao(),
            'card' => ImagensDoEvento::regraDeValidacao(),
            // Só hexadecimal de 6 dígitos: o valor vai direto para o style de
            // um elemento, então tudo que não for cor precisa ser barrado aqui.
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Nullable com padrão: formulário antigo (e teste antigo) que não
            // manda o campo continua valendo, com ano-calendário.
            'age_criteria' => ['nullable', \Illuminate\Validation\Rule::in(Event::CRITERIOS)],
            'active' => ['boolean'],
        ], [
            'accent_color.regex' => 'A cor precisa estar no formato #RRGGBB.',
            'registration_deadline.before_or_equal' => 'O prazo de inscrição não pode ser depois da data do evento.',
            'banner.image' => 'O banner precisa ser uma imagem (JPG, PNG ou WEBP).',
            'banner.max' => 'O banner passou de 5 MB.',
            'card.image' => 'O card precisa ser uma imagem (JPG, PNG ou WEBP).',
            'card.max' => 'O card passou de 5 MB.',
        ]);

        // Os arquivos não são colunas do evento — vão para o R2 em guardarImagens().
        unset($dados['banner'], $dados['card']);

        return $dados + [
            'active' => $request->boolean('active'),
            'age_criteria' => $request->input('age_criteria') ?: Event::CRITERIO_ANO_CALENDARIO,
        ];
    }

    /**
     * `events.slug` é único no banco inteiro (não por organizador), então dois
     * organizadores com "Corrida de Verão" colidiriam. Acrescenta sufixo até
     * achar um livre.
     */
    private function slugUnico(string $titulo, ?int $ignorarId = null): string
    {
        $base = Str::slug($titulo);
        $slug = $base;
        $n = 2;

        while (Event::where('slug', $slug)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
