<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportGenerationService
{
    public const EXTENSIONS = [
        'pdf' => 'pdf',
        'word' => 'docx',
        'excel' => 'xlsx',
    ];

    public function __construct(
        protected PdfService $pdfService,
        protected WordService $wordService,
        protected ExcelExportService $excelExportService,
    ) {}

    /**
     * Dataset laporan: judul + kolom terpilih + baris yang sudah difilter/diurutkan.
     *
     * @param  string[]  $fields
     * @return array{title: string, headings: string[], rows: array<int, array<string, mixed>>, total: int, generated_at: string}
     */
    public function buildDataset(
        Import $import,
        array $fields,
        ?string $search = null,
        ?string $filterColumn = null,
        ?string $filterValue = null,
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        ?int $limit = null
    ): array {
        if ($sortColumn !== null && $sortColumn !== '' && $sortColumn !== 'row_number' && ! in_array($sortColumn, $fields, true)) {
            $sortColumn = null;
        }

        $query = ImportData::forImport($import->id)
            ->search($search)
            ->filterColumn($filterColumn, $filterValue)
            ->sortBy($sortColumn, $sortDirection)
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = $query->cursor()
            ->map(function (ImportData $record) use ($fields) {
                $rowData = $record->row_data ?? [];
                $row = [];
                foreach ($fields as $field) {
                    $row[$field] = $rowData[$field] ?? null;
                }

                return $row;
            })
            ->all();

        return [
            'title' => $import->file_name,
            'headings' => array_values($fields),
            'rows' => $rows,
            'total' => count($rows),
            'generated_at' => now()->format('d M Y H:i'),
        ];
    }

    /**
     * Generate file laporan dari record Report (dipanggil dari Job).
     */
    public function generate(Report $report): void
    {
        $report->update(['status' => 'processing']);

        $config = $report->config_json ?? [];
        $import = $report->import;

        if (! $import) {
            $report->update(['status' => 'failed']);

            return;
        }

        $fields = array_values(array_filter($config['fields'] ?? []));

        if ($fields === [] || ($report->output_format === 'print')) {
            if ($report->output_format === 'print') {
                $report->update(['status' => 'generated', 'generated_at' => now()]);

                return;
            }

            $report->update(['status' => 'failed']);

            return;
        }

        try {
            if (! empty($config['is_merged']) && ! empty($config['merged_import_ids'])) {
                $dataset = app(MergeService::class)->buildMergedDataset(
                    $config['merged_import_ids'],
                    $fields,
                    $config['search'] ?? null,
                    $config['filter_column'] ?? null,
                    $config['filter_value'] ?? null,
                    $config['sort_column'] ?? null,
                    $config['sort_direction'] ?? 'asc',
                );
            } elseif (! empty($config['merged_import_ids'])) {
                $dataset = app(MergeService::class)->buildConcatDataset(
                    $config['merged_import_ids'],
                    $fields,
                    $config['search'] ?? null,
                    $config['filter_column'] ?? null,
                    $config['filter_value'] ?? null,
                    $config['sort_column'] ?? null,
                    $config['sort_direction'] ?? 'asc',
                );
            } else {
                $dataset = $this->buildDataset(
                    $import,
                    $fields,
                    $config['search'] ?? null,
                    $config['filter_column'] ?? null,
                    $config['filter_value'] ?? null,
                    $config['sort_column'] ?? null,
                    $config['sort_direction'] ?? 'asc',
                );
            }
            $dataset['title'] = $report->title;

            $extension = self::EXTENSIONS[$report->output_format] ?? null;

            if ($extension === null) {
                $report->update(['status' => 'failed']);

                return;
            }

            $relativePath = 'reports/user_'.$report->user_id.'/report_'.$report->id.'/laporan.'.$extension;
            $absolutePath = Storage::disk('local')->path($relativePath);

            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            match ($report->output_format) {
                'pdf' => $this->pdfService->generate($dataset, $absolutePath, $config['orientation'] ?? 'portrait'),
                'word' => $this->wordService->generate($dataset, $absolutePath),
                'excel' => $this->excelExportService->generate($dataset, $relativePath),
            };

            DB::transaction(function () use ($report, $relativePath) {
                $report->update([
                    'status' => 'generated',
                    'file_path' => $relativePath,
                    'file_size' => Storage::disk('local')->size($relativePath),
                    'generated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);
            $report->update(['status' => 'failed']);
        }
    }
}
