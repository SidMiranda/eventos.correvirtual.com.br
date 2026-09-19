<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ConfirmacaoDeInscricao;
use App\Services\MercadoPagoService;
use App\Services\MercadoPagoWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\HeaderUtils;

class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Registra no arquivo de Log tudo o que o Mercado Pago enviou (útil para debug)
        Log::info('Webhook Mercado Pago recebido:', $request->all());

        // 2. Valida a assinatura antes de confiar em qualquer coisa da notificação.
        // O manifest usa o data.id literal da query string. Não dá pra usar $request->query('data.id')
        // aqui: o PHP converte pontos em chave de query string para underscore ($_GET), então
        // precisamos reler a query string crua com o parser do Symfony, que preserva o ponto.
        $queryParams = HeaderUtils::parseQuery($request->getQueryString() ?? '');
        $dataIdFromQuery = $queryParams['data.id'] ?? $queryParams['id'] ?? null;

        $isValidSignature = MercadoPagoWebhookSignature::isValid(
            $request->header('x-signature'),
            $request->header('x-request-id'),
            $dataIdFromQuery,
            config('services.mercadopago.webhook_secret')
        );

        if (!$isValidSignature) {
            Log::warning('Webhook Mercado Pago rejeitado: assinatura ausente ou inválida.');
            return response()->json(['status' => 'invalid_signature'], 401);
        }

        // 3. Extrai o ID do pagamento da notificação
        // O Mercado Pago pode mandar o ID de formas diferentes dependendo do evento
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if ($paymentId) {
            try {
                // 4. Consultar a API do Mercado Pago de forma segura
                $payment = MercadoPagoService::getPayment($paymentId);

                Log::info("Pagamento {$paymentId} consultado. Status: {$payment->status}");

                // 5. Se o pagamento foi aprovado, atualizamos o banco de dados
                if ($payment->status === 'approved' && isset($payment->external_reference)) {
                    $subscriptionId = (int) $payment->external_reference;

                    // Update atômico e condicional + e-mail só na primeira vez: a regra
                    // vive em ConfirmacaoDeInscricao, que a inscrição gratuita (cupom de
                    // 100%) também usa. O Mercado Pago reenvia a mesma notificação (retry);
                    // é o where('status', '!=', 'paid') de lá que impede e-mail duplicado.
                    $confirmada = ConfirmacaoDeInscricao::confirmar($subscriptionId);

                    // Atualiza o status na tabela payments (usando o transaction_id)
                    Payment::where('transaction_id', $paymentId)->update(['status' => 'approved']);

                    Log::info($confirmada
                        ? "Inscrição {$subscriptionId} atualizada com sucesso para PAGO!"
                        : "Inscrição {$subscriptionId} já estava paga — notificação repetida.");
                }
            } catch (\Exception $e) {
                Log::error("Erro ao processar Webhook do Mercado Pago: " . $e->getMessage());
            }
        }

        // 6. Retorna Status 200 OK imediatamente para o Mercado Pago parar de enviar a mesma notificação
        return response()->json(['status' => 'success'], 200);
    }
}
