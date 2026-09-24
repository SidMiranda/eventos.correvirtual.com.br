<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O kit ganha vínculo com modalidades e tamanhos de camiseta (ADR 0007).
 */
class KitOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;
    private Event $evento;
    private EventModality $cinco;
    private EventModality $dez;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $organizadorA = Organizer::factory()->create();

        $this->adminA = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $organizadorA->id]);
        $this->evento = Event::factory()->create(['organizer_id' => $organizadorA->id, 'event_date' => now()->addMonths(2)]);
        $this->cinco = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '5km']);
        $this->dez = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '10km']);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Kit Camiseta',
            'description' => 'Camiseta + medalha',
            'price' => 89.90,
            'stock' => '',
            'active' => 1,
            'modalidades' => [$this->cinco->id],
            'tamanhos' => ['P', 'M', 'G'],
        ], $extra);
    }

    public function test_cria_kit_com_modalidades_e_tamanhos(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->evento->id}/kits", $this->dados(['modalidades' => [$this->cinco->id, $this->dez->id]]))
            ->assertRedirect();

        $kit = $this->evento->kits()->first();
        $this->assertEqualsCanonicalizing([$this->cinco->id, $this->dez->id], $kit->modalities()->pluck('event_modalities.id')->all());
        $this->assertSame(['P', 'M', 'G'], $kit->tamanhos());
        $this->assertTrue($kit->temTamanhos());
    }

    public function test_kit_sem_modalidade_e_recusado_quando_o_evento_tem_modalidades(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->evento->id}/kits", $this->dados(['modalidades' => []]))
            ->assertSessionHasErrors('modalidades');

        $this->assertSame(0, $this->evento->kits()->count());
    }

    public function test_kit_sem_modalidade_e_aceito_quando_o_evento_ainda_nao_tem_nenhuma(): void
    {
        $vazio = Event::factory()->create(['organizer_id' => $this->adminA->organizer_id, 'event_date' => now()->addMonths(2)]);

        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$vazio->id}/kits", $this->dados(['modalidades' => []]))
            ->assertSessionHasNoErrors();
    }

    public function test_modalidade_de_outro_evento_e_recusada(): void
    {
        $outro = Event::factory()->create(['organizer_id' => $this->adminA->organizer_id]);
        $alheia = EventModality::factory()->create(['event_id' => $outro->id]);

        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->evento->id}/kits", $this->dados(['modalidades' => [$alheia->id]]))
            ->assertSessionHasErrors('modalidades.0');
    }

    public function test_kit_sem_tamanho_nao_pede_tamanho(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->evento->id}/kits", $this->dados(['name' => 'Sem camiseta', 'tamanhos' => []]))
            ->assertSessionHasNoErrors();

        $kit = $this->evento->kits()->first();
        $this->assertSame([], $kit->tamanhos());
        $this->assertFalse($kit->temTamanhos());
    }

    public function test_tamanho_fora_da_tabela_e_recusado(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->evento->id}/kits", $this->dados(['tamanhos' => ['XPTO']]))
            ->assertSessionHasErrors('tamanhos.0');
    }

    public function test_editar_troca_os_tamanhos_mantendo_a_ordem_da_tabela(): void
    {
        $kit = EventKit::factory()->create(['event_id' => $this->evento->id]);
        $kit->modalities()->sync([$this->cinco->id]);

        // Manda fora de ordem e com repetição: grava na ordem da tabela, sem repetir.
        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->evento->id}/kits/{$kit->id}", $this->dados(['tamanhos' => ['GG', 'P', 'BLM', 'P']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['P', 'GG', 'BLM'], $kit->fresh()->tamanhos());

        // Tirou o GG: some. Os outros continuam.
        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->evento->id}/kits/{$kit->id}", $this->dados(['tamanhos' => ['P', 'BLM']]));

        $this->assertSame(['P', 'BLM'], $kit->fresh()->tamanhos());
    }

    public function test_o_formulario_do_kit_traz_as_caixas(): void
    {
        $this->actingAs($this->adminA)
            ->get("/admin/eventos/{$this->evento->id}/kits/create")
            ->assertOk()
            ->assertSee('name="modalidades[]"', false)
            ->assertSee('5km')
            ->assertSee('name="tamanhos[]"', false)
            ->assertSee('59 x 76 cm');
    }
}
