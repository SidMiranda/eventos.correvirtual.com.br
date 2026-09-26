<?php

namespace App\Services\Cobranca;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * O OAuth do Mercado Pago, pela aplicação da plataforma (ADR 0008).
 *
 * Documentação: developers/pt/docs/security/oauth/creation — autorização em
 * auth.mercadopago.com, troca e renovação em api.mercadopago.com/oauth/token
 * (token de authorization_code vale 180 dias; renova com o refresh_token, sem
 * o organizador autorizar de novo).
 *
 * HTTP pelo facade do Laravel de propósito: nos testes, Http::fake().
 */
final class MercadoPagoOAuth
{
    private const AUTORIZACAO = 'https://auth.mercadopago.com/authorization';
    private const TOKEN = 'https://api.mercadopago.com/oauth/token';
    private const EU = 'https://api.mercadopago.com/users/me';

    /** A aplicação da plataforma está configurada no .env? */
    public static function configurado(): bool
    {
        return filled(config('services.mercadopago.app.client_id'))
            && filled(config('services.mercadopago.app.client_secret'))
            && filled(config('services.mercadopago.app.redirect_uri'));
    }

    public static function urlDeAutorizacao(string $state): string
    {
        return self::AUTORIZACAO . '?' . http_build_query([
            'client_id' => config('services.mercadopago.app.client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => config('services.mercadopago.app.redirect_uri'),
        ]);
    }

    /** @return array{access_token:string, refresh_token:?string, expires_in:?int, user_id:string, public_key:?string, live_mode:bool} */
    public static function trocarCodigo(string $codigo): array
    {
        return self::pedirToken([
            'grant_type' => 'authorization_code',
            'code' => $codigo,
            'redirect_uri' => config('services.mercadopago.app.redirect_uri'),
        ]);
    }

    /** @return array{access_token:string, refresh_token:?string, expires_in:?int, user_id:string, public_key:?string, live_mode:bool} */
    public static function renovar(string $refreshToken): array
    {
        return self::pedirToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /** Nome e e-mail da conta — é o que a tela "Cobrança" mostra. */
    public static function quemE(string $accessToken): array
    {
        $resposta = Http::withToken($accessToken)->acceptJson()->timeout(15)->get(self::EU);

        if (! $resposta->successful()) {
            return ['nome' => null, 'email' => null];
        }

        $nome = trim(($resposta->json('first_name') ?? '') . ' ' . ($resposta->json('last_name') ?? ''));

        return [
            'nome' => $nome !== '' ? $nome : $resposta->json('nickname'),
            'email' => $resposta->json('email'),
        ];
    }

    private static function pedirToken(array $dados): array
    {
        $resposta = Http::asForm()->acceptJson()->timeout(15)->post(self::TOKEN, $dados + [
            'client_id' => config('services.mercadopago.app.client_id'),
            'client_secret' => config('services.mercadopago.app.client_secret'),
        ]);

        if (! $resposta->successful() || ! $resposta->json('access_token')) {
            // O corpo do erro não tem segredo (é a mensagem do Mercado Pago);
            // o que mandamos, com o client_secret, nunca vai para a exceção.
            throw new RuntimeException('Mercado Pago recusou o token (HTTP ' . $resposta->status() . '): '
                . mb_substr((string) ($resposta->json('message') ?? $resposta->body()), 0, 300));
        }

        return [
            'access_token' => $resposta->json('access_token'),
            'refresh_token' => $resposta->json('refresh_token'),
            'expires_in' => $resposta->json('expires_in'),
            'user_id' => (string) $resposta->json('user_id'),
            'public_key' => $resposta->json('public_key'),
            'live_mode' => (bool) $resposta->json('live_mode', true),
        ];
    }
}
