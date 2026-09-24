<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qual lote está vendendo agora.
 *
 * Resolvido pelo sistema — não existe "ativar lote". Pela data, pela
 * quantidade (se houver limite) e, em empate, pela ordem. Ver ADR 0007.
 */
class LoteVigenteTest extends TestCase
{
    use RefreshDatabase;

    private Event $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);
        $this->evento = Event::factory()->create([
            'organizer_id' => $organizador->id,
            'event_date' => now()->addMonths(2),
            'registration_deadline' => now()->addMonth(),
            'active' => true,
        ]);
    }

    private function lote(array $extra = []): EventLot
    {
        return EventLot::factory()->create(array_merge(['event_id' => $this->evento->id], $extra));
    }

    public function test_sem_lote_nao_ha_vigente_e_nao_aceita_inscricao(): void
    {
        $this->assertNull($this->evento->loteVigente());
        // As datas do evento estão abertas; é o lote que fecha.
        $this->assertTrue($this->evento->inscricoesAbertas());
        $this->assertFalse($this->evento->aceitaInscricao());
    }

    public function test_lote_aberto_e_o_vigente(): void
    {
        $lote = $this->lote();

        $this->assertTrue($this->evento->loteVigente()->is($lote));
        $this->assertTrue($this->evento->aceitaInscricao());
    }

    public function test_lote_futuro_nao_vende_ainda(): void
    {
        $futuro = $this->lote(['starts_at' => now()->addDay()]);

        $this->assertNull($this->evento->loteVigente());
        $this->assertTrue($this->evento->proximoLote()->is($futuro));
    }

    public function test_lote_encerrado_nao_vende_mais(): void
    {
        $this->lote(['starts_at' => now()->subWeeks(2), 'ends_at' => now()->subMinute()]);

        $this->assertNull($this->evento->loteVigente());
        $this->assertNull($this->evento->proximoLote());
    }

    public function test_a_virada_e_automatica_pela_data(): void
    {
        $um = $this->lote(['name' => 'Lote 1', 'position' => 0, 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour()]);
        $dois = $this->lote(['name' => 'Lote 2', 'position' => 1, 'starts_at' => now()->addHour()]);

        $this->assertTrue($this->evento->loteVigente()->is($um));

        // Duas horas depois, sem ninguém tocar em nada.
        $this->travel(2)->hours();

        $this->assertTrue($this->evento->fresh()->loteVigente()->is($dois));
    }

    public function test_a_virada_e_automatica_pela_quantidade(): void
    {
        $um = $this->lote(['name' => 'Lote 1', 'position' => 0, 'max_subscriptions' => 2]);
        $dois = $this->lote(['name' => 'Lote 2', 'position' => 1]);

        $this->assertTrue($this->evento->loteVigente()->is($um));

        $this->inscricoesNoLote($um, 2);

        $this->assertTrue($um->fresh()->esgotado());
        $this->assertTrue($this->evento->fresh()->loteVigente()->is($dois));
    }

    public function test_inscricao_cancelada_nao_conta_para_o_limite(): void
    {
        $lote = $this->lote(['max_subscriptions' => 1]);

        $this->inscricoesNoLote($lote, 1, Subscription::CANCELADA);

        $this->assertFalse($lote->fresh()->esgotado());
        $this->assertTrue($this->evento->loteVigente()->is($lote));
    }

    public function test_dois_vigentes_ao_mesmo_tempo_vence_a_menor_posicao(): void
    {
        $promocional = $this->lote(['name' => 'Promocional', 'position' => 0]);
        $this->lote(['name' => 'Lote 1', 'position' => 1]);

        $this->assertTrue($this->evento->loteVigente()->is($promocional));
    }

    public function test_lote_inativo_nao_conta(): void
    {
        $this->lote(['active' => false]);

        $this->assertNull($this->evento->loteVigente());
    }

    public function test_esgotado_sem_sucessor_fecha_as_inscricoes(): void
    {
        $unico = $this->lote(['max_subscriptions' => 1]);
        $this->inscricoesNoLote($unico, 1);

        $this->assertNull($this->evento->fresh()->loteVigente());
        $this->assertFalse($this->evento->fresh()->aceitaInscricao());
    }

    private function inscricoesNoLote(EventLot $lote, int $quantas, string $status = Subscription::PAGA): void
    {
        $modalidade = EventModality::factory()->create(['event_id' => $this->evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $this->evento->id]);

        for ($i = 0; $i < $quantas; $i++) {
            Subscription::factory()->create([
                'event_id' => $this->evento->id,
                'user_id' => User::factory()->create()->id,
                'modality_id' => $modalidade->id,
                'kit_id' => $kit->id,
                'lot_id' => $lote->id,
                'status' => $status,
                'price' => 50,
            ]);
        }
    }
}
