<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Support\Arquivos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ver docs/specs/armazenamento-r2.md.
 *
 * O que estes testes protegem: virar a chave do CDN tem que ser trocar UMA
 * variável. Se alguém voltar a montar caminho de imagem na mão numa view, a
 * troca deixa de funcionar e o site cai no fallback em silêncio.
 */
class ArquivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Organizer::factory()->create(['domain' => 'localhost']);
    }

    private function eventoCom(array $atributos = []): Event
    {
        $organizador = Organizer::factory()->create();

        return Event::factory()->create(array_merge([
            'organizer_id' => $organizador->id,
            'banner_url' => 'alguma-coisa.jpg',
        ], $atributos));
    }

    public function test_sem_cdn_configurado_serve_do_disco_local(): void
    {
        config(['arquivos.base_url' => '']);
        $evento = $this->eventoCom();

        $url = Arquivos::cardDoEvento($evento);

        $this->assertStringContainsString('/images/', $url);
        $this->assertStringContainsString(
            "organizadores/{$evento->organizer_id}/eventos/{$evento->id}/card.jpg",
            $url
        );
    }

    public function test_com_cdn_configurado_serve_do_cdn(): void
    {
        config(['arquivos.base_url' => 'https://cdn.exemplo.com/publico']);
        $evento = $this->eventoCom();

        $this->assertSame(
            "https://cdn.exemplo.com/publico/organizadores/{$evento->organizer_id}/eventos/{$evento->id}/card.jpg"
                . '?v=' . $evento->updated_at->timestamp,
            Arquivos::cardDoEvento($evento)
        );
    }

    public function test_caminho_da_imagem_carrega_o_organizador_dono_do_evento(): void
    {
        // Se o caminho fosse montado só com o id do evento, imagem de um
        // organizador poderia acabar servida sob o prefixo de outro.
        config(['arquivos.base_url' => 'https://cdn.exemplo.com/publico']);

        $doA = $this->eventoCom();
        $doB = $this->eventoCom();

        $this->assertStringContainsString("/organizadores/{$doA->organizer_id}/", Arquivos::cardDoEvento($doA));
        $this->assertStringNotContainsString("/organizadores/{$doB->organizer_id}/", Arquivos::cardDoEvento($doA));
    }

    public function test_evento_sem_imagem_cai_no_padrao(): void
    {
        config(['arquivos.base_url' => 'https://cdn.exemplo.com/publico']);
        $evento = $this->eventoCom(['banner_url' => null]);

        $this->assertSame(
            'https://cdn.exemplo.com/publico/plataforma/padrao/card.jpg',
            Arquivos::cardDoEvento($evento)
        );
        $this->assertSame(
            'https://cdn.exemplo.com/publico/plataforma/padrao/banner.jpg',
            Arquivos::bannerDoEvento($evento)
        );
    }

    public function test_barra_no_fim_do_cdn_nao_duplica(): void
    {
        config(['arquivos.base_url' => rtrim('https://cdn.exemplo.com/publico/', '/')]);

        $this->assertStringNotContainsString('//plataforma', Arquivos::cardPadrao());
    }

    public function test_com_cdn_ligado_nao_consulta_o_disco(): void
    {
        // Com CDN, checar existência custaria uma requisição de rede por imagem.
        // organizadorTem() responde true sem tocar o disco; o onerror do <img>
        // cobre o arquivo faltando.
        config(['arquivos.base_url' => 'https://cdn.exemplo.com/publico']);

        $this->assertTrue(Arquivos::organizadorTem('organizadores/999/nao-existe.jpg'));
    }

    public function test_sem_cdn_a_existencia_e_conferida_no_disco(): void
    {
        config(['arquivos.base_url' => '']);

        $this->assertTrue(Arquivos::organizadorTem('plataforma/padrao/card.jpg'));
        $this->assertFalse(Arquivos::organizadorTem('organizadores/999/nao-existe.jpg'));
    }

    public function test_nenhuma_view_monta_caminho_de_imagem_na_mao(): void
    {
        // Esta é a rede de proteção da migração: se voltar a existir caminho
        // literal ou file_exists numa view, o CDN para de funcionar sem avisar.
        $suspeitas = [];

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $caminho = str_replace('\\', '/', $arquivo->getPathname());

            $conteudo = file_get_contents($arquivo->getPathname());

            if (preg_match("#file_exists\(public_path#", $conteudo)
                || preg_match("#asset\(\s*'images/#", $conteudo)) {
                $suspeitas[] = basename($caminho);
            }
        }

        $this->assertSame([], $suspeitas,
            'Estas views voltaram a montar caminho de imagem na mão: ' . implode(', ', $suspeitas)
        );
    }

    public function test_url_da_imagem_muda_quando_o_evento_e_atualizado(): void
    {
        // O caminho no bucket é derivado do id e não muda quando a imagem é
        // trocada, e ela sobe com Cache-Control immutable. Sem o ?v=, o CDN
        // serviria a imagem antiga por um ano — foi assim que a primeira carga
        // de eventos saiu com as artes trocadas.
        config(['arquivos.base_url' => 'https://cdn.exemplo.com/publico']);
        $evento = $this->eventoCom();

        $antes = Arquivos::cardDoEvento($evento);

        // Data explícita: touch() no mesmo segundo não muda o timestamp.
        $evento->forceFill(['updated_at' => now()->addHour()])->saveQuietly();
        $evento->refresh();

        $this->assertStringContainsString('?v=', $antes);
        $this->assertNotSame($antes, Arquivos::cardDoEvento($evento));
    }
}
