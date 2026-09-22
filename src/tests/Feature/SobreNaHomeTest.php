<?php

namespace Tests\Feature;

use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O bloco "Sobre nós" na home pública.
 *
 * Até 2026-09-22 tag, título, os quatro parágrafos e o rótulo do botão eram
 * texto fixo no Blade, iguais em todos os sites da plataforma — inclusive
 * falando da Corre Virtual no site de outro organizador. O botão apontava para
 * `href="#"`.
 *
 * O layout não mudou: as duas metades continuam `flex: 1` dentro de
 * `.cv-container`, empilhando abaixo de 768px.
 */
class SobreNaHomeTest extends TestCase
{
    use RefreshDatabase;

    private function organizador(array $sobrescreve = []): Organizer
    {
        return Organizer::factory()->create(array_merge([
            'domain' => 'localhost',
            'about_badge' => 'SOBRE A PLATAFORMA',
            'about_title' => 'Corre Virtual - Desafie seus limites',
            'about_text' => 'Uma experiência completa de treinos e corridas.',
            'about_button_label' => 'COMEÇAR MEU DESAFIO',
            'about_button_url' => 'https://correvirtual.com.br/desafios',
        ], $sobrescreve));
    }

    public function test_a_home_mostra_o_que_foi_cadastrado(): void
    {
        $this->organizador();

        $this->get('/')
            ->assertOk()
            ->assertSee('SOBRE A PLATAFORMA')
            ->assertSee('Corre Virtual - Desafie seus limites')
            ->assertSee('Uma experiência completa de treinos e corridas.')
            ->assertSee('COMEÇAR MEU DESAFIO')
            ->assertSee('https://correvirtual.com.br/desafios', false);
    }

    public function test_o_layout_de_duas_metades_continua(): void
    {
        $this->organizador();

        $this->get('/')
            ->assertOk()
            ->assertSee('cv-container', false)
            ->assertSee('cv-text-card', false)
            ->assertSee('cv-video-card', false);
    }

    public function test_sem_titulo_e_texto_a_secao_inteira_some(): void
    {
        // É o caso do organizador que ainda não escreveu o próprio texto:
        // melhor uma seção a menos que meia seção vazia.
        $this->organizador(['about_title' => null, 'about_text' => null]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('SOBRE <span> NOS </span>', false)
            ->assertDontSee('cv-container', false);
    }

    public function test_sem_link_o_botao_nao_aparece(): void
    {
        // Antes o botão existia sempre, com href="#": não levava a lugar nenhum.
        $this->organizador(['about_button_url' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Corre Virtual - Desafie seus limites')
            ->assertDontSee('class="cv-btn"', false);
    }

    public function test_o_texto_respeita_a_quebra_de_linha_e_o_negrito(): void
    {
        $this->organizador([
            'about_text' => "Primeiro parágrafo.\n\nA **Corre Virtual** é assim.",
        ]);

        $resposta = $this->get('/')->assertOk();

        $this->assertStringContainsString("Primeiro parágrafo.<br />\n<br />\n", $resposta->getContent());
        $resposta->assertSee('A <strong>Corre Virtual</strong> é assim.', false);
    }

    public function test_o_texto_nao_injeta_html(): void
    {
        $this->organizador(['about_text' => 'Somos <script>alert(1)</script> demais']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_sem_tag_o_bloco_continua_de_pe(): void
    {
        // A tag é enfeite; título e texto é que seguram o bloco.
        $this->organizador(['about_badge' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Corre Virtual - Desafie seus limites')
            ->assertDontSee('class="cv-badge"', false);
    }
}
