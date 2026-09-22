<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'modality_id',
        'kit_id',
        'team_name',
        'list_price',
        'discount_amount',
        'coupon_id',
        'price',
        'bib_number',
        'status',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'list_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    /**
     * `list_price` (preço do kit na hora) nunca fica vazio: sem cupom, é o
     * próprio valor cobrado. O hook cobre factories, seeders e qualquer
     * caminho antigo que crie inscrição informando só `price`.
     */
    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if ($subscription->list_price === null) {
                $subscription->list_price = $subscription->price;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function modality()
    {
        return $this->belongsTo(EventModality::class);
    }

    public function kit()
    {
        return $this->belongsTo(EventKit::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPaid()
    {
        return $this->payments()
            ->where('status', 'approved')
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Valor
    |--------------------------------------------------------------------------
    | `price` é o que foi cobrado; `list_price` o preço do kit na hora;
    | `discount_amount` a diferença. O retrato é gravado na criação e não muda
    | — é o que um relatório financeiro vai ler (docs/specs/cupons-de-desconto.md).
    */

    /*
    |--------------------------------------------------------------------------
    | Situação
    |--------------------------------------------------------------------------
    | Os três estados vêm da coluna `status` (pending|paid|cancelled). Atenção
    | à grafia: o enum do banco usa DOIS L (`cancelled`) e as views chegaram a
    | comparar com um só — comparação que nunca batia, escondida pelo fato de
    | que cancelar apagava a linha. Ver docs/specs/gestao-de-inscricoes.md.
    */

    public const PENDENTE = 'pending';
    public const PAGA = 'paid';
    public const CANCELADA = 'cancelled';

    public function paga(): bool
    {
        return $this->status === self::PAGA;
    }

    public function pendente(): bool
    {
        return $this->status === self::PENDENTE;
    }

    /**
     * A equipe como ela é guardada: sem espaço sobrando e em CAIXA ALTA.
     *
     * É texto livre (decisão do dono em 2026-09-22), então "corre mogi",
     * "Corre Mogi" e "CORRE  MOGI" chegariam como três equipes diferentes na
     * hora de contar quem trouxe mais gente. Normalizar na entrada é o que
     * evita isso — `mb_strtoupper` para "são" virar "SÃO", e não "SãO".
     */
    public static function normalizarEquipe(?string $nome): ?string
    {
        $nome = trim(preg_replace('/\s+/u', ' ', (string) $nome));

        return $nome === '' ? null : mb_strtoupper($nome, 'UTF-8');
    }

    public function cancelada(): bool
    {
        return $this->status === self::CANCELADA;
    }

    /** Rótulo curto para as telas, no molde de Event::situacao(). */
    public function situacao(): string
    {
        return match ($this->status) {
            self::PAGA => $this->gratuita() ? 'Confirmada (gratuita)' : 'Paga',
            self::CANCELADA => 'Cancelada',
            default => 'Aguardando pagamento',
        };
    }

    /** Cor da etiqueta, seguindo as classes do template do painel. */
    public function corDaSituacao(): string
    {
        return match ($this->status) {
            self::PAGA => 'success',
            self::CANCELADA => 'danger',
            default => 'warning',
        };
    }

    /** Cupom zerou o valor: confirmada sem passar pelo Pix. */
    public function gratuita(): bool
    {
        return (float) $this->price <= 0;
    }

    public function temDesconto(): bool
    {
        return (float) $this->discount_amount > 0;
    }
}
