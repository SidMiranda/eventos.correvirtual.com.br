<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\PrecosLegados;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Roda de novo a migração de preços (kit → lote + grade) para quem ficou
 * faltando: evento criado depois da migration, kit novo sem vínculo, etc.
 *
 * Idempotente — só cria o que falta, nunca sobrescreve o que o organizador
 * já mexeu. Simula por padrão; grava com --force.
 */
class MigrarPrecosDosKits extends Command
{
    protected $signature = 'eventos:migrar-precos {--force : Grava de verdade (sem isto, só mostra o que mudaria)}';

    protected $description = 'Cria Lote 1, vínculos kit-modalidade, preços e tamanhos para eventos que ainda não têm';

    public function handle(): int
    {
        $this->info('Banco: ' . DB::connection()->getDatabaseName());

        if (! $this->option('force')) {
            $this->line('Eventos sem lote: ' . Event::doesntHave('lots')->count());
            $this->line('Kits sem vínculo com modalidade: ' . \App\Models\EventKit::doesntHave('modalities')->count());
            $this->newLine();
            $this->warn('Simulação. Rode com --force para gravar.');

            return self::SUCCESS;
        }

        $feito = PrecosLegados::migrar();

        $this->info(sprintf(
            'Pronto. Lotes: %d, vínculos: %d, preços: %d, tamanhos: %d.',
            $feito['lotes'], $feito['vinculos'], $feito['precos'], $feito['tamanhos']
        ));

        return self::SUCCESS;
    }
}
