<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lote: uma janela de vigência do evento, não um produto.
 *
 * Diz qual coluna da grade de preços está valendo. O sistema resolve o lote
 * vigente na hora da inscrição — ver Event::loteVigente(). Ver ADR 0007.
 */
class EventLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'starts_at',
        'ends_at',
        'max_subscriptions',
        'position',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function prices()
    {
        return $this->hasMany(EventPrice::class, 'lot_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'lot_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Vigência
    |--------------------------------------------------------------------------
    */

    /** Está na janela de datas agora? Não olha quantidade. */
    public function naJanela(): bool
    {
        $agora = now();

        return $this->active
            && $this->starts_at !== null
            && $this->starts_at->lte($agora)
            && ($this->ends_at === null || $this->ends_at->gt($agora));
    }

    /** Inscrições não canceladas feitas neste lote. */
    public function inscricoesContadas(): int
    {
        return $this->subscriptions()
            ->where('status', '!=', Subscription::CANCELADA)
            ->count();
    }

    /** Atingiu o limite? Sem limite, nunca. */
    public function esgotado(): bool
    {
        return $this->max_subscriptions !== null
            && $this->inscricoesContadas() >= $this->max_subscriptions;
    }

    /** Na janela e com vaga: é este que vende agora. */
    public function vigente(): bool
    {
        return $this->naJanela() && ! $this->esgotado();
    }

    public function futuro(): bool
    {
        return $this->active && $this->starts_at !== null && $this->starts_at->isFuture();
    }

    /** Rótulo curto para as telas do painel. */
    public function situacao(): string
    {
        if (! $this->active) {
            return 'Inativo';
        }
        if ($this->futuro()) {
            return 'Futuro';
        }
        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'Encerrado';
        }
        if ($this->esgotado()) {
            return 'Esgotado';
        }

        return 'Vigente';
    }

    public function corDaSituacao(): string
    {
        return match ($this->situacao()) {
            'Vigente' => 'success',
            'Futuro' => 'primary',
            'Esgotado' => 'warning',
            default => 'secondary',
        };
    }

    /** "01/10 a 15/10" ou "a partir de 01/10". */
    public function periodoPorExtenso(): string
    {
        $inicio = $this->starts_at?->format('d/m/Y H:i') ?? '?';

        return $this->ends_at
            ? "{$inicio} até {$this->ends_at->format('d/m/Y H:i')}"
            : "a partir de {$inicio}";
    }
}
