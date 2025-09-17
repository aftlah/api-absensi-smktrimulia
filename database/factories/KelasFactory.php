<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Kelas>
 */
class KelasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tingkat' => $this->faker->randomElement(['X', 'XI', 'XII']),
            'jurusan' => $this->faker->randomElement(['TKJ', 'OTKP', 'BDP']),
            'paralel' => $this->faker->numberBetween(1, 3),
        ];
    }
}
