<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\MercadoPagoConta;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Cobranca\ConfiguracaoDaPlataforma;
use App\Services\Cobranca\ContaDeCobranca;
use App\Services\Cobranca\TaxaDaPlataforma;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * O Pix com split (ADR 0008): pela conta conectada do organizador, com a taxa
 * da plataforma em `application_fee` só em evento de 2027 em diante. Sem conta
 * conectada, o modelo de sempre, intacto.
 */
class PixComSplitTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
    }

    private function inscricao(string $dataDoEvento, float $valor = 100.00): Subscription
    {
        $evento = Event::factory()->create([
            'organizer_id' => $this->organizador->id,
            'event_date' => $dataDoEvento,
            'registration_deadline' => now()->addWeek(),
            'active' => true,
        ]);

        return Subscription::factory()->create([
            'event_id' => $evento->id,
            'user_id' => User::factory()->create()->id,
            'modality_id' => EventModality::factory()->create(['event_id' => $evento->id])->id,
            'kit_id' => EventKit::factory()->create(['event_id' => $evento->id])->id,
            'status' => 'pending',
            'price' => $valor,
        ]);
    }

    private function conectar(array $extra = []): MercadoPagoConta
    {
        return MercadoPagoConta::create(array_merge([
            'organizer_id' => $this->organizador->id,
            'mp_user_id' => '998877',
            'access_token' => 'APP_USR-token-do-organizador',
            'refresh_token' => 'TG-refresh',
            'expires_at' => now()->addDays(150),
            'connected_at' => now(),
        ], $extra));
    }

    private function pix(string $id = 'mp-1'): object
    {
        return (object) [
            'id' => $id,
            'point_of_interaction' => (object) ['transaction_data' => (object) [
                'qr_code' => '000201', 'qr_code_base64' => 'b64', 'ticket_url' => 'https://mp/t',
            ]],
            'date_of_expiration' => null,
        ];
    }

    private function pagar(Subscription $inscricao)
    {
        return $this->actingAs($inscricao->user)->post('/event-pay', ['subscription_id' => $inscricao->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | A regra da taxa
    |--------------------------------------------------------------------------
    */

    public function test_taxa_so_com_conta_conectada_e_evento_de_2027_em_diante(): void
    {
        $conta = ContaDeCobranca::daConta($this->conectar());

        $this->assertSame(0.70, TaxaDaPlataforma::para($this->inscricao('2027-01-01 07:00'), $conta));
        $this->assertNull(TaxaDaPlataforma::para($this->inscricao('2026-12-31 23:00'), $conta));
        $this->assertNull(TaxaDaPlataforma::para($this->inscricao('2027-03-01 07:00'), ContaDeCobranca::doEnv()));
    }

    public function test_a_taxa_vem_da_configuracao(): void
    {
        ConfiguracaoDaPlataforma::definir(ConfiguracaoDaPlataforma::TAXA_INSCRICAO, '1.25');

        $this->assertSame(1.25, TaxaDaPlataforma::para($this->inscricao('2027-05-01'), ContaDeCobranca::daConta($this->conectar())));
    }

    public function test_inscricao_que_nao_comporta_a_taxa_sai_sem_taxa(): void
    {
        $conta = ContaDeCobranca::daConta($this->conectar());

        $this->assertNull(TaxaDaPlataforma::para($this->inscricao('2027-05-01', 0.70), $conta));
        $this->assertSame(0.70, TaxaDaPlataforma::para($this->inscricao('2027-05-01', 0.71), $conta));
    }

    /*
    |--------------------------------------------------------------------------
    | O Pix
    |--------------------------------------------------------------------------
    */

    public function test_conta_conectada_e_evento_2027_cobra_pelo_token_do_organizador_com_taxa(): void
    {
        $conta = $this->conectar();
        $inscricao = $this->inscricao('2027-02-10 07:00', 100.00);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('createPixPayment');
        $mock->shouldReceive('createPixPaymentForAccount')->once()
            ->with(100.00, $inscricao->user->email, (string) $inscricao->id, 'APP_USR-token-do-organizador', 0.70, \Mockery::type('string'))
            ->andReturn($this->pix('mp-split-1'));

        $this->pagar($inscricao)->assertOk();

        $pagamento = Payment::where('transaction_id', 'mp-split-1')->firstOrFail();
        $this->assertSame($conta->id, $pagamento->mercado_pago_conta_id);
        $this->assertSame('0.70', $pagamento->application_fee);
    }

    public function test_conta_conectada_e_evento_2026_cobra_pela_conta_dele_sem_taxa(): void
    {
        $conta = $this->conectar();
        $inscricao = $this->inscricao('2026-12-20 07:00');

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPaymentForAccount')->once()
            ->with(\Mockery::any(), \Mockery::any(), \Mockery::any(), 'APP_USR-token-do-organizador', null, \Mockery::any())
            ->andReturn($this->pix('mp-2026'));

        $this->pagar($inscricao)->assertOk();

        $pagamento = Payment::where('transaction_id', 'mp-2026')->firstOrFail();
        $this->assertSame($conta->id, $pagamento->mercado_pago_conta_id);
        $this->assertNull($pagamento->application_fee);
    }

    public function test_sem_conta_conectada_e_o_modelo_de_sempre_mesmo_em_2027(): void
    {
        $inscricao = $this->inscricao('2027-02-10 07:00', 100.00);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('createPixPaymentForAccount');
        $mock->shouldReceive('createPixPayment')->once()
            ->with(100.00, $inscricao->user->email, (string) $inscricao->id)
            ->andReturn($this->pix('mp-antigo'));

        $this->pagar($inscricao)->assertOk();

        $pagamento = Payment::where('transaction_id', 'mp-antigo')->firstOrFail();
        $this->assertNull($pagamento->mercado_pago_conta_id);
        $this->assertNull($pagamento->application_fee);
    }

    public function test_conta_de_outro_organizador_nao_e_usada(): void
    {
        MercadoPagoConta::create([
            'organizer_id' => Organizer::factory()->create()->id,
            'mp_user_id' => '1',
            'access_token' => 'token-de-outro',
        ]);
        $inscricao = $this->inscricao('2027-02-10 07:00');

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('createPixPaymentForAccount');
        $mock->shouldReceive('createPixPayment')->once()->andReturn($this->pix('mp-x'));

        $this->pagar($inscricao)->assertOk();
    }

    public function test_falha_com_conta_conectada_alerta(): void
    {
        config(['services.alertas.cobranca_email' => null]);
        $this->conectar();
        $inscricao = $this->inscricao('2027-02-10 07:00');

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPaymentForAccount')->once()->andReturn(null);

        Log::spy();

        $this->pagar($inscricao)->assertRedirect('/my-subscriptions');

        Log::shouldHaveReceived('critical')->withArgs(fn ($msg) => str_contains($msg, 'Pix não saiu pela conta conectada'))->once();
        $this->assertSame(0, Payment::count());
    }

    public function test_token_perto_de_vencer_e_renovado_antes_de_cobrar(): void
    {
        config([
            'services.mercadopago.app.client_id' => 'id',
            'services.mercadopago.app.client_secret' => 'segredo',
            'services.mercadopago.app.redirect_uri' => 'https://x/retorno',
        ]);
        Http::fake(['api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'APP_USR-token-novo',
            'refresh_token' => 'TG-refresh-novo',
            'expires_in' => 15552000,
            'user_id' => 998877,
        ])]);

        $conta = $this->conectar(['expires_at' => now()->addDays(3)]);
        $inscricao = $this->inscricao('2027-02-10 07:00');

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPaymentForAccount')->once()
            ->with(\Mockery::any(), \Mockery::any(), \Mockery::any(), 'APP_USR-token-novo', 0.70, \Mockery::any())
            ->andReturn($this->pix('mp-renovado'));

        $this->pagar($inscricao)->assertOk();

        $conta->refresh();
        $this->assertSame('TG-refresh-novo', $conta->refresh_token);
        $this->assertTrue($conta->expires_at->gt(now()->addDays(170)));
    }

    /*
    |--------------------------------------------------------------------------
    | Cupom de 100%: não passa pelo Mercado Pago
    |--------------------------------------------------------------------------
    */

    public function test_inscricao_gratuita_nao_chama_o_mercado_pago_nem_com_conta_conectada(): void
    {
        $this->conectar();
        $inscricao = $this->inscricao('2027-02-10 07:00', 0.00);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('createPixPayment');
        $mock->shouldNotReceive('createPixPaymentForAccount');

        $this->pagar($inscricao)->assertRedirect('/my-subscriptions');

        $this->assertSame(0, Payment::count());
    }
}
