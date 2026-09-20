<?php

namespace Database\Factories;

use App\Models\Organizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Photo>
 */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'caption' => $this->faker->sentence(3),
            'link_url' => null,
            'position' => 0,
            'active' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function comLink(string $url = 'https://www.instagram.com/p/abc123/'): static
    {
        return $this->state(fn () => ['link_url' => $url]);
    }
}
