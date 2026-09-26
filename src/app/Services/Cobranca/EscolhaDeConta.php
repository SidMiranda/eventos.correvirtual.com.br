<?php

namespace App\Services\Cobranca;

use App\Models\MercadoPagoConta;
use App\Models\Payment;

/**
 * Com que conta se cobra ou se consulta — o único lugar que decide entre o
 * modelo novo (conta conectada do organizador) e o antigo (.env). ADR 0008.
 */
final class EscolhaDeConta
{
    /** Para criar um Pix: a conta conectada do organizador, se houver. */
    public static function paraOrganizador(int $organizerId): ContaDeCobranca
    {
        $conta = MercadoPagoConta::where('organizer_id', $organizerId)->first();

        if (! $conta) {
            return ContaDeCobranca::doEnv();
        }

        // Perto de vencer: renova antes de cobrar.
        RenovacaoDeToken::seNecessario($conta, RenovacaoDeToken::DIAS_NA_COBRANCA);

        return ContaDeCobranca::daConta($conta->fresh());
    }

    /**
     * Para consultar um pagamento que já existe: a MESMA conta que o criou —
     * mesmo que o organizador tenha conectado depois. Pagamento desconhecido
     * ou do modelo antigo: .env.
     */
    public static function paraPagamento(?Payment $pagamento): ContaDeCobranca
    {
        $conta = $pagamento?->mercado_pago_conta_id
            ? MercadoPagoConta::find($pagamento->mercado_pago_conta_id)
            : null;

        return $conta ? ContaDeCobranca::daConta($conta) : ContaDeCobranca::doEnv();
    }
}
