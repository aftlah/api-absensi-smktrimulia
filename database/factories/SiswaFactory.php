<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Siswa>
 */
class SiswaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nis' => $this->faker->unique()->numerify('2025###'),
            'nama' => $this->faker->name(),
            'akun_id' => \App\Models\Akun::factory()->create(['role' => 'siswa'])->akun_id,
            'kelas_id' => \App\Models\Kelas::factory(),
        ];
    }
}
