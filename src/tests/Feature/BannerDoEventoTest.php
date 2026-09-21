<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\User;
use App\Support\ImagensDoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O banner no topo da página do evento.
 *
 * O campo de banner do painel recebeu, na prática, duas coisas diferentes: um
 * banner horizontal de verdade e o cartaz da prova (retrato). Cartaz retrato
 * num quadro largo fica recortado no nome e na data — foi por isso que o topo
 * virou um degradê fixo em 2026-08-30, e a imagem deixou de ser usada.
 *
 * O que estes testes protegem é a distinção: banner largo aparece, cartaz
 * retrato continua no degradê.
 */
class BannerDoEventoTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->admin = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizador->id,
        ]);
    }

    private function evento(array $extra = []): Event
    {
        $evento = Event::factory()->create(array_merge([
            'organizer_id' => $this->organizador->id,
            'event_date' => now()->addMonths(2),
            'registration_deadline' => now()->addMonth(),
            'active' => true,
        ], $extra));

        EventModality::factory()->create(['event_id' => $evento->id]);
        EventKit::factory()->create(['event_id' => $evento->id]);

        return $evento;
    }

    /*
    |--------------------------------------------------------------------------
    | A medida
    |--------------------------------------------------------------------------
    */

    public function test_reconhece_banner_horizontal_e_cartaz_retrato(): void
    {
        $imagem = fn (int $l, int $a) => UploadedFile::fake()->image('x.jpg', $l, $a)->get();

        // O banner de verdade recebido em produção: 1600x320 (5:1).
        $this->assertTrue(ImagensDoEvento::ehLarga($imagem(1600, 320)));
        // O cartaz da prova: 1080x1920.
        $this->assertFalse(ImagensDoEvento::ehLarga($imagem(1080, 1920)));
        // Quadrado não serve para um quadro de 320px de altura.
        $this->assertFalse(ImagensDoEvento::ehLarga($imagem(800, 800)));
        // No limite exato de 2:1, vale.
        $this->assertTrue(ImagensDoEvento::ehLarga($imagem(1200, 600)));

        $this->assertFalse(ImagensDoEvento::ehLarga('isto não é imagem'));
        $this->assertNull(ImagensDoEvento::proporcao('isto não é imagem'));
        $this->assertSame(5.0, ImagensDoEvento::proporcao($imagem(1600, 320)));
    }

    /*
    |--------------------------------------------------------------------------
    | O upload pelo painel
    |--------------------------------------------------------------------------
    */

    public function test_upload_de_banner_horizontal_marca_o_evento(): void
    {
        $evento = $this->evento();

        $this->actingAs($this->admin)->put("/admin/eventos/{$evento->id}", [
            'title' => $evento->title,
            'description' => 'Descrição.',
            'location' => 'Mogi Guaçu - SP',
            'event_date' => $evento->event_date->format('Y-m-d\TH:i'),
            'registration_deadline' => $evento->registration_deadline->format('Y-m-d\TH:i'),
            'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 320),
            'active' => 1,
        ]);

        $this->assertSame(5.0, (float) $evento->fresh()->banner_ratio);
        $this->assertTrue($evento->fresh()->temBannerParaOTopo());
    }

    public function test_upload_do_cartaz_retrato_no_campo_de_banner_nao_marca(): void
    {
        $evento = $this->evento();

        $this->actingAs($this->admin)->put("/admin/eventos/{$evento->id}", [
            'title' => $evento->title,
            'description' => 'Descrição.',
            'location' => 'Mogi Guaçu - SP',
            'event_date' => $evento->event_date->format('Y-m-d\TH:i'),
            'registration_deadline' => $evento->registration_deadline->format('Y-m-d\TH:i'),
            'banner' => UploadedFile::fake()->image('cartaz.jpg', 1080, 1920),
            'active' => 1,
        ]);

        $evento->refresh();

        $this->assertSame(0.563, round((float) $evento->banner_ratio, 3));
        $this->assertFalse($evento->temBannerParaOTopo());
        // A imagem foi salva mesmo assim: só não serve para o topo.
        Storage::disk('r2')->assertExists(ImagensDoEvento::caminho($evento, 'banner'));
    }

    /*
    |--------------------------------------------------------------------------
    | A página do evento
    |--------------------------------------------------------------------------
    */

    public function test_pagina_mostra_o_banner_quando_ele_e_horizontal(): void
    {
        $evento = $this->evento(['banner_url' => 'banner.jpg', 'banner_ratio' => 5.0]);

        $this->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('event-banner--imagem', false)
            ->assertSee("eventos/{$evento->id}/banner.jpg", false);
    }

    public function test_pagina_fica_no_degrade_quando_o_banner_e_retrato(): void
    {
        $evento = $this->evento(['banner_url' => 'banner.jpg', 'banner_ratio' => 0.563]);

        $this->get("/event/{$evento->id}")
            ->assertOk()
            ->assertDontSee('event-banner--imagem', false)
            ->assertDontSee("eventos/{$evento->id}/banner.jpg", false);
    }

    public function test_pagina_fica_no_degrade_quando_nao_ha_banner(): void
    {
        $evento = $this->evento(['banner_url' => null, 'banner_ratio' => null]);

        $this->get("/event/{$evento->id}")
            ->assertOk()
            ->assertDontSee('event-banner--imagem', false);
    }

    public function test_o_nome_do_evento_continua_no_html_nos_dois_casos(): void
    {
        // Com banner, o nome sai da vista (a arte já o traz) mas continua no
        // HTML para buscador e leitor de tela. Sem banner, aparece grande.
        $comBanner = $this->evento(['title' => 'Corrida Com Banner', 'banner_url' => 'banner.jpg', 'banner_ratio' => 5.0]);
        $semBanner = $this->evento(['title' => 'Corrida Sem Banner']);

        $this->get("/event/{$comBanner->id}")->assertOk()->assertSee('Corrida Com Banner');
        $this->get("/event/{$semBanner->id}")->assertOk()->assertSee('Corrida Sem Banner');
    }
}
