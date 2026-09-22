<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O texto do bloco "Inscrição" da página do evento.
 *
 * Mesmo caso do cronograma: a frase "A inscrição dá direito ao kit exclusivo
 * do evento" estava chumbada no Blade e saía igual em toda prova. A diferença
 * é que o bloco **não some** quando o texto está vazio — o prazo de inscrição
 * é calculado de `registration_deadline` e continua sempre lá.
 */
class InformacoesDaInscricaoTest extends TestCase
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

    public function test_o_texto_digitado_no_painel_e_gravado(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/eventos', [
                'title' => 'Corrida da Ponte',
                'description' => 'Uma corrida de teste.',
                'registration_info' => "Inclui camiseta e medalha.\r\nTroca de tamanho até 20/10.",
                'location' => 'Mogi Guaçu - SP',
                'event_date' => now()->addMonths(2)->format('Y-m-d\TH:i'),
                'registration_deadline' => now()->addMonth()->format('Y-m-d\TH:i'),
                'active' => 1,
            ])
            ->assertRedirect('/admin/eventos');

        $this->assertSame(
            "Inclui camiseta e medalha.\r\nTroca de tamanho até 20/10.",
            Event::where('title', 'Corrida da Ponte')->first()->registration_info
        );
    }

    public function test_o_formulario_de_edicao_traz_o_texto_atual(): void
    {
        $evento = $this->evento(['registration_info' => 'Kit retirado na loja.']);

        $this->actingAs($this->admin)
            ->get("/admin/eventos/{$evento->id}/edit")
            ->assertOk()
            ->assertSee('name="registration_info"', false)
            ->assertSee('Kit retirado na loja.');
    }

    public function test_a_pagina_mostra_o_texto_com_as_quebras_de_linha(): void
    {
        $evento = $this->evento([
            'registration_info' => "Inclui camiseta e medalha.\nTroca de tamanho até 20/10.",
        ]);

        $this->get('/event/' . $evento->id)
            ->assertOk()
            ->assertSee('Inscrição')
            ->assertSee('Inclui camiseta e medalha.<br />', false)
            ->assertSee('Troca de tamanho até 20/10.', false);
    }

    public function test_a_linha_em_branco_sobrevive(): void
    {
        $evento = $this->evento([
            'registration_info' => "O que inclui\nCamiseta, medalha e número de peito.\n\nRetirada do kit\nNa loja, com documento.",
        ]);

        $resposta = $this->get('/event/' . $evento->id)->assertOk();

        $this->assertStringContainsString(
            "número de peito.<br />\n<br />\nRetirada do kit",
            $resposta->getContent()
        );
    }

    public function test_sem_texto_o_bloco_continua_de_pe_com_o_prazo(): void
    {
        // Diferente do cronograma: o prazo é calculado e não depende do que foi
        // digitado, então o bloco não pode sumir junto com o texto livre.
        $evento = $this->evento([
            'registration_info' => null,
            'registration_deadline' => now()->addMonth()->setTime(23, 59),
        ]);

        $this->get('/event/' . $evento->id)
            ->assertOk()
            ->assertSee('Inscrição')
            ->assertSee('Encerramento das inscrições')
            // A frase que era fixa no Blade não sobreviveu à mudança.
            ->assertDontSee('dá direito ao kit exclusivo do evento');
    }

    public function test_o_texto_e_opcional_no_cadastro(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/eventos', [
                'title' => 'Corrida sem detalhes',
                'description' => 'Uma corrida de teste.',
                'location' => 'Mogi Guaçu - SP',
                'event_date' => now()->addMonths(2)->format('Y-m-d\TH:i'),
                'registration_deadline' => now()->addMonth()->format('Y-m-d\TH:i'),
                'active' => 1,
            ])
            ->assertRedirect('/admin/eventos')
            ->assertSessionHasNoErrors();
    }

    public function test_o_texto_nao_injeta_html_na_pagina(): void
    {
        $evento = $this->evento([
            'registration_info' => 'Kit <script>alert(1)</script>',
        ]);

        $this->get('/event/' . $evento->id)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
