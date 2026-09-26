<?php

namespace App\Services\Cobranca;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * O `state` do OAuth: diz, com segurança, de qual organizador é a volta.
 *
 * O retorno do Mercado Pago cai num endereço fixo (o redirect_uri cadastrado
 * na aplicação), que pode ser outro domínio que o do painel — sem a sessão de
 * quem clicou. Por isso o state não é um número guardado na sessão: é um pacote
 * CIFRADO com a APP_KEY (ninguém forja nem lê), com validade de 15 minutos e
 * um nonce de uso único no cache (ninguém reaproveita um state capturado).
 */
final class EstadoDoOAuth
{
    private const VALIDADE_MINUTOS = 15;

    public static function gerar(int $organizerId, int $userId, string $voltarPara): string
    {
        $nonce = Str::random(32);
        Cache::put(self::chave($nonce), true, now()->addMinutes(self::VALIDADE_MINUTOS));

        return Crypt::encryptString(json_encode([
            'organizador' => $organizerId,
            'usuario' => $userId,
            'voltar' => $voltarPara,
            'expira' => now()->addMinutes(self::VALIDADE_MINUTOS)->timestamp,
            'nonce' => $nonce,
        ]));
    }

    /**
     * @return array{organizador:int, usuario:int, voltar:string}|null null se
     *         adulterado, vencido ou já usado — nesses casos nada é gravado.
     */
    public static function conferir(?string $state): ?array
    {
        if (blank($state)) {
            return null;
        }

        try {
            $dados = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($dados) || ($dados['expira'] ?? 0) < now()->timestamp) {
            return null;
        }

        // Uso único: o pull apaga o nonce na primeira leitura.
        if (! Cache::pull(self::chave((string) ($dados['nonce'] ?? '')))) {
            return null;
        }

        return [
            'organizador' => (int) $dados['organizador'],
            'usuario' => (int) $dados['usuario'],
            'voltar' => (string) $dados['voltar'],
        ];
    }

    private static function chave(string $nonce): string
    {
        return 'mp-oauth-state:' . $nonce;
    }
}
