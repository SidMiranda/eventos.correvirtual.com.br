<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmed;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MercadoPagoWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingSubscriptionWithPayment(): Subscription
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

        $subscription = Subscription::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
            'price' => $kit->price,
            'status' => 'pending',
            'bib_number' => null,
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'transaction_id' => 'mp-payment-123',
            'status' => 'pending',
        ]);

        return $subscription;
    }

    /**
     * @return array{'x-signature': string, 'x-request-id': string}
     */
    private function validSignatureHeaders(string $dataId, string $secret): array
    {
        $ts = (string) time();
        $requestId = 'req-1';
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        return [
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ];
    }

    public function test_webhook_approved_with_valid_signature_confirms_subscription_and_sends_email(): void
    {
        Mail::fake();
        config(['services.mercadopago.webhook_secret' => 'segredo-do-webhook']);
        $subscription = $this->createPendingSubscriptionWithPayment();

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('getPayment')->once()->andReturn((object) [
            'status' => 'approved',
            'external_reference' => (string) $subscription->id,
        ]);

        $response = $this->withHeaders(
            $this->validSignatureHeaders('mp-payment-123', 'segredo-do-webhook')
        )->postJson('/api/webhooks/mercadopago?data.id=mp-payment-123', [
            'data' => ['id' => 'mp-payment-123'],
        ]);

        $response->assertOk();
        $this->assertSame('paid', $subscription->fresh()->status);
        // confirmed_at ficava vazio até 2026-09-20: o webhook só mudava o status.
        $this->assertNotNull($subscription->fresh()->confirmed_at);
        $this->assertSame('approved', Payment::where('transaction_id', 'mp-payment-123')->value('status'));

        Mail::assertSent(SubscriptionConfirmed::class, function ($mail) use ($subscription) {
            return $mail->hasTo($subscription->user->email)
                && $mail->subscription->id === $subscription->id;
        });
    }

    public function test_webhook_retry_for_already_paid_subscription_does_not_resend_email(): void
    {
        Mail::fake();
        config(['services.mercadopago.webhook_secret' => 'segredo-do-webhook']);
        $subscription = $this->createPendingSubscriptionWithPayment();
        $subscription->update(['status' => 'paid']); // já processado por uma notificação anterior

        $mock = \Mockery::mock('alias:' . MercadoPagoService::class);
        $mock->shouldReceive('getPayment')->once()->andReturn((object) [
            'status' => 'approved',
            'external_reference' => (string) $subscription->id,
        ]);

        $response = $this->withHeaders(
            $this->validSignatureHeaders('mp-payment-123', 'segredo-do-webhook')
        )->postJson('/api/webhooks/mercadopago?data.id=mp-payment-123', [
            'data' => ['id' => 'mp-payment-123'],
        ]);

        $response->assertOk();
        $this->assertSame('paid', $subscription->fresh()->status);
        Mail::assertNotSent(SubscriptionConfirmed::class);
    }

    public function test_webhook_rejects_notification_without_signature_header(): void
    {
        config(['services.mercadopago.webhook_secret' => 'segredo-do-webhook']);
        $subscription = $this->createPendingSubscriptionWithPayment();

        $response = $this->postJson('/api/webhooks/mercadopago?data.id=mp-payment-123', [
            'data' => ['id' => 'mp-payment-123'],
        ]);

        $response->assertStatus(401);
        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_webhook_rejects_notification_with_invalid_signature(): void
    {
        config(['services.mercadopago.webhook_secret' => 'segredo-do-webhook']);
        $subscription = $this->createPendingSubscriptionWithPayment();

        $response = $this->withHeaders([
            'x-signature' => 'ts=1700000000,v1=assinatura-forjada',
            'x-request-id' => 'req-1',
        ])->postJson('/api/webhooks/mercadopago?data.id=mp-payment-123', [
            'data' => ['id' => 'mp-payment-123'],
        ]);

        $response->assertStatus(401);
        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_webhook_rejects_when_secret_is_not_configured(): void
    {
        config(['services.mercadopago.webhook_secret' => null]);
        $subscription = $this->createPendingSubscriptionWithPayment();

        $ts = (string) time();
        $manifest = "id:mp-payment-123;request-id:req-1;ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, 'qualquer-coisa');

        $response = $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => 'req-1',
        ])->postJson('/api/webhooks/mercadopago?data.id=mp-payment-123', [
            'data' => ['id' => 'mp-payment-123'],
        ]);

        $response->assertStatus(401);
        $this->assertSame('pending', $subscription->fresh()->status);
    }
}
