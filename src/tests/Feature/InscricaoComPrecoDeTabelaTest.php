<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmed;
use App\Models\AgeCategory;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventModality;
use App\Models\EventPrice;
use App\Models\KitOption;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * O fluxo do atleta lendo a grade (fatia 2 de 2026-09-23, ADR 0007).
 *
 * Kit só na modalidade em que está vinculado; tamanho obrigatório só quando
 * o kit tem; preço do lote vigente; categoria etária pela data de nascimento
 * do cadastro; cupom em cascata. Cada recusa confere que NADA foi criado.
 */
class InscricaoComPrecoDeTabelaTest extends TestCase
{
    use RefreshDatabase;

    private Event $evento;
    private User $atleta;
    private EventModality $cinco;
    private EventModality $dez;
    private EventKit $basico;      // tamanhos P, M, G; vale no 5K e no 10K
    private EventKit $semCamiseta; // sem tamanhos; vale só no 5K
    private EventLot $lote1;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->atleta = User::factory()->create(['role' => 'athlete', 'birth_date' => '1990-05-20']);

        $this->evento = Event::factory()->create([
            'organizer_id' => $organizador->id,
            'event_date' => now()->addMonths(2),
            'registration_deadline' => now()->addMonth(),
            'active' => true,
        ]);

        $this->cinco = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '5km', 'distance_km' => 5]);
        $this->dez = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '10km', 'distance_km' => 10]);

        $this->basico = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Básico', 'price' => 999]);
        $this->semCamiseta = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Sem camiseta', 'price' => 999]);

        $this->basico->modalities()->sync([$this->cinco->id, $this->dez->id]);
        $this->semCamiseta->modalities()->sync([$this->cinco->id]);

        foreach (['P', 'M', 'G'] as $i => $t) {
            $this->basico->options()->create(['attribute' => KitOption::TAMANHO, 'value' => $t, 'position' => $i]);
        }

        $this->lote1 = EventLot::factory()->create(['event_id' => $this->evento->id, 'name' => 'Lote 1', 'position' => 0]);

        $this->preco($this->cinco, $this->basico, $this->lote1, 60);
        $this->preco($this->dez, $this->basico, $this->lote1, 70);
        $this->preco($this->cinco, $this->semCamiseta, $this->lote1, 40);
    }

    private function preco(EventModality $m, EventKit $k, EventLot $l, float $valor): EventPrice
    {
        return EventPrice::create(['event_id' => $this->evento->id, 'modality_id' => $m->id, 'kit_id' => $k->id, 'lot_id' => $l->id, 'price' => $valor]);
    }

    private function inscrever(array $dados, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->atleta)->post("/subscribe/event/{$this->evento->id}", $dados);
    }

    private function cotar(array $dados, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->atleta)->postJson("/subscribe/event/{$this->evento->id}/cotacao", $dados);
    }

    private function inscricao(): ?Subscription
    {
        return Subscription::where('user_id', $this->atleta->id)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | O preço vem da grade, não do kit
    |--------------------------------------------------------------------------
    */

    public function test_o_preco_e_o_da_grade_no_lote_vigente(): void
    {
        // O kit diz 999 de propósito: não pode ser lido.
        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'M'])
            ->assertRedirect('/my-subscriptions');

        $i = $this->inscricao();
        $this->assertSame('60.00', (string) $i->list_price);
        $this->assertSame('60.00', (string) $i->price);
        $this->assertSame($this->lote1->id, $i->lot_id);
        $this->assertSame('M', $i->shirt_size);
        $this->assertSame('0.00', (string) $i->age_discount_amount);
    }

    public function test_a_mesma_combinacao_custa_diferente_em_outra_modalidade(): void
    {
        $this->inscrever(['modality_id' => $this->dez->id, 'kit_id' => $this->basico->id, 'camiseta' => 'G']);

        $this->assertSame('70.00', (string) $this->inscricao()->price);
    }

    public function test_combinacao_sem_preco_no_lote_e_recusada(): void
    {
        // Vincula o Sem camiseta ao 10K mas não põe preço: célula vazia.
        $this->semCamiseta->modalities()->attach($this->dez->id);

        $this->inscrever(['modality_id' => $this->dez->id, 'kit_id' => $this->semCamiseta->id])
            ->assertSessionHasErrors('kit_id');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Kit só na modalidade em que está vinculado
    |--------------------------------------------------------------------------
    */

    public function test_kit_fora_da_modalidade_e_recusado(): void
    {
        $this->inscrever(['modality_id' => $this->dez->id, 'kit_id' => $this->semCamiseta->id])
            ->assertSessionHasErrors('kit_id');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Tamanho: obrigatório só quando o kit tem
    |--------------------------------------------------------------------------
    */

    public function test_kit_com_tamanhos_exige_tamanho(): void
    {
        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id])
            ->assertSessionHasErrors('camiseta');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_tamanho_que_o_kit_nao_oferece_e_recusado(): void
    {
        // BLM está na tabela geral, mas não neste kit.
        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'BLM'])
            ->assertSessionHasErrors('camiseta');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_kit_sem_tamanhos_nao_pede_e_ignora_o_que_vier(): void
    {
        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->semCamiseta->id, 'camiseta' => 'M'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->inscricao()->shirt_size);
        $this->assertSame('40.00', (string) $this->inscricao()->price);
    }

    /*
    |--------------------------------------------------------------------------
    | Lote
    |--------------------------------------------------------------------------
    */

    public function test_sem_lote_vigente_a_inscricao_esta_fechada(): void
    {
        $this->lote1->update(['starts_at' => now()->addDay()]);

        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'M'])
            ->assertRedirect("/event/{$this->evento->id}")
            ->assertSessionHasErrors('inscricao');

        $this->assertDatabaseCount('subscriptions', 0);

        $this->actingAs($this->atleta)->get("/subscribe/event/{$this->evento->id}")->assertRedirect("/event/{$this->evento->id}");
    }

    public function test_lote_esgotado_vira_para_o_proximo_e_o_preco_acompanha(): void
    {
        $this->lote1->update(['max_subscriptions' => 1]);
        $lote2 = EventLot::factory()->create(['event_id' => $this->evento->id, 'name' => 'Lote 2', 'position' => 1]);
        $this->preco($this->cinco, $this->basico, $lote2, 75);

        // Primeiro inscrito esgota o Lote 1.
        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'M'], User::factory()->create());

        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'M']);

        $i = $this->inscricao();
        $this->assertSame($lote2->id, $i->lot_id);
        $this->assertSame('75.00', (string) $i->price);
    }

    /*
    |--------------------------------------------------------------------------
    | Categoria etária e cascata com cupom
    |--------------------------------------------------------------------------
    */

    private function idoso(): User
    {
        AgeCategory::factory()->create(['event_id' => $this->evento->id, 'name' => 'Idoso', 'min_age' => 60, 'discount_value' => 50]);

        return User::factory()->create(['role' => 'athlete', 'birth_date' => '1960-03-10']);
    }

    public function test_categoria_etaria_abate_pelo_cadastro_sem_perguntar(): void
    {
        $idoso = $this->idoso();

        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'M'], $idoso)
            ->assertRedirect('/my-subscriptions');

        $i = Subscription::where('user_id', $idoso->id)->first();
        $this->assertSame('60.00', (string) $i->list_price);
        $this->assertSame('30.00', (string) $i->age_discount_amount);
        $this->assertSame('0.00', (string) $i->discount_amount);
        $this->assertSame('30.00', (string) $i->price);
        $this->assertSame('Idoso', $i->ageCategory->name);
    }

    public function test_cupom_em_cascata_sobre_o_que_sobrou_da_idade(): void
    {
        $idoso = $this->idoso();
        Coupon::factory()->create(['event_id' => $this->evento->id, 'code' => 'VINTE', 'discount_type' => Coupon::TIPO_PERCENTUAL, 'discount_value' => 20, 'total_quantity' => 5]);

        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'camiseta' => 'M', 'cupom' => 'VINTE'], $idoso);

        $i = Subscription::where('user_id', $idoso->id)->first();
        // 60 → idoso −30 = 30 → cupom 20% de 30 = 6 → 24.
        $this->assertSame('30.00', (string) $i->age_discount_amount);
        $this->assertSame('6.00', (string) $i->discount_amount);
        $this->assertSame('24.00', (string) $i->price);
    }

    public function test_desconto_de_idade_de_100_por_cento_confirma_sem_pix(): void
    {
        AgeCategory::factory()->create(['event_id' => $this->evento->id, 'name' => 'Cortesia kids', 'min_age' => null, 'max_age' => 10, 'discount_value' => 100]);
        $crianca = User::factory()->create(['role' => 'athlete', 'birth_date' => now()->subYears(8)->format('Y-m-d')]);

        $this->inscrever(['modality_id' => $this->cinco->id, 'kit_id' => $this->semCamiseta->id], $crianca)
            ->assertRedirect('/my-subscriptions');

        $i = Subscription::where('user_id', $crianca->id)->first();
        $this->assertSame(Subscription::PAGA, $i->status);
        $this->assertSame('0.00', (string) $i->price);
        $this->assertNotNull($i->confirmed_at);
        Mail::assertSent(SubscriptionConfirmed::class);
    }

    /*
    |--------------------------------------------------------------------------
    | A cotação (o resumo ao vivo do formulário)
    |--------------------------------------------------------------------------
    */

    public function test_a_cotacao_devolve_a_conta_inteira(): void
    {
        $idoso = $this->idoso();
        Coupon::factory()->create(['event_id' => $this->evento->id, 'code' => 'VINTE', 'discount_type' => Coupon::TIPO_PERCENTUAL, 'discount_value' => 20, 'total_quantity' => 5]);

        $this->cotar(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'cupom' => 'VINTE'], $idoso)
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'lote' => 'Lote 1',
                'bruto' => 'R$ 60,00',
                'categoria' => 'Idoso',
                'desconto_idade' => 'R$ 30,00',
                'desconto_cupom' => 'R$ 6,00',
                'liquido' => 'R$ 24,00',
                'gratuita' => false,
            ]);

        // Nada foi criado nem consumido.
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame(0, Coupon::first()->used_quantity);
    }

    public function test_a_cotacao_sem_cupom_e_sem_categoria(): void
    {
        $this->cotar(['modality_id' => $this->dez->id, 'kit_id' => $this->basico->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'bruto' => 'R$ 70,00', 'categoria' => null, 'liquido' => 'R$ 70,00']);
    }

    public function test_a_cotacao_recusa_kit_fora_da_modalidade(): void
    {
        $this->cotar(['modality_id' => $this->dez->id, 'kit_id' => $this->semCamiseta->id])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_a_cotacao_recusa_cupom_invalido(): void
    {
        $this->cotar(['modality_id' => $this->cinco->id, 'kit_id' => $this->basico->id, 'cupom' => 'NAOEXISTE'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | O formulário e a página do evento
    |--------------------------------------------------------------------------
    */

    public function test_o_formulario_leva_a_grade_do_lote_vigente(): void
    {
        $html = $this->actingAs($this->atleta)->get("/subscribe/event/{$this->evento->id}")->assertOk()->getContent();

        // Os dados que o JavaScript usa para montar kit por modalidade.
        $this->assertStringContainsString('id="dadosDaInscricao"', $html);
        $this->assertStringContainsString('"lote"', $html);
        $this->assertStringContainsString('Lote 1', $html);
        // O kit 999 não aparece em lugar nenhum: o preço é o da grade.
        $this->assertStringNotContainsString('R$ 999,00', $html);
    }

    public function test_a_pagina_do_evento_mostra_o_preco_do_lote_e_o_botao(): void
    {
        $this->get("/event/{$this->evento->id}")
            ->assertOk()
            ->assertSee('Inscreva-se')
            ->assertSee('Lote 1')
            ->assertSee('R$ 60,00')
            ->assertDontSee('R$ 999,00');
    }

    public function test_a_pagina_do_evento_sem_lote_vigente_diz_quando_abre(): void
    {
        $this->lote1->update(['starts_at' => now()->addDays(3)]);

        $this->get("/event/{$this->evento->id}")
            ->assertOk()
            ->assertDontSee('Inscreva-se')
            ->assertSee('ainda não abertas')
            ->assertSee(now()->addDays(3)->format('d/m/Y'));
    }
}
