<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class RusakDetectionService
{
    /**
     * Deteksi nomor perforasi hilang (Rusak) per prefix.
     * Sumber data rusak: celah interior pada rentang nomor
     * yang benar-benar ada di baris data (nomor di luar
     * rentang min..max = stok, bukan rusak).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int>> prefix => daftar nomor hilang (urut naik)
     */
    public function detectMissing(array $rows, int $prefixLength = 6): array
    {
        $perforasiByPrefix = [];
        foreach ($rows as $row) {
            $p = $this->normalizeNomor($row['Nomor Perforasi'] ?? '');
            if ($p === '' || ! ctype_digit($p)) {
                continue;
            }
            $pf = substr($p, 0, $prefixLength);
            $perforasiByPrefix[$pf][(int) $p] = true;
        }

        $missing = [];
        foreach ($perforasiByPrefix as $pf => $numSet) {
            $sortedNums = array_keys($numSet);
            sort($sortedNums);
            if (count($sortedNums) < 2) {
                continue;
            }
            $gap = [];
            for ($n = $sortedNums[0]; $n <= $sortedNums[count($sortedNums) - 1]; $n++) {
                if (! isset($numSet[$n])) {
                    $gap[] = $n;
                }
            }
            if ($gap !== []) {
                $missing[$pf] = $gap;
            }
        }

        return $missing;
    }

    /**
     * Deteksi celah nomor per prefix, dengan batas bawah (floor) dari
     * bulan paling awal. Nomor di bawah maksimum bulan sebelumnya
     * dianggap cetak ulang sehingga tidak menurunkan floor. Nomor yang
     * ada di bulan lain tetap dianggap ada (mis. cetak ulang mengganti
     * nomor yang hilang). Tanggal/bulan celah mengikuti nomor berikutnya.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{prefix: string, number: int, month: string, date: ?string}>
     */
    public function detectMissingByMonth(array $rows, int $prefixLength = 6): array
    {
        $parsed = [];
        foreach ($rows as $row) {
            $nomor = $this->normalizeNomor($row['Nomor Perforasi'] ?? '');
            if ($nomor === '' || ! ctype_digit($nomor)) {
                continue;
            }
            $parsed[substr($nomor, 0, $prefixLength)][] = [
                'number' => (int) $nomor,
                'date' => $this->parseTanggalCetak($row['Tanggal Cetak'] ?? null),
            ];
        }

        $gaps = [];
        foreach ($parsed as $prefix => $list) {
            $allNumbers = [];
            $monthNumbers = [];
            $dateByNumber = [];
            foreach ($list as $item) {
                $allNumbers[$item['number']] = true;
                if ($item['date'] !== null) {
                    $dateByNumber[$item['number']] = $item['date'];
                    $monthNumbers[$item['date']->format('Y-m')][$item['number']] = true;
                }
            }
            ksort($monthNumbers);

            $nonLateNumbers = [];
            $prevMax = null;
            foreach ($monthNumbers as $set) {
                foreach (array_keys($set) as $n) {
                    if ($prevMax === null || $n >= $prevMax) {
                        $nonLateNumbers[$n] = true;
                    }
                }
                $prevMax = max($prevMax ?? 0, max(array_keys($set)));
            }

            if ($allNumbers === []) {
                continue;
            }

            $floor = $nonLateNumbers !== []
                ? min(array_keys($nonLateNumbers))
                : min(array_keys($allNumbers));
            $max = max(array_keys($allNumbers));

            for ($n = $floor; $n <= $max; $n++) {
                if (isset($allNumbers[$n])) {
                    continue;
                }
                $next = $n + 1;
                while ($next <= $max && ! isset($allNumbers[$next])) {
                    $next++;
                }
                $date = $next <= $max ? ($dateByNumber[$next] ?? null) : null;

                $gaps[] = [
                    'prefix' => (string) $prefix,
                    'number' => $n,
                    'month' => $date?->format('Y-m') ?? '',
                    'date' => $date?->format('Y-m-d'),
                ];
            }
        }

        usort($gaps, fn (array $a, array $b) => [$a['prefix'], $a['number']] <=> [$b['prefix'], $b['number']]);

        return $gaps;
    }

    /**
     * Hitung buku rusak per bulan: baris berstatus RUSAK (Keterangan
     * "Rusak") ditambah celah nomor perforasi pada bulan tersebut.
     * Nomor yang sama dihitung sekali, baik eksplisit maupun celah.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $bulanList
     * @return array{bulan: array<int, int>, total: int}
     */
    public function countsByMonth(array $rows, int $tahun, array $bulanList, int $prefixLength = 6): array
    {
        $bulanList = array_values(array_unique(array_map('intval', $bulanList)));
        sort($bulanList);

        $result = ['bulan' => [], 'total' => 0];
        foreach ($bulanList as $bulan) {
            $result['bulan'][$bulan] = 0;
        }

        foreach ($this->detectMissingByMonth($rows, $prefixLength) as $gap) {
            if ($gap['date'] === null) {
                continue;
            }
            $date = Carbon::parse($gap['date']);
            if ($date->year !== $tahun) {
                continue;
            }
            $bulan = (int) $date->month;
            if (isset($result['bulan'][$bulan])) {
                $result['bulan'][$bulan]++;
            }
        }

        $seenEksplisit = [];
        foreach ($rows as $row) {
            if (mb_strtolower(trim((string) ($row['Keterangan'] ?? ''))) !== 'rusak') {
                continue;
            }
            $date = $this->parseTanggalCetak($row['Tanggal Cetak'] ?? null);
            if ($date === null || $date->year !== $tahun) {
                continue;
            }
            $bulan = (int) $date->month;
            if (! isset($result['bulan'][$bulan])) {
                continue;
            }
            $nomor = $this->normalizeNomor($row['Nomor Perforasi'] ?? '');
            if ($nomor !== '') {
                if (isset($seenEksplisit[$nomor])) {
                    continue;
                }
                $seenEksplisit[$nomor] = true;
            }
            $result['bulan'][$bulan]++;
        }

        $result['total'] = array_sum($result['bulan']);

        return $result;
    }

    private function parseTanggalCetak(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeNomor(mixed $value): string
    {
        $nomor = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) $value)));

        return is_string($nomor) ? $nomor : '';
    }
}
