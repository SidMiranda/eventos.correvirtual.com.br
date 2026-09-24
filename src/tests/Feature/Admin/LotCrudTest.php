<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventModality;
use App\Models\EventPrice;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lotes no painel: cadastro e isolamento por organizador.
 */
class LotCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private User $adminA;
    private Event $eventoDoA;
    private Event $eventoDoB;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $this->organizadorA = Organizer::factory()->create();
        $organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $this->organizadorA->id]);

        $this->eventoDoA = Event::factory()->create(['organizer_id' => $this->organizadorA->id, 'event_date' => now()->addMonths(2)]);
        $this->eventoDoB = Event::factory()->create(['organizer_id' => $organizadorB->id, 'event_date' => now()->addMonths(2)]);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Lote 1',
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'max_subscriptions' => '',
            'position' => 0,
            'active' => 1,
        ], $extra);
    }

    public function test_cria_lote_no_evento_proprio(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/lotes", $this->dados())
            ->assertRedirect("/admin/eventos/{$this->eventoDoA->id}/lotes");

        $lote = $this->eventoDoA->lots()->first();
        $this->assertSame('Lote 1', $lote->name);
        $this->assertNull($lote->max_subscriptions);
        $this->assertTrue($lote->vigente());
    }

    public function test_fim_antes_do_inicio_e_recusado(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/lotes", $this->dados([
                'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
                'ends_at' => now()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_lote_sem_fim_e_aceito(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/lotes", $this->dados(['ends_at' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->eventoDoA->lots()->first()->ends_at);
    }

    public function test_edita_e_lista(): void
    {
        $lote = EventLot::factory()->create(['event_id' => $this->eventoDoA->id]);

        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->eventoDoA->id}/lotes/{$lote->id}", $this->dados(['name' => 'Lote promocional', 'max_subscriptions' => 50]))
            ->assertRedirect();

        $this->assertSame('Lote promocional', $lote->fresh()->name);
        $this->assertSame(50, $lote->fresh()->max_subscriptions);

        $this->actingAs($this->adminA)
            ->get("/admin/eventos/{$this->eventoDoA->id}/lotes")
            ->assertOk()
            ->assertSee('Lote promocional')
            ->assertSee('Vigente');
    }

    public function test_apagar_lote_leva_os_precos_da_coluna(): void
    {
        $lote = EventLot::factory()->create(['event_id' => $this->eventoDoA->id]);
        $modalidade = EventModality::factory()->create(['event_id' => $this->eventoDoA->id]);
        $kit = EventKit::factory()->create(['event_id' => $this->eventoDoA->id]);
        EventPrice::create(['event_id' => $this->eventoDoA->id, 'modality_id' => $modalidade->id, 'kit_id' => $kit->id, 'lot_id' => $lote->id, 'price' => 50]);

        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$this->eventoDoA->id}/lotes/{$lote->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('event_lots', ['id' => $lote->id]);
        $this->assertDatabaseCount('event_prices', 0);
    }

    public function test_lote_com_inscricao_nao_pode_ser_apagado(): void
    {
        $lote = EventLot::factory()->create(['event_id' => $this->eventoDoA->id]);
        $modalidade = EventModality::factory()->create(['event_id' => $this->eventoDoA->id]);
        $kit = EventKit::factory()->create(['event_id' => $this->eventoDoA->id]);
        Subscription::factory()->create([
            'event_id' => $this->eventoDoA->id, 'user_id' => User::factory()->create()->id,
            'modality_id' => $modalidade->id, 'kit_id' => $kit->id, 'lot_id' => $lote->id, 'price' => 50,
        ]);

        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$this->eventoDoA->id}/lotes/{$lote->id}")
            ->assertSessionHasErrors('lote');

        $this->assertDatabaseHas('event_lots', ['id' => $lote->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento
    |--------------------------------------------------------------------------
    */

    public function test_nao_lista_nem_cria_lote_em_evento_de_outro_organizador(): void
    {
        $this->actingAs($this->adminA)->get("/admin/eventos/{$this->eventoDoB->id}/lotes")->assertNotFound();
        $this->actingAs($this->adminA)->post("/admin/eventos/{$this->eventoDoB->id}/lotes", $this->dados())->assertNotFound();

        $this->assertSame(0, $this->eventoDoB->lots()->count());
    }

    public function test_nao_edita_nem_apaga_lote_de_outro_organizador(): void
    {
        $loteDoB = EventLot::factory()->create(['event_id' => $this->eventoDoB->id, 'name' => 'Do B']);

        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->eventoDoB->id}/lotes/{$loteDoB->id}", $this->dados(['name' => 'Invadido']))
            ->assertNotFound();
        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$this->eventoDoB->id}/lotes/{$loteDoB->id}")
            ->assertNotFound();

        // Nem pelo caminho cruzado: evento meu, lote dele.
        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->eventoDoA->id}/lotes/{$loteDoB->id}", $this->dados(['name' => 'Invadido']))
            ->assertNotFound();

        $this->assertSame('Do B', $loteDoB->fresh()->name);
    }

    public function test_deslogado_e_atleta_nao_entram(): void
    {
        $this->get("/admin/eventos/{$this->eventoDoA->id}/lotes")->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get("/admin/eventos/{$this->eventoDoA->id}/lotes")->assertForbidden();
    }
}
