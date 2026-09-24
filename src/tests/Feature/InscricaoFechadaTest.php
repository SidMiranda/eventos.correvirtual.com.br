<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEventoParaVenda;
use Tests\TestCase;

/**
 * Evento fechado não recebe inscrição.
 *
 * A página do evento esconde o botão, mas quem barra de verdade é o controller:
 * /subscribe/event/{id} pode ser digitado à mão ou ter ficado num link antigo.
 * Esconder no front não é proteger — por isso cada caso é testado nas duas
 * pontas.
 */
class InscricaoFechadaTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEventoParaVenda;

    private Organizer $organizador;
    private User $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->atleta = User::factory()->create(['role' => 'athlete']);
    }

    private function evento(string $data, string $prazo): Event
    {
        $evento = Event::factory()->create([
            'organizer_id' => $this->organizador->id,
            'title' => 'Corrida de Teste',
            'event_date' => $data,
            'registration_deadline' => $prazo,
            'active' => true,
        ]);

        EventModality::factory()->create(['event_id' => $evento->id]);
        EventKit::factory()->create(['event_id' => $evento->id]);
        $this->prepararParaVenda($evento);

        return $evento;
    }

    private function realizado(): Event
    {
        return $this->evento(now()->subMonth(), now()->subMonths(2));
    }

    private function prazoEncerrado(): Event
    {
        return $this->evento(now()->addMonth(), now()->subDay());
    }

    private function aberto(): Event
    {
        return $this->evento(now()->addMonths(2), now()->addMonth());
    }

    /*
    |--------------------------------------------------------------------------
    | A página do evento
    |--------------------------------------------------------------------------
    */

    public function test_evento_realizado_nao_mostra_botao_de_inscricao(): void
    {
        $evento = $this->realizado();

        $this->get("/event/{$evento->id}")
            ->assertOk()
            ->assertDontSee("/subscribe/event/{$evento->id}", false)
            ->assertSee('Evento realizado');
    }

    public function test_evento_com_prazo_encerrado_nao_mostra_botao(): void
    {
        $evento = $this->prazoEncerrado();

        $this->get("/event/{$evento->id}")
            ->assertOk()
            ->assertDontSee("/subscribe/event/{$evento->id}", false)
            ->assertSee('Inscrições encerradas');
    }

    public function test_evento_aberto_continua_mostrando_o_botao(): void
    {
        $evento = $this->aberto();

        $this->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee("/subscribe/event/{$evento->id}", false)
            ->assertSee('Inscreva-se');
    }

    /*
    |--------------------------------------------------------------------------
    | O servidor, para quem chega pelo endereço direto
    |--------------------------------------------------------------------------
    */

    public function test_formulario_de_inscricao_recusa_evento_realizado(): void
    {
        $evento = $this->realizado();

        $this->actingAs($this->atleta)
            ->get("/subscribe/event/{$evento->id}")
            ->assertRedirect("/event/{$evento->id}")
            ->assertSessionHasErrors('inscricao');
    }

    public function test_envio_da_inscricao_e_recusado_em_evento_realizado(): void
    {
        $evento = $this->realizado();

        $this->actingAs($this->atleta)
            ->post("/subscribe/event/{$evento->id}", [
                'modality_id' => $evento->modalities->first()->id,
                'kit_id' => $evento->kits->first()->id,
            ])
            ->assertRedirect("/event/{$evento->id}")
            ->assertSessionHasErrors('inscricao');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_envio_da_inscricao_e_recusado_com_prazo_encerrado(): void
    {
        $evento = $this->prazoEncerrado();

        $this->actingAs($this->atleta)
            ->post("/subscribe/event/{$evento->id}", [
                'modality_id' => $evento->modalities->first()->id,
                'kit_id' => $evento->kits->first()->id,
            ])
            ->assertRedirect("/event/{$evento->id}")
            ->assertSessionHasErrors('inscricao');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_evento_aberto_aceita_a_inscricao_normalmente(): void
    {
        $evento = $this->aberto();

        $this->actingAs($this->atleta)
            ->post("/subscribe/event/{$evento->id}", [
                'modality_id' => $evento->modalities->first()->id,
                'kit_id' => $evento->kits->first()->id,
            ])
            ->assertRedirect('/my-subscriptions');

        $this->assertDatabaseHas('subscriptions', [
            'event_id' => $evento->id,
            'user_id' => $this->atleta->id,
        ]);
    }

    public function test_evento_inativo_tambem_nao_recebe_inscricao(): void
    {
        // Desativar é como o organizador tira um evento do ar sem apagar o
        // histórico — não pode continuar aceitando gente por link antigo.
        $evento = $this->aberto();
        $evento->update(['active' => false]);

        $this->actingAs($this->atleta)
            ->post("/subscribe/event/{$evento->id}", [
                'modality_id' => $evento->modalities->first()->id,
                'kit_id' => $evento->kits->first()->id,
            ])
            ->assertSessionHasErrors('inscricao');

        $this->assertDatabaseCount('subscriptions', 0);
    }
}
