<?php

namespace Database\Factories;

use App\Models\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportDataFactory extends Factory
{
    public function definition(): array
    {
        return [
            'import_id' => Import::factory(),
            'row_data' => [
                'nama' => fake()->name(),
                'tanggal' => fake()->date(),
                'nilai' => fake()->numberBetween(1000, 1000000),
            ],
            'row_number' => fake()->numberBetween(1, 500),
        ];
    }
}
