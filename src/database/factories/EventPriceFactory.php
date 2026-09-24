<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventModality;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'modality_id' => EventModality::factory(),
            'kit_id' => EventKit::factory(),
            'lot_id' => EventLot::factory(),
            'price' => 89.90,
        ];
    }
}
