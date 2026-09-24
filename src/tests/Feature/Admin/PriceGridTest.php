<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventModality;
use App\Models\EventPrice;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A grade de preços: linhas = modalidade × kit vinculado, colunas = lotes.
 *
 * É onde o dinheiro é definido. Cada teste de recusa confere que nada foi
 * gravado fora do evento do organizador.
 */
class PriceGridTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;
    private Event $evento;
    private Event $eventoDoB;
    private EventModality $cinco;
    private EventModality $dez;
    private EventKit $basico;
    private EventKit $completo;
    private EventLot $lote1;
    private EventLot $lote2;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $organizadorA = Organizer::factory()->create();
        $organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $organizadorA->id]);
        $this->evento = Event::factory()->create(['organizer_id' => $organizadorA->id, 'event_date' => now()->addMonths(2)]);
        $this->eventoDoB = Event::factory()->create(['organizer_id' => $organizadorB->id, 'event_date' => now()->addMonths(2)]);

        $this->cinco = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '5km', 'distance_km' => 5]);
        $this->dez = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '10km', 'distance_km' => 10]);
        $this->basico = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Básico']);
        $this->completo = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Completo']);
        $this->lote1 = EventLot::factory()->create(['event_id' => $this->evento->id, 'name' => 'Lote 1', 'position' => 0]);
        $this->lote2 = EventLot::factory()->futuro()->create(['event_id' => $this->evento->id, 'name' => 'Lote 2', 'position' => 1]);

        // Básico vale nas duas; Completo só no 10km.
        $this->basico->modalities()->sync([$this->cinco->id, $this->dez->id]);
        $this->completo->modalities()->sync([$this->dez->id]);
    }

    private function grade(): string
    {
        return "/admin/eventos/{$this->evento->id}/precos";
    }

    public function test_a_tela_mostra_uma_linha_por_par_vinculado_e_uma_coluna_por_lote(): void
    {
        $html = $this->actingAs($this->adminA)->get($this->grade())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, "precos[{$this->cinco->id}][{$this->basico->id}][{$this->lote1->id}]"));
        $this->assertSame(1, substr_count($html, "precos[{$this->dez->id}][{$this->completo->id}][{$this->lote2->id}]"));
        // Completo não está vinculado ao 5km: essa célula não existe.
        $this->assertSame(0, substr_count($html, "precos[{$this->cinco->id}][{$this->completo->id}]"));
    }

    public function test_salva_a_grade_inteira_de_uma_vez(): void
    {
        $this->actingAs($this->adminA)
            ->put($this->grade(), ['precos' => [
                $this->cinco->id => [$this->basico->id => [$this->lote1->id => '60.00', $this->lote2->id => '75.00']],
                $this->dez->id => [
                    $this->basico->id => [$this->lote1->id => '70.00', $this->lote2->id => '85.00'],
                    $this->completo->id => [$this->lote1->id => '100.00', $this->lote2->id => '120.00'],
                ],
            ]])
            ->assertRedirect($this->grade())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('event_prices', 6);
        $this->assertSame('120.00', (string) EventPrice::de($this->dez, $this->completo, $this->lote2)->price);
        $this->assertSame($this->evento->id, EventPrice::de($this->dez, $this->completo, $this->lote2)->event_id);
    }

    public function test_celula_vazia_apaga_o_preco(): void
    {
        EventPrice::create(['event_id' => $this->evento->id, 'modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'lot_id' => $this->lote1->id, 'price' => 60]);

        $this->actingAs($this->adminA)
            ->put($this->grade(), ['precos' => [$this->cinco->id => [$this->basico->id => [$this->lote1->id => '']]]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('event_prices', 0);
    }

    public function test_salvar_de_novo_atualiza_em_vez_de_duplicar(): void
    {
        $dados = ['precos' => [$this->cinco->id => [$this->basico->id => [$this->lote1->id => '60.00']]]];
        $this->actingAs($this->adminA)->put($this->grade(), $dados);

        $dados['precos'][$this->cinco->id][$this->basico->id][$this->lote1->id] = '65.50';
        $this->actingAs($this->adminA)->put($this->grade(), $dados);

        $this->assertDatabaseCount('event_prices', 1);
        $this->assertSame('65.50', (string) EventPrice::de($this->cinco, $this->basico, $this->lote1)->price);
    }

    public function test_preco_zero_e_recusado(): void
    {
        $this->actingAs($this->adminA)
            ->put($this->grade(), ['precos' => [$this->cinco->id => [$this->basico->id => [$this->lote1->id => '0']]]])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('event_prices', 0);
    }

    public function test_kit_fora_da_modalidade_e_ignorado(): void
    {
        // Completo não vale no 5km: mesmo mandando na marra, não grava.
        $this->actingAs($this->adminA)
            ->put($this->grade(), ['precos' => [$this->cinco->id => [$this->completo->id => [$this->lote1->id => '99.00']]]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('event_prices', 0);
    }

    public function test_ids_de_outro_evento_sao_ignorados(): void
    {
        $modalidadeDoB = EventModality::factory()->create(['event_id' => $this->eventoDoB->id]);
        $kitDoB = EventKit::factory()->create(['event_id' => $this->eventoDoB->id]);
        $loteDoB = EventLot::factory()->create(['event_id' => $this->eventoDoB->id]);
        $kitDoB->modalities()->sync([$modalidadeDoB->id]);

        $this->actingAs($this->adminA)
            ->put($this->grade(), ['precos' => [
                $modalidadeDoB->id => [$kitDoB->id => [$loteDoB->id => '10.00']],
                // Lote alheio numa linha minha também não.
                $this->cinco->id => [$this->basico->id => [$loteDoB->id => '10.00']],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('event_prices', 0);
    }

    public function test_grade_de_evento_de_outro_organizador_da_404(): void
    {
        $this->actingAs($this->adminA)->get("/admin/eventos/{$this->eventoDoB->id}/precos")->assertNotFound();
        $this->actingAs($this->adminA)->put("/admin/eventos/{$this->eventoDoB->id}/precos", ['precos' => []])->assertNotFound();
    }

    public function test_sem_lote_a_tela_diz_o_que_falta(): void
    {
        $this->evento->lots()->delete();

        $this->actingAs($this->adminA)
            ->get($this->grade())
            ->assertOk()
            ->assertSee('Falta cadastrar')
            ->assertSee('Lotes');
    }
}
