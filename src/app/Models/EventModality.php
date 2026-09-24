<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventModality extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'distance_km',
        'max_participants',
        'active',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'modality_id');
    }

    /** Os kits que podem ser comprados nesta modalidade (ADR 0007). */
    public function kits()
    {
        return $this->belongsToMany(EventKit::class, 'event_kit_modality', 'modality_id', 'kit_id');
    }
}
