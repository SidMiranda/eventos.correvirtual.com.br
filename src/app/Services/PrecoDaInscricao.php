<?php

namespace App\Services;

use App\Models\AgeCategory;
use App\Models\Coupon;
use App\Models\EventKit;

/**
 * Quanto uma inscrição custa: o preço base, o que a categoria etária abate, o
 * que o cupom abate do que sobrou, e o total.
 *
 * Toda a conta é feita em CENTAVOS INTEIROS. Dinheiro em float dá 80.91000001
 * na primeira subtração, e o Mercado Pago recebe o valor com duas casas — não
 * pode existir diferença entre o que a tela mostra, o que o banco guarda e o
 * que o Pix cobra. Só no fim os centavos viram reais.
 *
 * CASCATA (decisão do dono, 2026-09-23, ADR 0007): a idade abate o preço base;
 * o cupom abate o subtotal. R$ 100 com Idoso −50% e cupom −20% = R$ 40.
 *
 * Não sabe nada de HTTP nem de banco: recebe números e regras, devolve
 * números. É o que o financeiro vai reaproveitar quando existir.
 *
 * Ver docs/specs/precos-lotes-e-categorias.md e cupons-de-desconto.md.
 */
final class PrecoDaInscricao
{
    private function __construct(
        private readonly int $brutoCentavos,
        private readonly int $descontoIdadeCentavos,
        private readonly int $descontoCupomCentavos,
        public readonly ?AgeCategory $categoria,
        public readonly ?Coupon $cupom,
    ) {
    }

    /**
     * A conta a partir do preço base (o da grade, no lote vigente).
     */
    public static function de(float $precoBase, ?AgeCategory $categoria = null, ?Coupon $cupom = null): self
    {
        $bruto = self::centavos($precoBase);
        $idade = $categoria ? $categoria->descontoEmCentavos($bruto) : 0;

        // O cupom incide sobre o que sobrou da idade — é isso que "cascata"
        // quer dizer. O teto dele também é o subtotal, não o bruto.
        $cupomCentavos = $cupom ? self::descontoEmCentavos($cupom, $bruto - $idade) : 0;

        return new self($bruto, $idade, $cupomCentavos, $categoria, $cupom);
    }

    /**
     * O caminho antigo: preço do kit, sem categoria.
     *
     * Continua existindo porque a fatia 1 de 2026-09-23 não muda o checkout —
     * ele só passa a ler a grade na fatia 2. Aí isto sai.
     */
    public static function para(EventKit $kit, ?Coupon $cupom = null): self
    {
        return self::de((float) $kit->price, null, $cupom);
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
     * teto é o próprio valor: um cupom de R$ 50 num subtotal de R$ 30 abate
     * R$ 30 — sem isso a conta geraria cobrança negativa, que o Mercado Pago
     * recusa, e tarde demais, na frente do atleta.
     */
    public static function descontoEmCentavos(Coupon $cupom, int $sobreCentavos): int
    {
        if ($sobreCentavos <= 0) {
            return 0;
        }

        $desconto = $cupom->ehPercentual()
            ? (int) round($sobreCentavos * (float) $cupom->discount_value / 100)
            : self::centavos((float) $cupom->discount_value);

        return max(0, min($desconto, $sobreCentavos));
    }

    /*
    |--------------------------------------------------------------------------
    | Os valores
    |--------------------------------------------------------------------------
    */

    /** O preço base, antes de qualquer desconto. */
    public function bruto(): float
    {
        return $this->brutoCentavos / 100;
    }

    /** O que a categoria etária abateu. */
    public function descontoDeIdade(): float
    {
        return $this->descontoIdadeCentavos / 100;
    }

    /** Depois da idade, antes do cupom. */
    public function subtotal(): float
    {
        return ($this->brutoCentavos - $this->descontoIdadeCentavos) / 100;
    }

    /** O que o cupom abateu. */
    public function desconto(): float
    {
        return $this->descontoCupomCentavos / 100;
    }

    /** O que vai ser cobrado. Nunca negativo. */
    public function liquido(): float
    {
        return $this->liquidoCentavos() / 100;
    }

    private function liquidoCentavos(): int
    {
        return $this->brutoCentavos - $this->descontoIdadeCentavos - $this->descontoCupomCentavos;
    }

    /** Descontos que zeraram o valor: a inscrição confirma sem passar pelo Pix. */
    public function gratuita(): bool
    {
        return $this->liquidoCentavos() === 0;
    }

    public function temDesconto(): bool
    {
        return $this->descontoCupomCentavos > 0;
    }

    public function temDescontoDeIdade(): bool
    {
        return $this->descontoIdadeCentavos > 0;
    }

    /** As colunas de `subscriptions` que guardam este retrato. */
    public function paraInscricao(): array
    {
        return [
            'list_price' => $this->bruto(),
            'age_category_id' => $this->categoria?->id,
            'age_discount_amount' => $this->descontoDeIdade(),
            'discount_amount' => $this->desconto(),
            'coupon_id' => $this->cupom?->id,
            'price' => $this->liquido(),
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

    public function descontoDeIdadeFormatado(): string
    {
        return self::formatar($this->descontoDeIdade());
    }

    public function subtotalFormatado(): string
    {
        return self::formatar($this->subtotal());
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
