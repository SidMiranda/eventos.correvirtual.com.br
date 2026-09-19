<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmed;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * O cupom na inscrição do atleta: o valor cai, o contador sobe, o Pix sai com
 * o valor certo e o cupom de 100% confirma sem pagamento.
 *
 * É o fluxo de dinheiro. Cada caso de recusa confere também que NADA foi
 * criado e que o contador do cupom não se mexeu.
 *
 * Ver docs/specs/cupons-de-desconto.md.
 */
class InscricaoComCupomTest extends TestCase
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
        $this->kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 89.90]);
    }

    private function cupom(array $extra = []): Coupon
    {
        return Coupon::factory()->create(array_merge([
            'event_id' => $this->evento->id,
            'code' => 'CORRE10',
            'discount_type' => Coupon::TIPO_PERCENTUAL,
            'discount_value' => 10,
            'total_quantity' => 5,
        ], $extra));
    }

    private function inscrever(array $extra = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->atleta)->post("/subscribe/event/{$this->evento->id}", array_merge([
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
        ], $extra));
    }

    private function previa(array $dados, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->atleta)
            ->postJson("/subscribe/event/{$this->evento->id}/cupom", $dados);
    }

    /*
    |--------------------------------------------------------------------------
    | Inscrição
    |--------------------------------------------------------------------------
    */

    public function test_inscricao_com_cupom_grava_o_retrato_financeiro_e_consome_um_uso(): void
    {
        $cupom = $this->cupom();

        $this->inscrever(['cupom' => 'CORRE10'])
            ->assertRedirect('/my-subscriptions')
            ->assertSessionHas('modal_type', 'success');

        $inscricao = Subscription::firstOrFail();

        $this->assertSame('89.90', $inscricao->list_price);
        $this->assertSame('8.99', $inscricao->discount_amount);
        $this->assertSame('80.91', $inscricao->price);
        $this->assertSame($cupom->id, $inscricao->coupon_id);
        $this->assertSame('pending', $inscricao->status);

        $this->assertSame(1, $cupom->fresh()->used_quantity);
    }

    public function test_codigo_e_normalizado_antes_de_procurar(): void
    {
        $this->cupom();

        $this->inscrever(['cupom' => '  corre10 '])->assertRedirect('/my-subscriptions');

        $this->assertSame('80.91', Subscription::firstOrFail()->price);
    }

    public function test_sem_cupom_o_caminho_de_hoje_continua_igual(): void
    {
        $this->inscrever()->assertRedirect('/my-subscriptions');

        $inscricao = Subscription::firstOrFail();

        $this->assertSame('89.90', $inscricao->price);
        $this->assertSame('89.90', $inscricao->list_price);
        $this->assertSame('0.00', $inscricao->discount_amount);
        $this->assertNull($inscricao->coupon_id);
    }

    public function test_cupom_de_outro_evento_e_recusado(): void
    {
        $outroEvento = Event::factory()->create(['organizer_id' => $this->organizador->id]);
        $alheio = Coupon::factory()->create(['event_id' => $outroEvento->id, 'code' => 'CORRE10']);

        $this->inscrever(['cupom' => 'CORRE10'])->assertSessionHasErrors('cupom');

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame(0, $alheio->fresh()->used_quantity);
    }

    public function test_cupom_inativo_vencido_ou_esgotado_e_recusado(): void
    {
        $inativo = $this->cupom(['code' => 'INATIV', 'active' => false]);
        $vencido = $this->cupom(['code' => 'VENCEU', 'expires_at' => now()->subDay()->toDateString()]);
        $esgotado = $this->cupom(['code' => 'ACABOU', 'total_quantity' => 2, 'used_quantity' => 2]);

        foreach ([$inativo, $vencido, $esgotado] as $cupom) {
            $this->inscrever(['cupom' => $cupom->code])->assertSessionHasErrors('cupom');
        }

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame(0, $inativo->fresh()->used_quantity);
        $this->assertSame(0, $vencido->fresh()->used_quantity);
        $this->assertSame(2, $esgotado->fresh()->used_quantity);
    }

    public function test_cupom_inexistente_e_recusado_sem_criar_nada(): void
    {
        $this->inscrever(['cupom' => 'NAOTEM'])->assertSessionHasErrors('cupom');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_quem_ja_esta_inscrito_nao_gasta_uso(): void
    {
        $cupom = $this->cupom();
        $this->inscrever();

        $this->inscrever(['cupom' => 'CORRE10'])
            ->assertRedirect('/my-subscriptions')
            ->assertSessionHas('modal_type', 'info');

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(0, $cupom->fresh()->used_quantity);
    }

    public function test_ultimo_uso_disputado_so_um_atleta_leva(): void
    {
        $cupom = $this->cupom(['total_quantity' => 1]);
        $outroAtleta = User::factory()->create(['role' => 'athlete']);

        $this->inscrever(['cupom' => 'CORRE10'])->assertRedirect('/my-subscriptions');
        $this->inscrever(['cupom' => 'CORRE10'], $outroAtleta)->assertSessionHasErrors('cupom');

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(1, $cupom->fresh()->used_quantity);
    }

    public function test_cancelar_nao_devolve_o_uso(): void
    {
        // Decisão do dono (2026-09-20): uso consumido é uso gasto, mesmo sem
        // pagamento.
        $cupom = $this->cupom();
        $this->inscrever(['cupom' => 'CORRE10']);

        $this->actingAs($this->atleta)->post('/subscription/cancel', [
            'subscription_id' => Subscription::firstOrFail()->id,
        ]);

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame(1, $cupom->fresh()->used_quantity);
    }

    /*
    |--------------------------------------------------------------------------
    | Cupom de 100%
    |--------------------------------------------------------------------------
    */

    public function test_cupom_de_cem_por_cento_confirma_na_hora_sem_pix(): void
    {
        Mail::fake();
        $cupom = $this->cupom(['discount_value' => 100]);

        $this->inscrever(['cupom' => 'CORRE10'])
            ->assertRedirect('/my-subscriptions')
            ->assertSessionHas('modal_type', 'success')
            ->assertSessionHas('inscricao_gratuita', true);

        $inscricao = Subscription::firstOrFail();

        $this->assertSame('paid', $inscricao->status);
        $this->assertNotNull($inscricao->confirmed_at);
        $this->assertSame('0.00', $inscricao->price);
        $this->assertSame('89.90', $inscricao->discount_amount);
        $this->assertSame(1, $cupom->fresh()->used_quantity);
        $this->assertDatabaseCount('payments', 0);

        Mail::assertSent(SubscriptionConfirmed::class, fn ($mail) => $mail->hasTo($this->atleta->email));
    }

    public function test_cupom_em_reais_maior_que_o_kit_zera_sem_ficar_negativo(): void
    {
        Mail::fake();
        $this->cupom(['discount_type' => Coupon::TIPO_VALOR, 'discount_value' => 200]);

        $this->inscrever(['cupom' => 'CORRE10'])->assertSessionHas('inscricao_gratuita', true);

        $inscricao = Subscription::firstOrFail();

        $this->assertSame('0.00', $inscricao->price);
        $this->assertSame('89.90', $inscricao->discount_amount);
        $this->assertSame('paid', $inscricao->status);
    }

    public function test_email_da_inscricao_gratuita_diz_que_nao_houve_cobranca(): void
    {
        // Mail::fake() não renderiza o template: renderiza aqui para garantir
        // que o Blade com cupom e valor não quebra.
        $this->cupom(['discount_value' => 100]);
        $this->inscrever(['cupom' => 'CORRE10']);

        $inscricao = Subscription::with(['event', 'modality', 'kit', 'user', 'coupon'])->firstOrFail();
        $html = (new SubscriptionConfirmed($inscricao))->render();

        $this->assertStringContainsString('não houve cobrança', $html);
        $this->assertStringContainsString('CORRE10', $html);
        $this->assertStringNotContainsString('Seu pagamento foi aprovado', $html);
    }

    public function test_email_da_inscricao_paga_mostra_valor_e_cupom(): void
    {
        $this->cupom();
        $this->inscrever(['cupom' => 'CORRE10']);

        $inscricao = Subscription::with(['event', 'modality', 'kit', 'user', 'coupon'])->firstOrFail();
        $html = (new SubscriptionConfirmed($inscricao))->render();

        $this->assertStringContainsString('R$ 80,91', $html);
        $this->assertStringContainsString('CORRE10', $html);
        $this->assertStringContainsString('R$ 8,99', $html);
    }

    /*
    |--------------------------------------------------------------------------
    | O desconto chega no Pix
    |--------------------------------------------------------------------------
    */

    public function test_o_pix_e_gerado_com_o_valor_ja_descontado(): void
    {
        $this->cupom();
        $this->inscrever(['cupom' => 'CORRE10']);
        $inscricao = Subscription::firstOrFail();

        $pix = (object) [
            'id' => 'mp-cupom',
            'point_of_interaction' => (object) [
                'transaction_data' => (object) [
                    'qr_code' => '00020126...',
                    'qr_code_base64' => 'base64stuff',
                    'ticket_url' => 'https://mercadopago.com/ticket/cupom',
                ],
            ],
            'date_of_expiration' => null,
        ];

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')
            ->once()
            ->with(80.91, $this->atleta->email, (string) $inscricao->id)
            ->andReturn($pix);

        $this->actingAs($this->atleta)
            ->post('/event-pay', ['subscription_id' => $inscricao->id])
            ->assertOk()
            ->assertSee('R$ 80,91')
            ->assertSee('CORRE10');
    }

    /*
    |--------------------------------------------------------------------------
    | Telas
    |--------------------------------------------------------------------------
    */

    public function test_formulario_mostra_o_preco_do_kit_e_o_campo_de_cupom(): void
    {
        $this->actingAs($this->atleta)
            ->get("/subscribe/event/{$this->evento->id}")
            ->assertOk()
            ->assertSee('R$ 89,90')
            ->assertSee('name="cupom"', false);
    }

    public function test_minhas_inscricoes_mostra_o_valor_com_o_desconto(): void
    {
        $this->cupom();
        $this->inscrever(['cupom' => 'CORRE10']);

        $this->actingAs($this->atleta)
            ->get('/my-subscriptions')
            ->assertOk()
            ->assertSee('R$ 80,91')
            ->assertSee('R$ 8,99')
            ->assertSee('CORRE10');
    }

    public function test_minhas_inscricoes_mostra_gratuita_sem_botao_de_pagar(): void
    {
        Mail::fake();
        $this->cupom(['discount_value' => 100]);
        $this->inscrever(['cupom' => 'CORRE10']);

        $this->actingAs($this->atleta)
            ->get('/my-subscriptions')
            ->assertOk()
            ->assertSee('Gratuita')
            ->assertDontSee('Pagar Agora');
    }

    /*
    |--------------------------------------------------------------------------
    | Prévia
    |--------------------------------------------------------------------------
    */

    public function test_previa_devolve_os_valores_sem_consumir_uso(): void
    {
        $cupom = $this->cupom();

        $this->previa(['kit_id' => $this->kit->id, 'cupom' => 'corre10'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'codigo' => 'CORRE10',
                'bruto' => 'R$ 89,90',
                'desconto' => 'R$ 8,99',
                'liquido' => 'R$ 80,91',
                'gratuita' => false,
            ]);

        $this->assertSame(0, $cupom->fresh()->used_quantity);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_previa_de_cupom_de_cem_por_cento_avisa_que_e_gratuita(): void
    {
        $this->cupom(['discount_value' => 100]);

        $this->previa(['kit_id' => $this->kit->id, 'cupom' => 'CORRE10'])
            ->assertOk()
            ->assertJson(['ok' => true, 'gratuita' => true, 'liquido' => 'R$ 0,00']);
    }

    public function test_previa_recusa_com_a_mensagem_do_motivo(): void
    {
        $this->cupom(['code' => 'VENCEU', 'expires_at' => now()->subDay()->toDateString()]);

        $this->previa(['kit_id' => $this->kit->id, 'cupom' => 'NAOTEM'])
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonPath('mensagem', fn ($m) => str_contains($m, 'não existe'));

        $this->previa(['kit_id' => $this->kit->id, 'cupom' => 'VENCEU'])
            ->assertStatus(422)
            ->assertJsonPath('mensagem', fn ($m) => str_contains($m, 'venceu'));
    }

    public function test_previa_exige_kit_deste_evento(): void
    {
        $this->cupom();
        $kitAlheio = EventKit::factory()->create();

        $this->previa(['kit_id' => $kitAlheio->id, 'cupom' => 'CORRE10'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->previa(['cupom' => 'CORRE10'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_previa_recusa_evento_fechado(): void
    {
        $this->cupom();
        $this->evento->update(['registration_deadline' => now()->subDay()]);

        $this->previa(['kit_id' => $this->kit->id, 'cupom' => 'CORRE10'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_previa_exige_login(): void
    {
        $this->cupom();

        $this->postJson("/subscribe/event/{$this->evento->id}/cupom", [
            'kit_id' => $this->kit->id,
            'cupom' => 'CORRE10',
        ])->assertStatus(401);
    }
}
