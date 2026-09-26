<?php

namespace App\Services\Cobranca;

use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * Quanto a plataforma retém de uma inscrição (o `application_fee`), ou null.
 *
 * Regras do dono (MATRIX 2026-09-26, ADR 0008):
 *  - só com a conta do organizador conectada (sem ela, não há split possível);
 *  - só em evento de 2027 em diante (ano de `event_date`) — 2026 não tem taxa;
 *  - o valor vem de platform_settings, lido na hora.
 * E uma trava de segurança nossa: inscrição que não comporta a taxa (valor
 * menor ou igual a ela) sai sem taxa — o Mercado Pago recusaria a cobrança.
 */
final class TaxaDaPlataforma
{
    public const A_PARTIR_DO_ANO = 2027;

    public static function para(Subscription $inscricao, ContaDeCobranca $conta): ?float
    {
        if (! $conta->conectada()) {
            return null;
        }

        $evento = $inscricao->event;

        if ($evento?->event_date === null || $evento->event_date->year < self::A_PARTIR_DO_ANO) {
            return null;
        }

        $taxa = ConfiguracaoDaPlataforma::taxaDeInscricao();

        if ($taxa <= 0) {
            return null;
        }

        if ((float) $inscricao->price <= $taxa) {
            Log::warning('Inscrição sem taxa da plataforma: o valor não comporta a taxa.', [
                'subscription_id' => $inscricao->id,
                'valor' => $inscricao->price,
                'taxa' => $taxa,
            ]);

            return null;
        }

        return $taxa;
    }
}
