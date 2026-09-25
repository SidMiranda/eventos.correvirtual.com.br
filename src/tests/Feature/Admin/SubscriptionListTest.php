<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tela de inscrições do painel.
 *
 * Duas famílias de garantia: os filtros (que precisam bater com os totais
 * mostrados no topo) e o isolamento — inscrição em evento de outro organizador
 * não aparece aqui de jeito nenhum.
 *
 * Ver docs/specs/gestao-de-inscricoes.md.
 */
class SubscriptionListTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private Organizer $organizadorB;
    private User $adminA;
    private Event $eventoA;
    private Event $outroEventoA;
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
        $this->outroEventoA = Event::factory()->create(['organizer_id' => $this->organizadorA->id, 'title' => 'Corrida do Lago']);
        $this->eventoB = Event::factory()->create(['organizer_id' => $this->organizadorB->id, 'title' => 'Prova do Vizinho']);
    }

    private function inscricao(Event $evento, array $extra = [], array $usuario = []): Subscription
    {
        $modalidade = EventModality::factory()->create(['event_id' => $evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $evento->id]);

        return Subscription::factory()->create(array_merge([
            'event_id' => $evento->id,
            'user_id' => User::factory()->create($usuario)->id,
            'modality_id' => $modalidade->id,
            'kit_id' => $kit->id,
            'price' => 100,
            'list_price' => 100,
            'discount_amount' => 0,
            'status' => Subscription::PENDENTE,
        ], $extra));
    }

    private function lista(array $query = [])
    {
        return $this->actingAs($this->adminA)->get('/admin/inscricoes?' . http_build_query($query));
    }

    /*
    |--------------------------------------------------------------------------
    | Acesso e isolamento
    |--------------------------------------------------------------------------
    */

    public function test_deslogado_vai_para_o_login_e_atleta_leva_403(): void
    {
        $this->get('/admin/inscricoes')->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/inscricoes')->assertForbidden();
    }

    public function test_inscricao_de_outro_organizador_nao_aparece(): void
    {
        $this->inscricao($this->eventoA, [], ['name' => 'Atleta Meu']);
        $this->inscricao($this->eventoB, [], ['name' => 'Atleta Do Vizinho']);

        $this->lista()
            ->assertOk()
            ->assertSee('Atleta Meu')
            ->assertDontSee('Atleta Do Vizinho')
            ->assertDontSee('Prova do Vizinho');
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    public function test_filtra_por_evento(): void
    {
        $this->inscricao($this->eventoA, [], ['name' => 'Inscrito Na Serra']);
        $this->inscricao($this->outroEventoA, [], ['name' => 'Inscrito No Lago']);

        $this->lista(['evento' => $this->eventoA->id])
            ->assertOk()
            ->assertSee('Inscrito Na Serra')
            ->assertDontSee('Inscrito No Lago');
    }

    public function test_filtra_por_situacao(): void
    {
        $this->inscricao($this->eventoA, ['status' => Subscription::PAGA], ['name' => 'Ja Pagou']);
        $this->inscricao($this->eventoA, ['status' => Subscription::PENDENTE], ['name' => 'Ainda Nao Pagou']);
        $this->inscricao($this->eventoA, ['status' => Subscription::CANCELADA], ['name' => 'Desistiu Mesmo']);

        $this->lista(['situacao' => 'pagas'])->assertOk()
            ->assertSee('Ja Pagou')->assertDontSee('Ainda Nao Pagou')->assertDontSee('Desistiu Mesmo');

        $this->lista(['situacao' => 'pendentes'])->assertOk()
            ->assertSee('Ainda Nao Pagou')->assertDontSee('Ja Pagou');

        $this->lista(['situacao' => 'canceladas'])->assertOk()
            ->assertSee('Desistiu Mesmo')->assertDontSee('Ja Pagou');
    }

    public function test_filtra_so_as_com_desconto(): void
    {
        $cupom = Coupon::factory()->create(['event_id' => $this->eventoA->id, 'code' => 'CORRE10']);

        $this->inscricao($this->eventoA, [
            'coupon_id' => $cupom->id, 'discount_amount' => 10, 'price' => 90,
        ], ['name' => 'Usou Cupom']);
        $this->inscricao($this->eventoA, [], ['name' => 'Pagou Inteiro']);

        $this->lista(['desconto' => 1])
            ->assertOk()
            ->assertSee('Usou Cupom')
            ->assertDontSee('Pagou Inteiro');
    }

    public function test_busca_por_nome_email_e_cpf(): void
    {
        $this->inscricao($this->eventoA, [], [
            'name' => 'Joana Pereira', 'email' => 'joana@example.com', 'cpf' => '12345678901',
        ]);
        $this->inscricao($this->eventoA, [], ['name' => 'Outro Atleta', 'email' => 'outro@example.com']);

        $this->lista(['busca' => 'Joana'])->assertOk()->assertSee('Joana Pereira')->assertDontSee('Outro Atleta');
        $this->lista(['busca' => 'joana@example'])->assertOk()->assertSee('Joana Pereira')->assertDontSee('Outro Atleta');

        // O CPF está sem pontuação no banco, mas quem procura digita com.
        $this->lista(['busca' => '123.456.789-01'])->assertOk()->assertSee('Joana Pereira')->assertDontSee('Outro Atleta');
    }

    public function test_filtros_combinam(): void
    {
        $this->inscricao($this->eventoA, ['status' => Subscription::PAGA], ['name' => 'Paga Na Serra']);
        $this->inscricao($this->eventoA, ['status' => Subscription::PENDENTE], ['name' => 'Pendente Na Serra']);
        $this->inscricao($this->outroEventoA, ['status' => Subscription::PAGA], ['name' => 'Paga No Lago']);

        $this->lista(['evento' => $this->eventoA->id, 'situacao' => 'pagas'])
            ->assertOk()
            ->assertSee('Paga Na Serra')
            ->assertDontSee('Pendente Na Serra')
            ->assertDontSee('Paga No Lago');
    }

    public function test_situacao_desconhecida_na_url_mostra_todas(): void
    {
        // Filtro é conveniência: valor estranho na URL não vira erro na cara
        // de quem usa.
        $this->inscricao($this->eventoA, ['status' => Subscription::PAGA], ['name' => 'Ja Pagou']);
        $this->inscricao($this->eventoA, ['status' => Subscription::PENDENTE], ['name' => 'Ainda Nao Pagou']);

        $this->lista(['situacao' => 'invente-um-valor'])
            ->assertOk()
            ->assertSee('Ja Pagou')
            ->assertSee('Ainda Nao Pagou');
    }

    /*
    |--------------------------------------------------------------------------
    | Totais
    |--------------------------------------------------------------------------
    */

    public function test_os_totais_acompanham_o_filtro(): void
    {
        $cupom = Coupon::factory()->create(['event_id' => $this->eventoA->id]);

        // Na Serra: uma paga com desconto, uma paga inteira, uma pendente, uma cancelada.
        $this->inscricao($this->eventoA, ['status' => Subscription::PAGA, 'price' => 90, 'discount_amount' => 10, 'coupon_id' => $cupom->id]);
        $this->inscricao($this->eventoA, ['status' => Subscription::PAGA, 'price' => 100]);
        $this->inscricao($this->eventoA, ['status' => Subscription::PENDENTE, 'price' => 100]);
        $this->inscricao($this->eventoA, ['status' => Subscription::CANCELADA, 'price' => 100]);
        // No Lago, que não deve entrar quando o filtro for a Serra.
        $this->inscricao($this->outroEventoA, ['status' => Subscription::PAGA, 'price' => 500]);

        $resposta = $this->lista(['evento' => $this->eventoA->id])->assertOk();
        $totais = $resposta->viewData('totais');

        $this->assertSame(4, $totais['inscricoes']);
        $this->assertSame(2, $totais['pagas']);
        $this->assertSame(1, $totais['pendentes']);
        $this->assertSame(1, $totais['canceladas']);
        // Só o que entrou de verdade: 90 + 100. A pendente não é dinheiro.
        $this->assertSame(190.0, $totais['arrecadado']);
        $this->assertSame(10.0, $totais['descontos']);
    }

    public function test_a_paginacao_preserva_o_filtro(): void
    {
        foreach (range(1, 32) as $i) {
            $this->inscricao($this->eventoA, ['status' => Subscription::PAGA]);
        }

        $resposta = $this->lista(['evento' => $this->eventoA->id, 'situacao' => 'pagas'])->assertOk();

        $this->assertSame(32, $resposta->viewData('inscricoes')->total());
        $resposta->assertSee('evento=' . $this->eventoA->id, false);
        $resposta->assertSee('situacao=pagas', false);
    }

    public function test_sem_inscricao_a_tela_explica(): void
    {
        $this->lista()->assertOk()->assertSee('Nenhuma inscrição ainda.');
    }

    public function test_a_camiseta_infantil_aparece_por_nome(): void
    {
        $this->inscricao($this->eventoA, ['shirt_size' => 'INF10']);

        $this->lista()->assertOk()->assertSee('Camiseta Infantil 10');
    }
}
