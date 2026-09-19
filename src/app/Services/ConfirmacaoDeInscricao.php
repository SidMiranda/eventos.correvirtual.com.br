<?php

namespace App\Services;

use App\Mail\SubscriptionConfirmed;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Confirma uma inscrição — uma vez só — e avisa o atleta.
 *
 * Dois caminhos chegam aqui: o webhook do Mercado Pago (pagamento aprovado) e
 * a inscrição gratuita (cupom que zerou o valor). A regra é a mesma nos dois,
 * e por isso vive num lugar só.
 *
 * O update é condicional (`status != 'paid'`), e é o número de linhas afetadas
 * que decide se o e-mail sai. O Mercado Pago reenvia a mesma notificação
 * (retry); sem a condição, duas chegadas quase simultâneas mandariam o e-mail
 * duplicado. Com ela, o banco garante que só uma muda a linha.
 */
final class ConfirmacaoDeInscricao
{
    /**
     * @return bool true se ESTA chamada confirmou; false se já estava confirmada
     */
    public static function confirmar(int $subscriptionId): bool
    {
        $afetadas = Subscription::where('id', $subscriptionId)
            ->where('status', '!=', 'paid')
            ->update([
                'status' => 'paid',
                'confirmed_at' => now(),
            ]);

        if ($afetadas !== 1) {
            return false;
        }

        // Depois da resposta HTTP: não segura o webhook (o Mercado Pago tem
        // timeout e reenvia se demorar) nem o redirecionamento do atleta
        // esperando o SMTP. Não há worker de fila neste projeto (DEBT-010), e
        // afterResponse() roda logo após a resposta sem precisar de um. Falha
        // no envio é só logada — a confirmação já aconteceu e não volta.
        dispatch(function () use ($subscriptionId) {
            try {
                $subscription = Subscription::with(['event', 'modality', 'kit', 'user', 'coupon'])
                    ->find($subscriptionId);

                if ($subscription) {
                    Mail::to($subscription->user->email)->send(new SubscriptionConfirmed($subscription));
                }
            } catch (\Throwable $e) {
                Log::error("Falha ao enviar e-mail de confirmação da inscrição {$subscriptionId}: " . $e->getMessage());
            }
        })->afterResponse();

        return true;
    }
}
