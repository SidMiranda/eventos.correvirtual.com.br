<?php

namespace Tests\Feature;

use App\Models\AgeCategory;
use App\Models\Coupon;
use App\Services\PrecoDaInscricao;
use Tests\TestCase;

/**
 * A conta do preço, em centavos inteiros, com a cascata de descontos.
 *
 * Decisão do dono (2026-09-23): a idade abate o preço base, o cupom abate o
 * que sobrou. R$ 100 com Idoso −50% e cupom −20% = R$ 40 — não R$ 30 (soma
 * sobre a base) nem R$ 50 (só o maior). Ver ADR 0007.
 */
class CascataDeDescontosTest extends TestCase
{
    private function categoria(string $tipo, float $valor): AgeCategory
    {
        return new AgeCategory(['discount_type' => $tipo, 'discount_value' => $valor]);
    }

    private function cupom(string $tipo, float $valor): Coupon
    {
        return new Coupon(['discount_type' => $tipo, 'discount_value' => $valor]);
    }

    public function test_sem_desconto_o_liquido_e_o_bruto(): void
    {
        $preco = PrecoDaInscricao::de(89.90);

        $this->assertSame(89.90, $preco->bruto());
        $this->assertSame(0.0, $preco->descontoDeIdade());
        $this->assertSame(0.0, $preco->desconto());
        $this->assertSame(89.90, $preco->liquido());
        $this->assertFalse($preco->gratuita());
    }

    public function test_a_cascata_do_exemplo_do_dono(): void
    {
        $preco = PrecoDaInscricao::de(
            100.00,
            $this->categoria(AgeCategory::TIPO_PERCENTUAL, 50),
            $this->cupom(Coupon::TIPO_PERCENTUAL, 20),
        );

        $this->assertSame(100.00, $preco->bruto());
        $this->assertSame(50.00, $preco->descontoDeIdade());
        $this->assertSame(50.00, $preco->subtotal());
        // 20% de 50, não de 100.
        $this->assertSame(10.00, $preco->desconto());
        $this->assertSame(40.00, $preco->liquido());
    }

    public function test_categoria_de_valor_fixo(): void
    {
        $preco = PrecoDaInscricao::de(89.90, $this->categoria(AgeCategory::TIPO_VALOR, 30));

        $this->assertSame(30.00, $preco->descontoDeIdade());
        $this->assertSame(59.90, $preco->liquido());
    }

    public function test_categoria_maior_que_o_preco_zera_e_nao_fica_negativa(): void
    {
        $preco = PrecoDaInscricao::de(30.00, $this->categoria(AgeCategory::TIPO_VALOR, 50));

        $this->assertSame(30.00, $preco->descontoDeIdade());
        $this->assertSame(0.00, $preco->liquido());
        $this->assertTrue($preco->gratuita());
    }

    public function test_cupom_sobre_subtotal_zero_nao_abate_nada(): void
    {
        // A idade já zerou; o cupom não tem sobre o que incidir.
        $preco = PrecoDaInscricao::de(
            50.00,
            $this->categoria(AgeCategory::TIPO_PERCENTUAL, 100),
            $this->cupom(Coupon::TIPO_VALOR, 10),
        );

        $this->assertSame(0.00, $preco->desconto());
        $this->assertSame(0.00, $preco->liquido());
        $this->assertTrue($preco->gratuita());
    }

    public function test_o_teto_do_cupom_e_o_subtotal_e_nao_o_bruto(): void
    {
        // R$ 100 → Idoso −60 = 40. Cupom de R$ 50 abate só os 40 que sobraram.
        $preco = PrecoDaInscricao::de(
            100.00,
            $this->categoria(AgeCategory::TIPO_VALOR, 60),
            $this->cupom(Coupon::TIPO_VALOR, 50),
        );

        $this->assertSame(40.00, $preco->desconto());
        $this->assertSame(0.00, $preco->liquido());
    }

    public function test_arredondamento_em_centavos_nas_duas_pontas(): void
    {
        // 12,5% de R$ 89,90 = 1123,75 centavos → 1124 (meio para cima).
        $preco = PrecoDaInscricao::de(89.90, $this->categoria(AgeCategory::TIPO_PERCENTUAL, 12.5));
        $this->assertSame(11.24, $preco->descontoDeIdade());
        $this->assertSame(78.66, $preco->liquido());

        // E o cupom sobre o subtotal de 78,66: 10% = 786,6 → 787.
        $preco = PrecoDaInscricao::de(
            89.90,
            $this->categoria(AgeCategory::TIPO_PERCENTUAL, 12.5),
            $this->cupom(Coupon::TIPO_PERCENTUAL, 10),
        );
        $this->assertSame(7.87, $preco->desconto());
        $this->assertSame(70.79, $preco->liquido());
    }

    public function test_o_retrato_para_a_inscricao_separa_os_dois_descontos(): void
    {
        $preco = PrecoDaInscricao::de(
            100.00,
            $this->categoria(AgeCategory::TIPO_PERCENTUAL, 50),
            $this->cupom(Coupon::TIPO_PERCENTUAL, 20),
        );

        $retrato = $preco->paraInscricao();

        $this->assertSame(100.00, $retrato['list_price']);
        $this->assertSame(50.00, $retrato['age_discount_amount']);
        $this->assertSame(10.00, $retrato['discount_amount']);
        $this->assertSame(40.00, $retrato['price']);
        // list − idade − cupom = price, sempre.
        $this->assertSame(
            $retrato['price'],
            round($retrato['list_price'] - $retrato['age_discount_amount'] - $retrato['discount_amount'], 2)
        );
    }
}
