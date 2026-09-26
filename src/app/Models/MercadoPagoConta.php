<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A conta Mercado Pago de um organizador, conectada por OAuth (ADR 0008).
 * Os tokens nunca saem do banco em claro: cast `encrypted`, e fora do JSON.
 */
class MercadoPagoConta extends Model
{
    protected $table = 'mercado_pago_contas';

    protected $fillable = [
        'organizer_id',
        'mp_user_id',
        'nome',
        'email',
        'access_token',
        'refresh_token',
        'public_key',
        'live_mode',
        'expires_at',
        'connected_at',
        'refreshed_at',
        'last_error',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'live_mode' => 'boolean',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    /** Vence em até N dias (ou já venceu)? Sem vencimento conhecido, não. */
    public function venceEm(int $dias): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now()->addDays($dias));
    }

    /** "Fulano (fulano@x.com)" — o que a tela Cobrança mostra. */
    public function identificacao(): string
    {
        $partes = array_filter([$this->nome, $this->email ? "({$this->email})" : null]);

        return $partes ? implode(' ', $partes) : "conta {$this->mp_user_id}";
    }
}
