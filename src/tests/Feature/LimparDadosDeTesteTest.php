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
 * A limpeza da base de demonstração.
 *
 * O que este teste protege é a linha divisória: o comando apaga os eventos
 * mocados e todo o histórico de inscrição e pagamento, e **não encosta** nos
 * eventos reais, nas modalidades e kits deles, nem nos atletas.
 */
class LimparDadosDeTesteTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create();
    }

    private function evento(string $slug): Event
    {
        $evento = Event::factory()->create([
            'organizer_id' => $this->organizador->id,
            'slug' => $slug,
        ]);

        EventModality::factory()->create(['event_id' => $evento->id]);
        EventKit::factory()->create(['event_id' => $evento->id]);

        return $evento;
    }

    private function inscricao(Event $evento): Subscription
    {
        $inscricao = Subscription::factory()->create([
            'event_id' => $evento->id,
            'user_id' => User::factory()->create()->id,
        ]);

        Payment::factory()->create(['subscription_id' => $inscricao->id]);

        return $inscricao;
    }

    public function test_sem_force_nao_apaga_nada(): void
    {
        $mocado = $this->evento('carnarun-do-quarteto-2025');
        $this->inscricao($mocado);

        $this->artisan('base:limpar-testes')->assertSuccessful();

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_apaga_os_eventos_mocados_com_modalidades_e_kits(): void
    {
        $mocado = $this->evento('night-run-etapa-fogo-sp');

        $this->artisan('base:limpar-testes', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('events', ['id' => $mocado->id]);
        $this->assertDatabaseMissing('event_modalities', ['event_id' => $mocado->id]);
        $this->assertDatabaseMissing('event_kits', ['event_id' => $mocado->id]);
    }

    public function test_nao_encosta_no_evento_real_nem_nas_modalidades_e_kits_dele(): void
    {
        $real = $this->evento('1a-oab-run-rosa-e-azul');

        $this->artisan('base:limpar-testes', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('events', ['id' => $real->id]);
        $this->assertDatabaseHas('event_modalities', ['event_id' => $real->id]);
        $this->assertDatabaseHas('event_kits', ['event_id' => $real->id]);
    }

    public function test_leva_todo_o_historico_de_inscricao_e_pagamento(): void
    {
        // Inclusive a de evento que fica: o histórico inteiro é da fase de
        // demonstração, não só o dos eventos que somem.
        $real = $this->evento('2a-corrida-pela-vida');
        $this->inscricao($real);

        $this->artisan('base:limpar-testes', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('events', ['id' => $real->id]);
    }

    public function test_os_atletas_ficam(): void
    {
        $mocado = $this->evento('carnarun-do-quarteto-2025');
        $this->inscricao($mocado);

        $this->artisan('base:limpar-testes', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | --listar e --evento-de-teste (2026-09-20)
    |--------------------------------------------------------------------------
    */

    public function test_listar_mostra_o_banco_e_nao_apaga_nada(): void
    {
        $evento = $this->evento('corrida-teste-do-fluxo');
        $inscricao = $this->inscricao($evento);
        Coupon::factory()->create(['event_id' => $evento->id, 'code' => 'LISTA1']);

        $this->artisan('base:limpar-testes', ['--listar' => true])
            ->expectsOutputToContain('corrida-teste-do-fluxo')
            ->expectsOutputToContain($inscricao->user->email)
            ->expectsOutputToContain('LISTA1')
            ->assertSuccessful();

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_listar_conta_os_orfaos(): void
    {
        // Não dá para plantar um órfão de verdade: a FK barra, e o PRAGMA que
        // a desligaria é ignorado dentro da transação do RefreshDatabase. O
        // que este teste garante é que as consultas de órfão rodam e chegam à
        // saída — num banco íntegro, zeradas.
        $evento = $this->evento('carnarun-do-quarteto-2025');
        $this->inscricao($evento);

        $this->artisan('base:limpar-testes', ['--listar' => true])
            ->expectsOutputToContain('inscrições sem evento=0')
            ->expectsOutputToContain('pagamentos sem inscrição=0')
            ->assertSuccessful();
    }

    public function test_sem_a_opcao_o_evento_de_teste_do_fluxo_fica(): void
    {
        $teste = $this->evento('corrida-teste-do-fluxo');

        $this->artisan('base:limpar-testes', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('events', ['id' => $teste->id]);
    }

    public function test_evento_de_teste_apontado_sai_com_kits_modalidades_e_cupons(): void
    {
        $teste = $this->evento('corrida-teste-do-fluxo');
        $real = $this->evento('1a-oab-run-rosa-e-azul');
        $this->inscricao($teste);
        Coupon::factory()->create(['event_id' => $teste->id]);
        $cupomReal = Coupon::factory()->create(['event_id' => $real->id]);

        $this->artisan('base:limpar-testes', ['--force' => true, '--evento-de-teste' => 'corrida-teste-do-fluxo'])
            ->expectsOutputToContain('corrida-teste-do-fluxo')
            ->assertSuccessful();

        $this->assertDatabaseMissing('events', ['id' => $teste->id]);
        $this->assertDatabaseMissing('event_modalities', ['event_id' => $teste->id]);
        $this->assertDatabaseMissing('event_kits', ['event_id' => $teste->id]);
        $this->assertDatabaseMissing('coupons', ['event_id' => $teste->id]);
        $this->assertDatabaseCount('subscriptions', 0);

        // O real continua inteiro, cupom incluído.
        $this->assertDatabaseHas('events', ['id' => $real->id]);
        $this->assertDatabaseHas('coupons', ['id' => $cupomReal->id]);
    }

    public function test_slug_desconhecido_em_evento_de_teste_falha_sem_apagar(): void
    {
        $mocado = $this->evento('carnarun-do-quarteto-2025');
        $this->inscricao($mocado);

        $this->artisan('base:limpar-testes', ['--force' => true, '--evento-de-teste' => 'nao-existe'])
            ->expectsOutputToContain('Nenhum evento com o slug')
            ->assertFailed();

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('subscriptions', 1);
    }
}
