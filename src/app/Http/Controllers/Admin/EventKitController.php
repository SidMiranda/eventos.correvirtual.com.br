<?php

namespace App\Http\Controllers\Admin;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\KitOption;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kits de um evento — o que o atleta recebe e o que ele paga.
 *
 * O preço daqui é o valor cobrado de verdade no Pix: a inscrição copia
 * kit->price no momento em que é criada, e é esse valor que vai pro Mercado
 * Pago. Não existe mais sobreposição global de valor.
 */
class EventKitController extends AdminController
{
    public function index(int $eventoId)
    {
        // Listar continua valendo para evento já realizado: a tela vira só
        // leitura, sem os botões de criar, editar e apagar.
        $event = $this->eventoDoOrganizador($eventoId);
        $kits = $event->kits()->orderBy('price')->get();

        return view('admin.kits.index', compact('event', 'kits'));
    }

    public function create(int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        return view('admin.kits.create', compact('event'));
    }

    public function store(Request $request, int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        $kit = $event->kits()->create($this->soColunasDoKit($this->validar($request, $event)));
        $this->sincronizarVinculos($kit, $request);

        return redirect()
            ->route('admin.eventos.kits.index', $event->id)
            ->with('sucesso', 'Kit criado. Preencha o preço dele na grade de preços.');
    }

    public function edit(int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $kit = $this->kit($event, $id);

        return view('admin.kits.edit', compact('event', 'kit'));
    }

    public function update(Request $request, int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $kit = $this->kit($event, $id);

        $kit->update($this->soColunasDoKit($this->validar($request, $event)));
        $this->sincronizarVinculos($kit, $request);

        return redirect()
            ->route('admin.eventos.kits.index', $event->id)
            ->with('sucesso', 'Kit atualizado.');
    }

    public function destroy(int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $kit = $this->kit($event, $id);

        if ($kit->subscriptions()->exists()) {
            return back()->withErrors([
                'kit' => 'Este kit já foi escolhido por alguém e por isso não pode ser apagado. Desative-o para tirá-lo das novas inscrições.',
            ]);
        }

        $kit->delete();

        return redirect()
            ->route('admin.eventos.kits.index', $event->id)
            ->with('sucesso', 'Kit apagado.');
    }

    private function kit(Event $event, int $id): EventKit
    {
        return $event->kits()->with(['modalities', 'options'])->where('id', $id)->firstOrFail();
    }

    private function soColunasDoKit(array $dados): array
    {
        unset($dados['modalidades'], $dados['tamanhos']);

        return $dados;
    }

    /**
     * Modalidades em que o kit vale e tamanhos de camiseta que oferece.
     *
     * `sync` nas modalidades. Nos tamanhos, apaga o que saiu e cria o que
     * entrou, na ordem da tabela de medidas — sem recriar o que já estava.
     */
    private function sincronizarVinculos(EventKit $kit, Request $request): void
    {
        $kit->modalities()->sync(array_map('intval', (array) $request->input('modalidades', [])));

        $tamanhos = array_values(array_intersect(
            Subscription::tamanhosDeCamiseta(),
            (array) $request->input('tamanhos', [])
        ));

        $kit->options()->where('attribute', KitOption::TAMANHO)->whereNotIn('value', $tamanhos)->delete();

        foreach ($tamanhos as $posicao => $tamanho) {
            $kit->options()->updateOrCreate(
                ['attribute' => KitOption::TAMANHO, 'value' => $tamanho],
                ['position' => $posicao]
            );
        }
    }

    private function validar(Request $request, Event $event): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Kit sem modalidade não aparece para ninguém. Só é exigido quando
            // o evento já tem modalidade — no primeiro kit de um evento novo
            // ainda não há o que marcar.
            'modalidades' => [$event->modalities()->exists() ? 'required' : 'nullable', 'array'],
            'modalidades.*' => ['integer', Rule::exists('event_modalities', 'id')->where('event_id', $event->id)],
            'tamanhos' => ['nullable', 'array'],
            'tamanhos.*' => [Rule::in(Subscription::tamanhosDeCamiseta())],
            // min:0.01 e não min:0 — o Mercado Pago recusa cobrança de R$ 0,00,
            // e uma inscrição gratuita não deveria passar pelo fluxo de Pix.
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ], [
            'price.min' => 'O preço precisa ser de pelo menos R$ 0,01 — o Pix não aceita cobrança zerada.',
            'stock.min' => 'O estoque não pode ser negativo. Deixe em branco para não controlar estoque.',
            'modalidades.required' => 'Marque ao menos uma modalidade em que este kit pode ser comprado.',
            'modalidades.*.exists' => 'Uma das modalidades marcadas não é deste evento.',
            'tamanhos.*.in' => 'Um dos tamanhos marcados não está na tabela de medidas.',
        ]) + ['active' => $request->boolean('active')];
    }
}
