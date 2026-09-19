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
        'list_price',
        'discount_amount',
        'coupon_id',
        'price',
        'bib_number',
        'status',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
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
