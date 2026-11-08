<?php

namespace App\Http\Controllers;

use App\Exports\ImportDataExport;
use App\Models\Import;
use App\Models\ImportData;
use App\Services\ReportGenerationService;
use App\Services\RusakDetectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataController extends Controller
{
    use AuthorizesRequests;

    private const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function index(Request $request): View
    {
        $imports = Import::where('user_id', auth()->id())
            ->withCount('importData')
            ->latest()
            ->get();

        $grouped = $imports->groupBy('table_name')->map(function ($rows, $tableName) {
            return [
                'table_name' => $tableName,
                'total_rows' => $rows->sum('import_data_count'),
                'import_ids' => $rows->pluck('id')->toArray(),
            ];
        })->values();

        $selectedGroup = null;
        if ($request->filled('import_id')) {
            $import = Import::where('user_id', auth()->id())
                ->findOrFail($request->integer('import_id'));
            $selectedGroup = $grouped->firstWhere('table_name', $import->table_name);
        }

        return view('data.index', compact('grouped', 'selectedGroup'));
    }

    public function pelaksanaanKantor(): View
    {
        $imports = Import::where('user_id', auth()->id())
            ->whereIn('table_name', ['laporan peristiwa nikah', 'laporan pendaftaran nikah'])
            ->get();

        $importIds = $imports->pluck('id')->toArray();

        return view('data.pelaksanaan-kantor', compact('importIds'));
    }

    public function pelaksanaanLuarKantor(): View
    {
        $imports = Import::where('user_id', auth()->id())
            ->whereIn('table_name', ['laporan peristiwa nikah', 'laporan pendaftaran nikah'])
            ->get();

        $importIds = $imports->pluck('id')->toArray();

        return view('data.pelaksanaan-luar-kantor', compact('importIds'));
    }

    public function duplikat(): View
    {
        $importIds = Import::where('user_id', auth()->id())
            ->where('table_name', 'laporan model l3')
            ->pluck('id')
            ->toArray();

        return view('data.duplikat', compact('importIds'));
    }

    public function bukanDuplikat(): View
    {
        $importIds = Import::where('user_id', auth()->id())
            ->where('table_name', 'laporan model l3')
            ->pluck('id')
            ->toArray();

        return view('data.bukan-duplikat', compact('importIds'));
    }

    public function rusak(Request $request): View
    {
        $importIds = Import::where('user_id', auth()->id())
            ->where('table_name', 'laporan model l3')
            ->pluck('id')
            ->toArray();

        $rows = $importIds === [] ? [] : ImportData::whereIn('import_id', $importIds)
            ->get(['row_data'])
            ->pluck('row_data')
            ->all();

        $detected = app(RusakDetectionService::class)->detectMissingByMonth($rows);

        $rusakStatusRows = collect($rows)
            ->filter(fn ($row) => mb_strtoupper(trim((string) ($row['Status'] ?? ''))) === 'RUSAK')
            ->map(function ($row) {
                $nomor = trim((string) ($row['Nomor Perforasi'] ?? ''));
                $nomor = preg_replace('/^JT\s*/i', '', $nomor);

                return [
                    'number' => (string) $nomor,
                    'tanggal' => trim((string) ($row['Tanggal Cetak'] ?? '')),
                    'status' => 'RUSAK',
                ];
            })
            ->sortBy('number', SORT_NATURAL)
            ->values()
            ->all();

        $missingPage = max(1, $request->integer('page'));
        $missingRows = new LengthAwarePaginator(
            array_slice($detected, ($missingPage - 1) * 10, 10),
            count($detected),
            10,
            $missingPage,
            ['path' => Paginator::resolveCurrentPath()]
        );

        $statusPage = max(1, $request->integer('status_page'));
        $rusakStatusTotal = count($rusakStatusRows);
        $rusakStatusPaginator = new LengthAwarePaginator(
            array_slice($rusakStatusRows, ($statusPage - 1) * 10, 10),
            $rusakStatusTotal,
            10,
            $statusPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'status_page',
            ]
        );

        return view('data.rusak', [
            'importIds' => $importIds,
            'missingRows' => $missingRows,
            'totalRusak' => count($detected),
            'prefixCount' => count(array_unique(array_column($detected, 'prefix'))),
            'rusakStatusRows' => $rusakStatusPaginator,
            'totalRusakStatus' => $rusakStatusTotal,
        ]);
    }

    public function skp(Request $request): View
    {
        $tw = $request->integer('tw') ?: (int) ceil((int) now()->month / 3);
        if ($tw < 1 || $tw > 4) {
            $tw = (int) ceil((int) now()->month / 3);
        }

        $tahun = $request->integer('tahun') ?: (int) now()->year;
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) now()->year;
        }

        $bulanAwal = ($tw - 1) * 3 + 1;
        $bulanList = [$bulanAwal, $bulanAwal + 1, $bulanAwal + 2];
        $start = Carbon::create($tahun, $bulanAwal, 1)->startOfDay();
        $end = Carbon::create($tahun, $bulanAwal + 2, 1)->endOfMonth()->endOfDay();
        $periodeLabel = 'TW'.$tw.' '.$tahun.' · '.self::MONTH_NAMES[$bulanAwal - 1].'–'.self::MONTH_NAMES[$bulanAwal + 1];

        return view('data.skp', [
            'stats' => $this->skpStats(auth()->id(), $start, $end, $bulanList),
            'tw' => $tw,
            'tahun' => $tahun,
            'periodeLabel' => $periodeLabel,
            'bulanList' => $bulanList,
            'tahunTersedia' => range((int) now()->year, max((int) now()->year - 5, 2020)),
        ]);
    }

    /**
     * @param  array<int, int>  $bulanList
     * @return array{bulan: array<int, array<string, int>>, triwulan: array<string, int>, tahunan: array<string, int>}
     */
    private function skpStats(int $userId, Carbon $start, Carbon $end, array $bulanList): array
    {
        $kosong = fn () => [
            'pelaksanaan' => 0,
            'pelaksanaan_kantor' => 0,
            'pelaksanaan_luar' => 0,
            'pendaftaran' => 0,
            'pendaftaran_kantor' => 0,
            'pendaftaran_luar' => 0,
            'duplikat' => 0,
            'rusak' => 0,
        ];

        $stats = [
            'bulan' => [],
            'triwulan' => $kosong(),
            'tahunan' => $kosong(),
        ];
        foreach ($bulanList as $bulan) {
            $stats['bulan'][$bulan] = $kosong();
        }

        $rawRows = DB::table('import_data')
            ->join('imports', 'imports.id', '=', 'import_data.import_id')
            ->where('imports.user_id', $userId)
            ->whereIn('imports.status', ['success', 'appended'])
            ->where(function ($query) {
                $query->where('imports.table_name', 'like', '%peristiwa nikah%')
                    ->orWhere('imports.table_name', 'like', '%pendaftaran nikah%')
                    ->orWhere('imports.table_name', 'like', '%model l3%');
            })
            ->orderBy('import_data.row_number')
            ->select('imports.table_name', 'import_data.import_id', 'import_data.row_data')
            ->get();

        $pnRows = [];
        $pdkRows = [];
        $l3Rows = [];
        foreach ($rawRows as $raw) {
            $row = json_decode((string) $raw->row_data, true);
            if (! is_array($row)) {
                continue;
            }
            if (str_contains((string) $raw->table_name, 'peristiwa nikah')) {
                $pnRows[] = ['import_id' => (int) $raw->import_id, 'row' => $row];
            } elseif (str_contains((string) $raw->table_name, 'pendaftaran nikah')) {
                $pdkRows[] = ['import_id' => (int) $raw->import_id, 'row' => $row];
            } else {
                $l3Rows[] = $row;
            }
        }

        $pnRows = $this->skpDedupNewestWins($pnRows);
        $pdkRows = $this->skpDedupNewestWins($pdkRows);

        $pdkByNomorDaftar = [];
        foreach ($pdkRows as $row) {
            $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
            if ($nd !== '') {
                $pdkByNomorDaftar[$nd] = $row;
            }
        }

        $tahun = (int) $start->year;
        $inRange = fn (?Carbon $date) => $date !== null && $date->betweenIncluded($start, $end);
        $inYear = fn (?Carbon $date) => $date !== null && $date->year === $tahun;

        foreach ($pnRows as $row) {
            $date = $this->skpParseDate($row['Tanggal Nikah'] ?? null);
            if ($date === null) {
                continue;
            }
            $pdk = $pdkByNomorDaftar[trim((string) ($row['Nomor Daftar'] ?? ''))] ?? null;
            $isLuar = $pdk !== null && $this->skpIsLuarKantor($pdk);

            if ($inYear($date)) {
                $stats['tahunan']['pelaksanaan']++;
                if ($isLuar) {
                    $stats['tahunan']['pelaksanaan_luar']++;
                } else {
                    $stats['tahunan']['pelaksanaan_kantor']++;
                }
            }
            if ($inRange($date)) {
                $stats['triwulan']['pelaksanaan']++;
                $stats['bulan'][$date->month]['pelaksanaan']++;
                if ($isLuar) {
                    $stats['triwulan']['pelaksanaan_luar']++;
                    $stats['bulan'][$date->month]['pelaksanaan_luar']++;
                } else {
                    $stats['triwulan']['pelaksanaan_kantor']++;
                    $stats['bulan'][$date->month]['pelaksanaan_kantor']++;
                }
            }
        }

        foreach ($pdkRows as $row) {
            $date = $this->skpParseDate($row['Tanggal Daftar'] ?? null);
            if ($date === null) {
                continue;
            }
            $isLuar = $this->skpIsLuarKantor($row);

            if ($inYear($date)) {
                $stats['tahunan']['pendaftaran']++;
                if ($isLuar) {
                    $stats['tahunan']['pendaftaran_luar']++;
                } else {
                    $stats['tahunan']['pendaftaran_kantor']++;
                }
            }
            if ($inRange($date)) {
                $stats['triwulan']['pendaftaran']++;
                $stats['bulan'][$date->month]['pendaftaran']++;
                if ($isLuar) {
                    $stats['triwulan']['pendaftaran_luar']++;
                    $stats['bulan'][$date->month]['pendaftaran_luar']++;
                } else {
                    $stats['triwulan']['pendaftaran_kantor']++;
                    $stats['bulan'][$date->month]['pendaftaran_kantor']++;
                }
            }
        }

        $seenPerforasi = [];
        $seenPerforasiTahunan = [];
        foreach ($l3Rows as $row) {
            if (mb_strtolower(trim((string) ($row['Keterangan'] ?? ''))) !== 'duplikat') {
                continue;
            }
            $date = $this->skpParseDate($row['Tanggal Cetak'] ?? null);
            if (! $inYear($date)) {
                continue;
            }
            $perforasi = trim((string) ($row['Nomor Perforasi'] ?? ''));

            if ($perforasi === '' || ! isset($seenPerforasiTahunan[$perforasi])) {
                if ($perforasi !== '') {
                    $seenPerforasiTahunan[$perforasi] = true;
                }
                $stats['tahunan']['duplikat']++;
            }

            if ($inRange($date) && ($perforasi === '' || ! isset($seenPerforasi[$perforasi]))) {
                if ($perforasi !== '') {
                    $seenPerforasi[$perforasi] = true;
                }
                $stats['triwulan']['duplikat']++;
                $stats['bulan'][$date->month]['duplikat']++;
            }
        }

        // Buku rusak: baris Status RUSAK + celah nomor, per bulan; TW = jumlah 3 bulan.
        $rusak = app(RusakDetectionService::class)->countsByMonth($l3Rows, $tahun, range(1, 12));
        $stats['tahunan']['rusak'] = $rusak['total'];
        foreach ($bulanList as $bulan) {
            $stats['bulan'][$bulan]['rusak'] = $rusak['bulan'][$bulan] ?? 0;
            $stats['triwulan']['rusak'] += $stats['bulan'][$bulan]['rusak'];
        }

        return $stats;
    }

    /**
     * @param  array<int, array{import_id: int, row: array<string, mixed>}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function skpDedupNewestWins(array $rows): array
    {
        $newest = [];
        $tanpaNomor = [];

        foreach ($rows as $item) {
            $nd = trim((string) ($item['row']['Nomor Daftar'] ?? ''));

            if ($nd === '') {
                $tanpaNomor[] = $item['row'];

                continue;
            }

            if (! isset($newest[$nd]) || $item['import_id'] >= $newest[$nd]['import_id']) {
                $newest[$nd] = $item;
            }
        }

        return array_merge(array_column($newest, 'row'), $tanpaNomor);
    }

    private function skpParseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function skpIsLuarKantor(array $row): bool
    {
        $nikahDi = mb_strtoupper(trim((string) ($row['Nikah Di'] ?? '')));

        return str_contains($nikahDi, 'LUAR') || str_contains($nikahDi, 'BEDOL');
    }

    public function show(ImportData $record): View
    {
        $this->authorize('view', $record->import);

        $record->load('import');

        return view('data.show', compact('record'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'import_id' => 'required|integer',
            'search' => 'nullable|string|max:255',
            'filter_column' => 'nullable|string|max:255',
            'filter_value' => 'nullable|string|max:255',
            'sort_column' => 'nullable|string|max:255',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        $import = Import::where('user_id', auth()->id())->findOrFail($validated['import_id']);

        $headings = $import->availableColumns();

        $dataset = app(ReportGenerationService::class)->buildDataset(
            $import,
            $headings,
            $validated['search'] ?? null,
            $validated['filter_column'] ?? null,
            $validated['filter_value'] ?? null,
            $validated['sort_column'] ?? null,
            $validated['sort_direction'] ?? 'asc',
        );

        $filename = 'data_import_'.$import->id.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new ImportDataExport($headings, $dataset['rows']), $filename);
    }

    public function cetakNb(Request $request): View
    {
        $validated = $request->validate([
            'import_id' => 'required|integer',
            'record' => 'nullable|integer',
        ]);

        $import = Import::where('user_id', auth()->id())->findOrFail($validated['import_id']);
        $this->authorize('view', $import);

        $allIds = ImportData::where('import_id', $import->id)
            ->orderBy('row_number')
            ->pluck('id')
            ->all();

        if ($allIds === []) {
            abort(404, 'Tidak ada data untuk import ini.');
        }

        $recordId = $validated['record'] ?? $allIds[0];

        $importData = ImportData::where('id', $recordId)
            ->where('import_id', $import->id)
            ->first();

        if (! $importData) {
            abort(404, 'Record tidak ditemukan.');
        }

        $recordData = $importData->row_data ?? [];
        $currentIndex = array_search($recordId, $allIds, true);

        return view('reports.cetak-nb', [
            'report' => null,
            'importId' => $import->id,
            'recordData' => $recordData,
            'prevId' => $currentIndex > 0 ? $allIds[$currentIndex - 1] : null,
            'nextId' => $currentIndex < count($allIds) - 1 ? $allIds[$currentIndex + 1] : null,
            'currentPosition' => $currentIndex + 1,
            'totalRecords' => count($allIds),
            'entryMode' => 'data',
        ]);
    }
}
