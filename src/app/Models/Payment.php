<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;


    protected $fillable = [
        'subscription_id',
        'provider',
        'mercado_pago_conta_id',
        'application_fee',
        'transaction_id',
        'payment_method',
        'status',
        'qr_code',
        'qr_code_base64',
        'ticket_url',
        'expires_at',
        'paid_at',
        'payload'
    ];

    protected function casts(): array
    {
        return [
            // Dinheiro com duas casas, como os valores da inscrição.
            'application_fee' => 'decimal:2',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
