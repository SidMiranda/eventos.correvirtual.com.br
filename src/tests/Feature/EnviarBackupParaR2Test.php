<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A cópia do dump para o bucket privado.
 */
class EnviarBackupParaR2Test extends TestCase
{
    public function test_envia_o_arquivo_para_backups_no_bucket_privado(): void
    {
        Storage::fake('r2_privado');

        $arquivo = tempnam(sys_get_temp_dir(), 'dump');
        $nome = basename($arquivo);
        file_put_contents($arquivo, str_repeat('CREATE TABLE ', 200));

        $this->artisan('backup:enviar-r2', ['arquivo' => $arquivo])
            ->expectsOutputToContain("backups/{$nome}")
            ->assertSuccessful();

        Storage::disk('r2_privado')->assertExists("backups/{$nome}");
        $this->assertSame(file_get_contents($arquivo), Storage::disk('r2_privado')->get("backups/{$nome}"));

        unlink($arquivo);
    }

    public function test_arquivo_inexistente_falha_sem_enviar_nada(): void
    {
        Storage::fake('r2_privado');

        $this->artisan('backup:enviar-r2', ['arquivo' => '/tmp/nao-existe.sql.gz'])
            ->expectsOutputToContain('não encontrado')
            ->assertFailed();

        $this->assertSame([], Storage::disk('r2_privado')->allFiles());
    }
}
