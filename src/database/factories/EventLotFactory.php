<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventLotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Lote 1',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'max_subscriptions' => null,
            'position' => 0,
            'active' => true,
        ];
    }

    /** Começa amanhã: existe, mas ainda não vende. */
    public function futuro(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addDay(), 'ends_at' => now()->addWeeks(2)]);
    }

    /** Terminou ontem. */
    public function encerrado(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subWeeks(2), 'ends_at' => now()->subDay()]);
    }
}
