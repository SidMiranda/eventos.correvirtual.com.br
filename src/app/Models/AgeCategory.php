<?php

namespace App\Models;

use App\Services\PrecoDaInscricao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Categoria etária: desconto por idade, não kit nem modalidade.
 *
 * Criança e idoso recebem o mesmo kit e pagam menos. A idade vem do cadastro
 * do atleta, contada pelo critério do evento (Event::idadeDe()). Em mais de
 * uma categoria, vale a de maior desconto em reais — ver
 * App\Services\CategoriaEtaria.
 */
class AgeCategory extends Model
{
    use HasFactory;

    public const TIPO_PERCENTUAL = 'percent';
    public const TIPO_VALOR = 'amount';
    public const TIPOS = [self::TIPO_PERCENTUAL, self::TIPO_VALOR];

    protected $fillable = [
        'event_id',
        'name',
        'min_age',
        'max_age',
        'discount_type',
        'discount_value',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'age_category_id');
    }

    public function ehPercentual(): bool
    {
        return $this->discount_type === self::TIPO_PERCENTUAL;
    }

    /** A idade cai nesta faixa? Limites nulos são abertos. */
    public function abrange(int $idade): bool
    {
        return ($this->min_age === null || $idade >= $this->min_age)
            && ($this->max_age === null || $idade <= $this->max_age);
    }

    /**
     * Quanto abate de um preço base, em centavos. Teto no próprio valor —
     * a mesma conta do cupom (PrecoDaInscricao), para os dois nunca
     * discordarem sobre arredondamento.
     */
    public function descontoEmCentavos(int $baseCentavos): int
    {
        if ($baseCentavos <= 0) {
            return 0;
        }

        $desconto = $this->ehPercentual()
            ? (int) round($baseCentavos * (float) $this->discount_value / 100)
            : PrecoDaInscricao::centavos((float) $this->discount_value);

        return max(0, min($desconto, $baseCentavos));
    }

    /** "10%" ou "R$ 25,00". */
    public function descontoFormatado(): string
    {
        return $this->ehPercentual()
            ? rtrim(rtrim(number_format((float) $this->discount_value, 2, ',', '.'), '0'), ',') . '%'
            : PrecoDaInscricao::formatar((float) $this->discount_value);
    }

    /** "até 12 anos", "60 anos ou mais", "de 13 a 17 anos". */
    public function faixaPorExtenso(): string
    {
        return match (true) {
            $this->min_age !== null && $this->max_age !== null => "de {$this->min_age} a {$this->max_age} anos",
            $this->min_age !== null => "{$this->min_age} anos ou mais",
            $this->max_age !== null => "até {$this->max_age} anos",
            default => 'qualquer idade',
        };
    }
}
