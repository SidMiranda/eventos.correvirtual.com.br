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
        'shirt_size',
        'list_price',
        'discount_amount',
        'coupon_id',
        'lot_id',
        'age_category_id',
        'age_discount_amount',
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
        'age_discount_amount' => 'decimal:2',
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

    /** O lote em que a inscrição foi feita. Nulo nas anteriores a 2026-10. */
    public function lot()
    {
        return $this->belongsTo(EventLot::class, 'lot_id');
    }

    public function ageCategory()
    {
        return $this->belongsTo(AgeCategory::class, 'age_category_id');
    }

    public function temDescontoDeIdade(): bool
    {
        return (float) $this->age_discount_amount > 0;
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

    /*
    |--------------------------------------------------------------------------
    | Camiseta
    |--------------------------------------------------------------------------
    | A tabela de medidas do fornecedor, em largura x comprimento. Vive aqui
    | porque hoje é a mesma para todos os eventos do organizador — quando cada
    | evento passar a declarar as suas, isto vira tabela (ver o backlog).
    */

    public const CAMISETAS = [
        'P' => '51 x 67 cm',
        'M' => '54 x 70 cm',
        'G' => '56 x 73 cm',
        'GG' => '59 x 76 cm',
        'EG' => '63 x 79 cm',
        'EXG' => '67 x 83 cm',
    ];

    public const CAMISETAS_BABY_LOOK = [
        'BLP' => '41 x 61 cm',
        'BLM' => '43 x 63 cm',
        'BLG' => '46 x 65 cm',
        'BLGG' => '48 x 68 cm',
    ];

    /** Os códigos aceitos — é o que a validação do formulário confere. */
    public static function tamanhosDeCamiseta(): array
    {
        return array_merge(array_keys(self::CAMISETAS), array_keys(self::CAMISETAS_BABY_LOOK));
    }

    /** "G (56 x 73 cm)" — o que aparece na tela e no painel. */
    public function camisetaPorExtenso(): ?string
    {
        if (blank($this->shirt_size)) {
            return null;
        }

        $medida = self::CAMISETAS[$this->shirt_size]
            ?? self::CAMISETAS_BABY_LOOK[$this->shirt_size]
            ?? null;

        return $medida ? "{$this->shirt_size} ({$medida})" : $this->shirt_size;
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
