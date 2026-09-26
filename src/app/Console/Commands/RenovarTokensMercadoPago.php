<?php

namespace App\Console\Commands;

use App\Models\MercadoPagoConta;
use App\Services\Cobranca\RenovacaoDeToken;
use Illuminate\Console\Command;

/**
 * Renova os tokens das contas Mercado Pago conectadas que vencem em breve
 * (ADR 0008). Agendado todo dia em routes/console.php; a falha de cada conta
 * vira alerta (RenovacaoDeToken) e não impede as outras.
 */
class RenovarTokensMercadoPago extends Command
{
    protected $signature = 'mercadopago:renovar-tokens {--dias=30 : renova quem vence em até N dias}';

    protected $description = 'Renova os tokens OAuth das contas Mercado Pago conectadas que vencem em breve';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        $vencendo = MercadoPagoConta::whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($dias))
            ->get();

        if ($vencendo->isEmpty()) {
            $this->info("Nenhuma conta vence nos próximos {$dias} dias.");

            return self::SUCCESS;
        }

        $falhas = 0;
        foreach ($vencendo as $conta) {
            $ok = RenovacaoDeToken::renovar($conta);
            $falhas += $ok ? 0 : 1;
            $this->line(($ok ? 'renovada' : 'FALHOU') . " — organizador {$conta->organizer_id}");
        }

        return $falhas ? self::FAILURE : self::SUCCESS;
    }
}
