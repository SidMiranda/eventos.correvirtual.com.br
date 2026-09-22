<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A equipe digitada na inscrição.
 *
 * Texto livre por decisão do dono (2026-09-22): existe cadastro de equipes no
 * painel desde 2026-08-29, mas na primeira prova ninguém sabe ainda quais
 * assessorias vão aparecer — escolher de uma lista vazia seria pior.
 *
 * O que o servidor garante: só letras, números e espaço; no máximo 50
 * caracteres; e guardado em CAIXA ALTA, para a mesma equipe escrita de três
 * jeitos não virar três equipes na hora de contar.
 */
class EquipeNaInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private Event $evento;
    private EventModality $modalidade;
    private EventKit $kit;
    private User $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);

        $this->evento = Event::factory()->create([
            'organizer_id' => $organizador->id,
            'active' => true,
            'event_date' => now()->addMonth(),
            'registration_deadline' => now()->addWeeks(2),
        ]);

        $this->modalidade = EventModality::factory()->create(['event_id' => $this->evento->id]);
        $this->kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
        $this->atleta = User::factory()->create(['role' => 'athlete']);
    }

    private function inscrever(array $extra = [])
    {
        return $this->actingAs($this->atleta)->post("/subscribe/event/{$this->evento->id}", array_merge([
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
        ], $extra));
    }

    private function equipeGravada(): ?string
    {
        return Subscription::where('user_id', $this->atleta->id)->first()?->team_name;
    }

    public function test_a_equipe_e_gravada_em_caixa_alta(): void
    {
        $this->inscrever(['equipe' => 'corre mogi']);

        $this->assertSame('CORRE MOGI', $this->equipeGravada());
    }

    public function test_acento_sobrevive_a_caixa_alta(): void
    {
        // `strtoupper` devolveria "SãO JOãO" — daí o mb_strtoupper.
        $this->inscrever(['equipe' => 'equipe são joão']);

        $this->assertSame('EQUIPE SÃO JOÃO', $this->equipeGravada());
    }

    public function test_espaco_sobrando_e_removido(): void
    {
        // "CORRE  MOGI" e "CORRE MOGI" contariam como duas equipes.
        $this->inscrever(['equipe' => '  corre   mogi  ']);

        $this->assertSame('CORRE MOGI', $this->equipeGravada());
    }

    public function test_numero_e_aceito(): void
    {
        $this->inscrever(['equipe' => 'Equipe 42']);

        $this->assertSame('EQUIPE 42', $this->equipeGravada());
    }

    public function test_equipe_e_opcional(): void
    {
        $this->inscrever()->assertSessionHasNoErrors();

        $this->assertNull($this->equipeGravada());
    }

    public function test_so_espacos_gravam_nulo(): void
    {
        $this->inscrever(['equipe' => '   ']);

        $this->assertNull($this->equipeGravada());
    }

    public function test_pontuacao_e_simbolo_sao_recusados(): void
    {
        foreach (['Corre Mogi!', 'Corre-Mogi', 'Corre @ Mogi', '<b>Corre</b>'] as $nome) {
            $this->inscrever(['equipe' => $nome])->assertSessionHasErrors('equipe');
        }

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_acima_de_50_caracteres_e_recusado(): void
    {
        $this->inscrever(['equipe' => str_repeat('A', 51)])
            ->assertSessionHasErrors('equipe');

        $this->inscrever(['equipe' => str_repeat('A', 50)])
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('A', 50), $this->equipeGravada());
    }

    public function test_reinscrever_depois_de_cancelar_atualiza_a_equipe(): void
    {
        // A linha cancelada é reaproveitada (a unique (event_id, user_id) não
        // deixa criar outra): a equipe da nova tentativa tem que valer.
        $this->inscrever(['equipe' => 'equipe antiga']);

        $inscricao = Subscription::where('user_id', $this->atleta->id)->first();
        $inscricao->update(['status' => Subscription::CANCELADA, 'cancelled_at' => now()]);

        $this->inscrever(['equipe' => 'equipe nova']);

        $this->assertSame('EQUIPE NOVA', $this->equipeGravada());
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_o_formulario_tem_o_campo_depois_do_cupom(): void
    {
        $resposta = $this->actingAs($this->atleta)
            ->get("/subscribe/event/{$this->evento->id}")
            ->assertOk();

        $html = $resposta->getContent();

        $this->assertStringContainsString('name="equipe"', $html);
        $this->assertLessThan(
            strpos($html, 'name="equipe"'),
            strpos($html, 'name="cupom"'),
            'O campo de equipe deve vir depois do cupom.'
        );
    }
}
