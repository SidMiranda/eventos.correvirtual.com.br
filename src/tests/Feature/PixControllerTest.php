<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingSubscription(): Subscription
    {
        $organizer = Organizer::create([
            'name' => 'Organizador de Teste',
            'domain' => 'localhost',
            'email' => 'tenant@example.com',
            'slug' => 'organizador-de-teste',
            'cnpj' => '00.000.000/0001-00',
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Corrida de Teste',
            'slug' => 'corrida-de-teste',
            'description' => 'Descrição',
            'location' => 'Local de Teste',
            'event_date' => now()->addMonth(),
            'registration_deadline' => now()->addWeeks(2),
            'banner_url' => 'banner.jpg',
            'active' => true,
        ]);

        $modality = EventModality::factory()->create(['event_id' => $event->id]);
        $kit = EventKit::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        return Subscription::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
            'price' => $kit->price,
            'status' => 'pending',
            'bib_number' => null,
        ]);
    }

    public function test_generate_pix_creates_payment_when_mercadopago_succeeds(): void
    {
        $subscription = $this->createPendingSubscription();

        $pix = (object) [
            'id' => 'mp-123',
            'point_of_interaction' => (object) [
                'transaction_data' => (object) [
                    'qr_code' => '00020126...',
                    'qr_code_base64' => 'base64stuff',
                    'ticket_url' => 'https://mercadopago.com/ticket/123',
                ],
            ],
            'date_of_expiration' => null,
        ];

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')->once()->andReturn($pix);

        $response = $this->actingAs($subscription->user)->post('/event-pay', [
            'subscription_id' => $subscription->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'transaction_id' => 'mp-123',
            'status' => 'pending',
        ]);
    }

    public function test_generate_pix_charges_exactly_the_subscription_price(): void
    {
        // A asserção de dinheiro. Até 2026-08-29 existia um PixAmountResolver que
        // sobrepunha o valor de TODA cobrança por uma variável de ambiente — foi
        // removido, e o preço passou a viver no kit. Este teste garante que o
        // valor cobrado é o da inscrição, e que ninguém reintroduza sobreposição.
        $subscription = $this->createPendingSubscription();
        $subscription->update(['price' => 87.50]);

        $pix = (object) [
            'id' => 'mp-preco',
            'point_of_interaction' => (object) [
                'transaction_data' => (object) [
                    'qr_code' => '00020126...',
                    'qr_code_base64' => 'base64stuff',
                    'ticket_url' => 'https://mercadopago.com/ticket/preco',
                ],
            ],
            'date_of_expiration' => null,
        ];

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')
            ->once()
            ->with(87.50, $subscription->user->email, (string) $subscription->id)
            ->andReturn($pix);

        $this->actingAs($subscription->user)
            ->post('/event-pay', ['subscription_id' => $subscription->id])
            ->assertOk();
    }

    public function test_generate_pix_refuses_subscription_of_another_user(): void
    {
        // Até 2026-09-20 era um find($id) solto: qualquer usuário logado gerava
        // Pix para qualquer inscrição. 404 e não 403 — não é para descobrir que
        // a inscrição do outro existe.
        $subscription = $this->createPendingSubscription();
        $intruso = User::factory()->create();

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')->never();

        $this->actingAs($intruso)
            ->post('/event-pay', ['subscription_id' => $subscription->id])
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_generate_pix_refuses_already_paid_subscription(): void
    {
        $subscription = $this->createPendingSubscription();
        $subscription->update(['status' => 'paid']);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')->never();

        $this->actingAs($subscription->user)
            ->post('/event-pay', ['subscription_id' => $subscription->id])
            ->assertRedirect('/my-subscriptions')
            ->assertSessionHasErrors('pix');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_generate_pix_refuses_free_subscription(): void
    {
        // Inscrição gratuita já nasce paga e não chega aqui; é a rede de
        // segurança para o Mercado Pago nunca receber R$ 0,00.
        $subscription = $this->createPendingSubscription();
        $subscription->update(['price' => 0, 'discount_amount' => $subscription->list_price]);

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')->never();

        $this->actingAs($subscription->user)
            ->post('/event-pay', ['subscription_id' => $subscription->id])
            ->assertRedirect('/my-subscriptions')
            ->assertSessionHasErrors('pix');
    }

    public function test_generate_pix_requires_login(): void
    {
        $subscription = $this->createPendingSubscription();

        $this->post('/event-pay', ['subscription_id' => $subscription->id])
            ->assertRedirect('/login');
    }

    public function test_generate_pix_shows_friendly_error_when_mercadopago_fails(): void
    {
        $subscription = $this->createPendingSubscription();

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('createPixPayment')->once()->andReturn(null);

        $response = $this->actingAs($subscription->user)->post('/event-pay', [
            'subscription_id' => $subscription->id,
        ]);

        $response->assertRedirect('/my-subscriptions');
        $response->assertSessionHasErrors('pix');
        $this->assertDatabaseCount('payments', 0);
    }
}
