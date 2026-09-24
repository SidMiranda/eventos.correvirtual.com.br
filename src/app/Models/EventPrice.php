<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma célula da grade de preços: quanto custa (modalidade, kit) num lote.
 *
 * É daqui que o checkout lê o valor a partir da fatia 2 de 2026-09-23.
 * Ver ADR 0007.
 */
class EventPrice extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'modality_id', 'kit_id', 'lot_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function modality()
    {
        return $this->belongsTo(EventModality::class, 'modality_id');
    }

    public function kit()
    {
        return $this->belongsTo(EventKit::class, 'kit_id');
    }

    public function lot()
    {
        return $this->belongsTo(EventLot::class, 'lot_id');
    }

    /** O preço de uma combinação num lote, ou nulo se não está na grade. */
    public static function de(EventModality $modalidade, EventKit $kit, EventLot $lote): ?self
    {
        return static::where('modality_id', $modalidade->id)
            ->where('kit_id', $kit->id)
            ->where('lot_id', $lote->id)
            ->first();
    }
}
