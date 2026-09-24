<?php

namespace Tests\Feature;

use App\Models\AgeCategory;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\CategoriaEtaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A categoria etária do atleta, pelos dois critérios de idade do evento.
 *
 * `calendar_year` (padrão): ano do evento − ano de nascimento — quem faz 60
 * em dezembro conta 60 em janeiro. `exact_date`: anos completos na data do
 * evento. Em mais de uma categoria, a de maior desconto em reais.
 */
class CategoriaEtariaTest extends TestCase
{
    use RefreshDatabase;

    private Event $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $organizador = Organizer::factory()->create(['domain' => 'localhost']);
        // Prova em 15 de junho de 2027, para as fronteiras ficarem legíveis.
        $this->evento = Event::factory()->create([
            'organizer_id' => $organizador->id,
            'event_date' => Carbon::parse('2027-06-15 07:00'),
        ]);
    }

    private function atleta(?string $nascimento): User
    {
        return User::factory()->create(['birth_date' => $nascimento]);
    }

    private function categoria(array $extra): AgeCategory
    {
        return AgeCategory::factory()->create(array_merge(['event_id' => $this->evento->id], $extra));
    }

    /*
    |--------------------------------------------------------------------------
    | Os dois critérios
    |--------------------------------------------------------------------------
    */

    public function test_ano_calendario_ignora_mes_e_dia(): void
    {
        // Nasceu em 31/12/1967: em 2027 "faz" 60 mesmo antes do aniversário.
        $this->assertSame(60, $this->evento->idadeDe('1967-12-31'));
        // E quem nasceu em 01/01/1968 tem 59 o ano inteiro.
        $this->assertSame(59, $this->evento->idadeDe('1968-01-01'));
    }

    public function test_data_exata_conta_anos_completos_na_data_do_evento(): void
    {
        $this->evento->update(['age_criteria' => Event::CRITERIO_DATA_EXATA]);

        // Faz 60 em 31/12/2027 — na prova, em junho, ainda tem 59.
        $this->assertSame(59, $this->evento->idadeDe('1967-12-31'));
        // Fez 60 em 14/06/2027, um dia antes: 60.
        $this->assertSame(60, $this->evento->idadeDe('1967-06-14'));
        // Faz 60 no dia da prova: 60.
        $this->assertSame(60, $this->evento->idadeDe('1967-06-15'));
        // Faz 60 no dia seguinte: 59.
        $this->assertSame(59, $this->evento->idadeDe('1967-06-16'));
    }

    public function test_sem_nascimento_nao_ha_idade(): void
    {
        $this->assertNull($this->evento->idadeDe(null));
        $this->assertNull($this->evento->idadeDe(''));
    }

    /*
    |--------------------------------------------------------------------------
    | A escolha da categoria
    |--------------------------------------------------------------------------
    */

    public function test_cai_na_categoria_certa(): void
    {
        $idoso = $this->categoria(['name' => 'Idoso', 'min_age' => 60, 'max_age' => null]);
        $crianca = $this->categoria(['name' => 'Criança', 'min_age' => null, 'max_age' => 12, 'discount_value' => 30]);

        $this->assertTrue(CategoriaEtaria::para($this->evento, $this->atleta('1960-01-01'), 10000)->is($idoso));
        $this->assertTrue(CategoriaEtaria::para($this->evento, $this->atleta('2018-01-01'), 10000)->is($crianca));
        $this->assertNull(CategoriaEtaria::para($this->evento, $this->atleta('1990-01-01'), 10000));
    }

    public function test_fronteiras_da_faixa_sao_inclusivas(): void
    {
        $this->categoria(['name' => 'Juvenil', 'min_age' => 13, 'max_age' => 17]);

        // Ano-calendário: 2027 − 2014 = 13; 2027 − 2010 = 17; 2027 − 2009 = 18.
        $this->assertNotNull(CategoriaEtaria::para($this->evento, $this->atleta('2014-12-31'), 10000));
        $this->assertNotNull(CategoriaEtaria::para($this->evento, $this->atleta('2010-01-01'), 10000));
        $this->assertNull(CategoriaEtaria::para($this->evento, $this->atleta('2009-12-31'), 10000));
        $this->assertNull(CategoriaEtaria::para($this->evento, $this->atleta('2015-01-01'), 10000));
    }

    public function test_em_mais_de_uma_vale_a_de_maior_desconto_em_reais(): void
    {
        // Sobre R$ 100: 30% = R$ 30, R$ 40 fixos = R$ 40. Vence a fixa.
        $this->categoria(['name' => 'Sênior', 'min_age' => 60, 'discount_type' => AgeCategory::TIPO_PERCENTUAL, 'discount_value' => 30]);
        $fixa = $this->categoria(['name' => 'Idoso', 'min_age' => 60, 'discount_type' => AgeCategory::TIPO_VALOR, 'discount_value' => 40]);

        $this->assertTrue(CategoriaEtaria::para($this->evento, $this->atleta('1960-01-01'), 10000)->is($fixa));

        // Sobre R$ 200 a conta inverte: 30% = R$ 60 > R$ 40.
        $this->assertSame('Sênior', CategoriaEtaria::para($this->evento, $this->atleta('1960-01-01'), 20000)->name);
    }

    public function test_categoria_inativa_nao_conta(): void
    {
        $this->categoria(['min_age' => 60, 'active' => false]);

        $this->assertNull(CategoriaEtaria::para($this->evento, $this->atleta('1960-01-01'), 10000));
    }

    public function test_atleta_sem_nascimento_paga_integral(): void
    {
        $this->categoria(['min_age' => null, 'max_age' => null]);

        $this->assertNull(CategoriaEtaria::para($this->evento, $this->atleta(null), 10000));
        $this->assertNull(CategoriaEtaria::para($this->evento, null, 10000));
    }

    public function test_categoria_de_outro_evento_nao_conta(): void
    {
        $outro = Event::factory()->create(['organizer_id' => $this->evento->organizer_id]);
        AgeCategory::factory()->create(['event_id' => $outro->id, 'min_age' => 60]);

        $this->assertNull(CategoriaEtaria::para($this->evento, $this->atleta('1960-01-01'), 10000));
    }
}
