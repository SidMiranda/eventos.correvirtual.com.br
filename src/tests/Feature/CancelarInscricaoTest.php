<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O cancelamento de inscrição pelo atleta.
 *
 * Até 2026-09-21 cancelar APAGAVA a linha, e o organizador não tinha como
 * saber que alguém desistiu: a inscrição simplesmente sumia. Agora ela fica,
 * marcada como cancelada e com a data — é o que alimenta o filtro
 * "canceladas" na tela de gestão.
 *
 * Ver docs/specs/gestao-de-inscricoes.md.
 */
class CancelarInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $atleta;
    private Event $evento;
    private EventModality $modalidade;
    private EventKit $kit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->atleta = User::factory()->create(['role' => 'athlete']);

        $this->evento = Event::factory()->create([
            'organizer_id' => $this->organizador->id,
            'event_date' => now()->addMonths(2),
            'registration_deadline' => now()->addMonth(),
            'active' => true,
        ]);
        $this->modalidade = EventModality::factory()->create(['event_id' => $this->evento->id]);
        $this->kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
    }

    private function inscricao(array $extra = []): Subscription
    {
        return Subscription::factory()->create(array_merge([
            'event_id' => $this->evento->id,
            'user_id' => $this->atleta->id,
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
            'price' => 100,
            'list_price' => 100,
            'discount_amount' => 0,
            'status' => Subscription::PENDENTE,
        ], $extra));
    }

    private function cancelar(Subscription $inscricao)
    {
        return $this->actingAs($this->atleta)
            ->post('/subscription/cancel', ['subscription_id' => $inscricao->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | O cancelamento
    |--------------------------------------------------------------------------
    */

    public function test_cancelar_marca_a_inscricao_em_vez_de_apagar(): void
    {
        $inscricao = $this->inscricao();

        $this->cancelar($inscricao)->assertRedirect();

        $inscricao->refresh();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(Subscription::CANCELADA, $inscricao->status);
        $this->assertNotNull($inscricao->cancelled_at);
        $this->assertTrue($inscricao->cancelada());
    }

    public function test_cancelar_apaga_a_cobranca_pendente(): void
    {
        // Ninguém paga um Pix de inscrição cancelada, e deixá-lo atrapalharia
        // a conciliação.
        $inscricao = $this->inscricao();
        Payment::factory()->create(['subscription_id' => $inscricao->id, 'status' => 'pending']);

        $this->cancelar($inscricao);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_inscricao_paga_nao_cancela(): void
    {
        $inscricao = $this->inscricao(['status' => Subscription::PAGA]);

        $this->cancelar($inscricao)->assertSessionHas('error');

        $this->assertSame(Subscription::PAGA, $inscricao->fresh()->status);
        $this->assertNull($inscricao->fresh()->cancelled_at);
    }

    public function test_um_atleta_nao_cancela_a_inscricao_de_outro(): void
    {
        $inscricao = $this->inscricao();
        $intruso = User::factory()->create(['role' => 'athlete']);

        $this->actingAs($intruso)
            ->post('/subscription/cancel', ['subscription_id' => $inscricao->id])
            ->assertNotFound();

        $this->assertSame(Subscription::PENDENTE, $inscricao->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Inscrever-se de novo depois de cancelar
    |--------------------------------------------------------------------------
    */

    public function test_reinscrever_reaproveita_a_linha_cancelada(): void
    {
        // A unique (event_id, user_id) não deixa criar uma segunda linha, então
        // a cancelada volta a valer com os dados da nova tentativa.
        $inscricao = $this->inscricao();
        $this->cancelar($inscricao);

        $outroKit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 150]);

        $this->actingAs($this->atleta)
            ->post("/subscribe/event/{$this->evento->id}", [
                'modality_id' => $this->modalidade->id,
                'kit_id' => $outroKit->id,
            ])
            ->assertRedirect('/my-subscriptions')
            ->assertSessionHas('modal_type', 'success');

        $inscricao->refresh();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(Subscription::PENDENTE, $inscricao->status);
        $this->assertNull($inscricao->cancelled_at);
        $this->assertSame($outroKit->id, $inscricao->kit_id);
        $this->assertSame('150.00', $inscricao->price);
    }

    public function test_reinscrever_com_cupom_consome_um_uso_novo(): void
    {
        // O uso da tentativa anterior não volta (decisão de 2026-09-20), e a
        // nova tentativa gasta um uso próprio.
        $cupom = Coupon::factory()->create([
            'event_id' => $this->evento->id,
            'code' => 'CORRE10',
            'total_quantity' => 5,
        ]);

        $this->actingAs($this->atleta)->post("/subscribe/event/{$this->evento->id}", [
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
            'cupom' => 'CORRE10',
        ]);

        $this->assertSame(1, $cupom->fresh()->used_quantity);

        $this->cancelar(Subscription::firstOrFail());
        $this->assertSame(1, $cupom->fresh()->used_quantity, 'cancelar não devolve o uso');

        $this->actingAs($this->atleta)->post("/subscribe/event/{$this->evento->id}", [
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
            'cupom' => 'CORRE10',
        ]);

        $this->assertSame(2, $cupom->fresh()->used_quantity);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_inscricao_ativa_continua_barrando_nova_tentativa(): void
    {
        $this->inscricao();

        $this->actingAs($this->atleta)
            ->post("/subscribe/event/{$this->evento->id}", [
                'modality_id' => $this->modalidade->id,
                'kit_id' => $this->kit->id,
            ])
            ->assertSessionHas('modal_type', 'info');

        $this->assertDatabaseCount('subscriptions', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | A tela do atleta
    |--------------------------------------------------------------------------
    */

    public function test_minhas_inscricoes_mostra_cancelada_sem_botao_de_pagar(): void
    {
        // A grafia importa: o enum do banco usa dois L (`cancelled`) e a view
        // comparava com um só — o que passava despercebido porque a linha era
        // apagada e o caso nunca acontecia.
        $inscricao = $this->inscricao();
        $this->cancelar($inscricao);

        $this->actingAs($this->atleta)
            ->get('/my-subscriptions')
            ->assertOk()
            ->assertSee('Cancelada')
            ->assertDontSee('Cancelled')
            ->assertDontSee('Pagar Agora');
    }
}
