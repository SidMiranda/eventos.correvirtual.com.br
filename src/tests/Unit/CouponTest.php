<?php

namespace Tests\Unit;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As regras do cupom, sem passar pela tela.
 *
 * O que está aqui é o que a fatia seguinte (aplicar o cupom na inscrição) vai
 * chamar: o estado do cupom, quanto ele abate e, principalmente, o consumo do
 * limite sob concorrência.
 *
 * Ver docs/specs/cupons-de-desconto.md.
 */
class CouponTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Código
    |--------------------------------------------------------------------------
    */

    public function test_codigo_e_sempre_guardado_em_maiusculo_e_sem_espacos(): void
    {
        $cupom = Coupon::factory()->create(['code' => '  corre10 ']);

        $this->assertSame('CORRE10', $cupom->code);
        $this->assertSame('CORRE10', $cupom->fresh()->code);
    }

    /*
    |--------------------------------------------------------------------------
    | Estado
    |--------------------------------------------------------------------------
    */

    public function test_cupom_que_expira_hoje_ainda_vale(): void
    {
        // A data é o último dia, não o primeiro dia depois.
        $cupom = Coupon::factory()->create(['expires_at' => today()->toDateString()]);

        $this->assertFalse($cupom->vencido());
        $this->assertFalse($cupom->encerrado());
        $this->assertTrue($cupom->validoParaUso());
        $this->assertSame('Ativo', $cupom->situacao());
    }

    public function test_cupom_de_ontem_esta_vencido(): void
    {
        $cupom = Coupon::factory()->vencido()->create();

        $this->assertTrue($cupom->vencido());
        $this->assertTrue($cupom->encerrado());
        $this->assertFalse($cupom->validoParaUso());
        $this->assertSame('Vencido', $cupom->situacao());
    }

    public function test_cupom_sem_saldo_esta_esgotado(): void
    {
        $cupom = Coupon::factory()->esgotado()->create(['total_quantity' => 3]);

        $this->assertTrue($cupom->esgotado());
        $this->assertTrue($cupom->encerrado());
        $this->assertFalse($cupom->validoParaUso());
        $this->assertSame(0, $cupom->restantes());
        $this->assertSame('Esgotado', $cupom->situacao());
    }

    public function test_cupom_desativado_na_mao_nao_vale_mas_nao_esta_encerrado(): void
    {
        // A diferença importa: desativado volta atrás, encerrado não.
        $cupom = Coupon::factory()->usado(2)->create(['active' => false]);

        $this->assertFalse($cupom->validoParaUso());
        $this->assertFalse($cupom->encerrado());
        $this->assertSame('Inativo', $cupom->situacao());
    }

    /*
    |--------------------------------------------------------------------------
    | Desconto
    |--------------------------------------------------------------------------
    */

    public function test_desconto_percentual(): void
    {
        $cupom = Coupon::factory()->create(['discount_value' => 10]);

        $this->assertSame(12.0, $cupom->descontoSobre(120.00));
        $this->assertSame('10%', $cupom->descontoFormatado());
    }

    public function test_desconto_em_reais(): void
    {
        $cupom = Coupon::factory()->emReais(25)->create();

        $this->assertSame(25.0, $cupom->descontoSobre(120.00));
        $this->assertSame('R$ 25,00', $cupom->descontoFormatado());
    }

    public function test_desconto_nunca_passa_do_valor_da_inscricao(): void
    {
        // Sem esse teto a conta do checkout geraria cobrança negativa — que o
        // Mercado Pago recusa, e tarde demais, na frente do atleta.
        $cupom = Coupon::factory()->emReais(50)->create();

        $this->assertSame(30.0, $cupom->descontoSobre(30.00));
    }

    public function test_percentual_quebrado_mantem_as_casas_na_exibicao(): void
    {
        $cupom = Coupon::factory()->create(['discount_value' => 12.5]);

        $this->assertSame('12,50%', $cupom->descontoFormatado());
    }

    /*
    |--------------------------------------------------------------------------
    | Consumo do limite
    |--------------------------------------------------------------------------
    */

    public function test_registrar_uso_incrementa_o_contador(): void
    {
        $cupom = Coupon::factory()->create(['total_quantity' => 2]);

        $this->assertTrue($cupom->registrarUso());
        $this->assertSame(1, $cupom->used_quantity);
        $this->assertSame(1, $cupom->fresh()->used_quantity);
    }

    public function test_registrar_uso_nao_passa_do_limite(): void
    {
        // Duas instâncias do MESMO cupom, cada uma com sua cópia em memória —
        // é o que duas requisições simultâneas teriam. A segunda só descobre
        // que a vaga acabou porque a condição está no WHERE do UPDATE, e não
        // numa leitura feita antes.
        $cupom = Coupon::factory()->create(['total_quantity' => 1]);
        $mesmoCupom = Coupon::find($cupom->id);

        $this->assertTrue($cupom->registrarUso());
        $this->assertFalse($mesmoCupom->registrarUso());

        $this->assertSame(1, $cupom->fresh()->used_quantity);
    }

    public function test_registrar_uso_recusa_cupom_inativo(): void
    {
        $cupom = Coupon::factory()->create(['active' => false]);

        $this->assertFalse($cupom->registrarUso());
        $this->assertSame(0, $cupom->fresh()->used_quantity);
    }

    public function test_registrar_uso_recusa_cupom_vencido(): void
    {
        $cupom = Coupon::factory()->vencido()->create();

        $this->assertFalse($cupom->registrarUso());
        $this->assertSame(0, $cupom->fresh()->used_quantity);
    }

    public function test_registrar_uso_aceita_cupom_que_vence_hoje(): void
    {
        $cupom = Coupon::factory()->create(['expires_at' => today()->toDateString()]);

        $this->assertTrue($cupom->registrarUso());
    }

    /*
    |--------------------------------------------------------------------------
    | Escopo
    |--------------------------------------------------------------------------
    */

    public function test_escopo_validos_deixa_de_fora_inativo_vencido_e_esgotado(): void
    {
        $bom = Coupon::factory()->create();
        Coupon::factory()->create(['active' => false]);
        Coupon::factory()->vencido()->create();
        Coupon::factory()->esgotado()->create();

        $this->assertSame([$bom->id], Coupon::validos()->pluck('id')->all());
    }
}
