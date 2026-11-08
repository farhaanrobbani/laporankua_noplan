<?php

namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Jobs\ProcessExcelImport;
use App\Models\Import;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_excel_import_failed_menandai_import(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->for($user)->create(['status' => 'processing']);

        (new ProcessExcelImport($import))->failed(new \RuntimeException('koneksi putus'));

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertNotEmpty($import->error_log);
        $this->assertStringContainsString('koneksi putus', $import->error_log[0]['reason']);
    }

    public function test_generate_report_failed_menandai_laporan(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->for($user)->create();
        $report = Report::factory()->for($user)->for($import)->create(['status' => 'processing']);

        (new GenerateReport($report))->failed(new \RuntimeException('dompdf error'));

        $this->assertSame('failed', $report->fresh()->status);
    }
}
