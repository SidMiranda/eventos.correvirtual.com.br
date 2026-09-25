<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\KitOption;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AlteracaoDeInscricao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Minha conta" › editar inscrição (docs/specs/area-do-atleta.md, fatia 3):
 * equipe e camiseta, até o "Alterações até" do evento.
 */
class EditarInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $atleta;
    private Event $evento;
    private EventKit $kit;
    private Subscription $inscricao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->atleta = User::factory()->create(['role' => 'athlete']);

        $this->evento = Event::factory()->create([
            'organizer_id' => $this->organizador->id,
            'title' => 'Corrida da Serra',
            'event_date' => now()->addMonth(),
            'registration_deadline' => now()->addWeeks(2),
            'changes_deadline' => null,
        ]);

        $this->kit = EventKit::factory()->create(['event_id' => $this->evento->id]);
        foreach (['P', 'M', 'G', 'INF8'] as $i => $t) {
            $this->kit->options()->create(['attribute' => KitOption::TAMANHO, 'value' => $t, 'position' => $i]);
        }

        $this->inscricao = Subscription::factory()->create([
            'event_id' => $this->evento->id,
            'user_id' => $this->atleta->id,
            'modality_id' => EventModality::factory()->create(['event_id' => $this->evento->id])->id,
            'kit_id' => $this->kit->id,
            'status' => Subscription::PAGA,
            'shirt_size' => 'M',
            'team_name' => 'CORRE MOGI',
            'price' => 80,
            'list_price' => 80,
        ]);
    }

    private function editar(array $dados, ?Subscription $inscricao = null)
    {
        return $this->actingAs($this->atleta)
            ->put('/minha-conta/inscricoes/' . ($inscricao ?? $this->inscricao)->id, $dados);
    }

    /*
    |--------------------------------------------------------------------------
    | A regra
    |--------------------------------------------------------------------------
    */

    public function test_sem_prazo_proprio_vale_o_encerramento_das_inscricoes(): void
    {
        $alteracao = AlteracaoDeInscricao::para($this->inscricao);

        $this->assertTrue($alteracao->permitida());
        $this->assertTrue($alteracao->prazo()->equalTo($this->evento->registration_deadline));
    }

    public function test_o_prazo_proprio_manda(): void
    {
        $this->evento->update(['changes_deadline' => now()->subHour()]);

        $alteracao = AlteracaoDeInscricao::para($this->inscricao->fresh());

        $this->assertFalse($alteracao->permitida());
        $this->assertStringContainsString('O prazo para alterações terminou', $alteracao->motivo());
    }

    public function test_cancelada_e_realizada_nao_alteram(): void
    {
        $this->inscricao->update(['status' => Subscription::CANCELADA]);
        $this->assertSame('Esta inscrição foi cancelada.', AlteracaoDeInscricao::para($this->inscricao->fresh())->motivo());

        $this->inscricao->update(['status' => Subscription::PAGA]);
        $this->evento->update(['event_date' => now()->subDay(), 'registration_deadline' => now()->subWeek()]);
        $this->assertSame('Este evento já aconteceu.', AlteracaoDeInscricao::para($this->inscricao->fresh())->motivo());
    }

    /*
    |--------------------------------------------------------------------------
    | A tela
    |--------------------------------------------------------------------------
    */

    public function test_a_tela_traz_os_tamanhos_do_kit_e_o_prazo(): void
    {
        $this->actingAs($this->atleta)->get('/minha-conta/inscricoes/' . $this->inscricao->id)
            ->assertOk()
            ->assertSee('Corrida da Serra')
            ->assertSee('value="INF8"', false)
            ->assertSee('Infantil 8 (38 x 54 cm)')
            ->assertDontSee('value="GG"', false)
            ->assertSee('value="CORRE MOGI"', false)
            ->assertSee($this->evento->registration_deadline->format('d/m/Y'))
            ->assertSee('Salvar alterações');
    }

    public function test_fora_do_prazo_a_tela_trava_e_explica(): void
    {
        $this->evento->update(['changes_deadline' => now()->subHour()]);

        $this->actingAs($this->atleta)->get('/minha-conta/inscricoes/' . $this->inscricao->id)
            ->assertOk()
            ->assertSee('O prazo para alterações terminou')
            ->assertSee('disabled', false)
            ->assertDontSee('Salvar alterações');
    }

    public function test_a_lista_mostra_editar_so_quando_pode(): void
    {
        $url = route('conta.inscricao', $this->inscricao->id);

        $this->actingAs($this->atleta)->get('/minha-conta')->assertSee($url, false);

        $this->evento->update(['changes_deadline' => now()->subHour()]);

        $this->actingAs($this->atleta)->get('/minha-conta')->assertDontSee($url, false);
    }

    /*
    |--------------------------------------------------------------------------
    | Salvar
    |--------------------------------------------------------------------------
    */

    public function test_troca_camiseta_e_equipe(): void
    {
        $this->editar(['camiseta' => 'INF8', 'equipe' => '  são  paulo runners '])
            ->assertRedirect('/minha-conta')
            ->assertSessionHasNoErrors();

        $inscricao = $this->inscricao->fresh();
        $this->assertSame('INF8', $inscricao->shirt_size);
        $this->assertSame('SÃO PAULO RUNNERS', $inscricao->team_name);
    }

    public function test_equipe_em_branco_tira_a_equipe(): void
    {
        $this->editar(['camiseta' => 'M', 'equipe' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->inscricao->fresh()->team_name);
    }

    public function test_tamanho_fora_do_kit_e_recusado(): void
    {
        $this->editar(['camiseta' => 'GG', 'equipe' => 'X'])->assertSessionHasErrors('camiseta');
        $this->editar(['camiseta' => '', 'equipe' => 'X'])->assertSessionHasErrors('camiseta');

        $this->assertSame('M', $this->inscricao->fresh()->shirt_size);
    }

    public function test_equipe_com_simbolo_e_recusada(): void
    {
        $this->editar(['camiseta' => 'M', 'equipe' => 'Corre & Cia!'])->assertSessionHasErrors('equipe');

        $this->assertSame('CORRE MOGI', $this->inscricao->fresh()->team_name);
    }

    public function test_kit_sem_camiseta_so_muda_a_equipe(): void
    {
        $this->kit->options()->delete();

        $this->editar(['camiseta' => 'G', 'equipe' => 'Nova'])->assertSessionHasNoErrors();

        $inscricao = $this->inscricao->fresh();
        $this->assertSame('M', $inscricao->shirt_size);
        $this->assertSame('NOVA', $inscricao->team_name);
    }

    public function test_fora_do_prazo_nao_salva(): void
    {
        $this->evento->update(['changes_deadline' => now()->subHour()]);

        $this->editar(['camiseta' => 'G', 'equipe' => 'Nova'])->assertSessionHasErrors('inscricao');

        $this->assertSame('M', $this->inscricao->fresh()->shirt_size);
    }

    public function test_nada_alem_de_equipe_e_camiseta_muda(): void
    {
        $outroKit = EventKit::factory()->create(['event_id' => $this->evento->id]);

        $this->editar([
            'camiseta' => 'G',
            'equipe' => 'Nova',
            'kit_id' => $outroKit->id,
            'price' => 1,
            'status' => Subscription::CANCELADA,
        ])->assertSessionHasNoErrors();

        $inscricao = $this->inscricao->fresh();
        $this->assertSame($this->kit->id, $inscricao->kit_id);
        $this->assertSame('80.00', $inscricao->price);
        $this->assertSame(Subscription::PAGA, $inscricao->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Acesso
    |--------------------------------------------------------------------------
    */

    public function test_deslogado_vai_para_o_login(): void
    {
        $this->get('/minha-conta/inscricoes/' . $this->inscricao->id)->assertRedirect('/login');
    }

    public function test_inscricao_de_outra_pessoa_da_404(): void
    {
        $intruso = User::factory()->create(['role' => 'athlete']);

        $this->actingAs($intruso)->get('/minha-conta/inscricoes/' . $this->inscricao->id)->assertNotFound();
        $this->actingAs($intruso)->put('/minha-conta/inscricoes/' . $this->inscricao->id, ['camiseta' => 'G'])->assertNotFound();

        $this->assertSame('M', $this->inscricao->fresh()->shirt_size);
    }

    public function test_inscricao_de_outro_site_da_404(): void
    {
        $this->evento->update(['organizer_id' => Organizer::factory()->create()->id]);

        $this->actingAs($this->atleta)->get('/minha-conta/inscricoes/' . $this->inscricao->id)->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | O prazo no painel
    |--------------------------------------------------------------------------
    */

    public function test_o_organizador_define_o_prazo_no_cadastro_do_evento(): void
    {
        $admin = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $this->organizador->id]);

        $this->actingAs($admin)->get("/admin/eventos/{$this->evento->id}/edit")
            ->assertOk()
            ->assertSee('name="changes_deadline"', false)
            ->assertSee('Alterações até');
    }
}
