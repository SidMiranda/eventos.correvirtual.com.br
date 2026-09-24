<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Services\PrecoDaInscricao;
use Tests\TestCase;

/**
 * A conta do checkout, em centavos.
 *
 * Cada caso aqui é um valor que vai para o Mercado Pago e para o banco com
 * duas casas. A tela, a coluna `price` e o Pix precisam concordar no centavo —
 * e float não concorda consigo mesmo (0.1 + 0.2). Por isso a conta é inteira.
 */
class PrecoDaInscricaoTest extends TestCase
{
    private function percentual(float $pct): Coupon
    {
        return new Coupon(['discount_type' => Coupon::TIPO_PERCENTUAL, 'discount_value' => $pct]);
    }

    private function emReais(float $valor): Coupon
    {
        return new Coupon(['discount_type' => Coupon::TIPO_VALOR, 'discount_value' => $valor]);
    }

    public function test_sem_cupom_o_liquido_e_o_proprio_preco(): void
    {
        $preco = PrecoDaInscricao::de(89.90);

        $this->assertSame(89.90, $preco->bruto());
        $this->assertSame(0.0, $preco->desconto());
        $this->assertSame(89.90, $preco->liquido());
        $this->assertFalse($preco->gratuita());
        $this->assertFalse($preco->temDesconto());
        $this->assertNull($preco->paraInscricao()['coupon_id']);
    }

    public function test_dez_por_cento_de_89_90(): void
    {
        $preco = PrecoDaInscricao::de(89.90, null, $this->percentual(10));

        $this->assertSame(8.99, $preco->desconto());
        $this->assertSame(80.91, $preco->liquido());
    }

    public function test_percentual_quebrado_arredonda_meio_para_cima(): void
    {
        // 12,5% de 8990 centavos = 1123,75 → 1124.
        $preco = PrecoDaInscricao::de(89.90, null, $this->percentual(12.5));

        $this->assertSame(11.24, $preco->desconto());
        $this->assertSame(78.66, $preco->liquido());
    }

    public function test_valor_em_reais(): void
    {
        $preco = PrecoDaInscricao::de(59.90, null, $this->emReais(25));

        $this->assertSame(25.0, $preco->desconto());
        $this->assertSame(34.90, $preco->liquido());
    }

    public function test_desconto_maior_que_o_kit_zera_e_nao_fica_negativo(): void
    {
        $preco = PrecoDaInscricao::de(30.00, null, $this->emReais(50));

        $this->assertSame(30.0, $preco->desconto());
        $this->assertSame(0.0, $preco->liquido());
        $this->assertTrue($preco->gratuita());
    }

    public function test_cem_por_cento_e_gratuita(): void
    {
        $preco = PrecoDaInscricao::de(89.90, null, $this->percentual(100));

        $this->assertSame(89.90, $preco->desconto());
        $this->assertSame(0.0, $preco->liquido());
        $this->assertTrue($preco->gratuita());
    }

    public function test_nao_vaza_ruido_de_float(): void
    {
        // 0,30 com 10%: em float, 0.3 * 0.1 = 0.030000000000000002 e
        // 0.3 - 0.03 = 0.27 só por sorte. Em centavos é 30, 3 e 27.
        $preco = PrecoDaInscricao::de(0.30, null, $this->percentual(10));

        $this->assertSame(0.03, $preco->desconto());
        $this->assertSame(0.27, $preco->liquido());

        // 89,90 * 100 dá 8989.999999… em float; o round() é o que salva.
        $this->assertSame(8990, PrecoDaInscricao::centavos(89.90));
    }

    public function test_para_inscricao_devolve_as_colunas_do_retrato(): void
    {
        $cupom = $this->percentual(10);
        $cupom->id = 7;

        $this->assertSame([
            'list_price' => 89.90,
            // Sem categoria etária pelo caminho antigo: colunas zeradas, mas
            // presentes — é o retrato completo (ADR 0007).
            'age_category_id' => null,
            'age_discount_amount' => 0.0,
            'discount_amount' => 8.99,
            'coupon_id' => 7,
            'price' => 80.91,
        ], PrecoDaInscricao::de(89.90, null, $cupom)->paraInscricao());
    }

    public function test_formatacao_em_reais(): void
    {
        $preco = PrecoDaInscricao::de(1234.5, null, $this->percentual(10));

        $this->assertSame('R$ 1.234,50', $preco->brutoFormatado());
        $this->assertSame('R$ 123,45', $preco->descontoFormatado());
        $this->assertSame('R$ 1.111,05', $preco->liquidoFormatado());
    }

    public function test_o_model_do_cupom_usa_a_mesma_conta(): void
    {
        // Uma regra só: Coupon::descontoSobre() e PrecoDaInscricao não podem
        // discordar num centavo.
        $cupom = $this->percentual(12.5);

        $this->assertSame(11.24, $cupom->descontoSobre(89.90));
        $this->assertSame(PrecoDaInscricao::de(89.90, null, $cupom)->desconto(), $cupom->descontoSobre(89.90));
    }
}
