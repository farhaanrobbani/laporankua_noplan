<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\ReportGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Report $report) {}

    public function handle(ReportGenerationService $service): void
    {
        $service->generate($this->report->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        $this->report->update(['status' => 'failed']);
    }
}
