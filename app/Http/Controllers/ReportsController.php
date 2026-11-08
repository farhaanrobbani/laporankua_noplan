<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\Report;
use App\Models\User;
use App\Services\MergeService;
use App\Services\ReportGenerationService;
use App\Services\RusakDetectionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $reports = Report::where('user_id', auth()->id())
            ->with('import')
            ->latest()
            ->paginate(10);

        return view('reports.index', compact('reports'));
    }

    public function create(): View
    {
        return view('reports.create');
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        $report->load(['import', 'reportTemplate']);

        $firstRecord = ImportData::where('import_id', $report->import_id)
            ->orderBy('row_number')
            ->first();

        return view('reports.show', compact('report', 'firstRecord'));
    }

    public function download(Report $report): StreamedResponse|SymfonyRedirectResponse
    {
        $this->authorize('view', $report);

        if (! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return redirect()->route('reports.show', $report)
                ->with('status', 'File laporan belum tersedia.');
        }

        $extensions = ['pdf' => 'pdf', 'word' => 'docx', 'excel' => 'xlsx'];
        $extension = $extensions[$report->output_format] ?? 'bin';

        if ($report->status !== 'downloaded') {
            $report->update(['status' => 'downloaded']);
        }

        return Storage::disk('local')->download(
            $report->file_path,
            $report->title.'.'.$extension
        );
    }

    public function print(Report $report, ReportGenerationService $service): View
    {
        $this->authorize('view', $report);

        $config = $report->config_json ?? [];
        $fields = array_values(array_filter($config['fields'] ?? []));

        $sortColumn = $config['sort_column'] ?? $report->import?->default_sort_column;
        $sortDirection = $config['sort_direction'] ?? $report->import?->default_sort_direction ?? 'asc';

        $filterMonth = $config['filter_month'] ?? null;
        $filterYear = $config['filter_year'] ?? null;
        $filterDateFrom = $config['filter_date_from'] ?? null;
        $filterDateTo = $config['filter_date_to'] ?? null;
        $hasDateFilter = ($filterMonth !== null && $filterMonth !== '') || ($filterYear !== null && $filterYear !== '') || ($filterDateFrom !== null && $filterDateFrom !== '') || ($filterDateTo !== null && $filterDateTo !== '');
        $dateFilterField = $config['table_layout']['aggregation']['date_filter_field'] ?? 'Tanggal Nikah';

        if (! empty($config['is_merged']) && ! empty($config['merged_import_ids'])) {
            $mergeService = app(MergeService::class);
            $effectiveFields = $fields;
            if (! empty($config['table_layout'])) {
                $allColumns = $mergeService->getAllColumns($config['merged_import_ids']);
                $effectiveFields = array_values(array_unique(array_merge($fields, $allColumns)));
            }
            if ($hasDateFilter && ! in_array($dateFilterField, $effectiveFields, true)) {
                $allCols = $mergeService->getAllColumns($config['merged_import_ids']);
                if (in_array($dateFilterField, $allCols, true)) {
                    $effectiveFields[] = $dateFilterField;
                }
            }
            if (($config['table_layout']['type'] ?? '') === 'laporan_na' && ! in_array('Tanggal Cetak', $effectiveFields, true)) {
                $effectiveFields[] = 'Tanggal Cetak';
            }
            if (in_array(($config['table_layout']['type'] ?? ''), ['formulir', 'laporan_na'], true)) {
                $dataset = $mergeService->buildConcatDataset(
                    $config['merged_import_ids'],
                    $effectiveFields,
                    $config['search'] ?? null,
                    $config['filter_column'] ?? null,
                    $config['filter_value'] ?? null,
                    $sortColumn,
                    $sortDirection,
                );
            } else {
                $dataset = $mergeService->buildMergedDataset(
                    $config['merged_import_ids'],
                    $effectiveFields,
                    $config['search'] ?? null,
                    $config['filter_column'] ?? null,
                    $config['filter_value'] ?? null,
                    $sortColumn,
                    $sortDirection,
                );
            }
        } elseif (! empty($config['merged_import_ids'])) {
            $mergeService = app(MergeService::class);
            $allColumns = $mergeService->getAllColumns($config['merged_import_ids']);
            $effectiveFields = $fields === [] ? $allColumns : $fields;
            if ($hasDateFilter && ! in_array($dateFilterField, $effectiveFields, true) && ($config['table_layout']['type'] ?? '') !== 'laporan_na') {
                if (in_array($dateFilterField, $allColumns, true)) {
                    $effectiveFields[] = $dateFilterField;
                }
            }
            if (($config['table_layout']['type'] ?? '') === 'laporan_na' && ! in_array('Tanggal Cetak', $effectiveFields, true)) {
                $effectiveFields[] = 'Tanggal Cetak';
            }
            if (($config['table_layout']['type'] ?? '') === 'rekap_nr1' && ! in_array('Nomor Daftar', $effectiveFields, true)) {
                $effectiveFields[] = 'Nomor Daftar';
            }
            $dataset = $mergeService->buildConcatDataset(
                $config['merged_import_ids'],
                $effectiveFields,
                $config['search'] ?? null,
                $config['filter_column'] ?? null,
                $config['filter_value'] ?? null,
                $sortColumn,
                $sortDirection,
            );
        } else {
            $effectiveFields = $fields === [] ? $report->import->availableColumns() : $fields;
            if ($hasDateFilter && ! in_array($dateFilterField, $effectiveFields, true)) {
                $available = $report->import->availableColumns();
                if (in_array($dateFilterField, $available, true)) {
                    $effectiveFields[] = $dateFilterField;
                }
            }
            $dataset = $service->buildDataset(
                $report->import,
                $effectiveFields,
                $config['search'] ?? null,
                $config['filter_column'] ?? null,
                $config['filter_value'] ?? null,
                $sortColumn,
                $sortDirection,
            );
        }
        $dataset['title'] = $report->title;
        $dataset['table_layout'] = $config['table_layout'] ?? null;
        $dataset['orientation'] = $config['orientation'] ?? null;

        if (($config['table_layout']['type'] ?? '') === 'laporan_na' && isset($dataset['rows'])) {
            $dataset['rows'] = array_values(array_filter($dataset['rows'], fn ($row) => ! empty($row['Tanggal Cetak'])));
        }

        if (($config['table_layout']['type'] ?? '') === 'laporan_na') {
            $pnImports = Import::where('table_name', 'like', '%peristiwa nikah%')
                ->where('user_id', $report->user_id)
                ->whereIn('status', ['success', 'appended'])
                ->pluck('id');
            $aktaMap = [];
            if ($pnImports->isNotEmpty()) {
                $pnData = ImportData::whereIn('import_id', $pnImports)
                    ->select('row_data')
                    ->get()
                    ->pluck('row_data');
                foreach ($pnData as $pn) {
                    $nomorAkta = $pn['Nomor Akta Nikah'] ?? '';
                    $suami = trim((string) ($pn['No Porforasi Suami'] ?? ''));
                    $istri = trim((string) ($pn['No Porforasi Istri'] ?? ''));
                    if ($suami !== '') {
                        $aktaMap[$suami] = $nomorAkta;
                    }
                    if ($istri !== '') {
                        $aktaMap[$istri] = $nomorAkta;
                    }
                }
            }
            foreach ($dataset['rows'] as &$row) {
                $namaCatin = $row['Nama Catin'] ?? '';
                $parts = explode(' - ', $namaCatin, 2);
                $row['_nama_suami'] = trim($parts[0] ?? $namaCatin);
                $porforasi = trim((string) ($row['Nomor Perforasi'] ?? ''));
                $row['_nomor_akta'] = $aktaMap[$porforasi] ?? null;
            }
            unset($row);
        }

        if ($hasDateFilter) {
            $tableLayout = $config['table_layout'] ?? [];
            $layoutColumns = $tableLayout['columns'] ?? [];
            $tanggalNikahField = null;

            if ($dateFilterField && in_array($dateFilterField, $effectiveFields, true)) {
                $tanggalNikahField = $dateFilterField;
            }

            if ($tanggalNikahField === null) {
                foreach ($layoutColumns as $col) {
                    if (($col['type'] ?? '') === 'field' && ($col['field'] ?? '') === 'Tanggal Nikah') {
                        $tanggalNikahField = $col['field'];
                        break;
                    }
                    if (($col['type'] ?? '') === 'group') {
                        foreach ($col['children'] ?? [] as $child) {
                            if (($child['field'] ?? '') === 'Tanggal Nikah') {
                                $tanggalNikahField = $child['field'];
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($tanggalNikahField === null && in_array('Tanggal Nikah', $effectiveFields, true)) {
                $tanggalNikahField = 'Tanggal Nikah';
            }

            if (($config['table_layout']['type'] ?? '') === 'laporan_na' && in_array('Tanggal Cetak', $effectiveFields, true)) {
                $tanggalNikahField = 'Tanggal Cetak';
            }

            if ($tanggalNikahField !== null) {
                $dataset['rows'] = array_values(array_filter($dataset['rows'], function ($row) use ($tanggalNikahField, $filterMonth, $filterYear, $filterDateFrom, $filterDateTo) {
                    $dateVal = $row[$tanggalNikahField] ?? null;
                    if ($dateVal === null || $dateVal === '') {
                        return false;
                    }
                    try {
                        $date = Carbon::parse($dateVal);
                    } catch (\Exception $e) {
                        return false;
                    }
                    if ($filterMonth !== null && $filterMonth !== '' && (int) $date->month !== (int) $filterMonth) {
                        return false;
                    }
                    if ($filterYear !== null && $filterYear !== '' && (int) $date->year !== (int) $filterYear) {
                        return false;
                    }
                    if ($filterDateFrom !== null && $filterDateFrom !== '') {
                        $from = Carbon::parse($filterDateFrom)->startOfDay();
                        if ($date->lt($from)) {
                            return false;
                        }
                    }
                    if ($filterDateTo !== null && $filterDateTo !== '') {
                        $to = Carbon::parse($filterDateTo)->endOfDay();
                        if ($date->gt($to)) {
                            return false;
                        }
                    }

                    return true;
                }));
            }

            $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            if ($filterMonth !== null && $filterMonth !== '') {
                $dataset['bulan_override'] = $monthNames[(int) $filterMonth] ?? '-';
            }
            if ($filterYear !== null && $filterYear !== '') {
                $dataset['tahun_override'] = $filterYear;
            }
            $dataset['filter_month'] = $filterMonth;
            $dataset['filter_year'] = $filterYear;
            $dataset['filter_date_from'] = $filterDateFrom;
            $dataset['filter_date_to'] = $filterDateTo;
        }

        $user = $report->user;
        $dataset['kecamatan'] = $config['kecamatan'] ?? $user?->kecamatan;
        $dataset['nama_kepala_kua'] = $config['nama_kepala_kua'] ?? $user?->nama_kepala_kua;
        $dataset['nip_kepala'] = $config['nip_kepala'] ?? $user?->nip_kepala;
        $dataset['nama_petugas_stok'] = $config['nama_petugas_stok'] ?? $user?->nama_petugas_stok;
        $dataset['nip_petugas_stok'] = $config['nip_petugas_stok'] ?? $user?->nip_petugas_stok;
        $dataset['nama_kepala_kua_lama'] = $config['nama_kepala_kua_lama'] ?? $user?->nama_kepala_kua_lama;
        $dataset['nip_kepala_lama'] = $config['nip_kepala_lama'] ?? $user?->nip_kepala_lama;
        $dataset['nama_kepala_kemenag'] = $config['nama_kepala_kemenag'] ?? $user?->nama_kepala_kemenag;
        $dataset['nip_kepala_kemenag'] = $config['nip_kepala_kemenag'] ?? $user?->nip_kepala_kemenag;
        $dataset['kota_kabupaten'] = $config['kota_kabupaten'] ?? $user?->kota_kabupaten;
        $dataset['nama_kementerian'] = $config['nama_kementerian'] ?? null;
        $dataset['nama_kantor_kota'] = $config['nama_kantor_kota'] ?? null;
        $dataset['nama_kantor'] = $config['nama_kantor'] ?? null;
        $dataset['alamat_kantor'] = $config['alamat_kantor'] ?? null;
        $dataset['telepon_kantor'] = $config['telepon_kantor'] ?? null;
        $dataset['email_kantor'] = $config['email_kantor'] ?? null;
        $dataset['logo_kantor'] = $config['logo_kantor'] ?? null;
        $dataset['daftar_desa'] = $config['daftar_desa'] ?? null;
        $dataset['font_size_kop_kementerian'] = $config['font_size_kop_kementerian'] ?? null;
        $dataset['font_size_kop_kantor_kota'] = $config['font_size_kop_kantor_kota'] ?? null;
        $dataset['font_size_kop_kantor'] = $config['font_size_kop_kantor'] ?? null;
        $dataset['font_size_kop_alamat'] = $config['font_size_kop_alamat'] ?? null;
        $dataset['font_size_kop_kontak'] = $config['font_size_kop_kontak'] ?? null;
        $dataset['config_json'] = $config;

        if (($config['table_layout']['type'] ?? '') === 'laporan_l1') {
            $rows = $this->buildL1Data($config, $user, $filterMonth, $filterYear, $filterDateFrom, $filterDateTo);
            $dataset['rows'] = $this->mergeL1ManualData($config, $rows);
        }

        if (($config['table_layout']['type'] ?? '') === 'rekap_nr1') {
            $pnImports = Import::where('table_name', 'like', '%peristiwa nikah%')
                ->where('user_id', $report->user_id)
                ->whereIn('status', ['success', 'appended'])
                ->pluck('id');
            $pnMap = [];
            if ($pnImports->isNotEmpty()) {
                $pnRaw = ImportData::whereIn('import_id', $pnImports)
                    ->pluck('row_data')
                    ->all();
                foreach ($pnRaw as $r) {
                    $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                    $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
                    if ($nd !== '') {
                        $pnMap[$nd] = $row;
                    }
                }
            }

            $pdkImports = Import::where('table_name', 'like', '%pendaftaran nikah%')
                ->where('user_id', $report->user_id)
                ->whereIn('status', ['success', 'appended'])
                ->pluck('id');
            $pdkMap = [];
            if ($pdkImports->isNotEmpty()) {
                $pdkRaw = ImportData::whereIn('import_id', $pdkImports)
                    ->pluck('row_data')
                    ->all();
                foreach ($pdkRaw as $r) {
                    $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                    $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
                    if ($nd !== '') {
                        $pdkMap[$nd] = $row;
                    }
                }
            }

            $daftarDesa = $user?->daftar_desa ?? [];

            if (isset($dataset['rows'])) {
                foreach ($dataset['rows'] as &$dataRow) {
                    $nd = trim((string) ($dataRow['Nomor Daftar'] ?? ''));

                    $kelurahan = trim((string) ($pnMap[$nd]['Kelurahan'] ?? ''));
                    if ($kelurahan === '' && isset($pdkMap[$nd])) {
                        $pdk = $pdkMap[$nd];
                        $suamiAddr = mb_strtoupper(trim((string) ($pdk['Alamat Suami'] ?? '')));
                        $istriAddr = mb_strtoupper(trim((string) ($pdk['Alamat Istri'] ?? '')));
                        $matchSuami = str_contains($suamiAddr, 'AMPELGADING');
                        $matchIstri = str_contains($istriAddr, 'AMPELGADING');

                        $desaSuami = '';
                        if ($matchSuami) {
                            foreach ($daftarDesa as $desa) {
                                if (str_contains($suamiAddr, mb_strtoupper($desa))) {
                                    $desaSuami = $desa;

                                    break;
                                }
                            }
                        }

                        $desaIstri = '';
                        if ($matchIstri) {
                            foreach ($daftarDesa as $desa) {
                                if (str_contains($istriAddr, mb_strtoupper($desa))) {
                                    $desaIstri = $desa;

                                    break;
                                }
                            }
                        }

                        if ($matchSuami && $matchIstri) {
                            $kelurahan = $desaIstri ?: $desaSuami;
                        } elseif ($matchSuami) {
                            $kelurahan = $desaSuami;
                        } elseif ($matchIstri) {
                            $kelurahan = $desaIstri;
                        } else {
                            $raw = trim((string) ($pdk['Alamat Istri'] ?? ''));
                            $words = preg_split('/\s+/', $raw);
                            $kelurahan = implode(' ', array_slice($words ?? [], 0, 5));
                        }
                    }
                    $dataRow['Kelurahan'] = $kelurahan;

                    $pdk = $pdkMap[$nd] ?? null;
                    if ($pdk) {
                        if (empty($dataRow['Tanggal Daftar'])) {
                            $dataRow['Tanggal Daftar'] = $pdk['Tanggal Daftar'] ?? '';
                        }
                        if (empty($dataRow['Nikah Di'])) {
                            $dataRow['Nikah Di'] = $pdk['Nikah Di'] ?? '';
                        }
                    }
                }
                unset($dataRow);
            }
        }

        if (($config['table_layout']['type'] ?? '') === 'rekap_ntcr') {
            $dataset['rows'] = $this->buildNtcrData($config, $user, $filterYear);
            $dataset['config_json']['manual_data'] = $this->buildNtcrManualData($config);
        }

        if (($config['table_layout']['type'] ?? '') === 'laporan_nb') {
            $dataset['rows'] = $this->buildNbData($config, $user, $filterMonth, $filterYear, $filterDateFrom, $filterDateTo);
            $dataset['config_json']['manual_data'] = $this->buildNbManualData($config);
        }

        if (($config['table_layout']['type'] ?? '') === 'laporan_n') {
            $dataset['rows'] = $this->buildNData($config, $user, $filterMonth, $filterYear, $filterDateFrom, $filterDateTo);
            $dataset['config_json']['manual_data'] = $this->buildNManualData($config);
        }

        if (($config['table_layout']['type'] ?? '') === 'formulir') {
            $dataset['config_json']['manual_data'] = $this->buildL3ManualData($config);
        }
        if (($config['table_layout']['type'] ?? '') === 'laporan_na') {
            $manualData = $this->buildLaporanNaData($config);
            $manualData['rusak_by_prefix'] = $this->rusakByPrefixForReport($config, $report);
            $dataset['config_json']['manual_data'] = $manualData;
        }

        return view('reports.print', compact('dataset', 'report'));
    }

    public function cetakNb(Report $report, int $record): View
    {
        $this->authorize('view', $report);

        $importData = ImportData::where('id', $record)
            ->where('import_id', $report->import_id)
            ->first();

        if (! $importData) {
            abort(404, 'Record tidak ditemukan.');
        }

        $recordData = $importData->row_data ?? [];

        $allIds = ImportData::where('import_id', $report->import_id)
            ->orderBy('row_number')
            ->pluck('id')
            ->all();

        $currentIndex = array_search($record, $allIds, true);

        return view('reports.cetak-nb', [
            'report' => $report,
            'importId' => $report->import_id,
            'records' => [$recordData],
            'recordData' => $recordData,
            'prevId' => $currentIndex > 0 ? $allIds[$currentIndex - 1] : null,
            'nextId' => $currentIndex < count($allIds) - 1 ? $allIds[$currentIndex + 1] : null,
            'currentPosition' => $currentIndex + 1,
            'totalRecords' => count($allIds),
            'entryMode' => 'report',
            'multiMode' => false,
            'showNav' => true,
        ]);
    }

    /**
     * Celah nomor per prefix, difilter sesuai periode laporan.
     * Deteksi dilakukan dari seluruh baris agar cetak ulang lintas
     * bulan tidak dianggap sebagai awal seri baru.
     *
     * @param  array<int, array<string, mixed>>  $allRows
     * @return array<string, array<int>>
     */
    private function rusakByPrefix(array $allRows, array $config, ?int $prefixLength = null): array
    {
        $prefixLength = $prefixLength ?? ($config['table_layout']['na_version_prefix_length'] ?? 6);
        $filterMonth = $config['filter_month'] ?? null;
        $filterYear = $config['filter_year'] ?? null;
        $filterDateFrom = $config['filter_date_from'] ?? null;
        $filterDateTo = $config['filter_date_to'] ?? null;
        $from = ($filterDateFrom !== null && $filterDateFrom !== '') ? Carbon::parse($filterDateFrom)->startOfDay() : null;
        $to = ($filterDateTo !== null && $filterDateTo !== '') ? Carbon::parse($filterDateTo)->endOfDay() : null;

        $result = [];
        foreach (app(RusakDetectionService::class)->detectMissingByMonth($allRows, $prefixLength) as $gap) {
            if ($gap['date'] === null) {
                continue;
            }
            $date = Carbon::parse($gap['date']);
            if ($filterMonth !== null && $filterMonth !== '' && (int) $date->month !== (int) $filterMonth) {
                continue;
            }
            if ($filterYear !== null && $filterYear !== '' && (int) $date->year !== (int) $filterYear) {
                continue;
            }
            if ($from !== null && $date->lt($from)) {
                continue;
            }
            if ($to !== null && $date->gt($to)) {
                continue;
            }
            $result[$gap['prefix']][] = $gap['number'];
        }

        return $result;
    }

    /**
     * @return array<string, array<int>>
     */
    private function rusakByPrefixForReport(array $config, ?Report $report): array
    {
        $importIds = $config['merged_import_ids'] ?? [];
        if ($importIds === [] && $report?->import_id) {
            $importIds = [$report->import_id];
        }
        if ($importIds === []) {
            return [];
        }

        $allRows = ImportData::whereIn('import_id', $importIds)
            ->select('row_data')
            ->get()
            ->pluck('row_data')
            ->toArray();

        return $this->rusakByPrefix($allRows, $config);
    }

    private function buildL3ManualData(array $config): array
    {
        $importIds = $config['merged_import_ids'] ?? [];
        $savedRows = $config['manual_data']['rows'] ?? [];

        if ($importIds === [] || $savedRows === []) {
            return [
                'rows' => $savedRows,
                'na_versions' => $config['manual_data']['na_versions'] ?? [],
                'static_rows' => $config['table_layout']['static_rows'] ?? $config['manual_data']['static_rows'] ?? [],
            ];
        }

        $prefixLength = $config['table_layout']['na_version_prefix_length'] ?? 6;
        $filterMonth = $config['filter_month'] ?? null;
        $filterYear = $config['filter_year'] ?? null;

        $allRows = ImportData::whereIn('import_id', $importIds)
            ->select('row_data')
            ->get()
            ->pluck('row_data')
            ->toArray();

        $groups = [];
        foreach ($allRows as $row) {
            $nomor = trim((string) ($row['Nomor Perforasi'] ?? ''));
            $nomor = preg_replace('/^JT\s*/i', '', $nomor);
            $nomor = preg_replace('/\s*-\s*\d+$/', '', $nomor);
            if ($nomor === '' || ! preg_match('/^\d+$/', $nomor)) {
                continue;
            }
            $prefix = substr($nomor, 0, $prefixLength);
            if (! isset($groups[$prefix])) {
                $groups[$prefix] = ['filtered_porp' => [], 'count' => 0, 'rusak_status' => 0];
            }
        }

        foreach ($allRows as $row) {
            $nomor = trim((string) ($row['Nomor Perforasi'] ?? ''));
            $nomor = preg_replace('/^JT\s*/i', '', $nomor);
            $nomor = preg_replace('/\s*-\s*\d+$/', '', $nomor);
            if ($nomor === '' || ! preg_match('/^\d+$/', $nomor)) {
                continue;
            }
            $prefix = substr($nomor, 0, $prefixLength);

            $tanggalCetak = $row['Tanggal Cetak'] ?? null;
            if ($tanggalCetak !== null && $tanggalCetak !== '') {
                try {
                    $date = Carbon::parse($tanggalCetak);
                    if ($filterMonth !== null && $filterMonth !== '' && (int) $date->month !== (int) $filterMonth) {
                        continue;
                    }
                    if ($filterYear !== null && $filterYear !== '' && (int) $date->year !== (int) $filterYear) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (isset($groups[$prefix])) {
                $groups[$prefix]['count']++;
                $groups[$prefix]['filtered_porp'][] = (int) $nomor;
                if (mb_strtolower(trim((string) ($row['Keterangan'] ?? ''))) === 'rusak') {
                    $groups[$prefix]['rusak_status']++;
                }
            }
        }

        // Rusak = celah interior dari seluruh baris (agar cetak ulang lintas bulan
        // tidak dianggap awal seri baru), lalu difilter periode laporan;
        // masuk ke keluar_jumlah tanpa baris/keterangan terpisah.
        $rusakByPrefix = $this->rusakByPrefix($allRows, $config, $prefixLength);

        $naVersions = [];
        foreach ($groups as $prefix => $data) {
            if ($data['count'] === 0) {
                continue;
            }
            $filteredPorp = $data['filtered_porp'];
            $gapCount = count($rusakByPrefix[$prefix] ?? []);
            $rusakStatusCount = (int) ($data['rusak_status'] ?? 0);
            $naVersions[] = [
                'prefix' => (string) $prefix,
                'label' => 'Model NA ('.$prefix.')',
                'keluar_jumlah' => $data['count'] + $gapCount,
                'rusak_jumlah' => $gapCount + $rusakStatusCount,
                'min_porp' => (string) min($filteredPorp),
                'max_porp' => (string) max($filteredPorp),
            ];
        }

        $rows = [];
        $naIndex = 0;
        foreach ($savedRows as $saved) {
            $row = $saved;
            $formulir = $saved['formulir'] ?? '';
            $isNaRow = str_starts_with($formulir, 'Model NA');
            $ver = null;

            if ($isNaRow) {
                $ver = $naVersions[$naIndex] ?? null;
                $naIndex++;

                if ($ver) {
                    $row['keluar_jumlah'] = (string) $ver['keluar_jumlah'];
                    $row['keluar_seri_awal'] = $ver['min_porp'];
                    $row['keluar_seri_akhir'] = $ver['max_porp'];
                    $keluarSeri = 'JT '.$ver['min_porp'];
                    if ($ver['min_porp'] !== $ver['max_porp']) {
                        $keluarSeri .= ' - '.$ver['max_porp'];
                    }
                    $row['keluar_seri'] = $keluarSeri;
                } else {
                    $row['keluar_jumlah'] = '0';
                    $row['keluar_seri_awal'] = '';
                    $row['keluar_seri_akhir'] = '';
                    $row['keluar_seri'] = $row['masuk_seri'] ?? '';
                }
            }

            $masuk = (int) ($row['masuk_jumlah'] ?? 0);
            $keluar = (int) ($row['keluar_jumlah'] ?? 0);
            if ($masuk > 0) {
                $row['sisa_jumlah'] = (string) max(0, $masuk - $keluar);
            }

            $masukAkhir = (int) ($row['masuk_seri_akhir'] ?? 0);
            $keluarAkhir = (int) ($row['keluar_seri_akhir'] ?? 0);
            $sisaJumlah = (int) ($row['sisa_jumlah'] ?? 0);
            if ($sisaJumlah > 0 && $keluarAkhir > 0 && $masukAkhir >= $keluarAkhir) {
                $row['sisa_seri_awal'] = (string) ($keluarAkhir + 1);
                $row['sisa_seri_akhir'] = (string) $masukAkhir;
                $sisaSeri = 'JT '.($keluarAkhir + 1);
                if (($keluarAkhir + 1) !== $masukAkhir) {
                    $sisaSeri .= ' - '.$masukAkhir;
                }
                $row['sisa_seri'] = $sisaSeri;
            } elseif ($isNaRow && $ver === null) {
                $row['sisa_jumlah'] = (string) $masuk;
                $row['sisa_seri'] = $row['masuk_seri'] ?? '';
                $row['sisa_seri_awal'] = $row['masuk_seri_awal'] ?? '';
                $row['sisa_seri_akhir'] = $row['masuk_seri_akhir'] ?? '';
            }

            $rows[] = $row;
        }

        $staticRows = [
            ['formulir' => 'Model N', 'row_num' => 1, 'dynamic' => false],
        ];
        $rowNum = 2;
        foreach ($rows as $r) {
            $formulir = $r['formulir'] ?? '';
            if (preg_match('/^Model NA/', $formulir)) {
                $staticRows[] = [
                    'formulir' => $formulir,
                    'row_num' => $rowNum,
                    'dynamic' => true,
                    'dynamic_type' => 'na_version',
                ];
                $rowNum++;
            }
        }
        $staticRows[] = ['formulir' => 'Model DN', 'row_num' => $rowNum, 'dynamic' => false];
        $rowNum++;
        $staticRows[] = ['formulir' => 'Model NB', 'row_num' => $rowNum, 'dynamic' => false];

        return [
            'rows' => $rows,
            'na_versions' => $naVersions,
            'static_rows' => $staticRows,
        ];
    }

    private function buildLaporanNaData(array $config): array
    {
        $saved = $config['manual_data'] ?? null;
        if (! is_array($saved)) {
            return ['sisa_bulan_lalu' => []];
        }

        return ['sisa_bulan_lalu' => $saved['sisa_bulan_lalu'] ?? []];
    }

    private function buildL1Data(array $config, ?User $user, ?string $filterMonth = null, ?string $filterYear = null, ?string $filterDateFrom = null, ?string $filterDateTo = null): array
    {
        $importIds = $config['merged_import_ids'] ?? [];
        $daftarDesa = $user?->daftar_desa ?? [];

        // Load peristiwa nikah data (the selected imports)
        $pnRows = [];
        if ($importIds !== []) {
            $raw = ImportData::whereIn('import_id', $importIds)
                ->orderBy('row_number')
                ->pluck('row_data')
                ->all();
            foreach ($raw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $pnRows[] = $row;
            }
        }

        // Filter peristiwa nikah by Tanggal Nikah if month/year filter is set
        if (($filterMonth !== null && $filterMonth !== '') || ($filterYear !== null && $filterYear !== '') || ($filterDateFrom !== null && $filterDateFrom !== '') || ($filterDateTo !== null && $filterDateTo !== '')) {
            $pnRows = array_values(array_filter($pnRows, function ($row) use ($filterMonth, $filterYear, $filterDateFrom, $filterDateTo) {
                $dateVal = $row['Tanggal Nikah'] ?? null;
                if ($dateVal === null || $dateVal === '') {
                    return false;
                }
                try {
                    $date = Carbon::parse($dateVal);
                } catch (\Exception $e) {
                    return false;
                }
                if ($filterMonth !== null && $filterMonth !== '' && (int) $date->month !== (int) $filterMonth) {
                    return false;
                }
                if ($filterYear !== null && $filterYear !== '' && (int) $date->year !== (int) $filterYear) {
                    return false;
                }
                if ($filterDateFrom !== null && $filterDateFrom !== '') {
                    $from = Carbon::parse($filterDateFrom)->startOfDay();
                    if ($date->lt($from)) {
                        return false;
                    }
                }
                if ($filterDateTo !== null && $filterDateTo !== '') {
                    $to = Carbon::parse($filterDateTo)->endOfDay();
                    if ($date->gt($to)) {
                        return false;
                    }
                }

                return true;
            }));
        }

        // Load ALL pendaftaran nikah data
        $pdkImports = Import::where('table_name', 'like', '%pendaftaran nikah%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $pdkMap = [];
        if ($pdkImports->isNotEmpty()) {
            $pdkRaw = ImportData::whereIn('import_id', $pdkImports)
                ->pluck('row_data')
                ->all();
            foreach ($pdkRaw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
                if ($nd !== '') {
                    $pdkMap[$nd] = $row;
                }
            }
        }

        // Load ALL model l3 data for duplikat
        $l3Imports = Import::where('table_name', 'like', '%model l3%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $duplikatByDesa = [];
        if ($l3Imports->isNotEmpty()) {
            $l3Raw = ImportData::whereIn('import_id', $l3Imports)
                ->pluck('row_data')
                ->all();
            // Dedup by Nomor Perforasi (last-wins: data upload terbaru menang)
            // mencegah double count dari append/re-upload yang menaruh baris fisik ganda.
            $l3ByPerforasi = [];
            $l3TanpaPerforasi = [];
            foreach ($l3Raw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $perforasi = trim((string) ($row['Nomor Perforasi'] ?? ''));
                if ($perforasi !== '') {
                    $l3ByPerforasi[$perforasi] = $row;
                } else {
                    $l3TanpaPerforasi[] = $row;
                }
            }
            foreach (array_merge(array_values($l3ByPerforasi), $l3TanpaPerforasi) as $row) {
                if (mb_strtolower((string) ($row['Keterangan'] ?? '')) === 'duplikat') {
                    // Filter duplikat by Tanggal Cetak if month/year filter is set
                    if (($filterMonth !== null && $filterMonth !== '') || ($filterYear !== null && $filterYear !== '')) {
                        $dupDateVal = $row['Tanggal Cetak'] ?? null;
                        if ($dupDateVal === null || $dupDateVal === '') {
                            continue;
                        }
                        try {
                            $dupDate = Carbon::parse($dupDateVal);
                        } catch (\Exception $e) {
                            continue;
                        }
                        if ($filterMonth !== null && $filterMonth !== '' && (int) $dupDate->month !== (int) $filterMonth) {
                            continue;
                        }
                        if ($filterYear !== null && $filterYear !== '' && (int) $dupDate->year !== (int) $filterYear) {
                            continue;
                        }
                    }
                    $desaVal = '';
                    foreach (['Desa/Kelurahan/Kecamatan', 'Desa', 'Kelurahan'] as $desaKey) {
                        $candidate = trim((string) ($row[$desaKey] ?? ''));
                        if ($candidate !== '') {
                            $desaVal = $candidate;
                            break;
                        }
                    }
                    $desa = mb_strtoupper($desaVal);
                    if ($desa !== '') {
                        $duplikatByDesa[$desa] = ($duplikatByDesa[$desa] ?? 0) + 1;
                    }
                }
            }
        }

        // Group peristiwa nikah by Kelurahan and compute L1 columns
        $grouped = [];
        foreach ($pnRows as $pn) {
            $kelurahan = trim((string) ($pn['Kelurahan'] ?? ''));
            if ($kelurahan === '') {
                $kelurahan = 'Lainnya';
            }
            if (! isset($grouped[$kelurahan])) {
                $grouped[$kelurahan] = [];
            }
            $grouped[$kelurahan][] = $pn;
        }

        // Dedup peristiwa nikah by Nomor Daftar (prevent double count from appended imports)
        foreach ($grouped as $desa => &$pnGroup) {
            $seen = [];
            $pnGroup = array_values(array_filter($pnGroup, function ($row) use (&$seen) {
                $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
                if ($nd === '' || ! isset($seen[$nd])) {
                    $seen[$nd] = true;

                    return true;
                }

                return false;
            }));
        }
        unset($pnGroup);

        $rows = [];
        foreach ($daftarDesa as $desa) {
            $pnGroup = $grouped[$desa] ?? [];
            $jmlNikah = count($pnGroup);

            // Count wali nikah from pendaftaran nikah
            $nasab = 0;
            $adhal = 0;
            $hakim = 0;
            $kantor = 0;
            $luarKantor = 0;

            foreach ($pnGroup as $pn) {
                $nd = trim((string) ($pn['Nomor Daftar'] ?? ''));
                $pdk = $pdkMap[$nd] ?? null;

                if ($pdk) {
                    $statusWali = mb_strtoupper(trim((string) ($pdk['Status Wali'] ?? '')));
                    if ($statusWali === 'NASAB') {
                        $nasab++;
                    } elseif ($statusWali === 'HAKIM') {
                        $hakim++;
                    } else {
                        $adhal++;
                    }

                    $nikahDi = mb_strtoupper(trim((string) ($pdk['Nikah Di'] ?? '')));
                    if (str_contains($nikahDi, 'LUAR') || str_contains($nikahDi, 'BEDOL')) {
                        $luarKantor++;
                    } elseif (str_contains($nikahDi, 'KANTOR') || str_contains($nikahDi, 'KUA')) {
                        $kantor++;
                    }
                }
            }

            // Count itsbat nikah (Tanggal Isbat not empty)
            $itsbat = 0;
            foreach ($pnGroup as $pn) {
                if (! empty(trim((string) ($pn['Tanggal Isbat'] ?? '')))) {
                    $itsbat++;
                }
            }

            // Count campuran: NIK Suami/Istri empty
            $campuranL = 0;
            $campuranP = 0;
            foreach ($pnGroup as $pn) {
                if (empty(trim((string) ($pn['NIK Suami'] ?? '')))) {
                    $campuranL++;
                }
                if (empty(trim((string) ($pn['NIK Istri'] ?? '')))) {
                    $campuranP++;
                }
            }

            $duplikat = $duplikatByDesa[mb_strtoupper($desa)] ?? 0;

            $rows[] = [
                'Kelurahan' => $desa,
                'Jumlah Nikah' => $jmlNikah,
                'Nasab' => $nasab,
                'Adhal' => $adhal,
                'Lain-lain' => $hakim,
                'Itsbat Nikah' => $itsbat,
                'Campuran Laki-laki' => $campuranL,
                'Campuran Perempuan' => $campuranP,
                'Poligami II' => null,
                'Poligami III' => null,
                'Poligami IV' => null,
                'Kantor' => $kantor,
                'Luar Kantor' => $luarKantor,
                'Miskin' => null,
                'Bencana Alam' => null,
                'Pencatatan LN' => null,
                'Duplikat' => $duplikat,
                'Talak I' => null,
                'Talak II' => null,
                'Talak III' => null,
                'Cerai' => null,
                'Rujuk I' => null,
                'Rujuk II' => null,
                'Rujuk III' => null,
            ];
        }

        return $rows;
    }

    private function mergeL1ManualData(array $config, array $rows): array
    {
        $manualData = $config['manual_data'] ?? null;
        if (! is_array($manualData)) {
            return $rows;
        }

        $manualCols = ['Poligami II', 'Poligami III', 'Poligami IV', 'Miskin', 'Bencana Alam', 'Pencatatan LN', 'Talak I', 'Talak II', 'Talak III', 'Cerai', 'Rujuk I', 'Rujuk II', 'Rujuk III'];

        foreach ($rows as &$row) {
            $desa = $row['Kelurahan'] ?? '';
            if (isset($manualData[$desa])) {
                foreach ($manualCols as $col) {
                    $val = $manualData[$desa][$col] ?? null;
                    $row[$col] = ($val !== null && $val !== '') ? (int) $val : 0;
                }
            }
        }
        unset($row);

        return $rows;
    }

    private function buildNtcrData(array $config, ?User $user, ?string $filterYear): array
    {
        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $pnImports = Import::where('table_name', 'like', '%peristiwa nikah%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $pnRows = [];
        if ($pnImports->isNotEmpty()) {
            $pnRaw = ImportData::whereIn('import_id', $pnImports)
                ->pluck('row_data')
                ->all();
            foreach ($pnRaw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $pnRows[] = $row;
            }
        }

        $pdkImports = Import::where('table_name', 'like', '%pendaftaran nikah%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $pdkRows = [];
        if ($pdkImports->isNotEmpty()) {
            $pdkRaw = ImportData::whereIn('import_id', $pdkImports)
                ->pluck('row_data')
                ->all();
            foreach ($pdkRaw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $pdkRows[] = $row;
            }
        }

        $pdkMap = [];
        foreach ($pdkRows as $row) {
            $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
            if ($nd !== '') {
                $pdkMap[$nd] = $row;
            }
        }

        $simImports = Import::where('table_name', 'like', '%simponi%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $simRows = [];
        if ($simImports->isNotEmpty()) {
            $simRaw = ImportData::whereIn('import_id', $simImports)
                ->pluck('row_data')
                ->all();
            foreach ($simRaw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $simRows[] = $row;
            }
        }

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $rows[] = [
                'Bulan' => $monthNames[$m - 1],
                'month_num' => $m,
                'LK' => 0,
                'K' => 0,
                'Nikah' => 0,
                'Talak' => 0,
                'Cerai' => 0,
                'Rujuk' => 0,
                'Pdk_K' => 0,
                'Pdk_LK' => 0,
                'Pdk_Jml' => 0,
                'R1' => 0, 'R2' => 0, 'R3' => 0, 'R4' => 0, 'R5' => 0, 'R6' => 0,
                'R7' => 0, 'R8' => 0, 'R9' => 0, 'R10' => 0, 'R11' => 0, 'R12' => 0,
                'Gagal' => 0,
                'Tunda' => 0,
                'Miskin' => 0,
                'Bencana Alam' => 0,
                'Jumlah' => 0,
                'Setor' => 0,
            ];
        }

        $pnDedup = [];
        $seen = [];
        foreach ($pnRows as $row) {
            $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
            if ($nd !== '' && ! isset($seen[$nd])) {
                $seen[$nd] = true;
                $pnDedup[] = $row;
            }
        }

        foreach ($pnDedup as $row) {
            if ($filterYear !== null && $filterYear !== '') {
                $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
                $pdk = $pdkMap[$nd] ?? null;
                $tglDaftar = $pdk['Tanggal Daftar'] ?? ($row['Tanggal Daftar'] ?? '');
                try {
                    $d = Carbon::parse($tglDaftar);
                    if ((string) $d->year !== $filterYear) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
            $pdk = $pdkMap[$nd] ?? null;
            $tglNikah = $pdk['Tanggal Nikah'] ?? ($row['Tanggal Nikah'] ?? '');
            try {
                $bulan = (int) Carbon::parse($tglNikah)->month;
            } catch (\Exception $e) {
                continue;
            }
            if ($bulan < 1 || $bulan > 12) {
                continue;
            }

            $nikahDi = mb_strtoupper(trim((string) ($pdk['Nikah Di'] ?? '')));
            if (str_contains($nikahDi, 'LUAR') || str_contains($nikahDi, 'BEDOL')) {
                $rows[$bulan - 1]['LK']++;
            } else {
                $rows[$bulan - 1]['K']++;
            }
            $rows[$bulan - 1]['Nikah'] = $rows[$bulan - 1]['LK'] + $rows[$bulan - 1]['K'];
        }

        $pdkDedup = [];
        $pdkSeen = [];
        foreach ($pdkRows as $row) {
            $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
            if ($nd !== '' && ! isset($pdkSeen[$nd])) {
                $pdkSeen[$nd] = true;
                $pdkDedup[] = $row;
            }
        }

        foreach ($pdkDedup as $row) {
            if ($filterYear !== null && $filterYear !== '') {
                $tglDaftar = $row['Tanggal Daftar'] ?? '';
                try {
                    $d = Carbon::parse($tglDaftar);
                    if ((string) $d->year !== $filterYear) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $tglDaftar = $row['Tanggal Daftar'] ?? '';
            try {
                $bulan = (int) Carbon::parse($tglDaftar)->month;
            } catch (\Exception $e) {
                continue;
            }
            if ($bulan < 1 || $bulan > 12) {
                continue;
            }

            $nikahDi = mb_strtoupper(trim((string) ($row['Nikah Di'] ?? '')));
            if (str_contains($nikahDi, 'LUAR') || str_contains($nikahDi, 'BEDOL')) {
                $rows[$bulan - 1]['Pdk_LK']++;
            } else {
                $rows[$bulan - 1]['Pdk_K']++;
            }
            $rows[$bulan - 1]['Pdk_Jml'] = $rows[$bulan - 1]['Pdk_K'] + $rows[$bulan - 1]['Pdk_LK'];

            $tglNikah = $row['Tanggal Nikah'] ?? '';
            try {
                $bulanPelaksanaan = (int) Carbon::parse($tglNikah)->month;
            } catch (\Exception $e) {
                continue;
            }
            if ($bulanPelaksanaan >= 1 && $bulanPelaksanaan <= 12) {
                $key = 'R'.$bulanPelaksanaan;
                $rows[$bulan - 1][$key]++;
            }
        }

        $simDedup = [];
        $simSeen = [];
        foreach ($simRows as $row) {
            $np = trim((string) ($row['No Pendaftaran'] ?? ''));
            if ($np !== '' && ! isset($simSeen[$np])) {
                $simSeen[$np] = true;
                $simDedup[] = $row;
            }
        }

        foreach ($simDedup as $row) {
            $setorDate = $row['Tanggal dan Jam Setor'] ?? $row['Tanggal Setor'] ?? '';
            if ($setorDate === '') {
                continue;
            }
            try {
                $d = Carbon::parse($setorDate);
            } catch (\Exception $e) {
                continue;
            }
            if ($filterYear !== null && $filterYear !== '' && (string) $d->year !== $filterYear) {
                continue;
            }
            $bulan = (int) $d->month;
            if ($bulan >= 1 && $bulan <= 12) {
                $rows[$bulan - 1]['Setor']++;
            }
        }

        $totalRow = [
            'Bulan' => 'TOTAL',
            'month_num' => 0,
            'LK' => 0, 'K' => 0, 'Nikah' => 0,
            'Talak' => 0, 'Cerai' => 0, 'Rujuk' => 0,
            'Pdk_K' => 0, 'Pdk_LK' => 0, 'Pdk_Jml' => 0,
            'R1' => 0, 'R2' => 0, 'R3' => 0, 'R4' => 0, 'R5' => 0, 'R6' => 0,
            'R7' => 0, 'R8' => 0, 'R9' => 0, 'R10' => 0, 'R11' => 0, 'R12' => 0,
            'Gagal' => 0, 'Tunda' => 0, 'Miskin' => 0, 'Bencana Alam' => 0,
            'Jumlah' => 0, 'Setor' => 0,
        ];
        foreach ($rows as $r) {
            foreach (['LK', 'K', 'Nikah', 'Talak', 'Cerai', 'Rujuk', 'Pdk_K', 'Pdk_LK', 'Pdk_Jml', 'R1', 'R2', 'R3', 'R4', 'R5', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12', 'Gagal', 'Tunda', 'Miskin', 'Bencana Alam', 'Setor'] as $f) {
                $totalRow[$f] += $r[$f];
            }
        }
        foreach ($rows as &$r) {
            $r['Jumlah'] = $r['R1'] + $r['R2'] + $r['R3'] + $r['R4'] + $r['R5'] + $r['R6'] + $r['R7'] + $r['R8'] + $r['R9'] + $r['R10'] + $r['R11'] + $r['R12'] + $r['Gagal'] + $r['Tunda'] + $r['Miskin'] + $r['Bencana Alam'];
        }
        unset($r);
        $totalRow['Jumlah'] = $totalRow['R1'] + $totalRow['R2'] + $totalRow['R3'] + $totalRow['R4'] + $totalRow['R5'] + $totalRow['R6'] + $totalRow['R7'] + $totalRow['R8'] + $totalRow['R9'] + $totalRow['R10'] + $totalRow['R11'] + $totalRow['R12'] + $totalRow['Gagal'] + $totalRow['Tunda'] + $totalRow['Miskin'] + $totalRow['Bencana Alam'];

        $rows[] = $totalRow;

        return $rows;
    }

    private function buildNtcrManualData(array $config): array
    {
        $savedManual = $config['manual_data'] ?? [];
        if (! is_array($savedManual)) {
            $savedManual = [];
        }

        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $manualCols = ['Talak', 'Cerai', 'Rujuk', 'Gagal', 'Tunda', 'Miskin', 'Bencana Alam'];

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthName = $monthNames[$m - 1];
            $existing = $savedManual[$monthName] ?? [];
            $row = ['bulan' => $monthName];
            foreach ($manualCols as $col) {
                $row[$col] = $existing[$col] ?? 0;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function buildNbData(array $config, ?User $user, ?string $filterMonth, ?string $filterYear, ?string $filterDateFrom = null, ?string $filterDateTo = null): array
    {
        $pdkImports = Import::where('table_name', 'like', '%pendaftaran nikah%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $pdkRows = [];
        if ($pdkImports->isNotEmpty()) {
            $pdkRaw = ImportData::whereIn('import_id', $pdkImports)
                ->pluck('row_data')
                ->all();
            foreach ($pdkRaw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $pdkRows[] = $row;
            }
        }

        $pdkDedup = [];
        $pdkSeen = [];
        foreach ($pdkRows as $row) {
            $nd = trim((string) ($row['Nomor Daftar'] ?? ''));
            if ($nd !== '' && ! isset($pdkSeen[$nd])) {
                $pdkSeen[$nd] = true;
                $pdkDedup[] = $row;
            }
        }

        $filtered = [];
        foreach ($pdkDedup as $row) {
            $tglDaftar = $row['Tanggal Daftar'] ?? '';
            try {
                $d = Carbon::parse($tglDaftar);
            } catch (\Exception $e) {
                continue;
            }
            if ($filterYear !== null && $filterYear !== '' && (string) $d->year !== $filterYear) {
                continue;
            }
            if ($filterMonth !== null && $filterMonth !== '' && (string) $d->month !== $filterMonth) {
                continue;
            }
            if ($filterDateFrom !== null && $filterDateFrom !== '') {
                $from = Carbon::parse($filterDateFrom)->startOfDay();
                if ($d->lt($from)) {
                    continue;
                }
            }
            if ($filterDateTo !== null && $filterDateTo !== '') {
                $to = Carbon::parse($filterDateTo)->endOfDay();
                if ($d->gt($to)) {
                    continue;
                }
            }
            $filtered[] = $row;
        }

        $byDate = [];
        foreach ($filtered as $row) {
            $tglDaftar = $row['Tanggal Daftar'] ?? '';
            try {
                $d = Carbon::parse($tglDaftar);
            } catch (\Exception $e) {
                continue;
            }
            $dateKey = $d->format('Y-m-d');
            if (! isset($byDate[$dateKey])) {
                $byDate[$dateKey] = [];
            }
            $byDate[$dateKey][] = $row;
        }

        ksort($byDate);

        $manualRows = $config['manual_data'] ?? [];
        if (! is_array($manualRows)) {
            $manualRows = [];
        }

        $sisaRows = [];
        $otherManualRows = [];
        foreach ($manualRows as $mr) {
            if (! empty($mr['is_sisa_bulan_lalu'])) {
                $sisaRows[] = $mr;
            } else {
                $otherManualRows[] = $mr;
            }
        }

        $rows = [];
        $runningSisa = 0;
        $rowNum = 0;
        foreach ($sisaRows as $sr) {
            $rowNum++;
            $masuk = isset($sr['masuk']) && $sr['masuk'] !== '' ? (int) $sr['masuk'] : null;
            $rows[] = [
                'no' => $rowNum,
                'tanggal' => '01/'.str_pad((string) $filterMonth, 2, '0', STR_PAD_LEFT).'/'.$filterYear,
                'tanggal_key' => 'manual_sisa_'.$rowNum,
                'uraian' => $sr['uraian'] ?? 'Sisa bulan lalu',
                'masuk' => $masuk,
                'keluar' => 0,
                'sisa' => $masuk,
                'satuan' => 'Lembar',
                'penerimaan' => $sr['penerimaan'] ?? null,
                'pengeluaran' => '',
                'is_tgl1' => true,
                'is_manual' => true,
                'is_sisa_bulan_lalu' => true,
                'entries' => [],
            ];
            $runningSisa = $masuk ?? 0;
        }

        $matchedManual = [];

        foreach ($byDate as $dateKey => $entries) {
            $d = Carbon::parse($dateKey);
            $tanggal = $d->format('d/m/Y');
            $count = count($entries);
            usort($entries, fn ($a, $b) => strnatcmp(trim((string) ($a['Nomor Daftar'] ?? '')), trim((string) ($b['Nomor Daftar'] ?? ''))));

            $nomorDaftars = array_map(fn ($r) => trim((string) ($r['Nomor Daftar'] ?? '')), $entries);
            $nomorDaftars = array_values(array_filter($nomorDaftars));

            if (count($nomorDaftars) > 1) {
                $pengeluaran = $nomorDaftars[0].' - '.end($nomorDaftars);
            } elseif (count($nomorDaftars) === 1) {
                $pengeluaran = $nomorDaftars[0];
            } else {
                $pengeluaran = '';
            }

            if ($count > 1) {
                $nama1 = trim((string) ($entries[0]['Nama Suami'] ?? ''));
                $namaIstri1 = trim((string) ($entries[0]['Nama Istri'] ?? ''));
                $uraian = $nama1.' - '.$namaIstri1.' Cs';
            } else {
                $namaSuami = trim((string) ($entries[0]['Nama Suami'] ?? ''));
                $namaIstri = trim((string) ($entries[0]['Nama Istri'] ?? ''));
                $uraian = $namaSuami.' - '.$namaIstri;
            }

            $md = [];
            foreach ($otherManualRows as $mr) {
                if (! empty($mr['is_rusak'])) {
                    continue;
                }
                $mrDay = str_pad((string) ($mr['tanggal'] ?? ''), 2, '0', STR_PAD_LEFT);
                if ($mrDay === $d->format('d') && ! in_array($mr, $matchedManual, true)) {
                    $md = $mr;
                    $matchedManual[] = $mr;
                    break;
                }
            }

            $rowNum++;
            $rows[] = [
                'no' => $rowNum,
                'tanggal' => $tanggal,
                'tanggal_key' => $dateKey,
                'uraian' => $md['uraian'] ?? $uraian,
                'masuk' => isset($md['masuk']) && $md['masuk'] !== '' ? (int) $md['masuk'] : null,
                'keluar' => $count,
                'sisa' => null,
                'satuan' => 'Lembar',
                'penerimaan' => $md['penerimaan'] ?? null,
                'pengeluaran' => $pengeluaran,
                'is_tgl1' => $d->day === 1,
                'entries' => $entries,
            ];
        }

        foreach ($otherManualRows as $mr) {
            if (in_array($mr, $matchedManual, true)) {
                continue;
            }
            $rowNum++;
            $rows[] = [
                'no' => $rowNum,
                'tanggal' => $this->manualRowTanggal($mr['tanggal'] ?? '', $filterMonth, $filterYear, $filterDateFrom),
                'tanggal_key' => 'manual_extra_'.$rowNum,
                'uraian' => $mr['uraian'] ?? '',
                'masuk' => isset($mr['masuk']) && $mr['masuk'] !== '' ? (int) $mr['masuk'] : null,
                'keluar' => isset($mr['keluar']) && $mr['keluar'] !== '' ? (int) $mr['keluar'] : 0,
                'sisa' => null,
                'satuan' => 'Lembar',
                'penerimaan' => $mr['penerimaan'] ?? null,
                'pengeluaran' => '',
                'is_tgl1' => false,
                'is_manual' => true,
                'entries' => [],
            ];
        }

        foreach ($rows as &$r) {
            if (! empty($r['is_sisa_bulan_lalu'])) {
                continue;
            }
            if (! empty($r['is_manual'])) {
                // Baris manual tambahan (mis. Rusak): masuk menambah, keluar mengurangi sisa.
                if (isset($r['masuk']) && $r['masuk'] !== null) {
                    $runningSisa += $r['masuk'];
                }
                $runningSisa -= (int) ($r['keluar'] ?? 0);
                $r['sisa'] = $runningSisa;

                continue;
            }
            if ($r['is_tgl1'] && isset($r['masuk']) && $r['masuk'] !== null) {
                $r['sisa'] = $r['masuk'] - $r['keluar'];
                $runningSisa = $r['sisa'];
            } else {
                $runningSisa -= $r['keluar'];
                $r['sisa'] = $runningSisa;
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Tanggal lengkap dd/MM/YYYY untuk baris manual NB/N dari input hari + filter laporan.
     */
    private function manualRowTanggal(mixed $day, ?string $filterMonth, ?string $filterYear, ?string $filterDateFrom): string
    {
        $d = trim((string) ($day ?? ''));

        if ($d === '') {
            return '';
        }

        $month = $filterMonth;
        $year = $filterYear;

        if (($month === null || $month === '') && ! empty($filterDateFrom)) {
            try {
                $base = Carbon::parse($filterDateFrom);
                $month = (string) $base->month;
                $year = $year ?: (string) $base->year;
            } catch (\Exception $e) {
                return '';
            }
        }

        if ($month === null || $month === '' || $year === null || $year === '') {
            return '';
        }

        return str_pad($d, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.$year;
    }

    private function buildNbManualData(array $config): array
    {
        $savedManual = $config['manual_data'] ?? [];
        if (! is_array($savedManual)) {
            $savedManual = [];
        }

        return $savedManual;
    }

    private function buildNData(array $config, ?User $user, ?string $filterMonth, ?string $filterYear, ?string $filterDateFrom = null, ?string $filterDateTo = null): array
    {
        $pnImports = Import::where('table_name', 'like', '%peristiwa nikah%')
            ->where('user_id', $user?->id)
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id');
        $pnRows = [];
        if ($pnImports->isNotEmpty()) {
            $pnRaw = ImportData::whereIn('import_id', $pnImports)
                ->pluck('row_data')
                ->all();
            foreach ($pnRaw as $r) {
                $row = is_array($r) ? $r : json_decode((string) $r, true) ?? [];
                $pnRows[] = $row;
            }
        }

        $pnDedup = [];
        $pnSeen = [];
        foreach ($pnRows as $row) {
            $na = trim((string) ($row['Nomor Akta Nikah'] ?? ''));
            if ($na !== '' && ! isset($pnSeen[$na])) {
                $pnSeen[$na] = true;
                $pnDedup[] = $row;
            }
        }

        $filtered = [];
        foreach ($pnDedup as $row) {
            $tglNikah = $row['Tanggal Nikah'] ?? '';
            try {
                $d = Carbon::parse($tglNikah);
            } catch (\Exception $e) {
                continue;
            }
            if ($filterYear !== null && $filterYear !== '' && (string) $d->year !== $filterYear) {
                continue;
            }
            if ($filterMonth !== null && $filterMonth !== '' && (string) $d->month !== $filterMonth) {
                continue;
            }
            if ($filterDateFrom !== null && $filterDateFrom !== '') {
                $from = Carbon::parse($filterDateFrom)->startOfDay();
                if ($d->lt($from)) {
                    continue;
                }
            }
            if ($filterDateTo !== null && $filterDateTo !== '') {
                $to = Carbon::parse($filterDateTo)->endOfDay();
                if ($d->gt($to)) {
                    continue;
                }
            }
            $filtered[] = $row;
        }

        $byDate = [];
        foreach ($filtered as $row) {
            $tglNikah = $row['Tanggal Nikah'] ?? '';
            try {
                $d = Carbon::parse($tglNikah);
            } catch (\Exception $e) {
                continue;
            }
            $dateKey = $d->format('Y-m-d');
            if (! isset($byDate[$dateKey])) {
                $byDate[$dateKey] = [];
            }
            $byDate[$dateKey][] = $row;
        }

        ksort($byDate);

        $manualRows = $config['manual_data'] ?? [];
        if (! is_array($manualRows)) {
            $manualRows = [];
        }

        $sisaRows = [];
        $otherManualRows = [];
        foreach ($manualRows as $mr) {
            if (! empty($mr['is_sisa_bulan_lalu'])) {
                $sisaRows[] = $mr;
            } else {
                $otherManualRows[] = $mr;
            }
        }

        $rows = [];
        $runningSisa = 0;
        $rowNum = 0;

        foreach ($sisaRows as $sr) {
            $rowNum++;
            $masuk = isset($sr['masuk']) && $sr['masuk'] !== '' ? (int) $sr['masuk'] : null;
            $rows[] = [
                'no' => $rowNum,
                'tanggal' => '01/'.str_pad((string) $filterMonth, 2, '0', STR_PAD_LEFT).'/'.$filterYear,
                'tanggal_key' => 'manual_sisa_'.$rowNum,
                'uraian' => $sr['uraian'] ?? 'Sisa bulan lalu',
                'masuk' => $masuk,
                'keluar' => 0,
                'sisa' => $masuk,
                'satuan' => 'Lembar',
                'penerimaan' => $sr['penerimaan'] ?? null,
                'pengeluaran' => '',
                'is_tgl1' => true,
                'is_manual' => true,
                'is_sisa_bulan_lalu' => true,
                'entries' => [],
            ];
            $runningSisa = $masuk ?? 0;
        }

        $matchedManual = [];

        foreach ($byDate as $dateKey => $entries) {
            $d = Carbon::parse($dateKey);
            $tanggal = $d->format('d/m/Y');
            $count = count($entries);
            usort($entries, fn ($a, $b) => strnatcmp(trim((string) ($a['Nomor Akta Nikah'] ?? '')), trim((string) ($b['Nomor Akta Nikah'] ?? ''))));
            $nomorAktas = array_map(fn ($r) => trim((string) ($r['Nomor Akta Nikah'] ?? '')), $entries);
            $nomorAktas = array_values(array_filter($nomorAktas));

            if (count($nomorAktas) > 1) {
                $pengeluaran = $nomorAktas[0].' - '.end($nomorAktas);
            } elseif (count($nomorAktas) === 1) {
                $pengeluaran = $nomorAktas[0];
            } else {
                $pengeluaran = '';
            }

            if ($count > 1) {
                $nama1 = trim((string) ($entries[0]['Nama Suami'] ?? ''));
                $namaIstri1 = trim((string) ($entries[0]['Nama Istri'] ?? ''));
                $uraian = $nama1.' - '.$namaIstri1.' Cs';
            } else {
                $namaSuami = trim((string) ($entries[0]['Nama Suami'] ?? ''));
                $namaIstri = trim((string) ($entries[0]['Nama Istri'] ?? ''));
                $uraian = $namaSuami.' - '.$namaIstri;
            }

            $md = [];
            foreach ($otherManualRows as $mr) {
                if (! empty($mr['is_rusak'])) {
                    continue;
                }
                $mrDay = str_pad((string) ($mr['tanggal'] ?? ''), 2, '0', STR_PAD_LEFT);
                if ($mrDay === $d->format('d') && ! in_array($mr, $matchedManual, true)) {
                    $md = $mr;
                    $matchedManual[] = $mr;
                    break;
                }
            }

            $rowNum++;
            $rows[] = [
                'no' => $rowNum,
                'tanggal' => $tanggal,
                'tanggal_key' => $dateKey,
                'uraian' => $md['uraian'] ?? $uraian,
                'masuk' => isset($md['masuk']) && $md['masuk'] !== '' ? (int) $md['masuk'] : null,
                'keluar' => $count,
                'sisa' => null,
                'satuan' => 'Lembar',
                'penerimaan' => $md['penerimaan'] ?? null,
                'pengeluaran' => $pengeluaran,
                'is_tgl1' => $d->day === 1,
                'entries' => $entries,
            ];
        }

        foreach ($otherManualRows as $mr) {
            if (in_array($mr, $matchedManual, true)) {
                continue;
            }
            $rowNum++;
            $rows[] = [
                'no' => $rowNum,
                'tanggal' => $this->manualRowTanggal($mr['tanggal'] ?? '', $filterMonth, $filterYear, $filterDateFrom),
                'tanggal_key' => 'manual_extra_'.$rowNum,
                'uraian' => $mr['uraian'] ?? '',
                'masuk' => isset($mr['masuk']) && $mr['masuk'] !== '' ? (int) $mr['masuk'] : null,
                'keluar' => isset($mr['keluar']) && $mr['keluar'] !== '' ? (int) $mr['keluar'] : 0,
                'sisa' => null,
                'satuan' => 'Lembar',
                'penerimaan' => $mr['penerimaan'] ?? null,
                'pengeluaran' => '',
                'is_tgl1' => false,
                'is_manual' => true,
                'entries' => [],
            ];
        }

        foreach ($rows as &$r) {
            if (! empty($r['is_sisa_bulan_lalu'])) {
                continue;
            }
            if (! empty($r['is_manual'])) {
                // Baris manual tambahan (mis. Rusak): masuk menambah, keluar mengurangi sisa.
                if (isset($r['masuk']) && $r['masuk'] !== null) {
                    $runningSisa += $r['masuk'];
                }
                $runningSisa -= (int) ($r['keluar'] ?? 0);
                $r['sisa'] = $runningSisa;

                continue;
            }
            if ($r['is_tgl1'] && isset($r['masuk']) && $r['masuk'] !== null) {
                $r['sisa'] = $r['masuk'] - $r['keluar'];
                $runningSisa = $r['sisa'];
            } else {
                $runningSisa -= $r['keluar'];
                $r['sisa'] = $runningSisa;
            }
        }
        unset($r);

        return $rows;
    }

    private function buildNManualData(array $config): array
    {
        $savedManual = $config['manual_data'] ?? [];
        if (! is_array($savedManual)) {
            $savedManual = [];
        }

        return $savedManual;
    }

    public function destroy(Report $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        if ($report->file_path) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->delete();

        return redirect()->route('reports.index')
            ->with('status', 'Laporan berhasil dihapus.');
    }
}
