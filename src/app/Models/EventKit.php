<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventKit extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'stock',
        'active',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'kit_id');
    }

    /** Em quais modalidades este kit pode ser comprado (ADR 0007). */
    public function modalities()
    {
        return $this->belongsToMany(EventModality::class, 'event_kit_modality', 'kit_id', 'modality_id');
    }

    public function options()
    {
        return $this->hasMany(KitOption::class, 'kit_id')->orderBy('position');
    }

    public function prices()
    {
        return $this->hasMany(EventPrice::class, 'kit_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Tamanhos de camiseta
    |--------------------------------------------------------------------------
    | Kit sem tamanho cadastrado não pede tamanho — é o kit "sem camiseta".
    */

    /** @return string[] os códigos, na ordem cadastrada */
    public function tamanhos(): array
    {
        return $this->options
            ->where('attribute', KitOption::TAMANHO)
            ->pluck('value')
            ->values()
            ->all();
    }

    public function temTamanhos(): bool
    {
        return $this->tamanhos() !== [];
    }

    public function aceitaTamanho(?string $tamanho): bool
    {
        return in_array($tamanho, $this->tamanhos(), true);
    }
}
