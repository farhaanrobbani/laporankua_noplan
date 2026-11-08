<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ImportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelImportService
{
    public const PREVIEW_LIMIT = 100;

    public const MAX_ROWS = 50000;

    public const BATCH_SIZE = 500;

    public const MAX_LOGGED_ERRORS = 50;

    /**
     * Mapping nama kolom Excel → nama kolom database per table_name.
     * Digunakan untuk me-rename kolom yang sedikit berbeda saat import.
     *
     * @var array<string, array<string, string>>
     */
    private const COLUMN_ALIASES = [
        'laporan peristiwa nikah' => [
            'No. Daftar' => 'Nomor Daftar',
            'Petugas' => 'Penghulu',
        ],
        'laporan pendaftaran nikah' => [
            'Tanggal Akad' => 'Tanggal Nikah',
            'No. Daftar' => 'Nomor Daftar',
        ],
    ];

    /**
     * Daftar nama sheet dalam file Excel.
     *
     * @return string[]
     */
    public function getSheetNames(string $absolutePath): array
    {
        return IOFactory::load($absolutePath)->getSheetNames();
    }

    /**
     * Pratinjau header + baris pertama sebuah sheet.
     *
     * @return array{headers: string[], rows: array<int, array<int, mixed>>, total: int, sheet: string}
     */
    public function previewRows(string $absolutePath, ?string $sheetName = null, int $limit = self::PREVIEW_LIMIT): array
    {
        $sheet = $this->resolveSheet($absolutePath, $sheetName);

        $allRows = $this->dataRows($sheet);
        $headers = $this->normalizeHeaders(array_shift($allRows) ?? []);

        return [
            'headers' => $headers,
            'rows' => array_slice($allRows, 0, $limit),
            'total' => count($allRows),
            'sheet' => $sheet->getTitle(),
        ];
    }

    /**
     * Proses import penuh sebuah record Import (dipanggil dari Job).
     */
    public function import(Import $import): void
    {
        $import->update(['status' => 'processing', 'imported_at' => now()]);

        $absolutePath = Storage::disk('local')->path($import->file_path);

        if (! is_file($absolutePath)) {
            $this->markFailed($import, 0, 0, [['row' => 0, 'reason' => 'File Excel tidak ditemukan di storage.']]);

            return;
        }

        try {
            $sheet = $this->resolveSheet($absolutePath, $import->sheet_name);
            $rawRows = $this->dataRows($sheet);
        } catch (\Throwable $e) {
            report($e);
            $this->markFailed($import, 0, 0, [['row' => 0, 'reason' => 'File Excel tidak dapat dibaca.']]);

            return;
        }

        $headers = $this->normalizeHeaders(array_shift($rawRows) ?? []);
        $headers = $this->applyAliases($import->table_name, $headers);
        $totalRows = count($rawRows);

        if ($totalRows === 0) {
            $this->markFailed($import, 0, 0, [['row' => 0, 'reason' => 'Sheet tidak memiliki baris data.']]);

            return;
        }

        if ($totalRows > self::MAX_ROWS) {
            $this->markFailed($import, $totalRows, 0, [
                ['row' => 0, 'reason' => 'Jumlah baris ('.number_format($totalRows).') melebihi batas '.number_format(self::MAX_ROWS).' baris.'],
            ]);

            return;
        }

        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $overwritten = 0;
        $errors = [];
        $batch = [];
        $now = now()->toDateTimeString();

        $isAppend = (bool) $import->is_append;
        $appendExistingKeys = [];

        $dedupColumnRaw = $import->dedup_column;
        $dedupColumn = $dedupColumnRaw;
        if ($dedupColumn !== null && $dedupColumn !== '' && isset(self::COLUMN_ALIASES[$import->table_name][$dedupColumn])) {
            $dedupColumn = self::COLUMN_ALIASES[$import->table_name][$dedupColumn];
        }
        $dedupImportIds = [];

        if ($dedupColumnRaw !== null && $dedupColumnRaw !== '') {
            $dedupImportIds = Import::where('user_id', $import->user_id)
                ->where('table_name', $import->table_name)
                ->where('dedup_column', $dedupColumnRaw)
                ->where('id', '!=', $import->id)
                ->pluck('id')
                ->toArray();
        }

        // Mode append: kunci duplikat dari seluruh import sebelumnya (rentetan file
        // yang sama maupun nama file berbeda), bukan hanya import terakhir.
        if ($isAppend && $dedupImportIds !== []) {
            $appendExistingKeys = ImportData::whereIn('import_id', $dedupImportIds)
                ->whereNotNull('dedup_key_value')
                ->pluck('dedup_key_value')
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();

            if ($appendExistingKeys === [] && $dedupColumn !== null && $dedupColumn !== '') {
                $appendExistingKeys = ImportData::whereIn('import_id', $dedupImportIds)
                    ->get()
                    ->map(fn ($model) => $model->row_data[$dedupColumn] ?? null)
                    ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
                    ->map(fn ($v) => (string) $v)
                    ->values()
                    ->all();
            }
        }

        DB::transaction(function () use ($import, $headers, $rawRows, $totalRows, $now, $isAppend, $appendExistingKeys, $dedupColumn, $dedupImportIds, &$imported, &$failed, &$skipped, &$overwritten, &$errors, &$batch) {
            foreach ($rawRows as $index => $row) {
                $excelRow = $index + 2;

                $record = $this->mapRow($headers, $row);

                if ($record === null) {
                    $failed++;
                    if (count($errors) < self::MAX_LOGGED_ERRORS) {
                        $errors[] = ['row' => $excelRow, 'reason' => 'Baris kosong.'];
                    }

                    continue;
                }

                $dedupKeyValue = null;

                if ($dedupColumn !== null && $dedupColumn !== '' && isset($record[$dedupColumn])) {
                    $dedupKeyValue = (string) $record[$dedupColumn];

                    if (trim($dedupKeyValue) === '') {
                        $dedupKeyValue = null;
                    }
                }

                // Mode append: skip baris yang sudah ada di import existing
                if ($isAppend && $dedupKeyValue !== null && $appendExistingKeys !== []) {
                    if (in_array((string) $dedupKeyValue, $appendExistingKeys)) {
                        $skipped++;

                        continue;
                    }
                }

                // Mode normal: overwrite baris yang sama di import lain
                if (! $isAppend && $dedupKeyValue !== null && $dedupImportIds !== []) {
                    $existing = ImportData::where('dedup_key_value', $dedupKeyValue)
                        ->whereIn('import_id', $dedupImportIds)
                        ->first();

                    if ($existing !== null) {
                        $existing->update([
                            'row_data' => $record,
                            'import_id' => $import->id,
                            'dedup_key_value' => $dedupKeyValue,
                            'updated_at' => $now,
                        ]);
                        $overwritten++;

                        continue;
                    }
                }

                $batch[] = [
                    'import_id' => $import->id,
                    'row_data' => json_encode($record, JSON_UNESCAPED_UNICODE),
                    'row_number' => $excelRow,
                    'dedup_key_value' => $dedupKeyValue,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $imported++;

                if (count($batch) >= self::BATCH_SIZE) {
                    ImportData::insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                ImportData::insert($batch);
            }

            $import->update([
                'status' => $isAppend ? 'appended' : 'success',
                'total_rows' => $totalRows,
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'error_log' => $errors === [] ? null : $errors,
            ]);
        });

        if ($import->status === 'appended' && (int) $import->imported_rows === 0) {
            $this->deleteEmptyAppend($import);

            return;
        }

        if ($import->status === 'success' || $import->status === 'appended') {
            $this->postProcessModelL3($import);
        }
    }

    private function deleteEmptyAppend(Import $import): void
    {
        $filePath = $import->file_path;
        $fileName = $import->file_name;
        $userId = $import->user_id;

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        $import->delete();

        Cache::put(
            'import_notice:'.$userId,
            ['message' => 'File "'.$fileName.'" dihapus otomatis karena tidak ada data baru yang diambil.'],
            now()->addMinutes(10),
        );
    }

    private function markFailed(Import $import, int $total, int $imported, array $errors): void
    {
        $import->update([
            'status' => 'failed',
            'total_rows' => $total,
            'imported_rows' => $imported,
            'failed_rows' => $total - $imported,
            'error_log' => $errors,
        ]);
    }

    private function resolveSheet(string $absolutePath, ?string $sheetName): Worksheet
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);

        if ($sheetName !== null && $spreadsheet->sheetNameExists($sheetName)) {
            return $spreadsheet->getSheetByName($sheetName);
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * Semua baris sheet sebagai array numerik.
     * Baris kosong di tengah data dipertahankan (dihitung gagal saat import),
     * baris kosong di akhir sheet dibuang.
     *
     * @return array<int, array<int, mixed>>
     */
    private function dataRows(Worksheet $sheet): array
    {
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $row) {
            if (is_array($row)) {
                $rows[] = array_values($row);
            }
        }

        while ($rows !== [] && $this->isEmptyRow(end($rows))) {
            array_pop($rows);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $rawHeaders
     * @return string[]
     */
    private function normalizeHeaders(array $rawHeaders): array
    {
        $headers = [];
        $seen = [];

        foreach (array_values($rawHeaders) as $index => $value) {
            $name = trim((string) ($value ?? ''));

            if ($name === '') {
                $name = 'Kolom '.Coordinate::stringFromColumnIndex($index + 1);
            }

            if (isset($seen[$name])) {
                $seen[$name]++;
                $name .= ' ('.$seen[$name].')';
            } else {
                $seen[$name] = 1;
            }

            $headers[] = $name;
        }

        return $headers;
    }

    /**
     * Rename headers berdasarkan COLUMN_ALIASES per table_name.
     *
     * @param  string[]  $headers
     * @return string[]
     */
    private function applyAliases(string $tableName, array $headers): array
    {
        $aliases = self::COLUMN_ALIASES[$tableName] ?? [];

        if ($aliases === []) {
            return $headers;
        }

        return array_map(fn (string $h) => $aliases[$h] ?? $h, $headers);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>|null null jika baris kosong
     */
    private function mapRow(array $headers, array $row): ?array
    {
        $values = array_values($row);
        $record = [];
        $hasValue = false;

        foreach ($headers as $index => $header) {
            $value = $values[$index] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                $hasValue = true;
            }

            $record[$header] = $value;
        }

        return $hasValue ? $record : null;
    }

    /**
     * Post-process untuk laporan model L3: tambah kolom "Tanggal Cetak" dan "Keterangan".
     *
     * - Parse nama file untuk dapat bulan/tahun acuan
     * - Tanggal Akad sesuai bulan/tahun acuan → Tanggal Cetak = Tanggal Akad, Keterangan = "Bukan Duplikat"
     * - Tanggal Akad setelah bulan acuan (cetak maju) → Tanggal Cetak = Tanggal Akad,
     *   Keterangan = "Bukan Duplikat", baris pindah ke bulan akad sehingga tidak terhitung
     *   pada bulan file; baris berikutnya tetap mewarisi Tanggal Cetak baris sebelumnya
     * - Status = "RUSAK" (buku rusak, Tanggal Akad kosong) → Tanggal Cetak =
     *   Tanggal Cetak baris sebelumnya, Keterangan = "Rusak" (bukan Duplikat)
     * - Tanggal Akad sebelum bulan acuan (cetak ulang/tanpa tanggal) → Tanggal Cetak =
     *   Tanggal Cetak baris sebelumnya (atau tanggal dari file), Keterangan = "Duplikat"
     */
    private function postProcessModelL3(Import $import): void
    {
        if ($import->table_name !== 'laporan model l3') {
            return;
        }

        $fileDate = Import::parseFileDate($import->file_name);

        if ($fileDate === null) {
            return;
        }

        $rows = $import->importData()->orderBy('row_number')->get();

        if ($rows->isEmpty()) {
            return;
        }

        $hasTanggalAkad = false;

        foreach ($rows as $row) {
            $data = is_string($row->row_data) ? json_decode($row->row_data, true) : $row->row_data;
            if (is_array($data) && array_key_exists('Tanggal Akad', $data)) {
                $hasTanggalAkad = true;
                break;
            }
        }

        if (! $hasTanggalAkad) {
            return;
        }

        $refDate = Carbon::createFromDate($fileDate['year'], $fileDate['month'], 1);
        $refEnd = $refDate->copy()->endOfMonth();
        $refMonth = $fileDate['month'];
        $refYear = $fileDate['year'];
        $prevTanggalCetak = $refDate->toDateString();
        $now = now()->toDateTimeString();

        foreach ($rows as $row) {
            $data = is_string($row->row_data) ? json_decode($row->row_data, true) : $row->row_data;

            if (! is_array($data) || ! array_key_exists('Tanggal Akad', $data)) {
                continue;
            }

            $statusRusak = mb_strtoupper(trim((string) ($data['Status'] ?? ''))) === 'RUSAK';
            $tanggalAkad = $data['Tanggal Akad'];
            $akadState = 'lampau';

            if ($tanggalAkad !== null && $tanggalAkad !== '') {
                try {
                    $date = Carbon::parse($tanggalAkad);
                    if ($date->month === $refMonth && $date->year === $refYear) {
                        $akadState = 'sesuai';
                    } elseif ($date->greaterThan($refEnd)) {
                        $akadState = 'maju';
                    }
                } catch (\Throwable) {
                    // tanggal tidak bisa di-parse → anggap cetak ulang (lampau)
                }
            }

            $newData = [];

            foreach ($data as $key => $value) {
                $newData[$key] = $value;

                if ($key === 'Tanggal Akad') {
                    if ($statusRusak) {
                        $newData['Tanggal Cetak'] = $prevTanggalCetak;
                        $newData['Keterangan'] = 'Rusak';
                    } elseif ($akadState === 'lampau') {
                        $newData['Tanggal Cetak'] = $prevTanggalCetak;
                        $newData['Keterangan'] = 'Duplikat';
                    } else {
                        $newData['Tanggal Cetak'] = $tanggalAkad;
                        $newData['Keterangan'] = 'Bukan Duplikat';

                        if ($akadState === 'sesuai') {
                            $prevTanggalCetak = $tanggalAkad;
                        }
                    }
                }
            }

            if (! array_key_exists('Tanggal Cetak', $newData)) {
                $newData['Tanggal Cetak'] = $prevTanggalCetak;
                $newData['Keterangan'] = $statusRusak
                    ? 'Rusak'
                    : ($akadState === 'lampau' ? 'Duplikat' : 'Bukan Duplikat');
            }

            $row->update([
                'row_data' => $newData,
                'updated_at' => $now,
            ]);
        }
    }
}
