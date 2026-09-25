<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nas telas de um evento, o cabeçalho do painel mostra o banner daquele
 * evento — para o organizador saber onde está. Fora delas, o degradê.
 */
class BannerNoCabecalhoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $natal;
    private Event $halloween;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $organizador = Organizer::factory()->create();
        $this->admin = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $organizador->id]);

        $this->natal = Event::factory()->create(['organizer_id' => $organizador->id, 'banner_url' => 'x']);
        $this->halloween = Event::factory()->create(['organizer_id' => $organizador->id, 'banner_url' => 'x']);
    }

    private function banner(Event $evento): string
    {
        return "eventos/{$evento->id}/banner.jpg";
    }

    public function test_as_telas_do_evento_mostram_o_banner_dele(): void
    {
        $telas = [
            "/admin/eventos/{$this->natal->id}/edit",
            "/admin/eventos/{$this->natal->id}/kits",
            "/admin/eventos/{$this->natal->id}/modalidades",
            "/admin/eventos/{$this->natal->id}/lotes",
            "/admin/eventos/{$this->natal->id}/precos",
        ];

        foreach ($telas as $tela) {
            $this->actingAs($this->admin)->get($tela)
                ->assertOk()
                ->assertSee('page-header-dark page-header--banner', false)
                ->assertSee($this->banner($this->natal), false)
                ->assertDontSee($this->banner($this->halloween), false);
        }
    }

    public function test_trocar_de_evento_troca_o_banner(): void
    {
        $this->actingAs($this->admin)->get("/admin/eventos/{$this->halloween->id}/kits")
            ->assertSee($this->banner($this->halloween), false)
            ->assertDontSee($this->banner($this->natal), false);
    }

    public function test_evento_sem_banner_fica_no_degrade(): void
    {
        $this->natal->update(['banner_url' => null]);

        $this->actingAs($this->admin)->get("/admin/eventos/{$this->natal->id}/kits")
            ->assertOk()
            ->assertDontSee('page-header-dark page-header--banner', false)
            ->assertSee('bg-gradient-primary-to-secondary', false);
    }

    public function test_a_lista_de_eventos_fica_no_degrade(): void
    {
        // A lista percorre eventos com banner: nenhum deles pode vazar para o
        // cabeçalho.
        $this->actingAs($this->admin)->get('/admin/eventos')
            ->assertOk()
            ->assertDontSee('page-header-dark page-header--banner', false)
            ->assertSee('bg-gradient-primary-to-secondary', false);
    }

    public function test_dados_do_evento_mantem_as_abas_do_evento(): void
    {
        $this->actingAs($this->admin)->get("/admin/eventos/{$this->natal->id}/edit")
            ->assertOk()
            ->assertSee('nav nav-tabs', false)
            ->assertSee("/admin/eventos/{$this->natal->id}/kits", false)
            ->assertSee("/admin/eventos/{$this->natal->id}/precos", false);
    }
}
