<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O cronograma da prova, do cadastro até a página do evento.
 *
 * Até 2026-09-21 o bloco era texto fixo no Blade e mostrava os mesmos horários
 * em todo evento. Agora é um campo do cadastro, e o que importa aqui é que o
 * texto chegue à página **do jeito que foi digitado**: uma linha por horário,
 * com as linhas em branco que o organizador deixou.
 */
class CronogramaDoEventoTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Organizer::factory()->create(['domain' => 'localhost']);

        $this->admin = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizador->id,
        ]);
    }

    private function evento(array $sobrescreve = []): Event
    {
        return Event::factory()->create(array_merge([
            'organizer_id' => $this->organizador->id,
            'active' => true,
        ], $sobrescreve));
    }

    private function dadosValidos(array $sobrescreve = []): array
    {
        return array_merge([
            'title' => 'Corrida da Ponte',
            'description' => 'Uma corrida de teste.',
            'location' => 'Mogi Guaçu - SP',
            'event_date' => now()->addMonths(2)->format('Y-m-d\TH:i'),
            'registration_deadline' => now()->addMonth()->format('Y-m-d\TH:i'),
            'active' => 1,
        ], $sobrescreve);
    }

    public function test_o_cronograma_digitado_no_painel_e_gravado(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/eventos', $this->dadosValidos([
                'schedule' => "06h - Abertura\r\n07h - Largada",
            ]))
            ->assertRedirect('/admin/eventos');

        $this->assertSame(
            "06h - Abertura\r\n07h - Largada",
            Event::where('title', 'Corrida da Ponte')->first()->schedule
        );
    }

    public function test_o_cronograma_e_opcional(): void
    {
        // Nem toda prova tem a programação fechada na hora do cadastro.
        $this->actingAs($this->admin)
            ->post('/admin/eventos', $this->dadosValidos())
            ->assertRedirect('/admin/eventos')
            ->assertSessionHasNoErrors();
    }

    public function test_o_formulario_de_edicao_traz_o_cronograma_atual(): void
    {
        $evento = $this->evento(['schedule' => '07h - Largada única']);

        $this->actingAs($this->admin)
            ->get("/admin/eventos/{$evento->id}/edit")
            ->assertOk()
            ->assertSee('name="schedule"', false)
            ->assertSee('07h - Largada única');
    }

    public function test_a_pagina_do_evento_mostra_o_cronograma_com_as_quebras_de_linha(): void
    {
        $evento = $this->evento([
            'schedule' => "06h - Abertura do estacionamento\n07h - Largada 5km",
        ]);

        $resposta = $this->get('/event/' . $evento->id)->assertOk();

        $resposta->assertSee('Cronograma')
            // O <br> é o ponto do teste: sem ele as duas linhas viram um
            // parágrafo corrido e o atleta lê "estacionamento 07h - Largada".
            ->assertSee('06h - Abertura do estacionamento<br />', false)
            ->assertSee('07h - Largada 5km', false);
    }

    public function test_a_linha_em_branco_do_cronograma_sobrevive(): void
    {
        $evento = $this->evento([
            'schedule' => "SÁBADO\n07h - Retirada do kit\n\nDOMINGO\n06h - Largada",
        ]);

        $resposta = $this->get('/event/' . $evento->id)->assertOk();

        // Dois <br> seguidos: é a linha em branco que separa os dois dias.
        $this->assertStringContainsString("07h - Retirada do kit<br />\n<br />\nDOMINGO", $resposta->getContent());
    }

    public function test_evento_sem_cronograma_nao_mostra_o_bloco(): void
    {
        // Antes desta mudança, aqui apareceriam os horários fixos do Blade —
        // de uma prova que não é esta.
        $this->get('/event/' . $this->evento(['schedule' => null])->id)
            ->assertOk()
            ->assertDontSee('Cronograma');
    }

    public function test_o_cronograma_nao_injeta_html_na_pagina(): void
    {
        $evento = $this->evento([
            'schedule' => '07h - Largada <script>alert(1)</script>',
        ]);

        $this->get('/event/' . $evento->id)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_a_descricao_tambem_respeita_a_quebra_de_linha(): void
    {
        $evento = $this->evento([
            'description' => "Percurso plano.\nLargada no Campo da Brahma.",
        ]);

        $this->get('/event/' . $evento->id)
            ->assertOk()
            ->assertSee('Percurso plano.<br />', false);
    }

    public function test_a_descricao_continua_escapada(): void
    {
        $evento = $this->evento(['description' => 'Prova <b>boa</b>']);

        $this->get('/event/' . $evento->id)
            ->assertOk()
            ->assertDontSee('<b>boa</b>', false)
            ->assertSee('&lt;b&gt;boa&lt;/b&gt;', false);
    }
}
