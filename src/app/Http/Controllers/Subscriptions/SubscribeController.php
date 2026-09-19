<?php

namespace App\Http\Controllers\Subscriptions;

use App\Exceptions\CupomRecusado;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\Subscription;
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
        $eventId = $request->route('event_id');
        $event = Event::with(['modalities', 'kits'])->findOrFail($eventId);

        if ($erro = $this->recusarSeFechado($event)) {
            return $erro;
        }

        return view('subscriptions.subscribe', compact('event'));
    }

    /**
     * Recusa a inscrição quando o evento não está mais aberto.
     *
     * A página do evento já esconde o botão nesse caso, mas esconder no front
     * não é proteger: o endereço /subscribe/event/{id} pode ser digitado à mão,
     * ou ter ficado salvo num link antigo. Fecha aqui também.
     *
     * Devolve o redirecionamento a ser retornado, ou null quando está tudo bem.
     */
    private function recusarSeFechado(Event $event)
    {
        if ($event->inscricoesAbertas()) {
            return null;
        }

        $motivo = $event->jaAconteceu()
            ? "O evento \"{$event->title}\" já aconteceu e não recebe mais inscrições."
            : "As inscrições para \"{$event->title}\" estão encerradas.";

        return redirect()
            ->route('event.show', $event->id)
            ->withErrors(['inscricao' => $motivo]);
    }

    public function mySubscriptions(Request $request)
    {
        // Busca as inscrições do usuário logado, filtrar por organizador e carrega a relação do evento
        $subscriptions = Subscription::with(['event', 'modality', 'kit', 'coupon'])
            ->where('user_id', auth()->id())
            ->whereHas('event', function ($query) use ($request) {
                $query->where('organizer_id', $request->current_organizer_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('subscriptions.my', compact('subscriptions'));
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
            'modality_id' => ['required', 'integer', Rule::exists('event_modalities', 'id')->where('event_id', $event->id)],
            'kit_id'      => ['required', 'integer', Rule::exists('event_kits', 'id')->where('event_id', $event->id)],
            'cupom'       => ['nullable', 'string', 'max:20'],
        ], [
            'required' => 'Por favor, selecione as opções de modalidade e kit.',
            'exists'   => 'A modalidade ou o kit selecionado não é válido para este evento.',
            'cupom.max' => 'O código do cupom é curto: 6 ou 7 caracteres.',
        ]);

        $modalityInput = $request->input('modality_id');
        $kitInput      = $request->input('kit_id');

        // Já validado acima que este kit pertence ao evento (Rule::exists)
        $kit = EventKit::findOrFail($kitInput);

        // Busca a inscrição existente para este usuário neste evento.
        // Cancelar uma inscrição apaga a linha (ver cancel()), então uma inscrição
        // encontrada aqui só pode estar pending ou paid — nunca cancelled.
        //
        // Esta checagem vem ANTES de encostar no cupom: quem já está inscrito
        // não vai criar inscrição nenhuma, e não pode gastar um uso à toa.
        $existingSubscription = Subscription::where('event_id', $event->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingSubscription) {
            return redirect('/my-subscriptions')->with([
                'modal_type'  => 'info',
                'user_name'   => auth()->user()->name,
                'event_title' => $event->title,
            ]);
        }

        try {
            $cupom = CupomNoCheckout::localizar($event, $request->input('cupom'));
            $preco = PrecoDaInscricao::para($kit, $cupom);

            // Consumo do cupom e criação da inscrição na mesma transação: se a
            // inscrição falhar, o uso volta sozinho. O registrarUso() é um
            // UPDATE condicional — entre a prévia e o envio a última vaga pode
            // ter ido para outro atleta, e é aqui que isso aparece.
            $subscription = DB::transaction(function () use ($event, $modalityInput, $kitInput, $cupom, $preco) {
                if ($cupom && ! $cupom->registrarUso()) {
                    throw new CupomRecusado("O cupom \"{$cupom->code}\" acabou de atingir o limite de usos.");
                }

                return Subscription::create([
                    'event_id'    => $event->id,
                    'user_id'     => auth()->id(),
                    'modality_id' => $modalityInput,
                    'kit_id'      => $kitInput,
                    'status'      => 'pending',
                    'bib_number'  => null,
                ] + $preco->paraInscricao());
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
     * Prévia do cupom para o formulário: valida o código para o kit escolhido
     * e devolve os valores, sem criar nada nem gastar uso.
     *
     * É conveniência, não autorização: o envio da inscrição refaz todas as
     * checagens. Devolve JSON porque quem chama é o fetch() do formulário.
     */
    public function previaDoCupom(Request $request)
    {
        $event = Event::findOrFail($request->route('event_id'));

        if (! $event->inscricoesAbertas()) {
            return $this->previaRecusada('As inscrições deste evento não estão abertas.');
        }

        $validator = Validator::make($request->all(), [
            'kit_id' => ['required', 'integer', Rule::exists('event_kits', 'id')->where('event_id', $event->id)],
            'cupom'  => ['required', 'string', 'max:20'],
        ], [
            'kit_id.required' => 'Escolha o kit antes de aplicar o cupom.',
            'kit_id.exists'   => 'O kit escolhido não é deste evento.',
            'cupom.required'  => 'Digite o código do cupom.',
            'cupom.max'       => 'O código do cupom é curto: 6 ou 7 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->previaRecusada($validator->errors()->first());
        }

        try {
            $cupom = CupomNoCheckout::localizar($event, $request->input('cupom'));
        } catch (CupomRecusado $e) {
            return $this->previaRecusada($e->getMessage());
        }

        if (! $cupom) {
            return $this->previaRecusada('Digite o código do cupom.');
        }

        $preco = PrecoDaInscricao::para(EventKit::findOrFail($request->input('kit_id')), $cupom);

        return response()->json([
            'ok'       => true,
            'codigo'   => $cupom->code,
            'bruto'    => $preco->brutoFormatado(),
            'desconto' => $preco->descontoFormatado(),
            'liquido'  => $preco->liquidoFormatado(),
            'gratuita' => $preco->gratuita(),
            'mensagem' => $preco->gratuita()
                ? "Cupom {$cupom->code} aplicado: inscrição gratuita, sem nada a pagar."
                : "Cupom {$cupom->code} aplicado: desconto de {$preco->descontoFormatado()}.",
        ]);
    }

    private function previaRecusada(string $mensagem)
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
        if ($subscription->status === 'pending') {

            // Apaga possíveis registros de pagamento pendentes atrelados a esta inscrição para não gerar lixo na base
            if (class_exists(\App\Models\Payment::class)) {
                \App\Models\Payment::where('subscription_id', $subscription->id)->delete();
            }

            // Apaga fisicamente o registro de inscrição.
            //
            // O uso do cupom, se houve, NÃO volta para o contador — decisão do
            // dono (2026-09-20): uso consumido é uso gasto, mesmo sem pagamento.
            // Ver docs/specs/cupons-de-desconto.md.
            $subscription->delete();

            return redirect()->back()->with([
                'modal_type'  => 'cancel',
                'user_name'   => auth()->user()->name,
                'event_title' => $eventTitle,
            ]);
        }

        return redirect()->back()->with('error', 'Apenas inscrições pendentes podem ser canceladas.');
    }
}
