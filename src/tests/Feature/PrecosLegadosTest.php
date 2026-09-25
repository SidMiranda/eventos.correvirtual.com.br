<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\EventPrice;
use App\Models\KitOption;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PrecosLegados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A migração dos preços: do kit para lote + grade.
 *
 * É a parte de maior risco de 2026-10-01: se errar, o preço muda na cara do
 * atleta no dia seguinte. O teste monta um evento como ele era e confere que
 * cada (modalidade, kit) custa exatamente o que o kit custava.
 */
class PrecosLegadosTest extends TestCase
{
    use RefreshDatabase;

    private Event $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->evento = Event::factory()->create(['organizer_id' => $organizador->id]);
    }

    public function test_cria_o_lote_1_aberto(): void
    {
        PrecosLegados::migrarEvento($this->evento);

        $lote = $this->evento->lots()->first();

        $this->assertNotNull($lote);
        $this->assertSame('Lote 1', $lote->name);
        $this->assertNull($lote->ends_at);
        $this->assertNull($lote->max_subscriptions);
        $this->assertTrue($lote->vigente());
    }

    public function test_cada_combinacao_custa_exatamente_o_que_o_kit_custava(): void
    {
        $cinco = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '5km']);
        $dez = EventModality::factory()->create(['event_id' => $this->evento->id, 'name' => '10km']);
        $basico = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Básico', 'price' => 59.90]);
        $completo = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Completo', 'price' => 89.90]);

        $feito = PrecosLegados::migrarEvento($this->evento);

        $lote = $this->evento->lots()->first();

        // 2 modalidades × 2 kits = 4 células, todas com o preço do kit.
        $this->assertSame(4, $feito['precos']);
        foreach ([$cinco, $dez] as $modalidade) {
            $this->assertSame('59.90', (string) EventPrice::de($modalidade, $basico, $lote)->price);
            $this->assertSame('89.90', (string) EventPrice::de($modalidade, $completo, $lote)->price);
        }
    }

    public function test_kit_fica_disponivel_em_todas_as_modalidades(): void
    {
        EventModality::factory()->count(3)->create(['event_id' => $this->evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $this->evento->id]);

        PrecosLegados::migrarEvento($this->evento);

        $this->assertSame(3, $kit->modalities()->count());
    }

    public function test_kit_ganha_os_dez_tamanhos_menos_o_sem_camiseta(): void
    {
        $com = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Com camiseta — Geral']);
        $sem = EventKit::factory()->create(['event_id' => $this->evento->id, 'name' => 'Sem camiseta — Geral']);

        PrecosLegados::migrarEvento($this->evento);

        $this->assertSame(PrecosLegados::tamanhosAdultos(), $com->fresh()->tamanhos());
        // O infantil (2026-09-25) é escolha do organizador: kit nenhum ganha sozinho.
        $this->assertEmpty(array_filter($com->fresh()->tamanhos(), fn ($t) => str_starts_with($t, 'INF')));
        $this->assertSame([], $sem->fresh()->tamanhos());
        $this->assertFalse($sem->fresh()->temTamanhos());
    }

    public function test_rodar_duas_vezes_nao_duplica_nada(): void
    {
        EventModality::factory()->count(2)->create(['event_id' => $this->evento->id]);
        EventKit::factory()->count(2)->create(['event_id' => $this->evento->id]);

        $primeira = PrecosLegados::migrarEvento($this->evento);
        $segunda = PrecosLegados::migrarEvento($this->evento);

        $this->assertSame(1, $primeira['lotes']);
        $this->assertSame(['lotes' => 0, 'vinculos' => 0, 'precos' => 0, 'tamanhos' => 0], $segunda);
        $this->assertSame(1, $this->evento->lots()->count());
        $this->assertSame(4, EventPrice::count());
    }

    public function test_nao_sobrescreve_o_que_o_organizador_ja_mexeu(): void
    {
        $modalidade = EventModality::factory()->create(['event_id' => $this->evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 59.90]);

        PrecosLegados::migrarEvento($this->evento);

        // O organizador mudou o preço na grade e tirou um tamanho.
        $lote = $this->evento->lots()->first();
        EventPrice::de($modalidade, $kit, $lote)->update(['price' => 75.00]);
        $kit->options()->where('value', 'EXG')->delete();

        PrecosLegados::migrarEvento($this->evento);

        $this->assertSame('75.00', (string) EventPrice::de($modalidade, $kit, $lote)->fresh()->price);
        $this->assertNotContains('EXG', $kit->fresh()->tamanhos());
    }

    public function test_inscricao_existente_fica_intocada(): void
    {
        $modalidade = EventModality::factory()->create(['event_id' => $this->evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'price' => 89.90]);
        $inscricao = Subscription::factory()->create([
            'event_id' => $this->evento->id,
            'user_id' => User::factory()->create()->id,
            'modality_id' => $modalidade->id,
            'kit_id' => $kit->id,
            'price' => 80.91,
            'list_price' => 89.90,
            'discount_amount' => 8.99,
        ]);

        PrecosLegados::migrarEvento($this->evento);

        $depois = $inscricao->fresh();
        $this->assertSame('80.91', (string) $depois->price);
        $this->assertSame('89.90', (string) $depois->list_price);
        $this->assertNull($depois->lot_id);
        $this->assertSame('0.00', (string) $depois->age_discount_amount);
    }

    public function test_evento_sem_kit_ganha_so_o_lote(): void
    {
        EventModality::factory()->create(['event_id' => $this->evento->id]);

        $feito = PrecosLegados::migrarEvento($this->evento);

        $this->assertSame(['lotes' => 1, 'vinculos' => 0, 'precos' => 0, 'tamanhos' => 0], $feito);
    }

    public function test_migrar_percorre_todos_os_eventos(): void
    {
        $outro = Event::factory()->create(['organizer_id' => $this->evento->organizer_id]);
        foreach ([$this->evento, $outro] as $evento) {
            EventModality::factory()->create(['event_id' => $evento->id]);
            EventKit::factory()->create(['event_id' => $evento->id]);
        }

        $feito = PrecosLegados::migrar();

        $this->assertSame(2, $feito['lotes']);
        $this->assertSame(2, $feito['precos']);
        $this->assertSame(20, $feito['tamanhos']);
        $this->assertSame(0, KitOption::where('attribute', '!=', KitOption::TAMANHO)->count());
    }
}
