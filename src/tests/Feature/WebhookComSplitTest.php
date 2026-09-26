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
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * O webhook com as duas aplicações (ADR 0008): aceita a assinatura de qualquer
 * uma e consulta o pagamento com a conta que o criou.
 */
class WebhookComSplitTest extends TestCase
{
    use RefreshDatabase;

    private Subscription $inscricao;
    private MercadoPagoConta $conta;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'services.mercadopago.webhook_secret' => 'segredo-antigo',
            'services.mercadopago.app.webhook_secret' => 'segredo-da-plataforma',
        ]);

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $evento = Event::factory()->create(['organizer_id' => $organizador->id, 'event_date' => '2027-03-01 07:00']);
        $this->inscricao = Subscription::factory()->create([
            'event_id' => $evento->id,
            'user_id' => User::factory()->create()->id,
            'modality_id' => EventModality::factory()->create(['event_id' => $evento->id])->id,
            'kit_id' => EventKit::factory()->create(['event_id' => $evento->id])->id,
            'status' => 'pending',
            'price' => 100,
        ]);

        $this->conta = MercadoPagoConta::create([
            'organizer_id' => $organizador->id,
            'mp_user_id' => '998877',
            'access_token' => 'APP_USR-token-do-organizador',
        ]);
    }

    private function cabecalhos(string $id, string $segredo): array
    {
        $ts = (string) time();
        $v1 = hash_hmac('sha256', "id:{$id};request-id:req-1;ts:{$ts};", $segredo);

        return ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => 'req-1'];
    }

    private function notificar(string $id, string $segredo)
    {
        return $this->withHeaders($this->cabecalhos($id, $segredo))
            ->postJson("/api/webhooks/mercadopago?data.id={$id}", ['data' => ['id' => $id]]);
    }

    public function test_pagamento_da_conta_conectada_e_consultado_com_o_token_dela(): void
    {
        Payment::create([
            'subscription_id' => $this->inscricao->id,
            'transaction_id' => 'mp-split-9',
            'mercado_pago_conta_id' => $this->conta->id,
            'application_fee' => 0.70,
            'status' => 'pending',
        ]);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('getPayment');
        $mock->shouldReceive('getPaymentForAccount')->once()
            ->with('mp-split-9', 'APP_USR-token-do-organizador')
            ->andReturn((object) ['status' => 'approved', 'external_reference' => (string) $this->inscricao->id]);

        $this->notificar('mp-split-9', 'segredo-da-plataforma')->assertOk();

        $this->assertSame('paid', $this->inscricao->fresh()->status);
        $this->assertSame('approved', Payment::where('transaction_id', 'mp-split-9')->value('status'));
    }

    public function test_pagamento_do_modelo_antigo_continua_pelo_env(): void
    {
        Payment::create(['subscription_id' => $this->inscricao->id, 'transaction_id' => 'mp-antigo-9', 'status' => 'pending']);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('getPaymentForAccount');
        $mock->shouldReceive('getPayment')->once()->with('mp-antigo-9')
            ->andReturn((object) ['status' => 'approved', 'external_reference' => (string) $this->inscricao->id]);

        $this->notificar('mp-antigo-9', 'segredo-antigo')->assertOk();

        $this->assertSame('paid', $this->inscricao->fresh()->status);
    }

    public function test_assinatura_que_nao_e_de_nenhuma_das_duas_e_recusada(): void
    {
        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldNotReceive('getPayment');
        $mock->shouldNotReceive('getPaymentForAccount');

        $this->notificar('mp-split-9', 'segredo-inventado')->assertStatus(401);

        $this->assertSame('pending', $this->inscricao->fresh()->status);
    }

    public function test_sem_segredo_nenhum_configurado_continua_falhando_fechado(): void
    {
        config(['services.mercadopago.webhook_secret' => null, 'services.mercadopago.app.webhook_secret' => null]);

        $this->notificar('mp-split-9', '')->assertStatus(401);
    }
}
