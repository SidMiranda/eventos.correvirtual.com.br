<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\Arquivos;
use App\Support\ImagensDoEvento;
use Illuminate\Console\Command;

/**
 * Preenche `events.banner_ratio` para os eventos que já tinham banner.
 *
 * A coluna passou a ser medida no upload (ImagensDoEvento::salvarBanner), mas
 * quem já tinha imagem enviada antes disso nasceu vazio — e continuaria no
 * degradê para sempre, inclusive quem mandou um banner horizontal de verdade.
 * Este comando baixa cada banner do CDN uma vez e anota a proporção.
 *
 * Simula por padrão; só grava com --force. Roda quantas vezes quiser.
 */
class MedirBannersDosEventos extends Command
{
    protected $signature = 'eventos:medir-banners {--force : Grava de verdade (sem isto, só mostra o que mudaria)}';

    protected $description = 'Mede a proporção do banner de cada evento e anota em events.banner_ratio';

    public function handle(): int
    {
        $eventos = Event::whereNotNull('banner_url')->orderBy('id')->get();

        $this->info('Banco: ' . \DB::connection()->getDatabaseName());
        $this->line("Eventos com banner: {$eventos->count()}");
        $this->newLine();

        $mudariam = 0;

        foreach ($eventos as $evento) {
            $url = Arquivos::bannerDoEvento($evento);
            $bytes = @file_get_contents($url);

            if ($bytes === false) {
                $this->line(sprintf('  #%-3d %-42s  banner não baixou (%s)', $evento->id, mb_substr($evento->title, 0, 42), $url));
                continue;
            }

            $medidas = @getimagesizefromstring($bytes);
            $proporcao = ImagensDoEvento::proporcao($bytes);
            $larga = $proporcao !== null && $proporcao >= ImagensDoEvento::PROPORCAO_DE_BANNER;
            $atual = $evento->banner_ratio === null ? null : round((float) $evento->banner_ratio, 3);

            $this->line(sprintf(
                '  #%-3d %-42s  %-11s %-6s %-9s %s',
                $evento->id,
                mb_substr($evento->title, 0, 42),
                $medidas ? "{$medidas[0]}x{$medidas[1]}" : 'ilegível',
                $proporcao !== null ? number_format($proporcao, 2) . ':1' : '—',
                $larga ? 'BANNER' : 'cartaz',
                $atual === $proporcao ? '' : '← muda'
            ));

            if ($atual === $proporcao) {
                continue;
            }

            $mudariam++;

            if ($this->option('force')) {
                $evento->banner_ratio = $proporcao;
                $evento->save();
            }
        }

        $this->newLine();

        if (! $this->option('force')) {
            $this->warn("Simulação: {$mudariam} evento(s) mudariam. Repita com --force para gravar.");

            return self::SUCCESS;
        }

        $this->info("Feito. {$mudariam} evento(s) atualizado(s).");

        return self::SUCCESS;
    }
}
