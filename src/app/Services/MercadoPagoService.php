<?php

namespace App\Services;

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    /**
     * @return object|null O pagamento criado, ou null se a API do Mercado Pago recusar/falhar
     *                      (credencial inválida, instabilidade, etc.) — quem chama decide o
     *                      que mostrar ao usuário.
     */
    public static function createPixPayment($amount, $email, $externalReference = null)
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.token'));

        $client = new PaymentClient();

        try {
            $request = [
                "transaction_amount" => (float) $amount,
                "description" => "Teste inscrição",
                "payment_method_id" => "pix",
                "payer" => [
                    "email" => $email
                ]
            ];

            if ($externalReference) {
                $request["external_reference"] = (string) $externalReference;
            }

            return $client->create($request);

        } catch (MPApiException $e) {
            Log::error('Falha ao criar pagamento Pix no Mercado Pago', [
                'status' => $e->getApiResponse()?->getStatusCode(),
                'content' => $e->getApiResponse()?->getContent(),
                'external_reference' => $externalReference,
            ]);

            return null;
        }
    }

    /**
     * Pix pela conta CONECTADA do organizador (modelo de marketplace, ADR 0008):
     * o token é o dele, e a taxa da plataforma vai em `application_fee` — o
     * Mercado Pago a repassa à dona da aplicação. Sem taxa (evento de 2026),
     * o campo nem vai. O modelo antigo continua em createPixPayment(), intacto.
     *
     * @return object|null como createPixPayment()
     */
    public static function createPixPaymentForAccount($amount, $email, $externalReference, string $accessToken, ?float $applicationFee = null, ?string $notificationUrl = null)
    {
        MercadoPagoConfig::setAccessToken($accessToken);

        $client = new PaymentClient();

        try {
            $request = [
                "transaction_amount" => (float) $amount,
                "description" => "Inscrição",
                "payment_method_id" => "pix",
                "payer" => ["email" => $email],
                "external_reference" => (string) $externalReference,
            ];

            if ($applicationFee !== null) {
                $request["application_fee"] = round($applicationFee, 2);
            }

            if ($notificationUrl) {
                $request["notification_url"] = $notificationUrl;
            }

            return $client->create($request);
        } catch (MPApiException $e) {
            Log::error('Falha ao criar pagamento Pix pela conta conectada do organizador', [
                'status' => $e->getApiResponse()?->getStatusCode(),
                'content' => $e->getApiResponse()?->getContent(),
                'external_reference' => $externalReference,
                'application_fee' => $applicationFee,
            ]);

            return null;
        } finally {
            // O SDK guarda o token num estático: volta ao do .env para nada
            // depois disto sair, por engano, pela conta do organizador.
            MercadoPagoConfig::setAccessToken((string) config('services.mercadopago.token'));
        }
    }

    /** Consulta um pagamento com a conta que o criou (ver EscolhaDeConta). */
    public static function getPaymentForAccount($id, string $accessToken)
    {
        MercadoPagoConfig::setAccessToken($accessToken);

        try {
            return (new PaymentClient())->get($id);
        } finally {
            MercadoPagoConfig::setAccessToken((string) config('services.mercadopago.token'));
        }
    }

    public static function getPayment($id)
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.token'));

        $client = new PaymentClient();
        return $client->get($id);
    }
}
