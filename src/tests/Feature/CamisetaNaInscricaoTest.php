<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEventoParaVenda;
use Tests\TestCase;

/**
 * O tamanho da camiseta escolhido na inscrição.
 *
 * Uma coluna só, por decisão do dono (2026-09-22): a tabela de medidas é a
 * mesma para todos os eventos hoje. O fluxo certo — cada evento declarando o
 * que oferece, e o tamanho sendo exigido apenas nos kits que têm camiseta —
 * fica para depois.
 *
 * Desde a fatia 2 (ADR 0007) o tamanho vem do KIT: obrigatório quando o kit
 * oferece tamanhos, ausente quando não oferece (o kit "sem camiseta").
 */
class CamisetaNaInscricaoTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEventoParaVenda;

    private Event $evento;
    private EventModality $modalidade;
    private EventKit $kit;
    private User $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);

        $this->evento = Event::factory()->create([
            'organizer_id' => $organizador->id,
            'active' => true,
            'event_date' => now()->addMonth(),
            'registration_deadline' => now()->addWeeks(2),
        ]);

        $this->modalidade = EventModality::factory()->create(['event_id' => $this->evento->id]);
        $this->kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
        $this->prepararParaVenda($this->evento, comTamanhos: true);
        $this->atleta = User::factory()->create(['role' => 'athlete']);
    }

    private function inscrever(array $extra = [])
    {
        return $this->actingAs($this->atleta)->post("/subscribe/event/{$this->evento->id}", array_merge([
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
        ], $extra));
    }

    private function inscricao(): ?Subscription
    {
        return Subscription::where('user_id', $this->atleta->id)->first();
    }

    public function test_o_tamanho_escolhido_e_gravado(): void
    {
        $this->inscrever(['camiseta' => 'GG'])->assertSessionHasNoErrors();

        $this->assertSame('GG', $this->inscricao()->shirt_size);
    }

    public function test_baby_look_tambem(): void
    {
        $this->inscrever(['camiseta' => 'BLM'])->assertSessionHasNoErrors();

        $this->assertSame('BLM', $this->inscricao()->shirt_size);
    }

    public function test_kit_com_tamanhos_exige_o_tamanho(): void
    {
        // Desde a fatia 2 (ADR 0007) o tamanho é obrigatório quando o kit tem.
        $this->inscrever()->assertSessionHasErrors('camiseta');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_kit_sem_tamanhos_nao_pede(): void
    {
        $semCamiseta = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Sem camiseta', 'price' => 60]);
        $this->prepararParaVenda($this->evento, comTamanhos: true);

        $this->inscrever(['kit_id' => $semCamiseta->id])->assertSessionHasNoErrors();

        $this->assertNull($this->inscricao()->shirt_size);
    }

    public function test_tamanho_fora_da_lista_e_recusado(): void
    {
        // A lista é fechada: o que vier de fora dela é formulário adulterado.
        foreach (['XPTO', 'p', 'XXG', '1'] as $invalido) {
            $this->inscrever(['camiseta' => $invalido])->assertSessionHasErrors('camiseta');
        }

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_todos_os_tamanhos_da_tabela_sao_aceitos(): void
    {
        foreach (Subscription::tamanhosDeCamiseta() as $tamanho) {
            $atleta = User::factory()->create(['role' => 'athlete']);

            $this->actingAs($atleta)
                ->post("/subscribe/event/{$this->evento->id}", [
                    'modality_id' => $this->modalidade->id,
                    'kit_id' => $this->kit->id,
                    'camiseta' => $tamanho,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame(
                $tamanho,
                Subscription::where('user_id', $atleta->id)->first()->shirt_size,
                "Tamanho {$tamanho} não foi gravado."
            );
        }
    }

    public function test_a_medida_por_extenso_acompanha_o_tamanho(): void
    {
        // É o que o organizador lê para conferir o kit.
        $this->inscrever(['camiseta' => 'G']);
        $this->assertSame('G (56 x 73 cm)', $this->inscricao()->camisetaPorExtenso());

        $this->inscricao()->update(['shirt_size' => 'BLGG']);
        $this->assertSame('BLGG (48 x 68 cm)', $this->inscricao()->fresh()->camisetaPorExtenso());

        $this->inscricao()->update(['shirt_size' => null]);
        $this->assertNull($this->inscricao()->fresh()->camisetaPorExtenso());
    }

    public function test_reinscrever_depois_de_cancelar_atualiza_o_tamanho(): void
    {
        $this->inscrever(['camiseta' => 'P']);

        $this->inscricao()->update(['status' => Subscription::CANCELADA, 'cancelled_at' => now()]);

        $this->inscrever(['camiseta' => 'EXG']);

        $this->assertSame('EXG', $this->inscricao()->shirt_size);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_o_formulario_traz_a_tabela_de_medidas_abaixo_do_kit(): void
    {
        $html = $this->actingAs($this->atleta)
            ->get("/subscribe/event/{$this->evento->id}")
            ->assertOk()
            ->assertSee('name="camiseta"', false)
            ->assertSee('51 x 67 cm')
            ->assertSee('48 x 68 cm')
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'name="camiseta"'),
            strpos($html, 'name="kit_id"'),
            'A camiseta deve vir depois do kit.'
        );
    }
}
