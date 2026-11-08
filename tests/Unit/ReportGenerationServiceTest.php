<?php

namespace Tests\Unit;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use App\Services\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function importWithRows(User $user): Import
    {
        $import = Import::factory()->for($user)->create(['status' => 'success']);

        foreach ([['Budi', 100], ['Siti', 200], ['Andi', 300]] as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => ['Nama' => $row[0], 'Nilai' => $row[1]],
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    public function test_dataset_hanya_memuat_field_terpilih(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $dataset = app(ReportGenerationService::class)->buildDataset($import, ['Nama']);

        $this->assertSame(['Nama'], $dataset['headings']);
        $this->assertSame(3, $dataset['total']);
        $this->assertArrayNotHasKey('Nilai', $dataset['rows'][0]);
        $this->assertSame('Budi', $dataset['rows'][0]['Nama']);
    }

    public function test_sort_tidak_valid_fallback_ke_row_number(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $dataset = app(ReportGenerationService::class)->buildDataset(
            $import, ['Nama'], null, null, null, '1; DROP TABLE users', 'desc'
        );

        // sort_column liar diabaikan → urut row_number desc
        $this->assertSame(['Andi', 'Siti', 'Budi'], array_column($dataset['rows'], 'Nama'));
    }

    public function test_limit_membatasi_baris(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $dataset = app(ReportGenerationService::class)->buildDataset($import, ['Nama'], limit: 2);

        $this->assertSame(2, $dataset['total']);
    }

    public function test_search_dan_sort_berlaku_di_dataset(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $dataset = app(ReportGenerationService::class)->buildDataset(
            $import, ['Nama', 'Nilai'], 'di', null, null, 'Nama', 'desc'
        );

        // 'di' hanya cocok Budi & Andi (case-insensitive), urut Nama desc
        $this->assertSame(['Budi', 'Andi'], array_column($dataset['rows'], 'Nama'));
    }
}
