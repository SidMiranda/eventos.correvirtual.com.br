<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\Photo;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A galeria de fotos na home.
 *
 * A faixa no estilo do feed do Instagram, logo depois dos próximos eventos:
 * até 12 fotos ativas do organizador do domínio, na ordem dele, e nada quando
 * não há foto.
 */
class FotosNoSiteTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
        config(['galeria.realizados' => []]);
    }

    private function foto(array $sobrescreve = []): Photo
    {
        return Photo::factory()->create(array_merge([
            'organizer_id' => $this->organizador->id,
        ], $sobrescreve));
    }

    public function test_a_secao_nao_aparece_quando_nao_ha_foto(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="fotos"', false)
            ->assertDontSee('GALERIA <span> DE FOTOS </span>', false);
    }

    public function test_foto_ativa_aparece_com_a_imagem_do_caminho_derivado(): void
    {
        $foto = $this->foto(['caption' => 'Largada da prova']);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="fotos"', false)
            ->assertSee("organizadores/{$this->organizador->id}/fotos/{$foto->id}.jpg", false)
            // A faixa mostra só o recorte; a inteira fica no bucket para um
            // "ver inteira" futuro e não entra no HTML.
            ->assertDontSee('-inteira.jpg', false)
            ->assertSee('alt="Largada da prova"', false);
    }

    public function test_foto_inativa_fica_fora_do_site(): void
    {
        $this->foto(['caption' => 'Escondida', 'active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="fotos"', false)
            ->assertDontSee('Escondida');
    }

    public function test_foto_de_outro_organizador_nao_aparece(): void
    {
        $vizinho = Organizer::factory()->create();
        Photo::factory()->create(['organizer_id' => $vizinho->id, 'caption' => 'Foto Do Vizinho']);

        $this->get('/')->assertOk()->assertDontSee('Foto Do Vizinho')->assertDontSee('id="fotos"', false);
    }

    public function test_a_ordem_e_a_que_o_organizador_definiu(): void
    {
        $this->foto(['caption' => 'Aparece Depois', 'position' => 9]);
        $this->foto(['caption' => 'Aparece Antes', 'position' => 1]);

        $conteudo = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($conteudo, 'Aparece Depois'),
            strpos($conteudo, 'Aparece Antes')
        );
    }

    public function test_a_home_mostra_no_maximo_doze(): void
    {
        foreach (range(1, 14) as $i) {
            $this->foto(['caption' => "Foto {$i}", 'position' => $i]);
        }

        $conteudo = $this->get('/')->assertOk()->getContent();

        // Só as fotos: class="foto" fechado, para não contar os invólucros
        // (.fotos-secao e .fotos) nem as com link (não há nenhuma aqui).
        $this->assertSame(12, substr_count($conteudo, 'class="foto"'));
        $this->assertStringContainsString('Foto 12', $conteudo);
        $this->assertStringNotContainsString('Foto 13', $conteudo);
    }

    public function test_foto_com_link_abre_em_aba_nova_e_sem_link_nao_e_link(): void
    {
        $this->foto(['caption' => 'Com Link', 'link_url' => 'https://www.instagram.com/p/abc/']);
        $this->foto(['caption' => 'Sem Link']);

        $conteudo = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="https://www.instagram.com/p/abc/"', $conteudo);
        // rel="noopener": sem isso a página aberta ganha acesso a esta.
        $this->assertStringContainsString('class="foto foto--link" href="https://www.instagram.com/p/abc/"', $conteudo);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $conteudo);
        $this->assertStringContainsString('<figure class="foto" title="Sem Link">', $conteudo);
    }

    public function test_a_secao_fica_entre_proximos_eventos_e_patrocinadores(): void
    {
        $this->foto();
        Sponsor::factory()->create(['organizer_id' => $this->organizador->id]);

        $conteudo = $this->get('/')->assertOk()->getContent();

        $eventos = strpos($conteudo, 'id="eventos"');
        $fotos = strpos($conteudo, 'id="fotos"');
        $patrocinadores = strpos($conteudo, 'id="patrocinadores"');

        $this->assertLessThan($fotos, $eventos);
        $this->assertLessThan($patrocinadores, $fotos);
    }
}
