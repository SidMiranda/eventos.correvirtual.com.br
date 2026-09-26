<?php

namespace App\Console\Commands;

use App\Services\Cobranca\ConfiguracaoDaPlataforma;
use Illuminate\Console\Command;

/**
 * Mostra ou muda a taxa da plataforma por inscrição paga (ADR 0008). O valor
 * vale a partir do próximo Pix gerado — sem deploy e sem o organizador
 * autorizar de novo. Pix já gerado mantém a taxa com que saiu.
 */
class DefinirTaxaDaPlataforma extends Command
{
    protected $signature = 'plataforma:taxa {valor? : novo valor em reais, ex.: 0.80}';

    protected $description = 'Mostra ou define a taxa da plataforma por inscrição paga';

    public function handle(): int
    {
        $valor = $this->argument('valor');

        if ($valor === null) {
            $this->info('Taxa atual: R$ ' . number_format(ConfiguracaoDaPlataforma::taxaDeInscricao(), 2, ',', '.'));

            return self::SUCCESS;
        }

        $valor = str_replace(',', '.', $valor);
        if (! is_numeric($valor) || (float) $valor < 0 || (float) $valor > 50) {
            $this->error('Valor inválido: use reais com ponto ou vírgula, entre 0 e 50 (ex.: 0.80).');

            return self::INVALID;
        }

        ConfiguracaoDaPlataforma::definir(ConfiguracaoDaPlataforma::TAXA_INSCRICAO, number_format((float) $valor, 2, '.', ''));
        $this->info('Taxa definida: R$ ' . number_format((float) $valor, 2, ',', '.'));

        return self::SUCCESS;
    }
}
