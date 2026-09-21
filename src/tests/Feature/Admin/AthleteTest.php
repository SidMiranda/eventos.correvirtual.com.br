<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A lista e a ficha de atletas.
 *
 * "Atleta do organizador" não existe no banco: a conta é da plataforma. O que
 * liga um atleta a este painel é ter ao menos uma inscrição num evento dele —
 * e é isso que estes testes protegem, nos dois sentidos: quem não tem some da
 * lista, e a ficha não mostra inscrição de evento alheio.
 */
class AthleteTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private Organizer $organizadorB;
    private User $adminA;
    private Event $eventoA;
    private Event $eventoB;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $this->organizadorA = Organizer::factory()->create();
        $this->organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizadorA->id,
        ]);

        $this->eventoA = Event::factory()->create(['organizer_id' => $this->organizadorA->id, 'title' => 'Corrida da Serra']);
        $this->eventoB = Event::factory()->create(['organizer_id' => $this->organizadorB->id, 'title' => 'Prova do Vizinho']);
    }

    private function inscrever(User $atleta, Event $evento, array $extra = []): Subscription
    {
        $modalidade = EventModality::factory()->create(['event_id' => $evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $evento->id]);

        return Subscription::factory()->create(array_merge([
            'event_id' => $evento->id,
            'user_id' => $atleta->id,
            'modality_id' => $modalidade->id,
            'kit_id' => $kit->id,
            'price' => 100,
            'list_price' => 100,
            'discount_amount' => 0,
            'status' => Subscription::PAGA,
        ], $extra));
    }

    /*
    |--------------------------------------------------------------------------
    | Acesso
    |--------------------------------------------------------------------------
    */

    public function test_deslogado_vai_para_o_login_e_atleta_leva_403(): void
    {
        $this->get('/admin/atletas')->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/atletas')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Lista
    |--------------------------------------------------------------------------
    */

    public function test_lista_so_quem_se_inscreveu_em_evento_meu(): void
    {
        $meu = User::factory()->create(['name' => 'Corredor Inscrito']);
        $this->inscrever($meu, $this->eventoA);

        $doVizinho = User::factory()->create(['name' => 'Corredor Do Vizinho']);
        $this->inscrever($doVizinho, $this->eventoB);

        // Cadastrado na plataforma, mas nunca se inscreveu em nada meu.
        User::factory()->create(['name' => 'Apenas Cadastrado']);

        $this->actingAs($this->adminA)
            ->get('/admin/atletas')
            ->assertOk()
            ->assertSee('Corredor Inscrito')
            ->assertDontSee('Corredor Do Vizinho')
            ->assertDontSee('Apenas Cadastrado');
    }

    public function test_conta_apenas_as_inscricoes_nos_meus_eventos(): void
    {
        $atleta = User::factory()->create(['name' => 'Corre Em Tudo']);
        $this->inscrever($atleta, $this->eventoA);
        $this->inscrever($atleta, $this->eventoB); // do vizinho: não conta

        $resposta = $this->actingAs($this->adminA)->get('/admin/atletas')->assertOk();

        $this->assertSame(1, $resposta->viewData('atletas')->first()->inscricoes_count);
    }

    public function test_busca_por_nome_email_e_cpf(): void
    {
        $joana = User::factory()->create(['name' => 'Joana Pereira', 'email' => 'joana@example.com', 'cpf' => '12345678901']);
        $outro = User::factory()->create(['name' => 'Outro Atleta', 'email' => 'outro@example.com']);
        $this->inscrever($joana, $this->eventoA);
        $this->inscrever($outro, $this->eventoA);

        foreach (['Joana', 'joana@example', '123.456.789-01'] as $termo) {
            $this->actingAs($this->adminA)
                ->get('/admin/atletas?busca=' . urlencode($termo))
                ->assertOk()
                ->assertSee('Joana Pereira')
                ->assertDontSee('Outro Atleta');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Ficha
    |--------------------------------------------------------------------------
    */

    public function test_ficha_mostra_o_cadastro_e_as_inscricoes_do_meu_evento(): void
    {
        $atleta = User::factory()->create([
            'name' => 'Joana Pereira',
            'email' => 'joana@example.com',
            'phone' => '19999990000',
        ]);
        $this->inscrever($atleta, $this->eventoA);

        $this->actingAs($this->adminA)
            ->get("/admin/atletas/{$atleta->id}")
            ->assertOk()
            ->assertSee('Joana Pereira')
            ->assertSee('joana@example.com')
            ->assertSee('19999990000')
            ->assertSee('Corrida da Serra');
    }

    public function test_ficha_nao_mostra_inscricao_em_evento_de_outro_organizador(): void
    {
        $atleta = User::factory()->create(['name' => 'Corre Em Tudo']);
        $this->inscrever($atleta, $this->eventoA);
        $this->inscrever($atleta, $this->eventoB);

        $this->actingAs($this->adminA)
            ->get("/admin/atletas/{$atleta->id}")
            ->assertOk()
            ->assertSee('Corrida da Serra')
            ->assertDontSee('Prova do Vizinho');
    }

    public function test_atleta_sem_inscricao_em_evento_meu_da_404(): void
    {
        // 404 e não 403: não é para descobrir que a pessoa existe na
        // plataforma — é o mesmo princípio do resto do painel.
        $doVizinho = User::factory()->create();
        $this->inscrever($doVizinho, $this->eventoB);

        $this->actingAs($this->adminA)->get("/admin/atletas/{$doVizinho->id}")->assertNotFound();

        $semNada = User::factory()->create();
        $this->actingAs($this->adminA)->get("/admin/atletas/{$semNada->id}")->assertNotFound();
    }
}
