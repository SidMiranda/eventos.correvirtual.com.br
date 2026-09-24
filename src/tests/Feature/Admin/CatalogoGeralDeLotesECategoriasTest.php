<?php

namespace Tests\Feature\Admin;

use App\Models\AgeCategory;
use App\Models\Event;
use App\Models\EventLot;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As páginas gerais de lotes e categorias (menu lateral › Eventos), atravessando
 * todos os eventos do organizador — e só dele.
 */
class CatalogoGeralDeLotesECategoriasTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;
    private Event $eventoDoA;
    private Event $eventoDoB;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $organizadorA = Organizer::factory()->create();
        $organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $organizadorA->id]);
        $this->eventoDoA = Event::factory()->create(['organizer_id' => $organizadorA->id, 'title' => 'Corrida do A', 'event_date' => now()->addMonths(2)]);
        $this->eventoDoB = Event::factory()->create(['organizer_id' => $organizadorB->id, 'title' => 'Corrida do B', 'event_date' => now()->addMonths(2)]);
    }

    public function test_lotes_gerais_so_mostram_os_do_organizador(): void
    {
        EventLot::factory()->create(['event_id' => $this->eventoDoA->id, 'name' => 'Lote do A']);
        EventLot::factory()->create(['event_id' => $this->eventoDoB->id, 'name' => 'Lote do B']);

        $this->actingAs($this->adminA)
            ->get('/admin/lotes')
            ->assertOk()
            ->assertSee('Lote do A')
            ->assertSee('Corrida do A')
            ->assertDontSee('Lote do B')
            ->assertDontSee('Corrida do B');
    }

    public function test_categorias_gerais_so_mostram_as_do_organizador(): void
    {
        AgeCategory::factory()->create(['event_id' => $this->eventoDoA->id, 'name' => 'Idoso do A']);
        AgeCategory::factory()->create(['event_id' => $this->eventoDoB->id, 'name' => 'Idoso do B']);

        $this->actingAs($this->adminA)
            ->get('/admin/categorias')
            ->assertOk()
            ->assertSee('Idoso do A')
            ->assertDontSee('Idoso do B');
    }

    public function test_atalho_de_cadastro_leva_ao_formulario_do_evento_escolhido(): void
    {
        $this->actingAs($this->adminA)
            ->get("/admin/catalogo/lotes/novo?evento={$this->eventoDoA->id}")
            ->assertRedirect("/admin/eventos/{$this->eventoDoA->id}/lotes/create");

        $this->actingAs($this->adminA)
            ->get("/admin/catalogo/categorias/novo?evento={$this->eventoDoA->id}")
            ->assertRedirect("/admin/eventos/{$this->eventoDoA->id}/categorias/create");
    }

    public function test_atalho_recusa_evento_de_outro_organizador(): void
    {
        $this->actingAs($this->adminA)
            ->get("/admin/catalogo/lotes/novo?evento={$this->eventoDoB->id}")
            ->assertRedirect('/admin/lotes')
            ->assertSessionHasErrors('evento');

        $this->actingAs($this->adminA)
            ->get("/admin/catalogo/categorias/novo?evento={$this->eventoDoB->id}")
            ->assertRedirect('/admin/categorias')
            ->assertSessionHasErrors('evento');
    }

    public function test_tipo_desconhecido_no_atalho_da_404(): void
    {
        $this->actingAs($this->adminA)
            ->get("/admin/catalogo/precos/novo?evento={$this->eventoDoA->id}")
            ->assertNotFound();
    }

    public function test_o_menu_agrupa_os_itens_do_evento(): void
    {
        $html = $this->actingAs($this->adminA)->get('/admin/lotes')->assertOk()->getContent();

        $this->assertStringContainsString('id="collapseEventos"', $html);
        // Numa página do grupo, ele vem aberto.
        $this->assertStringContainsString('class="collapse show" id="collapseEventos"', $html);
        foreach (['/admin/eventos', '/admin/modalidades', '/admin/kits', '/admin/lotes', '/admin/categorias'] as $rota) {
            $this->assertStringContainsString("href=\"http://localhost{$rota}\"", $html);
        }
    }

    public function test_fora_do_grupo_o_menu_vem_fechado(): void
    {
        $html = $this->actingAs($this->adminA)->get('/admin/cupons')->assertOk()->getContent();

        $this->assertStringContainsString('class="collapse " id="collapseEventos"', $html);
    }

    public function test_deslogado_e_atleta_nao_entram(): void
    {
        $this->get('/admin/lotes')->assertRedirect('/login');
        $this->get('/admin/categorias')->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/lotes')->assertForbidden();
        $this->actingAs($atleta)->get('/admin/categorias')->assertForbidden();
    }
}
