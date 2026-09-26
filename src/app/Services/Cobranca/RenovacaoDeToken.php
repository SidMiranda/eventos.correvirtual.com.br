<?php

namespace App\Services\Cobranca;

use App\Models\MercadoPagoConta;

/**
 * Renova o token de uma conta conectada (vale 180 dias no Mercado Pago).
 *
 * Dois gatilhos: o comando diário (mercadopago:renovar-tokens, quem vence em
 * até 30 dias) e a hora de cobrar (quem vence em até 7). Em falha, grava o
 * erro na conta e alerta — o token antigo continua lá enquanto valer.
 */
final class RenovacaoDeToken
{
    public const DIAS_NO_AGENDADO = 30;
    public const DIAS_NA_COBRANCA = 7;

    public static function renovar(MercadoPagoConta $conta): bool
    {
        if (blank($conta->refresh_token)) {
            $conta->update(['last_error' => 'Sem refresh_token: o organizador precisa conectar de novo.']);
            AlertaDeCobranca::disparar('Conta Mercado Pago sem refresh_token', ['organizer_id' => $conta->organizer_id]);

            return false;
        }

        try {
            $token = MercadoPagoOAuth::renovar($conta->refresh_token);
        } catch (\Throwable $e) {
            $conta->update(['last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            AlertaDeCobranca::disparar('Falha ao renovar o token do Mercado Pago', [
                'organizer_id' => $conta->organizer_id,
                'vence_em' => $conta->expires_at?->toIso8601String(),
                'erro' => $e->getMessage(),
            ]);

            return false;
        }

        $conta->update([
            'access_token' => $token['access_token'],
            // O Mercado Pago pode devolver um refresh_token novo; sem ele,
            // mantém o que já tinha.
            'refresh_token' => $token['refresh_token'] ?: $conta->refresh_token,
            'public_key' => $token['public_key'] ?: $conta->public_key,
            'expires_at' => $token['expires_in'] ? now()->addSeconds((int) $token['expires_in']) : $conta->expires_at,
            'refreshed_at' => now(),
            'last_error' => null,
        ]);

        return true;
    }

    /** Renova só se vence em até $dias. */
    public static function seNecessario(MercadoPagoConta $conta, int $dias): void
    {
        if ($conta->venceEm($dias)) {
            self::renovar($conta);
        }
    }
}
