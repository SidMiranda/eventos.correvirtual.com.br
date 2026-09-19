<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sobe um dump do banco para o bucket privado do R2.
 *
 * O backup diário fica no disco da VPS (docs/runbook.md, "Backup do banco").
 * Isto é a cópia fora dela: o bucket `correvirtual-privado` não tem domínio
 * público — só se chega nele com a credencial que o próprio app usa. O
 * arquivo é enviado em stream e conferido pelo tamanho depois de subir.
 *
 * Usado pelo workflow "Zerar uso em produção" antes de apagar qualquer coisa;
 * serve igual para o cron diário passar a deixar uma cópia off-site.
 */
class EnviarBackupParaR2 extends Command
{
    protected $signature = 'backup:enviar-r2 {arquivo : Caminho do dump (.sql.gz) dentro do container}';

    protected $description = 'Copia um dump do banco para o bucket privado do R2, em backups/';

    public function handle(): int
    {
        $arquivo = $this->argument('arquivo');

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $destino = 'backups/' . basename($arquivo);
        $tamanho = filesize($arquivo);

        try {
            $stream = fopen($arquivo, 'r');
            Storage::disk('r2_privado')->writeStream($destino, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $noBucket = Storage::disk('r2_privado')->size($destino);
        } catch (\Throwable $e) {
            $this->error('Falha ao enviar para o R2: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($noBucket !== $tamanho) {
            $this->error("Tamanho diferente depois do envio: local {$tamanho} bytes, bucket {$noBucket} bytes.");

            return self::FAILURE;
        }

        $this->info(sprintf('Enviado: %s (%s)', $destino, $this->legivel($tamanho)));

        return self::SUCCESS;
    }

    private function legivel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
}
