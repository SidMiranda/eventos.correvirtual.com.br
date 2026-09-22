<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carrega os municípios do IBGE na tabela `cities`.
 *
 * A fonte é um arquivo versionado (`database/data/municipios-ibge.json`),
 * gerado uma vez a partir de
 * `servicodados.ibge.gov.br/api/v1/localidades/municipios`. O arquivo fica no
 * repositório de propósito: a lista muda de anos em anos, e buscar na API
 * durante o deploy tornaria a subida refém de um serviço de fora estar no ar.
 *
 * Idempotente — reconhece a cidade pelo código do IBGE, então rodar de novo
 * atualiza nome e UF sem criar duplicata e sem perder o vínculo de ninguém
 * (`users.city_id` aponta para o `id` da tabela, que não muda).
 */
class ImportarCidades extends Command
{
    protected $signature = 'cidades:importar {--force : Grava de verdade (sem isto, só mostra o que mudaria)}';

    protected $description = 'Carrega os municípios do IBGE na tabela cities';

    public function handle(): int
    {
        $caminho = database_path('data/municipios-ibge.json');

        if (! is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $municipios = json_decode(file_get_contents($caminho), true);

        if (! is_array($municipios) || $municipios === []) {
            $this->error('O arquivo não tem uma lista de municípios válida.');

            return self::FAILURE;
        }

        $this->info('Banco: ' . DB::connection()->getDatabaseName());
        $this->line('Arquivo: ' . count($municipios) . ' municípios.');

        $existentes = City::count();
        $this->line("Na tabela hoje: {$existentes}.");

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Simulação. Rode com --force para gravar.');

            return self::SUCCESS;
        }

        $linhas = array_map(fn (array $m) => [
            'ibge_code' => $m['id'],
            'name' => $m['nome'],
            'name_normalized' => City::normalizar($m['nome']),
            'state' => $m['uf'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $municipios);

        // Em blocos: 5.571 linhas num INSERT só estoura o limite de
        // placeholders do driver. O upsert casa pelo código do IBGE.
        foreach (array_chunk($linhas, 500) as $bloco) {
            City::upsert($bloco, ['ibge_code'], ['name', 'name_normalized', 'state', 'updated_at']);
        }

        $this->newLine();
        $this->info('Pronto. Na tabela agora: ' . City::count() . '.');

        return self::SUCCESS;
    }
}
