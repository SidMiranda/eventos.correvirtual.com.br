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
 * "Minha conta" › Inscrições (docs/specs/area-do-atleta.md, fatia 1).
 */
class MinhaContaTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->atleta = User::factory()->create(['role' => 'athlete']);
    }

    private function inscricao(string $titulo, $data, array $extra = [], ?Organizer $organizador = null, ?User $quem = null): Subscription
    {
        $evento = Event::factory()->create([
            'organizer_id' => ($organizador ?? $this->organizador)->id,
            'title' => $titulo,
            'event_date' => $data,
        ]);

        return Subscription::factory()->create(array_merge([
            'event_id' => $evento->id,
            'user_id' => ($quem ?? $this->atleta)->id,
            'modality_id' => EventModality::factory()->create(['event_id' => $evento->id])->id,
            'kit_id' => EventKit::factory()->create(['event_id' => $evento->id])->id,
            'status' => Subscription::PAGA,
            'price' => 80,
        ], $extra));
    }

    private function conta()
    {
        return $this->actingAs($this->atleta)->get('/minha-conta');
    }

    public function test_deslogado_vai_para_o_login(): void
    {
        $this->get('/minha-conta')->assertRedirect('/login');
        $this->get('/my-subscriptions')->assertRedirect('/login');
    }

    public function test_o_endereco_antigo_mostra_a_mesma_tela(): void
    {
        $this->inscricao('Corrida do Sol', now()->addMonth());

        $this->actingAs($this->atleta)->get('/my-subscriptions')
            ->assertOk()
            ->assertSee('Minha conta')
            ->assertSee('Corrida do Sol');
    }

    public function test_separa_proximas_e_realizadas_na_ordem_certa(): void
    {
        $this->inscricao('Prova Longe', now()->addMonths(3));
        $this->inscricao('Prova Perto', now()->addWeek());
        $this->inscricao('Prova Antiga', now()->subYear());
        $this->inscricao('Prova Recente', now()->subMonth());

        $this->conta()->assertOk()->assertSeeInOrder([
            'Próximas', 'Prova Perto', 'Prova Longe',
            'Realizadas', 'Prova Recente', 'Prova Antiga',
        ]);
    }

    public function test_sem_realizadas_nao_mostra_a_secao(): void
    {
        $this->inscricao('Prova Perto', now()->addWeek());

        $this->conta()->assertOk()->assertSee('Próximas')->assertDontSee('Realizadas');
    }

    public function test_so_as_inscricoes_do_atleta_e_do_organizador_do_site(): void
    {
        $this->inscricao('Minha Prova', now()->addWeek());
        $this->inscricao('Prova de Outro Atleta', now()->addWeek(), [], null, User::factory()->create());
        $this->inscricao('Prova de Outro Site', now()->addWeek(), [], Organizer::factory()->create());

        $this->conta()->assertOk()
            ->assertSee('Minha Prova')
            ->assertDontSee('Prova de Outro Atleta')
            ->assertDontSee('Prova de Outro Site');
    }

    public function test_pendente_futura_pode_pagar_e_cancelar(): void
    {
        $this->inscricao('Prova Perto', now()->addWeek(), ['status' => Subscription::PENDENTE]);

        $this->conta()->assertOk()
            ->assertSee('Aguardando pagamento')
            ->assertSee('Pagar Agora')
            ->assertSee(route('subscriptions.cancel'), false);
    }

    public function test_pendente_de_prova_passada_nao_oferece_pagar(): void
    {
        $this->inscricao('Prova Antiga', now()->subMonth(), ['status' => Subscription::PENDENTE]);

        $this->conta()->assertOk()
            ->assertSee('Realizadas')
            ->assertDontSee('Pagar Agora')
            ->assertDontSee(route('subscriptions.cancel'), false);
    }

    public function test_mostra_miniatura_e_nao_o_cartaz_grande(): void
    {
        $inscricao = $this->inscricao('Prova Perto', now()->addWeek());
        $inscricao->event->update(['banner_url' => 'x']);

        $this->conta()->assertOk()
            ->assertSee('insc__thumb', false)
            ->assertSee("eventos/{$inscricao->event_id}/card.jpg", false);
    }

    public function test_mostra_camiseta_e_equipe(): void
    {
        $this->inscricao('Prova Perto', now()->addWeek(), ['shirt_size' => 'INF8', 'team_name' => 'CORRE MOGI']);

        $this->conta()->assertOk()
            ->assertSee('Camiseta Infantil 8')
            ->assertSee('Equipe CORRE MOGI');
    }

    public function test_sem_inscricao_convida_a_ver_os_eventos(): void
    {
        $this->conta()->assertOk()
            ->assertSee('Você ainda não tem nenhuma inscrição.')
            ->assertDontSee('Próximas');
    }

    public function test_o_menu_leva_a_minha_conta(): void
    {
        $this->conta()->assertOk()->assertSee('href="/minha-conta"', false);
    }

    public function test_a_home_tem_o_atalho_da_conta_ao_lado_do_hamburguer(): void
    {
        foreach ([null, $this->atleta] as $quem) {
            $resposta = $quem ? $this->actingAs($quem)->get('/') : $this->get('/');

            $resposta->assertOk()->assertSeeInOrder([
                'class="cv-nav__conta"',
                'aria-label="Minha conta"',
                'class="cv-nav__toggle"',
            ], false);
        }
    }
}
