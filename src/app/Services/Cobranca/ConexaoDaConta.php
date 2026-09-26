<?php

namespace App\Services\Cobranca;

use App\Models\MercadoPagoConta;

/**
 * Grava (ou regrava) a conta Mercado Pago de um organizador a partir da
 * resposta do OAuth. Uma conta por organizador: reconectar substitui.
 */
final class ConexaoDaConta
{
    public static function gravar(int $organizerId, array $token): MercadoPagoConta
    {
        $quem = MercadoPagoOAuth::quemE($token['access_token']);

        return MercadoPagoConta::updateOrCreate(
            ['organizer_id' => $organizerId],
            [
                'mp_user_id' => $token['user_id'],
                'nome' => $quem['nome'],
                'email' => $quem['email'],
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'public_key' => $token['public_key'],
                'live_mode' => $token['live_mode'],
                'expires_at' => $token['expires_in'] ? now()->addSeconds((int) $token['expires_in']) : null,
                'connected_at' => now(),
                'refreshed_at' => now(),
                'last_error' => null,
            ]
        );
    }
}
