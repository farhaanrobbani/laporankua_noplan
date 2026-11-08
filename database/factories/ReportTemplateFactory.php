<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'output_format' => fake()->randomElement(['pdf', 'word', 'excel', 'print']),
            'fields_json' => ['nama', 'tanggal', 'nilai'],
            'filters_json' => [],
            'sorting_json' => ['column' => 'tanggal', 'direction' => 'asc'],
            'layout_json' => ['orientation' => 'portrait'],
            'is_default' => false,
        ];
    }
}
