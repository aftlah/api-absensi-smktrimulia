<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Akun>
 */
class AkunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->numerify('NIS#####'),
            'password' => Hash::make('password'), // default password
            'role' => $this->faker->randomElement(['siswa', 'walas', 'gurket', 'admin']),
        ];
    }
}
