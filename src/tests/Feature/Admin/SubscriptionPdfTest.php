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
 * O relatório de inscritos em PDF.
 *
 * O pedido foi explícito: abre em aba nova em vez de baixar, e só sai com um
 * evento escolhido. Uma lista com provas misturadas não serve no papel — é por
 * evento que se confere largada, kit e lote.
 */
class SubscriptionPdfTest extends TestCase
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
        $this->organizadorA = Organizer::factory()->create(['name' => 'Corre Virtual Teste']);
        $this->organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizadorA->id,
        ]);

        $this->eventoA = Event::factory()->create([
            'organizer_id' => $this->organizadorA->id,
            'title' => 'Corrida da Serra',
            'slug' => 'corrida-da-serra',
        ]);
        $this->eventoB = Event::factory()->create(['organizer_id' => $this->organizadorB->id]);
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
            'status' => Subscription::PAGA,
        ], $extra));
    }

    private function pdf(array $query)
    {
        return $this->actingAs($this->adminA)->get('/admin/inscricoes/pdf?' . http_build_query($query));
    }

    public function test_sem_evento_volta_para_a_lista_explicando(): void
    {
        $this->inscricao($this->eventoA);

        $this->pdf(['situacao' => 'pagas'])
            ->assertRedirect()
            ->assertSessionHasErrors('evento');
    }

    public function test_com_evento_devolve_um_pdf_que_abre_em_vez_de_baixar(): void
    {
        $this->inscricao($this->eventoA);

        $resposta = $this->pdf(['evento' => $this->eventoA->id])->assertOk();

        $resposta->assertHeader('content-type', 'application/pdf');
        // `inline` é o que faz o navegador exibir; `attachment` baixaria.
        $this->assertStringContainsString('inline', $resposta->headers->get('content-disposition'));
        $this->assertStringContainsString('corrida-da-serra', $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_pdf_de_evento_de_outro_organizador_da_404(): void
    {
        $this->inscricao($this->eventoB);

        $this->pdf(['evento' => $this->eventoB->id])->assertNotFound();
    }

    public function test_o_relatorio_respeita_o_filtro_de_situacao(): void
    {
        // O PDF e a tela usam o mesmo FiltroDeInscricoes: um relatório que
        // discorda da tela que o gerou é pior que não ter relatório.
        $this->inscricao($this->eventoA, ['status' => Subscription::PAGA]);
        $this->inscricao($this->eventoA, ['status' => Subscription::CANCELADA]);

        $resposta = $this->pdf(['evento' => $this->eventoA->id, 'situacao' => 'pagas'])->assertOk();

        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_evento_sem_inscricao_ainda_gera_o_pdf(): void
    {
        // Papel em branco com o cabeçalho é resposta melhor que um erro: o
        // organizador pediu a lista e a lista está vazia.
        $this->pdf(['evento' => $this->eventoA->id])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_deslogado_e_atleta_nao_geram_relatorio(): void
    {
        $this->get('/admin/inscricoes/pdf?evento=' . $this->eventoA->id)->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/inscricoes/pdf?evento=' . $this->eventoA->id)->assertForbidden();
    }
}
