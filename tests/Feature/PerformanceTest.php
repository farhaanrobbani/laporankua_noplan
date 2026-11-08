<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        return $count;
    }

    public function test_dashboard_tidak_n_plus_satu(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 8) as $i) {
            $import = Import::factory()->for($user)->create();
            ImportData::factory()->for($import)->count(3)->create();
            Report::factory()->for($user)->for($import)->create();
        }

        $queries = $this->countQueries(function () use ($user) {
            $this->actingAs($user)->get('/dashboard')->assertOk();
        });

        // 3 count statistik + 2 list (import+count, report) + sesi/auth wajar.
        $this->assertLessThanOrEqual(12, $queries, "Dashboard memakai {$queries} query (indikasi N+1).");
    }

    public function test_daftar_import_query_wajar(): void
    {
        $user = User::factory()->create();
        Import::factory()->for($user)->count(15)->create();

        $queries = $this->countQueries(function () use ($user) {
            $this->actingAs($user)->get('/imports')->assertOk();
        });

        // Pagination 10 + withCount + auth/sesi; tidak tumbuh per baris.
        $this->assertLessThanOrEqual(14, $queries, "Daftar import memakai {$queries} query (indikasi N+1).");
    }

    public function test_daftar_data_query_wajar(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->for($user)->create(['status' => 'success']);
        ImportData::factory()->for($import)->count(30)->create();

        $queries = $this->countQueries(function () use ($user, $import) {
            $this->actingAs($user)->get('/data/data-import?import_id='.$import->id)->assertOk();
        });

        // availableColumns (1) + paginate data (1) + count (1) + picker + auth/sesi.
        $this->assertLessThanOrEqual(16, $queries, "Halaman data memakai {$queries} query (indikasi N+1).");
    }

    public function test_build_dataset_1000_baris_selesai_cepat(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->for($user)->create(['status' => 'success']);

        $now = now()->toDateTimeString();
        $rows = [];
        foreach (range(1, 1000) as $i) {
            $rows[] = [
                'import_id' => $import->id,
                'row_data' => json_encode(['Nama' => 'Baris '.$i, 'Nilai' => $i]),
                'row_number' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            ImportData::insert($chunk);
        }

        $start = microtime(true);
        $dataset = app(ReportGenerationService::class)->buildDataset(
            $import, ['Nama', 'Nilai'], null, null, null, 'Nama', 'asc'
        );
        $elapsed = microtime(true) - $start;

        $this->assertSame(1000, $dataset['total']);
        $this->assertSame('Baris 1', $dataset['rows'][0]['Nama']);
        // Batas longgar untuk lingkungan CI/dev; fokus mencegah regresi O(n^2).
        $this->assertLessThan(5.0, $elapsed, sprintf('buildDataset 1000 baris memakan %.2fs.', $elapsed));
    }
}
