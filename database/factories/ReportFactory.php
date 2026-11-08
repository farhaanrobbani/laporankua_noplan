<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'report_template_id' => null,
            'import_id' => Import::factory(),
            'title' => fake()->words(4, true),
            'description' => fake()->sentence(),
            'output_format' => fake()->randomElement(['pdf', 'word', 'excel']),
            'file_path' => null,
            'file_size' => null,
            'status' => 'generated',
            'generated_at' => now(),
        ];
    }
}
