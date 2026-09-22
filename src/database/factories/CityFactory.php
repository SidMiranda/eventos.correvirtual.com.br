<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        $nome = $this->faker->unique()->city();

        return [
            'ibge_code' => $this->faker->unique()->numberBetween(1000000, 5999999),
            'name' => $nome,
            'name_normalized' => City::normalizar($nome),
            'state' => $this->faker->randomElement(['SP', 'MG', 'RJ', 'PR', 'RS']),
        ];
    }

    /** Uma cidade com nome certo — para o teste poder procurar por ele. */
    public function chamada(string $nome, string $uf = 'SP'): static
    {
        return $this->state(fn () => [
            'name' => $nome,
            'name_normalized' => City::normalizar($nome),
            'state' => $uf,
        ]);
    }
}
