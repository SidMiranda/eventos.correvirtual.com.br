<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;
use App\Models\Subscription;
use App\Models\Payment;
use App\Services\Cobranca\AlertaDeCobranca;
use App\Services\Cobranca\EscolhaDeConta;
use App\Services\Cobranca\TaxaDaPlataforma;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class PixController extends Controller
{
    public function generatePix(Request $request)
    {

        $subscriptionId = $request->subscription_id;

        // Só a inscrição de quem está logado. Até 2026-09-20 era um find($id)
        // solto: qualquer usuário logado gerava Pix para qualquer inscrição.
        // 404 e não 403 — não é para descobrir que a inscrição do outro existe.
        $subscription = Subscription::where('id', $subscriptionId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($subscription->status !== 'pending') {
            return redirect('/my-subscriptions')->withErrors([
                'pix' => 'Esta inscrição já está confirmada — não há o que pagar.',
            ]);
        }

        // Inscrição gratuita (cupom que zerou o valor) já nasce confirmada e
        // nunca chega aqui. É a rede de segurança: o Mercado Pago recusa
        // cobrança de R$ 0,00, e o erro dele é pior que este aviso.
        if ($subscription->gratuita()) {
            return redirect('/my-subscriptions')->withErrors([
                'pix' => 'Esta inscrição não tem valor a pagar.',
            ]);
        }

        // Cobra o preço que a inscrição registrou — o preço do kit escolhido no
        // momento em que ela foi criada, já com o desconto do cupom, se houve
        // (SubscribeController + PrecoDaInscricao). Não existe sobreposição
        // global de valor: os eventos de teste têm R$ 0,05 gravado como preço
        // real do kit, e os cadastrados pelo painel têm o preço deles.
        // Com que conta cobrar (ADR 0008): a conectada do organizador, com a
        // taxa da plataforma quando o evento é de 2027 em diante; sem conta
        // conectada, o modelo de sempre, pelo .env, sem taxa.
        $conta = EscolhaDeConta::paraOrganizador((int) $subscription->event->organizer_id);
        $taxa = TaxaDaPlataforma::para($subscription, $conta);

        if ($conta->conectada()) {
            $pix = MercadoPagoService::createPixPaymentForAccount(
                (float) $subscription->price,
                auth()->user()->email,
                $subscriptionId,
                (string) $conta->token(),
                $taxa,
                url('/api/webhooks/mercadopago')
            );

            if (!$pix) {
                AlertaDeCobranca::disparar('Pix não saiu pela conta conectada do organizador', [
                    'organizer_id' => $subscription->event->organizer_id,
                    'subscription_id' => $subscription->id,
                    'valor' => $subscription->price,
                    'taxa' => $taxa,
                ]);
            }
        } else {
            if ($subscription->event->event_date?->year >= TaxaDaPlataforma::A_PARTIR_DO_ANO) {
                Log::warning('Evento com taxa da plataforma cobrado sem conta conectada: sai sem taxa.', [
                    'organizer_id' => $subscription->event->organizer_id,
                    'subscription_id' => $subscription->id,
                ]);
            }

            $pix = MercadoPagoService::createPixPayment(
                (float) $subscription->price,
                auth()->user()->email,
                $subscriptionId // Enviando o ID da inscrição como referência externa
            );
        }

        if (!$pix) {
            return redirect('/my-subscriptions')->withErrors([
                'pix' => 'Estamos com instabilidade no pagamento no momento. Tente novamente mais tarde.',
            ]);
        }

        Payment::create([
            'subscription_id' => $subscriptionId,
            'provider' => 'mercadopago',
            'mercado_pago_conta_id' => $conta->contaId(),
            'application_fee' => $conta->conectada() ? $taxa : null,
            'payment_method' => 'pix',
            'status' => 'pending',
            'transaction_id' => $pix->id,
            'qr_code' => $pix->point_of_interaction->transaction_data->qr_code,
            'qr_code_base64' => $pix->point_of_interaction->transaction_data->qr_code_base64,
            'ticket_url' => $pix->point_of_interaction->transaction_data->ticket_url,
            'expires_at' => !empty($pix->date_of_expiration) ? Carbon::parse($pix->date_of_expiration)->format('Y-m-d H:i:s') : null,
            'payload' => json_encode($pix)
        ]);

        return view('subscriptions.generate-pix', compact('pix', 'subscriptionId', 'subscription'));

    }

    // Retorna apenas o status da inscrição para o Javascript via API
    public function checkStatus($id)
    {
        $subscription = Subscription::find($id);
        return response()->json(['status' => $subscription ? $subscription->status : 'pending']);
    }

    // Carrega a tela de Sucesso
    public function success($id)
    {
        $subscription = Subscription::findOrFail($id);
        return view('subscriptions.success', compact('subscription'));
    }
}
