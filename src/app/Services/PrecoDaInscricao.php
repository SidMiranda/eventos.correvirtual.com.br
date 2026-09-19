<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\EventKit;

/**
 * Quanto uma inscrição custa: o preço do kit, o que o cupom abate e o que
 * sobra para cobrar.
 *
 * Toda a conta é feita em CENTAVOS INTEIROS. Dinheiro em float dá 80.91000001
 * na primeira subtração, e o Mercado Pago recebe o valor com duas casas — não
 * pode existir diferença entre o que a tela mostra, o que o banco guarda e o
 * que o Pix cobra. Só no fim os centavos viram reais.
 *
 * Não sabe nada de HTTP nem de banco: recebe o kit e o cupom, devolve números.
 * É o que o financeiro vai reaproveitar quando existir.
 *
 * Ver docs/specs/cupons-de-desconto.md.
 */
final class PrecoDaInscricao
{
    private function __construct(
        private readonly int $brutoCentavos,
        private readonly int $descontoCentavos,
        public readonly ?Coupon $cupom,
    ) {
    }

    public static function para(EventKit $kit, ?Coupon $cupom = null): self
    {
        $bruto = self::centavos((float) $kit->price);
        $desconto = $cupom ? self::descontoEmCentavos($cupom, $bruto) : 0;

        return new self($bruto, $desconto, $cupom);
    }

    /*
    |--------------------------------------------------------------------------
    | A conta
    |--------------------------------------------------------------------------
    */

    public static function centavos(float $valor): int
    {
        return (int) round($valor * 100);
    }

    /**
     * Quanto o cupom abate de um valor, em centavos.
     *
     * Percentual arredonda meio para cima (10% de R$ 89,90 = 899 centavos;
     * 12,5% = 1123,75 → 1124). Valor fixo entra como está. Nos dois casos o
     * teto é o próprio valor: um cupom de R$ 50 num kit de R$ 30 abate R$ 30 —
     * sem isso a conta geraria cobrança negativa, que o Mercado Pago recusa,
     * e tarde demais, na frente do atleta.
     */
    public static function descontoEmCentavos(Coupon $cupom, int $brutoCentavos): int
    {
        if ($brutoCentavos <= 0) {
            return 0;
        }

        $desconto = $cupom->ehPercentual()
            ? (int) round($brutoCentavos * (float) $cupom->discount_value / 100)
            : self::centavos((float) $cupom->discount_value);

        return max(0, min($desconto, $brutoCentavos));
    }

    /*
    |--------------------------------------------------------------------------
    | Os valores
    |--------------------------------------------------------------------------
    */

    /** O preço do kit. */
    public function bruto(): float
    {
        return $this->brutoCentavos / 100;
    }

    public function desconto(): float
    {
        return $this->descontoCentavos / 100;
    }

    /** O que vai ser cobrado. Nunca negativo. */
    public function liquido(): float
    {
        return ($this->brutoCentavos - $this->descontoCentavos) / 100;
    }

    /** Desconto que zerou o valor: a inscrição confirma sem passar pelo Pix. */
    public function gratuita(): bool
    {
        return $this->brutoCentavos - $this->descontoCentavos === 0;
    }

    public function temDesconto(): bool
    {
        return $this->descontoCentavos > 0;
    }

    /** As colunas de `subscriptions` que guardam este retrato. */
    public function paraInscricao(): array
    {
        return [
            'list_price' => $this->bruto(),
            'discount_amount' => $this->desconto(),
            'price' => $this->liquido(),
            'coupon_id' => $this->cupom?->id,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Para a tela
    |--------------------------------------------------------------------------
    */

    public static function formatar(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    public function brutoFormatado(): string
    {
        return self::formatar($this->bruto());
    }

    public function descontoFormatado(): string
    {
        return self::formatar($this->desconto());
    }

    public function liquidoFormatado(): string
    {
        return self::formatar($this->liquido());
    }
}
