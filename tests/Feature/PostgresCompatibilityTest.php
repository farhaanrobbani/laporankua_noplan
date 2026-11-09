<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filter_and_date_sort_work_on_postgresql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Test ini hanya berjalan pada PostgreSQL.');
        }

        $user = User::factory()->create();

        $import = Import::factory()->for($user)->create([
            'table_name' => 'laporan model l3',
            'default_sort_column' => 'Tanggal Nikah',
            'default_sort_direction' => 'desc',
        ]);

        foreach ([
            ['Nama' => 'Budi', 'Tanggal Nikah' => '05-10-2026'],
            ['Nama' => 'Siti', 'Tanggal Nikah' => '20-09-2026'],
            ['Nama' => 'Andi', 'Tanggal Nikah' => '2026-01-15'],
            ['Nama' => 'Rusak', 'Tanggal Nikah' => 'bukan-tanggal'],
        ] as $index => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => $row,
                'row_number' => $index + 2,
            ]);
        }

        $searchResults = ImportData::forImport($import->id)
            ->search('budi')
            ->get()
            ->map(fn (ImportData $record) => $record->row_data['Nama'])
            ->all();

        $this->assertSame(['Budi'], $searchResults);

        $filterResults = ImportData::forImport($import->id)
            ->filterColumn('Nama', 'sit')
            ->get()
            ->map(fn (ImportData $record) => $record->row_data['Nama'])
            ->all();

        $this->assertSame(['Siti'], $filterResults);

        $sortedResults = ImportData::forImport($import->id)
            ->sortBy('Tanggal Nikah', 'desc')
            ->get()
            ->map(fn (ImportData $record) => $record->row_data['Nama'])
            ->all();

        $this->assertSame(['Budi', 'Siti', 'Andi', 'Rusak'], $sortedResults);
    }
}
