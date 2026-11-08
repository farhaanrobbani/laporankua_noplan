<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(10, 500);
        $imported = fake()->numberBetween(0, $total);

        $fileName = fake()->word().'.xlsx';

        return [
            'user_id' => User::factory(),
            'file_name' => $fileName,
            'table_name' => Import::parseTableName($fileName),
            'file_path' => 'imports/'.fake()->uuid().'.xlsx',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'sheet_name' => 'Sheet1',
            'total_rows' => $total,
            'imported_rows' => $imported,
            'failed_rows' => $total - $imported,
            'status' => 'success',
            'imported_at' => now(),
        ];
    }
}
