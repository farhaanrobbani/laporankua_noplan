<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ImportData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class L3ReportService
{
    private const TABLE_NAME = 'laporan model l3';

    private const MONTH_TOLERANCE = 1;

    /**
     * @return array{imports: int, processed: int, updated: int, duplicates: int, skipped: int}
     */
    public function reprocessUser(int $userId, bool $dryRun = false): array
    {
        $imports = Import::query()
            ->where('user_id', $userId)
            ->where('table_name', self::TABLE_NAME)
            ->whereIn('status', ['success', 'appended'])
            ->get()
            ->sort(function (Import $a, Import $b): int {
                return [
                    $this->fileMonthOrder($a),
                    $a->created_at?->getTimestamp() ?? 0,
                    $a->id,
                ] <=> [
                    $this->fileMonthOrder($b),
                    $b->created_at?->getTimestamp() ?? 0,
                    $b->id,
                ];
            })
            ->values();

        $seenKeys = [];
        $stats = [
            'imports' => 0,
            'processed' => 0,
            'updated' => 0,
            'duplicates' => 0,
            'skipped' => 0,
        ];

        foreach ($imports as $import) {
            $fileDate = Import::parseFileDate($import->file_name);

            if ($fileDate === null) {
                $stats['skipped']++;

                continue;
            }

            $rows = $import->importData()->orderBy('row_number')->get();

            if ($rows->isEmpty() || ! $this->hasTanggalAkad($rows)) {
                $stats['skipped']++;

                continue;
            }

            $stats['imports']++;
            $refDate = Carbon::createFromDate($fileDate['year'], $fileDate['month'], 1)->startOfDay();
            $refEnd = $refDate->copy()->endOfMonth();
            $windowStart = $refDate->copy()->subMonthsNoOverflow(self::MONTH_TOLERANCE)->startOfMonth();
            $windowEnd = $refDate->copy()->addMonthsNoOverflow(self::MONTH_TOLERANCE)->endOfMonth();
            $prevTanggalCetak = $refDate->toDateString();
            $now = now()->toDateTimeString();
            $currentKeys = [];

            foreach ($rows as $row) {
                $data = $this->rowData($row);

                if (! array_key_exists('Tanggal Akad', $data)) {
                    continue;
                }

                $statusRusak = $this->isRusak($data);
                $tanggalAkad = $data['Tanggal Akad'];
                $akadDate = $this->normalizeDate($tanggalAkad);
                $akadState = $this->akadState($akadDate, $refDate, $refEnd, $windowEnd);
                $eventKey = $this->eventKey($data, $akadDate);
                $duplicate = ! $statusRusak && $this->isDuplicate(
                    $eventKey,
                    $akadDate,
                    $windowStart,
                    $windowEnd,
                    $seenKeys,
                );

                $newData = $data;

                if ($statusRusak) {
                    $newData['Tanggal Cetak'] = $prevTanggalCetak;
                    $newData['Keterangan'] = 'Rusak';
                } else {
                    if ($akadState === 'sesuai' || $akadState === 'maju') {
                        $newData['Tanggal Cetak'] = $tanggalAkad;

                        if ($akadState === 'sesuai') {
                            $prevTanggalCetak = $tanggalAkad;
                        }
                    } else {
                        $newData['Tanggal Cetak'] = $prevTanggalCetak;
                    }

                    $newData['Keterangan'] = $duplicate ? 'Duplikat' : 'Bukan Duplikat';
                }

                if (! $statusRusak && $eventKey !== null) {
                    $currentKeys[$eventKey] = true;
                }

                $stats['processed']++;

                if ($duplicate) {
                    $stats['duplicates']++;
                }

                if ($data != $newData) {
                    $stats['updated']++;

                    if (! $dryRun) {
                        $row->update([
                            'row_data' => $newData,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            foreach ($currentKeys as $key => $_) {
                $seenKeys[$key] = true;
            }
        }

        return $stats;
    }

    private function fileMonthOrder(Import $import): int
    {
        $fileDate = Import::parseFileDate($import->file_name);

        if ($fileDate === null) {
            return PHP_INT_MAX;
        }

        return ($fileDate['year'] * 12) + $fileDate['month'];
    }

    /**
     * @param  Collection<int, ImportData>  $rows
     */
    private function hasTanggalAkad($rows): bool
    {
        foreach ($rows as $row) {
            if (array_key_exists('Tanggal Akad', $this->rowData($row))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowData(ImportData $row): array
    {
        $data = $row->row_data;

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isRusak(array $data): bool
    {
        return mb_strtoupper(trim((string) ($data['Status'] ?? ''))) === 'RUSAK';
    }

    private function akadState(?Carbon $date, Carbon $refDate, Carbon $refEnd, Carbon $windowEnd): string
    {
        if ($date === null) {
            return 'invalid';
        }

        if ($date->year === $refDate->year && $date->month === $refDate->month) {
            return 'sesuai';
        }

        if ($date->greaterThan($windowEnd)) {
            return 'invalid';
        }

        return $date->greaterThan($refEnd) ? 'maju' : 'lampau';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function eventKey(array $data, ?Carbon $date): ?string
    {
        $name = $this->normalizeName((string) ($data['Nama Catin'] ?? ''));

        if ($name === '' || $date === null) {
            return null;
        }

        return $name.'|'.$date->toDateString();
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*-\s*/u', ' - ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_int($value) || is_float($value)) {
            return $this->excelSerialToDate((float) $value);
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $date = $this->excelSerialToDate((float) $raw);

            if ($date !== null) {
                return $date;
            }
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y'] as $format) {
            if (Carbon::canBeCreatedFromFormat($raw, $format)) {
                return Carbon::createFromFormat('!'.$format, $raw)->startOfDay();
            }
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function excelSerialToDate(float $serial): ?Carbon
    {
        if ($serial < 1 || $serial >= 60000) {
            return null;
        }

        return Carbon::createFromDate(1899, 12, 30)->addDays((int) $serial)->startOfDay();
    }

    /**
     * @param  array<string, bool>  $seenKeys
     */
    private function isDuplicate(
        ?string $eventKey,
        ?Carbon $akadDate,
        Carbon $windowStart,
        Carbon $windowEnd,
        array $seenKeys,
    ): bool {
        if ($eventKey === null || $akadDate === null) {
            return true;
        }

        if (isset($seenKeys[$eventKey])) {
            return true;
        }

        return $akadDate->lessThan($windowStart) || $akadDate->greaterThan($windowEnd);
    }
}
