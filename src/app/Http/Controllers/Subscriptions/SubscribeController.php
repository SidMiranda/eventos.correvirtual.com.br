<?php

namespace App\Http\Controllers\Subscriptions;

use App\Exceptions\CupomRecusado;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventPrice;
use App\Models\Subscription;
use App\Services\CategoriaEtaria;
use App\Services\ConfirmacaoDeInscricao;
use App\Services\CupomNoCheckout;
use App\Services\PrecoDaInscricao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubscribeController extends Controller
{
    public function showSubscribeForm(Request $request)
    {
        $event = Event::with([
            'modalities' => fn ($q) => $q->where('active', true)->orderBy('distance_km')->orderBy('name'),
        ])->findOrFail($request->route('event_id'));

        if ($erro = $this->recusarSeFechado($event)) {
            return $erro;
        }

        return view('subscriptions.subscribe', [
            'event' => $event,
            'dados' => $this->dadosDoFormulario($event),
        ]);
    }

    /**
     * O que o formulário precisa para montar kit por modalidade, tamanho por
     * kit e o preço do lote vigente — tudo de uma vez, para o JavaScript não
     * perguntar ao servidor a cada clique. Kit sem preço no lote não entra:
     * célula vazia da grade é combinação que não se vende agora (ADR 0007).
     */
    private function dadosDoFormulario(Event $event): array
    {
        $lote = $event->loteVigente();

        $precos = $event->prices()->where('lot_id', $lote->id)->get()
            ->keyBy(fn (EventPrice $p) => "{$p->modality_id}-{$p->kit_id}");

        $modalidades = [];

        foreach ($event->modalities as $modalidade) {
            $kits = [];

            foreach ($modalidade->kits()->where('active', true)->with('options')->orderBy('name')->get() as $kit) {
                $preco = $precos["{$modalidade->id}-{$kit->id}"] ?? null;

                if (! $preco) {
                    continue;
                }

                $kits[] = [
                    'id' => $kit->id,
                    'nome' => $kit->name,
                    'descricao' => $kit->description,
                    'preco' => PrecoDaInscricao::formatar((float) $preco->price),
                    'tamanhos' => array_map(
                        fn (string $t) => ['codigo' => $t, 'rotulo' => Subscription::rotuloDoTamanho($t)],
                        $kit->tamanhos()
                    ),
                ];
            }

            $modalidades[] = ['id' => $modalidade->id, 'nome' => $modalidade->name, 'kits' => $kits];
        }

        return [
            'lote' => ['id' => $lote->id, 'nome' => $lote->name],
            'modalidades' => $modalidades,
        ];
    }

    /**
     * Recusa a inscrição quando o evento não está vendendo.
     *
     * A página do evento já esconde o botão nesse caso, mas esconder no front
     * não é proteger: o endereço /subscribe/event/{id} pode ser digitado à mão.
     * Três motivos, cada um com a sua frase: já aconteceu; prazo encerrado; ou
     * as datas estão abertas mas nenhum lote está vigente (ADR 0007).
     *
     * Devolve o redirecionamento a ser retornado, ou null quando está tudo bem.
     */
    private function recusarSeFechado(Event $event)
    {
        if ($event->aceitaInscricao()) {
            return null;
        }

        if ($event->jaAconteceu()) {
            $motivo = "O evento \"{$event->title}\" já aconteceu e não recebe mais inscrições.";
        } elseif (! $event->inscricoesAbertas()) {
            $motivo = "As inscrições para \"{$event->title}\" estão encerradas.";
        } elseif ($proximo = $event->proximoLote()) {
            $motivo = "As inscrições para \"{$event->title}\" abrem em {$proximo->starts_at->format('d/m/Y \à\s H:i')}.";
        } else {
            $motivo = "As inscrições para \"{$event->title}\" ainda não abriram.";
        }

        return redirect()
            ->route('event.show', $event->id)
            ->withErrors(['inscricao' => $motivo]);
    }

    public function subscribe(Request $request)
    {
        $eventId = $request->route('event_id');

        // Valida se o evento realmente existe no banco antes de criar a inscrição.
        // Se não existir, retorna um erro 404 automaticamente.
        $event = Event::findOrFail($eventId);

        if ($erro = $this->recusarSeFechado($event)) {
            return $erro;
        }

        // Valida que a modalidade e o kit foram preenchidos e que de fato pertencem a este evento
        $request->validate([
            'modality_id' => ['required', 'integer', Rule::exists('event_modalities', 'id')->where('event_id', $event->id)->where('active', true)],
            'kit_id'      => ['required', 'integer', Rule::exists('event_kits', 'id')->where('event_id', $event->id)->where('active', true)],
            'cupom'       => ['nullable', 'string', 'max:20'],
            // Equipe é texto livre (decisão do dono em 2026-09-22), mas não é
            // campo aberto: só letras, números e espaço. O nome vai para a
            // lista de largada e para o relatório do organizador — pontuação e
            // símbolo ali só criam equipe duplicada e linha torta no papel.
            'equipe'      => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}\p{N} ]+$/u'],
            // O tamanho é conferido contra o KIT, logo abaixo: obrigatório só
            // quando o kit tem tamanhos, e só entre os que ele oferece.
            'camiseta'    => ['nullable', 'string', 'max:20'],
        ], [
            'required' => 'Por favor, selecione as opções de modalidade e kit.',
            'exists'   => 'A modalidade ou o kit selecionado não é válido para este evento.',
            'equipe.regex' => 'O nome da equipe aceita só letras, números e espaços.',
            'equipe.max'   => 'O nome da equipe passou de 50 caracteres.',
            'cupom.max' => 'O código do cupom é curto: 6 ou 7 caracteres.',
        ]);

        $modalityInput = $request->input('modality_id');
        $kitInput      = $request->input('kit_id');

        $lote = $event->loteVigente();
        $modalidade = $event->modalities()->findOrFail($modalityInput);
        $kit = $event->kits()->with('options')->findOrFail($kitInput);

        // O kit só vale nas modalidades em que está vinculado: é isto que
        // impede escolher o 5K e levar o kit do 10K (ADR 0007).
        if (! $kit->modalities()->where('event_modalities.id', $modalidade->id)->exists()) {
            return back()->withInput()->withErrors(['kit_id' => 'O kit escolhido não está disponível nesta modalidade.']);
        }

        // O preço vem da grade, no lote vigente. Célula vazia = não se vende.
        $precoBase = EventPrice::de($modalidade, $kit, $lote);

        if (! $precoBase) {
            return back()->withInput()->withErrors(['kit_id' => "Este kit não está à venda nesta modalidade no {$lote->name}."]);
        }

        // Tamanho: obrigatório quando o kit tem, e só entre os que ele oferece.
        // Kit sem tamanho ignora o que vier — não há o que escolher.
        $camiseta = null;

        if ($kit->temTamanhos()) {
            $camiseta = $request->input('camiseta');

            if (! $kit->aceitaTamanho($camiseta)) {
                return back()->withInput()->withErrors(['camiseta' => 'Escolha o tamanho da camiseta entre os oferecidos por este kit.']);
            }
        }

        // Busca a inscrição existente para este usuário neste evento.
        //
        // Inscrição ATIVA (pendente ou paga) barra aqui. Inscrição CANCELADA é
        // reaproveitada logo abaixo: a unique (event_id, user_id) não deixa
        // criar uma segunda linha para o mesmo par.
        //
        // Esta checagem vem ANTES de encostar no cupom: quem já está inscrito
        // não vai criar inscrição nenhuma, e não pode gastar um uso à toa.
        $inscricaoExistente = Subscription::where('event_id', $event->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($inscricaoExistente && ! $inscricaoExistente->cancelada()) {
            return redirect('/my-subscriptions')->with([
                'modal_type'  => 'info',
                'user_name'   => auth()->user()->name,
                'event_title' => $event->title,
            ]);
        }

        try {
            $cupom = CupomNoCheckout::localizar($event, $request->input('cupom'));

            // A categoria etária vem do cadastro (data de nascimento), pelo
            // critério do evento; em cascata, o cupom abate o que sobrou dela.
            $categoria = CategoriaEtaria::para($event, auth()->user(), PrecoDaInscricao::centavos((float) $precoBase->price));
            $preco = PrecoDaInscricao::de((float) $precoBase->price, $categoria, $cupom);

            // Em caixa alta e sem espaço sobrando: a mesma equipe escrita de
            // três jeitos viraria três equipes na hora de contar.
            $equipe = Subscription::normalizarEquipe($request->input('equipe'));

            // Consumo do cupom e criação da inscrição na mesma transação: se a
            // inscrição falhar, o uso volta sozinho. O registrarUso() é um
            // UPDATE condicional — entre a prévia e o envio a última vaga pode
            // ter ido para outro atleta, e é aqui que isso aparece.
            $subscription = DB::transaction(function () use ($event, $modalityInput, $kitInput, $lote, $equipe, $camiseta, $cupom, $preco, $inscricaoExistente) {
                if ($cupom && ! $cupom->registrarUso()) {
                    throw new CupomRecusado("O cupom \"{$cupom->code}\" acabou de atingir o limite de usos.");
                }

                $dados = [
                    'modality_id' => $modalityInput,
                    'kit_id'      => $kitInput,
                    'team_name'   => $equipe,
                    'shirt_size'  => $camiseta,
                    'lot_id'      => $lote->id,
                    'status'      => Subscription::PENDENTE,
                    'bib_number'  => null,
                ] + $preco->paraInscricao();

                // Inscreveu-se de novo depois de cancelar: a linha cancelada
                // volta a valer, com os dados desta tentativa. O uso do cupom
                // da tentativa anterior não volta (decisão de 2026-09-20), e
                // esta tentativa consome um uso novo.
                if ($inscricaoExistente) {
                    $inscricaoExistente
                        ->fill($dados + ['cancelled_at' => null, 'confirmed_at' => null])
                        ->save();

                    return $inscricaoExistente;
                }

                return Subscription::create($dados + [
                    'event_id' => $event->id,
                    'user_id'  => auth()->id(),
                ]);
            });
        } catch (CupomRecusado $e) {
            return back()->withInput()->withErrors(['cupom' => $e->getMessage()]);
        }

        // Cupom que zerou o valor: não existe o que pagar, então não existe
        // Pix. A inscrição confirma agora, pelo mesmo caminho que o webhook usa
        // quando o pagamento cai — inclusive o e-mail.
        if ($preco->gratuita()) {
            ConfirmacaoDeInscricao::confirmar($subscription->id);

            return redirect('/my-subscriptions')->with([
                'modal_type'          => 'success',
                'inscricao_gratuita'  => true,
                'user_name'           => auth()->user()->name,
                'event_title'         => $event->title,
            ]);
        }

        return redirect('/my-subscriptions')->with([
            'modal_type'  => 'success',
            'user_name'   => auth()->user()->name,
            'event_title' => $event->title,
        ]);
    }

    /**
     * A cotação do formulário: preço da grade, categoria etária e cupom,
     * para a pessoa ver o total antes de confirmar.
     *
     * É conveniência, não autorização: o envio da inscrição refaz todas as
     * checagens. Não cria nada nem gasta uso de cupom. JSON porque quem chama
     * é o fetch() do formulário.
     */
    public function cotacao(Request $request)
    {
        $event = Event::findOrFail($request->route('event_id'));

        if (! $event->aceitaInscricao()) {
            return $this->cotacaoRecusada('As inscrições deste evento não estão abertas.');
        }

        $lote = $event->loteVigente();

        $validator = Validator::make($request->all(), [
            'modality_id' => ['required', 'integer', Rule::exists('event_modalities', 'id')->where('event_id', $event->id)->where('active', true)],
            'kit_id'      => ['required', 'integer', Rule::exists('event_kits', 'id')->where('event_id', $event->id)->where('active', true)],
            'cupom'       => ['nullable', 'string', 'max:20'],
        ], [
            'modality_id.required' => 'Escolha a modalidade.',
            'kit_id.required'      => 'Escolha o kit.',
            'exists'               => 'A modalidade ou o kit não é deste evento.',
            'cupom.max'            => 'O código do cupom é curto: 6 ou 7 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->cotacaoRecusada($validator->errors()->first());
        }

        $modalidade = $event->modalities()->find($request->input('modality_id'));
        $kit = $event->kits()->find($request->input('kit_id'));

        if (! $kit->modalities()->where('event_modalities.id', $modalidade->id)->exists()) {
            return $this->cotacaoRecusada('O kit escolhido não está disponível nesta modalidade.');
        }

        $precoBase = EventPrice::de($modalidade, $kit, $lote);

        if (! $precoBase) {
            return $this->cotacaoRecusada("Este kit não está à venda nesta modalidade no {$lote->name}.");
        }

        try {
            $cupom = CupomNoCheckout::localizar($event, $request->input('cupom'));
        } catch (CupomRecusado $e) {
            return $this->cotacaoRecusada($e->getMessage());
        }

        $categoria = CategoriaEtaria::para($event, auth()->user(), PrecoDaInscricao::centavos((float) $precoBase->price));
        $preco = PrecoDaInscricao::de((float) $precoBase->price, $categoria, $cupom);

        return response()->json([
            'ok'             => true,
            'lote'           => $lote->name,
            'bruto'          => $preco->brutoFormatado(),
            'categoria'      => $categoria?->name,
            'desconto_idade' => $preco->descontoDeIdadeFormatado(),
            'subtotal'       => $preco->subtotalFormatado(),
            'cupom'          => $cupom?->code,
            'desconto_cupom' => $preco->descontoFormatado(),
            'liquido'        => $preco->liquidoFormatado(),
            'gratuita'       => $preco->gratuita(),
            'tem_idade'      => $preco->temDescontoDeIdade(),
            'tem_cupom'      => $preco->temDesconto(),
        ]);
    }

    private function cotacaoRecusada(string $mensagem)
    {
        return response()->json(['ok' => false, 'mensagem' => $mensagem], 422);
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|integer',
        ]);

        $subscription = Subscription::with('event')->where('id', $request->subscription_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Guarda o título do evento para exibir no modal após a exclusão
        $eventTitle = $subscription->event->title ?? 'Evento';

        // Só permite o cancelamento se a inscrição ainda estiver pendente de pagamento
        if ($subscription->pendente()) {

            // A cobrança pendente é lixo: ninguém paga um Pix de inscrição
            // cancelada, e deixá-la atrapalharia a conciliação.
            \App\Models\Payment::where('subscription_id', $subscription->id)->delete();

            // A inscrição FICA, marcada como cancelada. Até 2026-09-21 a linha
            // era apagada, e o organizador não tinha como saber que alguém
            // desistiu: a inscrição simplesmente sumia da base.
            //
            // O uso do cupom, se houve, NÃO volta para o contador — decisão do
            // dono (2026-09-20): uso consumido é uso gasto, mesmo sem pagamento.
            // Ver docs/specs/cupons-de-desconto.md.
            $subscription->status = Subscription::CANCELADA;
            $subscription->cancelled_at = now();
            $subscription->save();

            return redirect()->back()->with([
                'modal_type'  => 'cancel',
                'user_name'   => auth()->user()->name,
                'event_title' => $eventTitle,
            ]);
        }

        return redirect()->back()->with('error', 'Apenas inscrições pendentes podem ser canceladas.');
    }
}
