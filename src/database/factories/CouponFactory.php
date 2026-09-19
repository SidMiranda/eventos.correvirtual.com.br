<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            // 6 caracteres A-Z0-9, dentro do formato aceito pela validação.
            'code' => mb_strtoupper($this->faker->unique()->bothify('??####')),
            'description' => null,
            'discount_type' => Coupon::TIPO_PERCENTUAL,
            'discount_value' => 10,
            'total_quantity' => 10,
            'used_quantity' => 0,
            'expires_at' => now()->addDays(30)->toDateString(),
            'active' => true,
        ];
    }

    public function vencido(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()->toDateString()]);
    }

    public function esgotado(): static
    {
        return $this->state(fn (array $atributos) => [
            'used_quantity' => $atributos['total_quantity'],
        ]);
    }

    public function usado(int $vezes = 1): static
    {
        return $this->state(fn () => ['used_quantity' => $vezes]);
    }

    public function emReais(float $valor): static
    {
        return $this->state(fn () => [
            'discount_type' => Coupon::TIPO_VALOR,
            'discount_value' => $valor,
        ]);
    }
}
