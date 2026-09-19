<?php

namespace App\Models;

use App\Services\PrecoDaInscricao;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cupom de desconto de um evento.
 *
 * O cupom pertence a UM evento — diferente de equipe e patrocinador, que são do
 * organizador. Um desconto é sempre uma decisão sobre uma prova específica: o
 * preço, a data e a lotação são daquela prova.
 *
 * O estado do cupom é deduzido, não gravado: "esgotado" é o contador, "vencido"
 * é a data. Mesma escolha já feita para a situação do evento (Event::situacao()).
 *
 * Ver docs/specs/cupons-de-desconto.md.
 */
class Coupon extends Model
{
    use HasFactory;

    public const TIPO_PERCENTUAL = 'percent';
    public const TIPO_VALOR = 'amount';

    public const TIPOS = [self::TIPO_PERCENTUAL, self::TIPO_VALOR];

    /**
     * `used_quantity` fica de fora de propósito: o contador de uso não é campo
     * de formulário. Quem mexe nele é registrarUso(), e só ele.
     */
    protected $fillable = [
        'event_id',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'total_quantity',
        'expires_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'discount_value' => 'decimal:2',
            'total_quantity' => 'integer',
            'used_quantity' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /** As inscrições que usaram este cupom — é o histórico de "por quem". */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Código
    |--------------------------------------------------------------------------
    */

    /**
     * O código é sempre guardado em maiúsculo e sem espaços nas pontas, venha
     * do formulário, de um seeder ou do tinker.
     *
     * O que NÃO se faz aqui é remover caracteres inválidos: "corre-10" vira
     * "CORRE-10" e é recusado pela validação com uma mensagem clara, em vez de
     * virar em silêncio um "CORRE10" que ninguém pediu e que o organizador não
     * vai reconhecer quando o atleta reclamar.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => mb_strtoupper(trim((string) $valor)),
        );
    }

    /** A mesma normalização, para usar antes de validar ou de buscar. */
    public static function normalizarCodigo(?string $valor): string
    {
        return mb_strtoupper(trim((string) $valor));
    }

    /*
    |--------------------------------------------------------------------------
    | Estado
    |--------------------------------------------------------------------------
    */

    public function foiUsado(): bool
    {
        return $this->used_quantity > 0;
    }

    public function restantes(): int
    {
        return max(0, $this->total_quantity - $this->used_quantity);
    }

    public function esgotado(): bool
    {
        return $this->used_quantity >= $this->total_quantity;
    }

    /** Vence no FIM do dia: um cupom que expira hoje ainda vale hoje. */
    public function vencido(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    /**
     * Encerrado é estado sem volta — esgotou ou passou da data. O toggle da
     * tela fica desabilitado e o controller recusa a troca nos dois sentidos:
     * um cupom encerrado já lê como inativo, então "desligar" não diria nada.
     */
    public function encerrado(): bool
    {
        return $this->esgotado() || $this->vencido();
    }

    public function validoParaUso(): bool
    {
        return $this->active && ! $this->encerrado();
    }

    public function situacao(): string
    {
        if ($this->esgotado()) {
            return 'Esgotado';
        }

        if ($this->vencido()) {
            return 'Vencido';
        }

        return $this->active ? 'Ativo' : 'Inativo';
    }

    /** Cor da etiqueta, seguindo as classes do template do painel. */
    public function corDaSituacao(): string
    {
        return match ($this->situacao()) {
            'Ativo' => 'success',
            'Esgotado' => 'warning',
            'Vencido' => 'danger',
            default => 'secondary',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Desconto
    |--------------------------------------------------------------------------
    */

    public function ehPercentual(): bool
    {
        return $this->discount_type === self::TIPO_PERCENTUAL;
    }

    /** "10%" ou "R$ 25,00". */
    public function descontoFormatado(): string
    {
        if ($this->ehPercentual()) {
            // Sem casas decimais quando é número redondo: "10%" e não "10,00%".
            $valor = (float) $this->discount_value;
            $casas = fmod($valor, 1.0) === 0.0 ? 0 : 2;

            return number_format($valor, $casas, ',', '.') . '%';
        }

        return 'R$ ' . number_format((float) $this->discount_value, 2, ',', '.');
    }

    /**
     * Quanto este cupom abate de um valor.
     *
     * A conta mora em PrecoDaInscricao, em centavos inteiros — aqui é só o
     * atalho. Uma regra só, para a tela, o banco e o Pix nunca discordarem.
     */
    public function descontoSobre(float $valor): float
    {
        return PrecoDaInscricao::descontoEmCentavos($this, PrecoDaInscricao::centavos($valor)) / 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Uso
    |--------------------------------------------------------------------------
    */

    /**
     * Marca um uso do cupom. Devolve false quando não havia mais vaga.
     *
     * UPDATE condicional em vez de ler-conferir-gravar: a condição vive no
     * WHERE, e é o banco que decide. Duas inscrições disputando a última vaga —
     * o que acontece de verdade quando um cupom viraliza num grupo de WhatsApp —
     * resolvem-se sozinhas: a que chega depois afeta 0 linhas e sai com false.
     *
     * Ler o saldo antes e gravar depois deixaria uma janela entre as duas
     * operações em que o valor já está velho, e o limite estouraria.
     */
    public function registrarUso(): bool
    {
        $afetadas = static::query()
            ->whereKey($this->getKey())
            ->where('active', true)
            ->whereColumn('used_quantity', '<', 'total_quantity')
            ->where('expires_at', '>=', today()->toDateString())
            ->increment('used_quantity');

        if ($afetadas === 0) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Escopos
    |--------------------------------------------------------------------------
    */

    /**
     * Os cupons de um organizador.
     *
     * `coupons` não tem organizer_id, e não vai ter: o vínculo é o evento —
     * mesma regra já usada para inscrições no DashboardController.
     */
    public function scopeDoOrganizador($query, int $organizerId)
    {
        return $query->whereHas('event', fn ($q) => $q->where('organizer_id', $organizerId));
    }

    /** Os que um atleta poderia usar agora, neste evento. */
    public function scopeValidos($query)
    {
        return $query->where('active', true)
            ->whereColumn('used_quantity', '<', 'total_quantity')
            ->where('expires_at', '>=', today()->toDateString());
    }
}
