<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use App\Support\FiltroDeInscricoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Atleta PCD (2026-09-25): um checkbox no cadastro, consultado no painel e
 * presente nos relatórios. Só informação — nenhuma regra depende disto.
 */
class AtletaPcdTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $admin;
    private Event $evento;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Organizer::factory()->create(['domain' => 'localhost']);
        $this->organizador = Organizer::factory()->create();
        $this->admin = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $this->organizador->id]);
        $this->evento = Event::factory()->create(['organizer_id' => $this->organizador->id, 'title' => 'Corrida da Serra']);
    }

    private function cadastro(array $extra = [])
    {
        $cidade = City::factory()->chamada('Mogi Guaçu')->create();

        return $this->post('/register', array_merge([
            'name' => 'Fulano de Tal',
            'birth_date' => now()->subYears(30)->format('Y-m-d'),
            'sex' => 'male',
            'phone' => '(19) 99999-9999',
            'email' => 'fulano@teste.com',
            'cpf' => '390.533.447-05',
            'password' => 'segredo123',
            'cidade' => $cidade->nomeCompleto(),
            'city_id' => $cidade->id,
        ], $extra));
    }

    private function inscrito(string $nome, bool $pcd): Subscription
    {
        return Subscription::factory()->create([
            'event_id' => $this->evento->id,
            'user_id' => User::factory()->create(['name' => $nome, 'is_pcd' => $pcd])->id,
            'modality_id' => EventModality::factory()->create(['event_id' => $this->evento->id])->id,
            'kit_id' => EventKit::factory()->create(['event_id' => $this->evento->id])->id,
            'status' => Subscription::PAGA,
        ]);
    }

    public function test_o_cadastro_tem_o_checkbox(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="is_pcd"', false)
            ->assertSee('Sou PCD');
    }

    public function test_marcado_grava_pcd(): void
    {
        $this->cadastro(['is_pcd' => '1'])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'fulano@teste.com')->first()->is_pcd);
    }

    public function test_desmarcado_grava_nao_pcd(): void
    {
        // Checkbox desmarcado não vem no POST.
        $this->cadastro()->assertSessionHasNoErrors();

        $this->assertFalse(User::where('email', 'fulano@teste.com')->first()->is_pcd);
    }

    public function test_a_ficha_do_atleta_mostra_pcd(): void
    {
        $sim = $this->inscrito('Maria PCD', true);
        $nao = $this->inscrito('João Sem', false);

        $this->actingAs($this->admin)->get("/admin/atletas/{$sim->user_id}")
            ->assertOk()->assertSeeInOrder(['PCD', 'Sim']);
        $this->actingAs($this->admin)->get("/admin/atletas/{$nao->user_id}")
            ->assertOk()->assertSeeInOrder(['PCD', 'Não']);
    }

    public function test_a_lista_de_atletas_marca_quem_e_pcd(): void
    {
        $this->inscrito('Maria PCD', true);

        $this->actingAs($this->admin)->get('/admin/atletas')
            ->assertOk()
            ->assertSee('badge badge-blue-soft text-blue ml-1">PCD', false);
    }

    public function test_a_lista_de_inscricoes_filtra_so_pcd(): void
    {
        $this->inscrito('Maria PCD', true);
        $this->inscrito('João Sem', false);

        $this->actingAs($this->admin)->get('/admin/inscricoes?pcd=1')
            ->assertOk()
            ->assertSee('Maria PCD')
            ->assertDontSee('João Sem');

        $this->actingAs($this->admin)->get('/admin/inscricoes')
            ->assertSee('Maria PCD')
            ->assertSee('João Sem');
    }

    public function test_o_filtro_pcd_viaja_para_o_relatorio(): void
    {
        $filtro = FiltroDeInscricoes::daRequisicao(Request::create('/', 'GET', ['pcd' => '1', 'evento' => $this->evento->id]));

        $this->assertTrue($filtro->ativo());
        $this->assertSame(1, $filtro->paraUrl()['pcd']);
        $this->assertStringContainsString('só PCD', $filtro->descricao());
    }

    public function test_o_pdf_tem_a_coluna_pcd(): void
    {
        $this->inscrito('Maria PCD', true);
        $this->inscrito('João Sem', false);

        $html = view('admin.subscriptions.pdf', [
            'evento' => $this->evento,
            'organizador' => $this->organizador,
            'inscricoes' => Subscription::with(['user', 'modality', 'kit'])->get(),
            'totais' => ['inscricoes' => 2, 'arrecadado' => 0, 'pagas' => 2, 'pendentes' => 0, 'canceladas' => 0, 'descontos' => 0],
            'filtro' => FiltroDeInscricoes::daRequisicao(Request::create('/', 'GET')),
        ])->render();

        $this->assertStringContainsString('>PCD</th>', $html);
        $this->assertSame(1, substr_count($html, '<td class="centro">Sim</td>'));

        // E o endpoint de verdade continua gerando o PDF com o filtro.
        $this->actingAs($this->admin)->get("/admin/inscricoes/pdf?evento={$this->evento->id}&pcd=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
