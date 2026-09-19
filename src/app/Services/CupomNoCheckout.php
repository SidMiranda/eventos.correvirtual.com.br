<?php

namespace App\Services;

use App\Exceptions\CupomRecusado;
use App\Models\Coupon;
use App\Models\Event;

/**
 * Encontra e valida o cupom que o atleta digitou, para um evento.
 *
 * Só olha; não consome. É usado pela prévia do formulário (que não pode gastar
 * uso) e pelo envio da inscrição (que consome depois, de forma atômica, com
 * Coupon::registrarUso()). A checagem de saldo aqui é para dar a mensagem
 * certa cedo — quem garante o limite sob concorrência é o registrarUso().
 *
 * A busca é sempre PELO EVENTO: o código é único por evento, e um cupom de
 * uma prova nunca vale em outra.
 */
final class CupomNoCheckout
{
    /**
     * @throws CupomRecusado com a mensagem para o atleta
     */
    public static function localizar(Event $evento, ?string $codigo): ?Coupon
    {
        $codigo = Coupon::normalizarCodigo($codigo);

        if ($codigo === '') {
            return null;
        }

        $cupom = Coupon::where('event_id', $evento->id)
            ->where('code', $codigo)
            ->first();

        if (! $cupom) {
            throw new CupomRecusado("O cupom \"{$codigo}\" não existe para este evento. Confira o código e o evento.");
        }

        if (! $cupom->active) {
            throw new CupomRecusado("O cupom \"{$codigo}\" não está mais ativo.");
        }

        if ($cupom->vencido()) {
            throw new CupomRecusado("O cupom \"{$codigo}\" venceu em {$cupom->expires_at->format('d/m/Y')}.");
        }

        if ($cupom->esgotado()) {
            throw new CupomRecusado("O cupom \"{$codigo}\" já atingiu o limite de usos.");
        }

        return $cupom;
    }
}
