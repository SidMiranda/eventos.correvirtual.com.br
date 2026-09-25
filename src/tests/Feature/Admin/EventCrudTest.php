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

class EventCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private Organizer $organizadorB;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);

        $this->organizadorA = Organizer::factory()->create(['name' => 'Organizador A']);
        $this->organizadorB = Organizer::factory()->create(['name' => 'Organizador B']);

        $this->adminA = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizadorA->id,
        ]);
    }

    private function dadosValidos(array $sobrescreve = []): array
    {
        return array_merge([
            'title' => 'Corrida de Primavera',
            'description' => 'Uma corrida de teste.',
            'location' => 'Mogi Guaçu - SP',
            'event_date' => now()->addMonths(2)->format('Y-m-d\TH:i'),
            'registration_deadline' => now()->addMonth()->format('Y-m-d\TH:i'),
            'banner_url' => null,
            'active' => 1,
        ], $sobrescreve);
    }

    /*
    |--------------------------------------------------------------------------
    | Caminho feliz
    |--------------------------------------------------------------------------
    */

    public function test_admin_cria_evento_no_proprio_organizador(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/eventos', $this->dadosValidos())
            ->assertRedirect('/admin/eventos');

        $evento = Event::where('title', 'Corrida de Primavera')->first();

        $this->assertNotNull($evento);
        // O organizador vem do usuário logado, nunca do formulário.
        $this->assertSame($this->organizadorA->id, $evento->organizer_id);
        $this->assertSame('corrida-de-primavera', $evento->slug);
    }

    public function test_organizador_do_formulario_e_ignorado(): void
    {
        // Mesmo mandando organizer_id na marra, o evento nasce no organizador
        // do usuário logado.
        $this->actingAs($this->adminA)
            ->post('/admin/eventos', $this->dadosValidos([
                'organizer_id' => $this->organizadorB->id,
            ]));

        $this->assertSame(
            $this->organizadorA->id,
            Event::where('title', 'Corrida de Primavera')->value('organizer_id')
        );
    }

    public function test_slug_ganha_sufixo_quando_ja_existe(): void
    {
        // events.slug é único no banco inteiro, não por organizador — dois
        // organizadores com o mesmo nome de evento colidiriam.
        Event::factory()->create([
            'organizer_id' => $this->organizadorB->id,
            'title' => 'Corrida de Primavera',
            'slug' => 'corrida-de-primavera',
        ]);

        $this->actingAs($this->adminA)->post('/admin/eventos', $this->dadosValidos());

        $this->assertSame(
            'corrida-de-primavera-2',
            Event::where('organizer_id', $this->organizadorA->id)->value('slug')
        );
    }

    public function test_admin_edita_evento_proprio(): void
    {
        $evento = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);

        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$evento->id}", $this->dadosValidos(['title' => 'Nome Novo']))
            ->assertRedirect('/admin/eventos');

        $this->assertSame('Nome Novo', $evento->fresh()->title);
    }

    public function test_listagem_mostra_so_eventos_do_proprio_organizador(): void
    {
        Event::factory()->create([
            'organizer_id' => $this->organizadorA->id,
            'title' => 'Evento do A',
        ]);
        Event::factory()->create([
            'organizer_id' => $this->organizadorB->id,
            'title' => 'Evento do B',
        ]);

        $this->actingAs($this->adminA)
            ->get('/admin/eventos')
            ->assertOk()
            ->assertSee('Evento do A')
            ->assertDontSee('Evento do B');
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento entre organizadores (BUG-005)
    |--------------------------------------------------------------------------
    */

    public function test_admin_nao_abre_edicao_de_evento_de_outro_organizador(): void
    {
        $doOutro = Event::factory()->create(['organizer_id' => $this->organizadorB->id]);

        // 404 e não 403: o admin de A não deve nem descobrir que o evento existe.
        $this->actingAs($this->adminA)
            ->get("/admin/eventos/{$doOutro->id}/edit")
            ->assertNotFound();
    }

    public function test_admin_nao_altera_evento_de_outro_organizador(): void
    {
        $doOutro = Event::factory()->create([
            'organizer_id' => $this->organizadorB->id,
            'title' => 'Intocável',
        ]);

        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$doOutro->id}", $this->dadosValidos(['title' => 'Invadido']))
            ->assertNotFound();

        $this->assertSame('Intocável', $doOutro->fresh()->title);
    }

    public function test_admin_nao_apaga_evento_de_outro_organizador(): void
    {
        $doOutro = Event::factory()->create(['organizer_id' => $this->organizadorB->id]);

        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$doOutro->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('events', ['id' => $doOutro->id]);
    }

    public function test_painel_conta_so_o_que_e_do_proprio_organizador(): void
    {
        Event::factory()->count(2)->create(['organizer_id' => $this->organizadorA->id]);
        Event::factory()->count(5)->create(['organizer_id' => $this->organizadorB->id]);

        $this->actingAs($this->adminA)
            ->get('/admin')
            ->assertOk()
            ->assertViewHas('resumo', fn ($resumo) => $resumo['eventos'] === 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Validação
    |--------------------------------------------------------------------------
    */

    public function test_campos_obrigatorios_sao_recusados_vazios(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/eventos', ['title' => '', 'description' => '', 'location' => ''])
            ->assertSessionHasErrors(['title', 'description', 'location', 'event_date', 'registration_deadline']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_prazo_de_inscricao_depois_do_evento_e_recusado(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/eventos', $this->dadosValidos([
                'event_date' => now()->addMonth()->format('Y-m-d\TH:i'),
                'registration_deadline' => now()->addMonths(2)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('registration_deadline');

        $this->assertDatabaseCount('events', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusão
    |--------------------------------------------------------------------------
    */

    public function test_evento_sem_inscricao_pode_ser_apagado(): void
    {
        $evento = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);

        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$evento->id}")
            ->assertRedirect('/admin/eventos');

        $this->assertDatabaseMissing('events', ['id' => $evento->id]);
    }

    public function test_evento_com_inscricao_nao_pode_ser_apagado(): void
    {
        // Apagar cascatearia para modalidades, kits e inscrições — inclusive
        // inscrição paga. O caminho certo nesse caso é desativar.
        $evento = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);
        $modalidade = EventModality::factory()->create(['event_id' => $evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $evento->id]);

        Subscription::create([
            'event_id' => $evento->id,
            'user_id' => User::factory()->create()->id,
            'modality_id' => $modalidade->id,
            'kit_id' => $kit->id,
            'price' => $kit->price,
            'status' => 'paid',
        ]);

        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$evento->id}")
            ->assertSessionHasErrors('evento');

        $this->assertDatabaseHas('events', ['id' => $evento->id]);
    }

    public function test_prazo_de_alteracoes_e_gravado_e_pode_ficar_vazio(): void
    {
        $prazo = now()->addWeeks(5)->startOfMinute();

        $this->actingAs($this->adminA)
            ->post('/admin/eventos', $this->dadosValidos(['changes_deadline' => $prazo->format('Y-m-d\TH:i')]))
            ->assertRedirect('/admin/eventos');

        $evento = Event::where('title', 'Corrida de Primavera')->first();
        $this->assertTrue($evento->changes_deadline->equalTo($prazo));

        // Vazio: vale o encerramento das inscrições.
        $this->actingAs($this->adminA)
            ->post('/admin/eventos', $this->dadosValidos(['title' => 'Sem Prazo']))
            ->assertRedirect('/admin/eventos');
        $semPrazo = Event::where('title', 'Sem Prazo')->first();
        $this->assertNull($semPrazo->changes_deadline);
        $this->assertTrue($semPrazo->prazoDeAlteracoes()->equalTo($semPrazo->registration_deadline));
    }

    public function test_prazo_de_alteracoes_depois_do_evento_e_recusado(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/eventos', $this->dadosValidos(['changes_deadline' => now()->addMonths(3)->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('changes_deadline');
    }
}
