<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ExcelImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessExcelImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Import $import,
    ) {}

    public function handle(ExcelImportService $service): void
    {
        $service->import($this->import->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update([
            'status' => 'failed',
            'error_log' => [
                ['row' => 0, 'reason' => 'Import gagal setelah 3x percobaan. '.($exception?->getMessage() ?? '')],
            ],
        ]);
    }
}
