<?php

namespace Database\Factories;

use App\Models\AgeCategory;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Idoso',
            'min_age' => 60,
            'max_age' => null,
            'discount_type' => AgeCategory::TIPO_PERCENTUAL,
            'discount_value' => 50,
            'active' => true,
        ];
    }
}
