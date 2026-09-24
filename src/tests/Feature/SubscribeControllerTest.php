<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEventoParaVenda;
use Tests\TestCase;

class SubscribeControllerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEventoParaVenda;

    protected function setUp(): void
    {
        parent::setUp();

        // O middleware de tenant (IdentifyOrganizerByDomain) roda em toda request e
        // resolve o organizador pelo domínio "localhost" (host default dos testes).
        // "domain" é único, então só pode existir um organizador com esse domínio.
        Organizer::create([
            'name' => 'Organizador Padrão de Teste',
            'domain' => 'localhost',
            'email' => 'tenant@example.com',
            'slug' => 'organizador-padrao-de-teste',
            'cnpj' => '00.000.000/0001-00',
        ]);
    }

    /**
     * @return array{0: Event, 1: EventModality, 2: EventKit}
     */
    private function createEventWithModalityAndKit(): array
    {
        $organizer = Organizer::create([
            'name' => 'Organizador ' . uniqid(),
            'domain' => 'evento-' . uniqid() . '.example.com',
            'email' => uniqid() . '@example.com',
            'slug' => 'organizador-' . uniqid(),
            'cnpj' => '00.000.000/0001-00',
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Corrida de Teste',
            'slug' => 'corrida-de-teste-' . uniqid(),
            'description' => 'Descrição',
            'location' => 'Local de Teste',
            'event_date' => now()->addMonth(),
            'registration_deadline' => now()->addWeeks(2),
            'banner_url' => 'banner.jpg',
            'active' => true,
        ]);

        $modality = EventModality::factory()->create(['event_id' => $event->id]);
        $kit = EventKit::factory()->create(['event_id' => $event->id]);
        $this->prepararParaVenda($event);

        return [$event, $modality, $kit];
    }

    public function test_subscribe_rejects_modality_and_kit_that_belong_to_another_event(): void
    {
        [$event] = $this->createEventWithModalityAndKit();
        [, $otherModality, $otherKit] = $this->createEventWithModalityAndKit();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $otherModality->id,
            'kit_id' => $otherKit->id,
        ]);

        $response->assertSessionHasErrors(['modality_id', 'kit_id']);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_subscribe_creates_subscription_with_valid_modality_and_kit(): void
    {
        [$event, $modality, $kit] = $this->createEventWithModalityAndKit();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
        ]);

        $response->assertRedirect('/my-subscriptions');
        $this->assertDatabaseHas('subscriptions', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
            'status' => 'pending',
        ]);
    }

    public function test_subscribe_charges_the_grid_price_not_the_kit_price(): void
    {
        // Desde a fatia 2 de 2026-09-24 (ADR 0007) o preço é a célula da grade
        // no lote vigente. O kit continua com 59,90 na coluna antiga — e ela
        // não pode ser lida.
        [$event, $modality, $kit] = $this->createEventWithModalityAndKit();
        \App\Models\EventPrice::where('kit_id', $kit->id)->update(['price' => 149.90]);
        $user = User::factory()->create();

        $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'price' => 149.90,
        ]);
    }

    public function test_subscribe_blocks_second_attempt_when_already_subscribed(): void
    {
        [$event, $modality, $kit] = $this->createEventWithModalityAndKit();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
        ]);

        $response = $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
        ]);

        $response->assertRedirect('/my-subscriptions');
        $response->assertSessionHas('modal_type', 'info');
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_user_can_subscribe_again_after_cancelling(): void
    {
        [$event, $modality, $kit] = $this->createEventWithModalityAndKit();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
        ]);

        $subscription = Subscription::firstOrFail();

        $this->actingAs($user)->post('/subscription/cancel', [
            'subscription_id' => $subscription->id,
        ]);

        // Desde 2026-09-21 cancelar MARCA a inscrição em vez de apagá-la: sem
        // isso o organizador não tinha como saber que alguém desistiu. A linha
        // é reaproveitada na reinscrição, porque a unique (event_id, user_id)
        // não deixa criar uma segunda. Ver CancelarInscricaoTest.
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame('cancelled', $subscription->fresh()->status);

        $response = $this->actingAs($user)->post("/subscribe/event/{$event->id}", [
            'modality_id' => $modality->id,
            'kit_id' => $kit->id,
        ]);

        $response->assertRedirect('/my-subscriptions');
        $response->assertSessionHas('modal_type', 'success');
        $this->assertDatabaseCount('subscriptions', 1);
    }
}
