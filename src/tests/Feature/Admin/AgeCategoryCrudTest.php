<?php

namespace Tests\Feature\Admin;

use App\Models\AgeCategory;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categorias etárias no painel: cadastro e isolamento por organizador.
 */
class AgeCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;
    private Event $eventoDoA;
    private Event $eventoDoB;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $organizadorA = Organizer::factory()->create();
        $organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $organizadorA->id]);
        $this->eventoDoA = Event::factory()->create(['organizer_id' => $organizadorA->id, 'event_date' => now()->addMonths(2)]);
        $this->eventoDoB = Event::factory()->create(['organizer_id' => $organizadorB->id, 'event_date' => now()->addMonths(2)]);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Idoso',
            'min_age' => 60,
            'max_age' => '',
            'discount_type' => AgeCategory::TIPO_PERCENTUAL,
            'discount_value' => 50,
            'active' => 1,
        ], $extra);
    }

    public function test_cria_categoria_no_evento_proprio(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/categorias", $this->dados())
            ->assertRedirect("/admin/eventos/{$this->eventoDoA->id}/categorias");

        $categoria = $this->eventoDoA->ageCategories()->first();
        $this->assertSame('Idoso', $categoria->name);
        $this->assertSame(60, $categoria->min_age);
        $this->assertNull($categoria->max_age);
        $this->assertSame('60 anos ou mais', $categoria->faixaPorExtenso());
        $this->assertSame('50%', $categoria->descontoFormatado());
    }

    public function test_categoria_sem_nenhuma_idade_e_recusada(): void
    {
        // "Qualquer idade" é desconto para todo mundo — para isso existe cupom.
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/categorias", $this->dados(['min_age' => '', 'max_age' => '']))
            ->assertSessionHasErrors('min_age');
    }

    public function test_maxima_menor_que_minima_e_recusada(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/categorias", $this->dados(['min_age' => 60, 'max_age' => 50]))
            ->assertSessionHasErrors('max_age');
    }

    public function test_percentual_acima_de_100_e_recusado_e_valor_fixo_nao(): void
    {
        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/categorias", $this->dados(['discount_value' => 150]))
            ->assertSessionHasErrors('discount_value');

        $this->actingAs($this->adminA)
            ->post("/admin/eventos/{$this->eventoDoA->id}/categorias", $this->dados([
                'discount_type' => AgeCategory::TIPO_VALOR, 'discount_value' => 150,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_edita_e_lista(): void
    {
        $categoria = AgeCategory::factory()->create(['event_id' => $this->eventoDoA->id]);

        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->eventoDoA->id}/categorias/{$categoria->id}", $this->dados(['name' => 'Melhor idade', 'discount_value' => 40]))
            ->assertRedirect();

        $this->assertSame('Melhor idade', $categoria->fresh()->name);

        $this->actingAs($this->adminA)
            ->get("/admin/eventos/{$this->eventoDoA->id}/categorias")
            ->assertOk()
            ->assertSee('Melhor idade')
            ->assertSee('40%');
    }

    public function test_apaga_categoria_sem_uso(): void
    {
        $categoria = AgeCategory::factory()->create(['event_id' => $this->eventoDoA->id]);

        $this->actingAs($this->adminA)
            ->delete("/admin/eventos/{$this->eventoDoA->id}/categorias/{$categoria->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('age_categories', ['id' => $categoria->id]);
    }

    public function test_nao_cria_nem_edita_em_evento_de_outro_organizador(): void
    {
        $doB = AgeCategory::factory()->create(['event_id' => $this->eventoDoB->id, 'name' => 'Do B']);

        $this->actingAs($this->adminA)->get("/admin/eventos/{$this->eventoDoB->id}/categorias")->assertNotFound();
        $this->actingAs($this->adminA)->post("/admin/eventos/{$this->eventoDoB->id}/categorias", $this->dados())->assertNotFound();
        $this->actingAs($this->adminA)->put("/admin/eventos/{$this->eventoDoB->id}/categorias/{$doB->id}", $this->dados(['name' => 'Invadida']))->assertNotFound();
        $this->actingAs($this->adminA)->put("/admin/eventos/{$this->eventoDoA->id}/categorias/{$doB->id}", $this->dados(['name' => 'Invadida']))->assertNotFound();
        $this->actingAs($this->adminA)->delete("/admin/eventos/{$this->eventoDoB->id}/categorias/{$doB->id}")->assertNotFound();

        $this->assertSame('Do B', $doB->fresh()->name);
        $this->assertSame(1, $this->eventoDoB->ageCategories()->count());
    }

    public function test_o_criterio_de_idade_e_salvo_no_evento(): void
    {
        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->eventoDoA->id}", [
                'title' => $this->eventoDoA->title,
                'description' => 'x',
                'location' => 'y',
                'event_date' => now()->addMonths(2)->format('Y-m-d\TH:i'),
                'registration_deadline' => now()->addMonth()->format('Y-m-d\TH:i'),
                'age_criteria' => Event::CRITERIO_DATA_EXATA,
                'active' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(Event::CRITERIO_DATA_EXATA, $this->eventoDoA->fresh()->age_criteria);

        // Sem o campo (formulário antigo), fica no padrão.
        $this->actingAs($this->adminA)
            ->put("/admin/eventos/{$this->eventoDoA->id}", [
                'title' => $this->eventoDoA->title,
                'description' => 'x',
                'location' => 'y',
                'event_date' => now()->addMonths(2)->format('Y-m-d\TH:i'),
                'registration_deadline' => now()->addMonth()->format('Y-m-d\TH:i'),
                'active' => 1,
            ]);

        $this->assertSame(Event::CRITERIO_ANO_CALENDARIO, $this->eventoDoA->fresh()->age_criteria);
    }
}
