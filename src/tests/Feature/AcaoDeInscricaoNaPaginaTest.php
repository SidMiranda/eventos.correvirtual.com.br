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

/**
 * O botão de inscrição da página do evento: aparece no alto e no fim, e para
 * quem já se inscreveu vira a situação da inscrição, levando às inscrições.
 */
class AcaoDeInscricaoNaPaginaTest extends TestCase
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
            'event_date' => $data,
            'registration_deadline' => $prazo,
            'active' => true,
        ]);

        EventModality::factory()->create(['event_id' => $evento->id]);
        EventKit::factory()->create(['event_id' => $evento->id]);
        $this->prepararParaVenda($evento);

        return $evento;
    }

    private function aberto(): Event
    {
        return $this->evento(now()->addMonths(2), now()->addMonth());
    }

    private function inscrever(Event $evento, string $status, ?User $quem = null): void
    {
        Subscription::factory()->create([
            'event_id' => $evento->id,
            'user_id' => ($quem ?? $this->atleta)->id,
            'status' => $status,
        ]);
    }

    public function test_visitante_ve_o_botao_no_alto_e_no_fim(): void
    {
        $evento = $this->aberto();

        $html = $this->get("/event/{$evento->id}")->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, "/subscribe/event/{$evento->id}\""));
        $this->assertStringContainsString('cta-button--rodape', $html);
        // O do alto vem antes do bloco "Data".
        $this->assertLessThan(strpos($html, '<h3>Data</h3>'), strpos($html, 'cta-button'));
    }

    public function test_logado_sem_inscricao_ve_inscreva_se(): void
    {
        $evento = $this->aberto();

        $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Inscreva-se')
            ->assertDontSee('Inscrição confirmada')
            ->assertDontSee('Aguardando pagamento');
    }

    public function test_inscricao_paga_mostra_confirmada_e_leva_as_inscricoes(): void
    {
        $evento = $this->aberto();
        $this->inscrever($evento, 'paid');

        $html = $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Inscrição confirmada')
            ->assertDontSee('Inscreva-se')
            ->assertDontSee("/subscribe/event/{$evento->id}\"", false)
            ->getContent();

        $this->assertSame(2, substr_count($html, 'href="' . route('subscriptions.my') . '"'));
    }

    public function test_inscricao_pendente_mostra_aguardando_pagamento(): void
    {
        $evento = $this->aberto();
        $this->inscrever($evento, 'pending');

        $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Aguardando pagamento')
            ->assertSee(route('subscriptions.my'), false)
            ->assertDontSee('Inscreva-se');
    }

    public function test_inscricao_cancelada_volta_a_oferecer_inscricao(): void
    {
        $evento = $this->aberto();
        $this->inscrever($evento, 'cancelled');

        $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Inscreva-se')
            ->assertDontSee('Aguardando pagamento');
    }

    public function test_inscricao_de_outra_pessoa_nao_conta(): void
    {
        $evento = $this->aberto();
        $this->inscrever($evento, 'paid', User::factory()->create());

        $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Inscreva-se')
            ->assertDontSee('Inscrição confirmada');
    }

    public function test_paga_com_prazo_encerrado_mostra_confirmada_e_nao_encerradas(): void
    {
        $evento = $this->evento(now()->addMonth(), now()->subDay());
        $this->inscrever($evento, 'paid');

        $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Inscrição confirmada')
            ->assertDontSee('Inscrições encerradas');
    }

    public function test_prazo_encerrado_nao_repete_o_aviso_no_fim(): void
    {
        $evento = $this->evento(now()->addMonth(), now()->subDay());

        $html = $this->get("/event/{$evento->id}")->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Inscrições encerradas'));
    }

    public function test_evento_realizado_mostra_realizado_mesmo_para_quem_pagou(): void
    {
        $evento = $this->evento(now()->subMonth(), now()->subMonths(2));
        $this->inscrever($evento, 'paid');

        $this->actingAs($this->atleta)->get("/event/{$evento->id}")
            ->assertOk()
            ->assertSee('Evento realizado')
            ->assertDontSee('Inscrição confirmada');
    }
}
