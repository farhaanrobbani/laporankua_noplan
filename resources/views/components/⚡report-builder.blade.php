<?php

use App\Jobs\GenerateReport;
use App\Models\Import;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\MergeService;
use App\Services\ReportGenerationService;
use Livewire\Component;

new class extends Component
{
    public ?int $importId = null;

    /** @var array<int, array{id: int, file_name: string, table_name: string|null}> */
    public array $imports = [];

    /** @var string[] */
    public array $columns = [];

    /** @var string[] */
    public array $fields = [];

    public string $search = '';

    public string $filterColumn = '';

    public string $filterValue = '';

    public string $sortColumn = '';

    public string $sortDirection = 'asc';

    public string $title = '';

    public string $format = 'print';

    public string $orientation = 'portrait';

    public string $filterMonth = '';

    public string $filterYear = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    /** @var array{headings: string[], rows: array, total: int}|null */
    public ?array $preview = null;

    public bool $hasDefaultTemplate = false;

    public bool $isMergeMode = false;

    /** @var int[] */
    public array $mergeImportIds = [];

    /** @var int[] */
    public array $selectedImportIds = [];

    /** @var string[] */
    public array $autoJoinColumns = [];

    /** @var array<int, array{id: int, name: string, description: string|null, output_format: string, fields_json: array, filters_json: array, sorting_json: array, layout_json: array}> */
    public array $globalTemplates = [];

    public ?int $selectedTemplateId = null;

    /** @var array{template_name: string, description: string|null, label: string, keyword: string, example: string, note: string}|null */
    public ?array $importGuide = null;

    public ?array $tableLayout = null;

    /** @var array<int, array{prefix: string, label: string, keluar_jumlah: int, min_porp: string, max_porp: string}> */
    public array $naVersions = [];

    /** @var array<int, array{masuk_jumlah: string, masuk_seri: string, keluar_jumlah: string, keluar_seri: string, sisa_jumlah: string, sisa_seri: string, keterangan: string}> */
    public array $manualData = [];

    public string $closureReason = 'akhir_bulan';

    public string $gantiKepalaSk = '';

    public string $monevStNumber = '';

    public string $pageStart = '1';

    /** @var array<int, array{id: string, nama: string}> */
    public array $monevMembers = [];

    public function mount(): void
    {
        $this->imports = Import::where('user_id', auth()->id())
            ->whereIn('status', ['success', 'appended'])
            ->whereHas('importData')
            ->latest()
            ->withCount('importData')
            ->get(['id', 'file_name', 'table_name'])
            ->groupBy('table_name')
            ->map(fn ($rows, $tableName) => [
                'id' => $rows->first()->id,
                'table_name' => $tableName,
                'total_rows' => $rows->sum('import_data_count'),
                'import_ids' => $rows->pluck('id')->toArray(),
            ])
            ->values()
            ->all();

        $this->globalTemplates = ReportTemplate::where('is_global', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'output_format' => $t->output_format,
                'fields_json' => $t->fields_json ?? [],
                'filters_json' => $t->filters_json ?? [],
                'sorting_json' => $t->sorting_json ?? [],
                'layout_json' => $t->layout_json ?? [],
            ])
            ->all();

        $this->hasDefaultTemplate = ReportTemplate::where('is_global', true)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
    }

    public function updatedMergeImportIds(): void
    {
        $this->columns = [];
        $this->fields = [];
        $this->preview = null;
        $this->tableLayout = null;
        $this->autoJoinColumns = [];

        if (count($this->mergeImportIds) >= 2) {
            $this->columns = app(MergeService::class)->getAllColumns($this->mergeImportIds);
            $this->fields = $this->columns;
            $this->autoJoinColumns = app(MergeService::class)->getSharedColumns($this->mergeImportIds);

            if ($this->selectedTemplateId) {
                $this->applySelectedTemplate();
            }
        }
    }

    public function toggleImportGroup(array $ids): void
    {
        $allChecked = count(array_intersect($ids, $this->mergeImportIds)) === count($ids);

        if ($allChecked) {
            $this->mergeImportIds = array_values(array_diff($this->mergeImportIds, $ids));
        } else {
            $this->mergeImportIds = array_values(array_unique(array_merge($this->mergeImportIds, $ids)));
        }

        $this->updatedMergeImportIds();
    }

    public function updatedSelectedTemplateId(): void
    {
        $this->applySelectedTemplate();
    }

    public function loadDefaultTemplate(): void
    {
        $template = ReportTemplate::where('is_global', true)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $template) {
            return;
        }

        $this->selectedTemplateId = $template->id;
        $this->applySelectedTemplate();
    }

    public function applySelectedTemplate(): void
    {
        if (! $this->selectedTemplateId) {
            $this->importGuide = null;

            return;
        }

        $template = ReportTemplate::where('is_global', true)
            ->where('is_active', true)
            ->find($this->selectedTemplateId);

        if (! $template) {
            $this->importGuide = null;

            return;
        }

        $this->importGuide = $this->importGuideForTemplate($template);
        $this->applyTemplateConfig($template);
    }

    public function isImportRecommended(string $tableName): bool
    {
        if (! $this->importGuide) {
            return false;
        }

        return str_contains(mb_strtolower($tableName), $this->importGuide['keyword']);
    }

    private function importGuideForTemplate(ReportTemplate $template): array
    {
        $type = $template->layout_json['table_layout']['type'] ?? null;
        $name = mb_strtolower($template->name);

        $guide = match (true) {
            $type === 'grouped_detail' || str_contains($name, 'l4') => [
                'label' => 'Laporan Simponi',
                'keyword' => 'simponi',
                'example' => 'laporan-simponi-08-2026.xlsx',
                'note' => 'File setoran simponi yang memuat data setoran per akad.',
            ],
            $type === 'formulir' || $type === 'laporan_na' || str_contains($name, 'l3') || str_contains($name, 'laporan na') => [
                'label' => 'Laporan Model L3',
                'keyword' => 'model l3',
                'example' => 'laporan-model-l3-08-2026.xlsx',
                'note' => 'File laporan model L3 yang memuat data formulir, perforasi, dan keterangan.',
            ],
            $type === 'laporan_nb' || str_contains($name, 'nb') => [
                'label' => 'Laporan Pendaftaran Nikah',
                'keyword' => 'pendaftaran nikah',
                'example' => 'laporan-pendaftaran-nikah-08-2026.xlsx',
                'note' => 'File pendaftaran nikah yang memuat data calon pengantin dan status wali.',
            ],
            $type === 'rekap_nr1' || str_contains($name, 'nr1') => [
                'label' => 'Laporan Pendaftaran Nikah',
                'keyword' => 'pendaftaran nikah',
                'example' => 'laporan-pendaftaran-nikah-08-2026.xlsx',
                'note' => 'Data peristiwa nikah dipakai otomatis untuk melengkapi kelurahan.',
            ],
            $type === 'laporan_l1' || str_contains($name, 'l1') => [
                'label' => 'Laporan Peristiwa Nikah',
                'keyword' => 'peristiwa nikah',
                'example' => 'laporan-peristiwa-nikah-08-2026.xlsx',
                'note' => 'File peristiwa nikah. Data pendaftaran nikah dan model L3 dipakai otomatis bila tersedia.',
            ],
            $type === 'rekap_ntcr' || str_contains($name, 'ntcr') => [
                'label' => 'Laporan Peristiwa Nikah',
                'keyword' => 'peristiwa nikah',
                'example' => 'laporan-peristiwa-nikah-08-2026.xlsx',
                'note' => 'File peristiwa nikah. Data pendaftaran nikah dan simponi dipakai otomatis bila tersedia.',
            ],
            $type === 'laporan_n' || $name === 'laporan n' => [
                'label' => 'Laporan Peristiwa Nikah',
                'keyword' => 'peristiwa nikah',
                'example' => 'laporan-peristiwa-nikah-08-2026.xlsx',
                'note' => 'File peristiwa nikah.',
            ],
            default => [
                'label' => 'Laporan Peristiwa Nikah',
                'keyword' => 'peristiwa nikah',
                'example' => 'laporan-peristiwa-nikah-08-2026.xlsx',
                'note' => 'Template ini memakai data peristiwa nikah.',
            ],
        };

        $guide['template_name'] = $template->name;
        $guide['description'] = $template->description;

        return $guide;
    }

    private function applyTemplateConfig(ReportTemplate $template): void
    {
        $this->format = $template->output_format;
        $this->orientation = $template->layout_json['orientation'] ?? 'portrait';
        $this->search = $template->filters_json['search'] ?? '';
        $this->filterColumn = $template->filters_json['filter_column'] ?? '';
        $this->filterValue = $template->filters_json['filter_value'] ?? '';
        $this->sortColumn = $template->sorting_json['column'] ?? '';
        $this->sortDirection = $template->sorting_json['direction'] ?? 'asc';
        $this->tableLayout = $template->layout_json['table_layout'] ?? null;
        $this->filterMonth = '';
        $this->filterYear = '';

        if ($this->isMergeMode && count($this->mergeImportIds) >= 2) {
            $this->columns = app(MergeService::class)->getAllColumns($this->mergeImportIds);
            $this->fields = array_values(array_intersect($template->fields_json ?? [], $this->columns));
            if ($this->fields === []) {
                $this->fields = $this->columns;
            }
            $this->loadPreview();
        } elseif ($this->importId) {
            $import = $this->selectedImport();
            if ($import) {
                $this->columns = $import->availableColumns();
                $this->fields = array_values(array_intersect($template->fields_json ?? [], $this->columns));
                if ($this->fields === []) {
                    $this->fields = $this->columns;
                }
                $this->loadPreview();
            }
        }

        if (($this->tableLayout['type'] ?? '') === 'formulir') {
            $this->initManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_na') {
            $this->initLaporanNaData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_l1') {
            $this->initL1ManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'rekap_ntcr') {
            $this->initNtcrManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_nb' || ($this->tableLayout['type'] ?? '') === 'laporan_n') {
            $this->initNbManualData();
        }
    }

    private function initManualData(): void
    {
        $empty = ['formulir' => '', 'masuk_jumlah' => '', 'masuk_seri_awal' => '', 'masuk_seri_akhir' => '', 'keluar_jumlah' => '', 'keluar_seri_awal' => '', 'keluar_seri_akhir' => '', 'sisa_jumlah' => '', 'sisa_seri_awal' => '', 'sisa_seri_akhir' => '', 'keterangan' => ''];
        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        $this->naVersions = $hasFilter ? $this->detectNaVersions() : [];

        $customRows = [];
        $naCount = count($this->naVersions);
        $naSeen = 0;
        if ($this->manualData) {
            foreach ($this->manualData as $row) {
                $formulir = $row['formulir'] ?? '';
                if (str_starts_with($formulir, 'Model NA')) {
                    if ($naSeen < $naCount) {
                        $naSeen++;
                    } else {
                        $row['keluar_jumlah'] = '0';
                        $row['keluar_seri_awal'] = $row['masuk_seri_awal'] ?? '';
                        $row['keluar_seri_akhir'] = $row['masuk_seri_akhir'] ?? '';
                        $row['keluar_seri'] = $this->buildSeriRange($row['masuk_seri_awal'] ?? '', $row['masuk_seri_akhir'] ?? '');
                        $customRows[] = $row;
                    }
                }
            }
        }

        $baseRows = [
            array_merge($empty, ['formulir' => 'Model N']),
        ];

        foreach ($this->naVersions as $i => $ver) {
            $baseRows[] = array_merge($empty, [
                'formulir' => $ver['label'],
                'keluar_jumlah' => (string) $ver['keluar_jumlah'],
                'keluar_seri_awal' => $ver['min_porp'],
                'keluar_seri_akhir' => $ver['max_porp'],
            ]);
        }

        foreach ($customRows as $customRow) {
            $baseRows[] = $customRow;
        }

        $baseRows[] = array_merge($empty, ['formulir' => 'Model DN']);
        $baseRows[] = array_merge($empty, ['formulir' => 'Model NB']);

        $this->manualData = $baseRows;
        $this->rebuildStaticRows();
    }

    private function detectNaVersions(): array
    {
        $importIds = $this->isMergeMode ? $this->mergeImportIds : $this->selectedImportIds;
        if (empty($importIds)) {
            return [];
        }

        $prefixLength = $this->tableLayout['na_version_prefix_length'] ?? 6;

        $allRows = \App\Models\ImportData::whereIn('import_id', $importIds)
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
                $groups[$prefix] = ['porporasi' => [], 'filtered_porp' => [], 'count' => 0, 'rusak_status' => 0];
            }
            $groups[$prefix]['porporasi'][] = (int) $nomor;
        }

        $filterMonth = $this->filterMonth !== '' ? (int) $this->filterMonth : null;
        $filterYear = $this->filterYear !== '' ? (int) $this->filterYear : null;
        $filterDateFrom = $this->filterDateFrom;
        $filterDateTo = $this->filterDateTo;

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
                    $date = \Carbon\Carbon::parse($tanggalCetak);
                    if ($filterMonth !== null && (int) $date->month !== $filterMonth) {
                        continue;
                    }
                    if ($filterYear !== null && (int) $date->year !== $filterYear) {
                        continue;
                    }
                    if ($filterDateFrom !== '') {
                        $from = \Carbon\Carbon::parse($filterDateFrom)->startOfDay();
                        if ($date->lt($from)) {
                            continue;
                        }
                    }
                    if ($filterDateTo !== '') {
                        $to = \Carbon\Carbon::parse($filterDateTo)->endOfDay();
                        if ($date->gt($to)) {
                            continue;
                        }
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

        // Rusak = celah interior, dihitung dari seluruh baris agar cetak ulang
        // lintas bulan tidak dianggap sebagai awal seri baru, lalu difilter periode.
        $gaps = app(\App\Services\RusakDetectionService::class)->detectMissingByMonth($allRows, $prefixLength);
        $fromDate = $filterDateFrom !== '' ? \Carbon\Carbon::parse($filterDateFrom)->startOfDay() : null;
        $toDate = $filterDateTo !== '' ? \Carbon\Carbon::parse($filterDateTo)->endOfDay() : null;
        $rusakByPrefix = [];
        foreach ($gaps as $gap) {
            if ($gap['date'] === null) {
                continue;
            }
            $gapDate = \Carbon\Carbon::parse($gap['date']);
            if ($filterMonth !== null && (int) $gapDate->month !== $filterMonth) {
                continue;
            }
            if ($filterYear !== null && (int) $gapDate->year !== $filterYear) {
                continue;
            }
            if ($fromDate !== null && $gapDate->lt($fromDate)) {
                continue;
            }
            if ($toDate !== null && $gapDate->gt($toDate)) {
                continue;
            }
            $rusakByPrefix[$gap['prefix']][] = $gap['number'];
        }

        ksort($groups);

        $versions = [];
        foreach ($groups as $prefix => $data) {
            if ($data['count'] === 0) {
                continue;
            }
            $filteredPorp = $data['filtered_porp'];
            $gapCount = count($rusakByPrefix[$prefix] ?? []);
            $rusakStatusCount = (int) ($data['rusak_status'] ?? 0);
            $versions[] = [
                'prefix' => (string) $prefix,
                'label' => 'Model NA ('.$prefix.')',
                'keluar_jumlah' => $data['count'] + $gapCount,
                'rusak_jumlah' => $gapCount + $rusakStatusCount,
                'min_porp' => (string) min($filteredPorp),
                'max_porp' => (string) max($filteredPorp),
            ];
        }

        return $versions;
    }

    public function updatedManualData(): void
    {
        $this->computeMasukJumlah();
        $this->recalculateSisa();
        $this->computeSisaSeri();
    }

    private function computeMasukJumlah(): void
    {
        foreach ($this->manualData as $i => $row) {
            $awal = (int) ($row['masuk_seri_awal'] ?? 0);
            $akhir = (int) ($row['masuk_seri_akhir'] ?? 0);

            if ($awal > 0 && $akhir >= $awal) {
                $this->manualData[$i]['masuk_jumlah'] = (string) ($akhir - $awal + 1);
            }
        }
    }

    private function recalculateSisa(): void
    {
        foreach ($this->manualData as $i => $row) {
            $masuk = (int) ($row['masuk_jumlah'] ?? 0);
            $keluar = (int) ($row['keluar_jumlah'] ?? 0);
            $this->manualData[$i]['sisa_jumlah'] = $masuk > 0 ? (string) max(0, $masuk - $keluar) : '';
        }
    }

    private function computeSisaSeri(): void
    {
        foreach ($this->manualData as $i => $row) {
            $keluarAkhir = (int) ($row['keluar_seri_akhir'] ?? 0);
            $masukAkhir = (int) ($row['masuk_seri_akhir'] ?? 0);
            $sisaJumlah = (int) ($row['sisa_jumlah'] ?? 0);

            if ($sisaJumlah <= 0) {
                $this->manualData[$i]['sisa_seri_awal'] = '';
                $this->manualData[$i]['sisa_seri_akhir'] = '';
            } elseif ($keluarAkhir > 0 && $masukAkhir >= $keluarAkhir) {
                $this->manualData[$i]['sisa_seri_awal'] = (string) ($keluarAkhir + 1);
                $this->manualData[$i]['sisa_seri_akhir'] = (string) $masukAkhir;
            } else {
                $masukAwal = (int) ($row['masuk_seri_awal'] ?? 0);
                if ($sisaJumlah > 0 && $masukAwal > 0) {
                    $this->manualData[$i]['sisa_seri_awal'] = (string) $masukAwal;
                    $this->manualData[$i]['sisa_seri_akhir'] = (string) $masukAkhir;
                } else {
                    $this->manualData[$i]['sisa_seri_awal'] = '';
                    $this->manualData[$i]['sisa_seri_akhir'] = '';
                }
            }
        }
    }

    private function rebuildStaticRows(): void
    {
        if (! $this->tableLayout) {
            return;
        }

        $staticRows = [
            ['formulir' => 'Model N', 'row_num' => 1, 'dynamic' => false],
        ];

        $rowNum = 2;
        foreach ($this->manualData as $i => $row) {
            if ($i === 0) {
                continue;
            }
            $formulir = $row['formulir'] ?? '';
            if (! str_starts_with($formulir, 'Model NA')) {
                break;
            }
            $staticRows[] = [
                'formulir' => $formulir,
                'row_num' => $rowNum,
                'dynamic' => true,
                'dynamic_type' => 'na_version',
            ];
            $rowNum++;
        }

        $staticRows[] = ['formulir' => 'Model DN', 'row_num' => $rowNum, 'dynamic' => false];
        $rowNum++;
        $staticRows[] = ['formulir' => 'Model NB', 'row_num' => $rowNum, 'dynamic' => false];

        $this->tableLayout['static_rows'] = $staticRows;
    }

    public function addNaRow(): void
    {
        $empty = ['formulir' => '', 'masuk_jumlah' => '', 'masuk_seri_awal' => '', 'masuk_seri_akhir' => '', 'keluar_jumlah' => '0', 'keluar_seri_awal' => '', 'keluar_seri_akhir' => '', 'sisa_jumlah' => '', 'sisa_seri_awal' => '', 'sisa_seri_akhir' => '', 'keterangan' => ''];

        $count = count($this->manualData);
        $insertIdx = max(1, $count - 2);
        array_splice($this->manualData, $insertIdx, 0, [array_merge($empty, ['formulir' => 'Model NA ()'])]);

        $this->rebuildStaticRows();
    }

    public function removeNaRow(int $index): void
    {
        if (! isset($this->manualData[$index])) {
            return;
        }
        $formulir = $this->manualData[$index]['formulir'] ?? '';
        if (str_starts_with($formulir, 'Model NA') && $index > 0) {
            array_splice($this->manualData, $index, 1);
            $this->rebuildStaticRows();
        }
    }

    public function addLaporanNaRow(): void
    {
        $empty = ['uraian' => '', 'masuk' => '', 'keluar' => 0, 'model' => '', 'seri_dari' => '', 'seri_sampai' => '', 'penerimaan' => '', 'pengeluaran' => ''];
        $this->manualData[] = $empty;
    }

    public function removeLaporanNaRow(int $index): void
    {
        if (isset($this->manualData[$index])) {
            array_splice($this->manualData, $index, 1);
        }
    }

    public function addSisaBulanLalu(): void
    {
        $this->manualData[] = ['is_sisa_bulan_lalu' => true, 'uraian' => 'Sisa bulan lalu', 'masuk' => '', 'keluar' => '', 'sisa' => '', 'model' => 'NA', 'seri_dari' => '', 'seri_sampai' => '', 'penerimaan' => '', 'pengeluaran' => ''];
    }

    private function initLaporanNaData(): void
    {
        $sisaBLEntries = [];
        $existingRows = [];
        if ($this->manualData) {
            foreach ($this->manualData as $row) {
                if (! empty($row['is_sisa_bulan_lalu'])) {
                    $sisaBLEntries[] = $row;
                } else {
                    $existingRows[] = $row;
                }
            }
        }
        if (empty($sisaBLEntries)) {
            $sisaBLEntries[] = ['is_sisa_bulan_lalu' => true, 'uraian' => 'Sisa bulan lalu', 'masuk' => '', 'keluar' => '', 'sisa' => '', 'model' => 'NA', 'seri_dari' => '', 'seri_sampai' => '', 'penerimaan' => '', 'pengeluaran' => ''];
        }
        $this->manualData = array_values(array_merge($sisaBLEntries, $existingRows));
    }

    private function initL1ManualData(): void
    {
        $daftarDesa = auth()->user()->daftar_desa ?? [];
        $manualCols = ['Poligami II', 'Poligami III', 'Poligami IV', 'Miskin', 'Bencana Alam', 'Pencatatan LN', 'Talak I', 'Talak II', 'Talak III', 'Cerai', 'Rujuk I', 'Rujuk II', 'Rujuk III'];

        $existing = [];
        if ($this->manualData) {
            foreach ($this->manualData as $row) {
                $desa = $row['desa'] ?? '';
                if ($desa !== '') {
                    $existing[$desa] = $row;
                }
            }
        }

        $rows = [];
        foreach ($daftarDesa as $desa) {
            if (isset($existing[$desa])) {
                $rows[] = $existing[$desa];
            } else {
                $row = ['desa' => $desa];
                foreach ($manualCols as $col) {
                    $row[$col] = '';
                }
                $rows[] = $row;
            }
        }

        $this->manualData = $rows;
    }

    private function initNtcrManualData(): void
    {
        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $manualCols = ['Talak', 'Cerai', 'Rujuk', 'Gagal', 'Tunda', 'Miskin', 'Bencana Alam'];

        $existing = [];
        if ($this->manualData) {
            foreach ($this->manualData as $row) {
                $bulan = $row['bulan'] ?? '';
                if ($bulan !== '') {
                    $existing[$bulan] = $row;
                }
            }
        }

        $rows = [];
        foreach ($monthNames as $month) {
            if (isset($existing[$month])) {
                $rows[] = $existing[$month];
            } else {
                $row = ['bulan' => $month];
                foreach ($manualCols as $col) {
                    $row[$col] = 0;
                }
                $rows[] = $row;
            }
        }

        $this->manualData = $rows;
    }

    private function initNbManualData(): void
    {
        $existing = [];
        if ($this->manualData) {
            if (is_array($this->manualData)) {
                foreach ($this->manualData as $row) {
                    if (is_array($row)) {
                        $existing[] = $row;
                    }
                }
            }
        }

        if (empty($existing)) {
            $existing[] = ['is_sisa_bulan_lalu' => true, 'tanggal' => '01', 'uraian' => 'Sisa bulan lalu', 'masuk' => '', 'penerimaan' => ''];
        }

        $this->manualData = $existing;
    }

    public function addNbRow(): void
    {
        $this->manualData[] = ['tanggal' => '', 'uraian' => '', 'masuk' => '', 'keluar' => '', 'penerimaan' => ''];
    }

    public function addNbRusakRow(): void
    {
        $this->manualData[] = ['tanggal' => '', 'uraian' => 'Rusak', 'masuk' => '', 'keluar' => '', 'penerimaan' => '', 'is_rusak' => true];
    }

    public function addSisaBulanLaluNb(): void
    {
        $this->manualData[] = ['is_sisa_bulan_lalu' => true, 'tanggal' => '01', 'uraian' => 'Sisa bulan lalu', 'masuk' => '', 'penerimaan' => ''];
    }

    public function removeNbRow(int $index): void
    {
        if (isset($this->manualData[$index])) {
            array_splice($this->manualData, $index, 1);
        }
    }

    public function addMonevMember(): void
    {
        $this->monevMembers[] = ['nama' => '', 'id' => bin2hex(random_bytes(8))];
    }

    public function removeMonevMember(int $index): void
    {
        if (isset($this->monevMembers[$index])) {
            array_splice($this->monevMembers, $index, 1);
        }
    }

    private function buildSeriRange(string $awal, string $akhir): string
    {
        if ($awal === '' && $akhir === '') {
            return '';
        }
        if ($awal === $akhir || $akhir === '') {
            return 'JT '.$awal;
        }
        if ($awal === '') {
            return 'JT '.$akhir;
        }

        return 'JT '.$awal.' - '.$akhir;
    }

    private function buildManualDataForSave(): array
    {
        if (($this->tableLayout['type'] ?? '') === 'laporan_na') {
            $sisaBLEntries = [];
            $rows = [];
            foreach ($this->manualData as $row) {
                if (! empty($row['is_sisa_bulan_lalu'])) {
                    $sisaBLEntries[] = $row;
                } else {
                    $rows[] = $row;
                }
            }

            return [
                'sisa_bulan_lalu' => count($sisaBLEntries) === 1 ? $sisaBLEntries[0] : $sisaBLEntries,
                'rows' => $rows,
            ];
        }

        if (($this->tableLayout['type'] ?? '') === 'laporan_l1') {
            $data = [];
            foreach ($this->manualData as $row) {
                $desa = $row['desa'] ?? '';
                if ($desa === '') {
                    continue;
                }
                $data[$desa] = [
                    'Poligami II' => $row['Poligami II'] ?? null,
                    'Poligami III' => $row['Poligami III'] ?? null,
                    'Poligami IV' => $row['Poligami IV'] ?? null,
                    'Miskin' => $row['Miskin'] ?? null,
                    'Bencana Alam' => $row['Bencana Alam'] ?? null,
                    'Pencatatan LN' => $row['Pencatatan LN'] ?? null,
                    'Talak I' => $row['Talak I'] ?? null,
                    'Talak II' => $row['Talak II'] ?? null,
                    'Talak III' => $row['Talak III'] ?? null,
                    'Cerai' => $row['Cerai'] ?? null,
                    'Rujuk I' => $row['Rujuk I'] ?? null,
                    'Rujuk II' => $row['Rujuk II'] ?? null,
                    'Rujuk III' => $row['Rujuk III'] ?? null,
                ];
            }

            return $data;
        }

        if (($this->tableLayout['type'] ?? '') === 'rekap_ntcr') {
            $data = [];
            foreach ($this->manualData as $row) {
                $bulan = $row['bulan'] ?? '';
                if ($bulan === '') {
                    continue;
                }
                $data[$bulan] = [
                    'Talak' => $row['Talak'] ?? 0,
                    'Cerai' => $row['Cerai'] ?? 0,
                    'Rujuk' => $row['Rujuk'] ?? 0,
                    'Gagal' => $row['Gagal'] ?? 0,
                    'Tunda' => $row['Tunda'] ?? 0,
                    'Miskin' => $row['Miskin'] ?? 0,
                    'Bencana Alam' => $row['Bencana Alam'] ?? 0,
                ];
            }

            return $data;
        }

        if (($this->tableLayout['type'] ?? '') === 'laporan_nb' || ($this->tableLayout['type'] ?? '') === 'laporan_n') {
            return $this->manualData ?? [];
        }

        $data = array_map(function ($row) {
            $row['masuk_seri'] = $this->buildSeriRange($row['masuk_seri_awal'] ?? '', $row['masuk_seri_akhir'] ?? '');
            $row['keluar_seri'] = $this->buildSeriRange($row['keluar_seri_awal'] ?? '', $row['keluar_seri_akhir'] ?? '');
            $row['sisa_seri'] = $this->buildSeriRange($row['sisa_seri_awal'] ?? '', $row['sisa_seri_akhir'] ?? '');

            return $row;
        }, $this->manualData);

        return [
            'rows' => $data,
            'na_versions' => $this->naVersions,
            'static_rows' => $this->tableLayout['static_rows'] ?? [],
        ];
    }

    public function updatedImportId(): void
    {
        if ($this->isMergeMode) {
            return;
        }

        $this->reset(['columns', 'fields', 'preview', 'filterColumn', 'filterValue', 'sortColumn', 'search']);
        $this->tableLayout = null;

        $import = $this->selectedImport();

        if (! $import) {
            $this->selectedImportIds = [];

            return;
        }

        $group = collect($this->imports)->firstWhere('id', $import->id);
        $this->selectedImportIds = $group['import_ids'] ?? [$import->id];

        $this->columns = $import->availableColumns();
        $this->fields = $this->columns;

        if ($this->title === '') {
            $this->title = 'Laporan '.($import->table_name ?? pathinfo($import->file_name, PATHINFO_FILENAME));
        }

        if ($this->selectedTemplateId) {
            $this->applySelectedTemplate();
        } else {
            $this->loadPreview();
        }
    }

    public function loadPreview(): void
    {
        if ($this->isMergeMode) {
            $this->loadMergePreview();

            return;
        }

        if ($this->selectedImportIds === [] || $this->fields === []) {
            $this->preview = null;

            return;
        }

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        $effectiveFields = $this->fields;
        if ($hasFilter && ! in_array('Tanggal Nikah', $effectiveFields, true) && ($this->tableLayout['type'] ?? '') !== 'laporan_na') {
            $import = Import::whereIn('id', $this->selectedImportIds)->first();
            if ($import && in_array('Tanggal Nikah', $import->availableColumns(), true)) {
                $effectiveFields[] = 'Tanggal Nikah';
            }
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_na' && ! in_array('Tanggal Cetak', $effectiveFields, true)) {
            $effectiveFields[] = 'Tanggal Cetak';
        }

        $dataset = app(MergeService::class)->buildConcatDataset(
            $this->selectedImportIds,
            $effectiveFields,
            $this->search ?: null,
            $this->filterColumn ?: null,
            $this->filterValue ?: null,
            $this->sortColumn ?: null,
            $this->sortDirection,
        );

        $this->preview = [
            'headings' => $dataset['headings'],
            'rows' => $dataset['rows'],
            'total' => $dataset['total'],
        ];

        $this->applyPreviewFilter();

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        if (! $hasFilter && count($this->preview['rows']) > 10) {
            $this->preview['rows'] = array_slice($this->preview['rows'], 0, 10);
        }

        if (($this->tableLayout['type'] ?? '') === 'formulir') {
            $this->initManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_na') {
            $this->initLaporanNaData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_l1') {
            $this->initL1ManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'rekap_ntcr') {
            $this->initNtcrManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_nb' || ($this->tableLayout['type'] ?? '') === 'laporan_n') {
            $this->initNbManualData();
        }
    }

    private function loadMergePreview(): void
    {
        if (count($this->mergeImportIds) < 2 || $this->fields === []) {
            $this->preview = null;

            return;
        }

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        $effectiveFields = $this->fields;
        if ($hasFilter && ! in_array('Tanggal Nikah', $effectiveFields, true) && ($this->tableLayout['type'] ?? '') !== 'laporan_na') {
            $allCols = app(MergeService::class)->getAllColumns($this->mergeImportIds);
            if (in_array('Tanggal Nikah', $allCols, true)) {
                $effectiveFields[] = 'Tanggal Nikah';
            }
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_na' && ! in_array('Tanggal Cetak', $effectiveFields, true)) {
            $effectiveFields[] = 'Tanggal Cetak';
        }

        if (in_array(($this->tableLayout['type'] ?? ''), ['formulir', 'laporan_na'], true)) {
            $dataset = app(MergeService::class)->buildConcatDataset(
                $this->mergeImportIds,
                $effectiveFields,
                $this->search ?: null,
                $this->filterColumn ?: null,
                $this->filterValue ?: null,
                $this->sortColumn ?: null,
                $this->sortDirection,
            );
        } else {
            $dataset = app(MergeService::class)->buildMergedDataset(
                $this->mergeImportIds,
                $effectiveFields,
                $this->search ?: null,
                $this->filterColumn ?: null,
                $this->filterValue ?: null,
                $this->sortColumn ?: null,
                $this->sortDirection,
            );
        }

        $this->preview = [
            'headings' => $dataset['headings'],
            'rows' => $dataset['rows'],
            'total' => $dataset['total'],
        ];

        $this->applyPreviewFilter();

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        if (! $hasFilter && count($this->preview['rows']) > 10) {
            $this->preview['rows'] = array_slice($this->preview['rows'], 0, 10);
        }

        if (($this->tableLayout['type'] ?? '') === 'formulir') {
            $this->initManualData();
        }
        if (($this->tableLayout['type'] ?? '') === 'laporan_na') {
            $this->initLaporanNaData();
        }
    }

    private function applyPreviewFilter(): void
    {
        if (! $this->preview || ($this->filterMonth === '' && $this->filterYear === '')) {
            return;
        }

        $tanggalNikahField = null;
        if ($this->tableLayout) {
            foreach ($this->tableLayout['columns'] ?? [] as $col) {
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

        if ($tanggalNikahField === null && in_array('Tanggal Nikah', $this->preview['headings'] ?? [], true)) {
            $tanggalNikahField = 'Tanggal Nikah';
        }

        if ($tanggalNikahField === null && $this->tableLayout) {
            $dateFilterField = $this->tableLayout['aggregation']['date_filter_field'] ?? null;
            if ($dateFilterField && in_array($dateFilterField, $this->preview['headings'] ?? [], true)) {
                $tanggalNikahField = $dateFilterField;
            }
        }

        if (($this->tableLayout['type'] ?? '') === 'laporan_na') {
            if (in_array('Tanggal Cetak', $this->preview['headings'] ?? [], true)) {
                $tanggalNikahField = 'Tanggal Cetak';
            }
        }

        if (! $tanggalNikahField || ! in_array($tanggalNikahField, $this->preview['headings'] ?? [], true)) {
            return;
        }

        $filterMonth = $this->filterMonth;
        $filterYear = $this->filterYear;
        $filterDateFrom = $this->filterDateFrom;
        $filterDateTo = $this->filterDateTo;

        $this->preview['rows'] = array_values(array_filter(
            $this->preview['rows'],
            function ($row) use ($tanggalNikahField, $filterMonth, $filterYear, $filterDateFrom, $filterDateTo) {
                $dateVal = $row[$tanggalNikahField] ?? null;
                if ($dateVal === null || $dateVal === '') {
                    return false;
                }
                try {
                    $date = \Carbon\Carbon::parse($dateVal);
                } catch (\Exception $e) {
                    return false;
                }
                if ($filterMonth !== '' && (int) $date->month !== (int) $filterMonth) {
                    return false;
                }
                if ($filterYear !== '' && (int) $date->year !== (int) $filterYear) {
                    return false;
                }
                if ($filterDateFrom !== '') {
                    try {
                        $from = \Carbon\Carbon::parse($filterDateFrom)->startOfDay();
                        if ($date->lt($from)) {
                            return false;
                        }
                    } catch (\Exception $e) {
                    }
                }
                if ($filterDateTo !== '') {
                    try {
                        $to = \Carbon\Carbon::parse($filterDateTo)->endOfDay();
                        if ($date->gt($to)) {
                            return false;
                        }
                    } catch (\Exception $e) {
                    }
                }

                return true;
            }
        ));

        $this->preview['total'] = count($this->preview['rows']);
    }

    public function generate(): void
    {
        if ($this->isMergeMode) {
            $this->generateMergeReport();

            return;
        }

        $this->validate([
            'importId' => 'required|integer',
            'title' => 'required|string|max:255',
            'format' => 'required|in:pdf,word,excel,print',
            'orientation' => 'required|in:portrait,landscape',
            'sortDirection' => 'required|in:asc,desc',
        ]);

        $import = $this->selectedImport();

        if (! $import) {
            $this->addError('importId', 'Sumber data tidak valid.');

            return;
        }

        $fields = array_values(array_intersect($this->fields, $import->availableColumns()));

        if ($fields === [] && $this->format !== 'print') {
            $this->addError('fields', 'Pilih minimal satu kolom.');

            return;
        }

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        if ($hasFilter && ! in_array('Tanggal Nikah', $fields, true)) {
            if (in_array('Tanggal Nikah', $import->availableColumns(), true)) {
                $fields[] = 'Tanggal Nikah';
            }
        }

        $report = Report::create([
            'user_id' => auth()->id(),
            'import_id' => $import->id,
            'title' => $this->title,
            'output_format' => $this->format,
            'config_json' => array_merge($this->getUserConfig(), [
                'fields' => $fields === [] ? $import->availableColumns() : $fields,
                'search' => $this->search ?: null,
                'filter_column' => $this->filterColumn ?: null,
                'filter_value' => $this->filterValue ?: null,
                'sort_column' => $this->sortColumn ?: null,
                'sort_direction' => $this->sortDirection,
                'orientation' => $this->orientation,
                'filter_month' => $this->filterMonth ?: null,
                'filter_year' => $this->filterYear ?: null,
                'filter_date_from' => $this->filterDateFrom ?: null,
                'filter_date_to' => $this->filterDateTo ?: null,
                'merged_import_ids' => $this->selectedImportIds,
                'manual_data' => in_array($this->tableLayout['type'] ?? '', ['formulir', 'laporan_na', 'laporan_l1', 'rekap_ntcr', 'laporan_nb', 'laporan_n']) ? $this->buildManualDataForSave() : null,
                'closure_reason' => in_array($this->tableLayout['type'] ?? '', ['laporan_na', 'laporan_nb', 'laporan_n']) ? $this->closureReason : null,
                'page_start' => in_array($this->tableLayout['type'] ?? '', ['laporan_na', 'laporan_nb', 'laporan_n']) ? ($this->pageStart !== '' ? $this->pageStart : '1') : null,
                'monev_members' => $this->closureReason === 'monev' ? array_values(array_filter(array_map(fn ($m) => trim((string) ($m['nama'] ?? '')), $this->monevMembers))) : null,
                'monev_st_number' => $this->closureReason === 'monev' ? $this->monevStNumber : null,
                'ganti_kepala_sk' => $this->closureReason === 'ganti_kepala' ? $this->gantiKepalaSk : null,
            ], $this->tableLayout ? ['table_layout' => $this->tableLayout] : []),
            'status' => $this->format === 'print' ? 'generated' : 'pending',
            'generated_at' => $this->format === 'print' ? now() : null,
        ]);

        if ($this->format === 'print') {
            $this->redirectRoute('reports.print', $report);

            return;
        }

        GenerateReport::dispatch($report);
        $this->redirectRoute('reports.index');
    }

    private function generateMergeReport(): void
    {
        $this->validate([
            'mergeImportIds' => 'required|array|min:2',
            'title' => 'required|string|max:255',
            'format' => 'required|in:pdf,word,excel,print',
            'orientation' => 'required|in:portrait,landscape',
            'sortDirection' => 'required|in:asc,desc',
        ]);

        if ($this->fields === [] && $this->format !== 'print') {
            $this->addError('fields', 'Pilih minimal satu kolom.');

            return;
        }

        $firstImport = Import::where('id', $this->mergeImportIds[0])->first();

        if (! $firstImport) {
            $this->addError('importId', 'Sumber data tidak valid.');

            return;
        }

        $effectiveFields = $this->fields;
        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        if ($hasFilter && ! in_array('Tanggal Nikah', $effectiveFields, true)) {
            $allCols = app(MergeService::class)->getAllColumns($this->mergeImportIds);
            if (in_array('Tanggal Nikah', $allCols, true)) {
                $effectiveFields[] = 'Tanggal Nikah';
            }
        }

        $report = Report::create([
            'user_id' => auth()->id(),
            'import_id' => $firstImport->id,
            'title' => $this->title,
            'output_format' => $this->format,
            'config_json' => array_merge($this->getUserConfig(), [
                'fields' => $effectiveFields,
                'search' => $this->search ?: null,
                'filter_column' => $this->filterColumn ?: null,
                'filter_value' => $this->filterValue ?: null,
                'sort_column' => $this->sortColumn ?: null,
                'sort_direction' => $this->sortDirection,
                'orientation' => $this->orientation,
                'is_merged' => true,
                'merged_import_ids' => $this->mergeImportIds,
                'filter_month' => $this->filterMonth ?: null,
                'filter_year' => $this->filterYear ?: null,
                'filter_date_from' => $this->filterDateFrom ?: null,
                'filter_date_to' => $this->filterDateTo ?: null,
                'manual_data' => in_array($this->tableLayout['type'] ?? '', ['formulir', 'laporan_na', 'laporan_l1', 'rekap_ntcr', 'laporan_nb', 'laporan_n']) ? $this->buildManualDataForSave() : null,
                'closure_reason' => in_array($this->tableLayout['type'] ?? '', ['laporan_na', 'laporan_nb', 'laporan_n']) ? $this->closureReason : null,
                'page_start' => in_array($this->tableLayout['type'] ?? '', ['laporan_na', 'laporan_nb', 'laporan_n']) ? ($this->pageStart !== '' ? $this->pageStart : '1') : null,
                'monev_members' => $this->closureReason === 'monev' ? array_values(array_filter(array_map(fn ($m) => trim((string) ($m['nama'] ?? '')), $this->monevMembers))) : null,
                'monev_st_number' => $this->closureReason === 'monev' ? $this->monevStNumber : null,
                'ganti_kepala_sk' => $this->closureReason === 'ganti_kepala' ? $this->gantiKepalaSk : null,
            ], $this->tableLayout ? ['table_layout' => $this->tableLayout] : []),
            'status' => $this->format === 'print' ? 'generated' : 'pending',
            'generated_at' => $this->format === 'print' ? now() : null,
        ]);

        if ($this->format === 'print') {
            $this->redirectRoute('reports.print', $report);

            return;
        }

        GenerateReport::dispatch($report);
        $this->redirectRoute('reports.index');
    }

    private function selectedImport(): ?Import
    {
        if (! $this->importId) {
            return null;
        }

        return Import::where('user_id', auth()->id())->find($this->importId);
    }

    private function getUserConfig(): array
    {
        $user = auth()->user();

        return [
            'kecamatan' => $user->kecamatan ?? null,
            'nama_kepala_kua' => $user->nama_kepala_kua ?? null,
            'nip_kepala' => $user->nip_kepala ?? null,
            'nama_kementerian' => $user->nama_kementerian ?? null,
            'nama_kantor_kota' => $user->nama_kantor_kota ?? null,
            'nama_kantor' => $user->nama_kantor ?? null,
            'alamat_kantor' => $user->alamat_kantor ?? null,
            'telepon_kantor' => $user->telepon_kantor ?? null,
            'email_kantor' => $user->email_kantor ?? null,
            'logo_kantor' => $user->logo_kantor ?? null,
            'daftar_desa' => $user->daftar_desa ?? null,
            'font_size_kop_kementerian' => $user->font_size_kop_kementerian ?? null,
            'font_size_kop_kantor_kota' => $user->font_size_kop_kantor_kota ?? null,
            'font_size_kop_kantor' => $user->font_size_kop_kantor ?? null,
            'font_size_kop_alamat' => $user->font_size_kop_alamat ?? null,
            'font_size_kop_kontak' => $user->font_size_kop_kontak ?? null,
            'nama_kepala_kua_lama' => $user->nama_kepala_kua_lama ?? null,
            'nip_kepala_lama' => $user->nip_kepala_lama ?? null,
            'nama_kepala_kemenag' => $user->nama_kepala_kemenag ?? null,
            'nip_kepala_kemenag' => $user->nip_kepala_kemenag ?? null,
            'kota_kabupaten' => $user->kota_kabupaten ?? null,
        ];
    }
};
?>

<div class="space-y-6">
    {{-- 1. Template & sumber data --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">1. Template &amp; Sumber Data</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Pilih template laporan terlebih dahulu, lalu pilih file import yang sesuai.</p>

        @if (! empty($this->globalTemplates))
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Laporan</label>
                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <select wire:model.live="selectedTemplateId" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full sm:w-auto">
                        <option value="">-- Pilih Template --</option>
                        @foreach ($this->globalTemplates as $tpl)
                            <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }} ({{ strtoupper($tpl['output_format']) }})</option>
                        @endforeach
                    </select>
                    @if ($this->hasDefaultTemplate)
                        <button type="button" wire:click="loadDefaultTemplate" class="text-sm text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">Muat template default</button>
                    @endif
                </div>

                @if ($this->importGuide)
                    <div class="mt-3 rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/50">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">{{ $this->importGuide['template_name'] }}</p>
                        @if ($this->importGuide['description'])
                            <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">{{ $this->importGuide['description'] }}</p>
                        @endif
                        <p class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            File yang disarankan: <strong>{{ $this->importGuide['label'] }}</strong>
                        </p>
                        <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                            Contoh nama file: <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900">{{ $this->importGuide['example'] }}</code>
                        </p>
                        <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">{{ $this->importGuide['note'] }}</p>
                    </div>
                @else
                    <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">Pilih template di atas untuk melihat panduan file import.</p>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada template laporan aktif. Hubungi admin untuk mengaktifkan template.</p>
        @endif

        <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Sumber Data</h4>
            @if (! $this->selectedTemplateId)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih template terlebih dahulu. Setelah itu daftar file import akan muncul di sini.</p>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Pilih file import yang sesuai dengan panduan di atas.</p>

                {{-- Mode Toggle --}}
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1 w-fit mb-4">
                    <button type="button" wire:click="$set('isMergeMode', false)"
                        class="px-4 py-2 text-sm font-medium rounded-md transition {{ ! $this->isMergeMode ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                        Single Import
                    </button>
                    <button type="button" wire:click="$set('isMergeMode', true)"
                        class="px-4 py-2 text-sm font-medium rounded-md transition {{ $this->isMergeMode ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                        Gabung Data
                    </button>
                </div>

                @if ($this->isMergeMode)
                    {{-- Merge Mode --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pilih file import (minimal 2)</label>
                        @if (empty($this->imports))
                            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data import yang berhasil. <a href="{{ route('imports.create') }}" class="text-blue-600 dark:text-blue-400 font-medium">Upload Excel dulu</a>.</p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($this->imports as $import)
                                    @php $allChecked = count(array_intersect($import['import_ids'], $this->mergeImportIds)) === count($import['import_ids']); @endphp
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 cursor-pointer hover:border-blue-400 dark:hover:border-blue-500">
                                        <input type="checkbox" wire:click="toggleImportGroup({{ json_encode($import['import_ids']) }})" @checked($allChecked) class="rounded text-blue-600 dark:text-blue-400" />
                                        <span class="flex-1">{{ $import['table_name'] }} ({{ number_format($import['total_rows']) }} baris)</span>
                                        @if ($this->isImportRecommended($import['table_name']))
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">Disarankan</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        @if (count($this->mergeImportIds) >= 2)
                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-md">
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                    Bergabung otomatis berdasarkan kolom: <strong>{{ implode(' + ', $this->autoJoinColumns) }}</strong>
                                </p>
                                @if (empty($this->autoJoinColumns))
                                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Tidak ada kolom yang sama antara file yang dipilih.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    {{-- Single Mode --}}
                    @if (empty($this->imports))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data import yang berhasil. <a href="{{ route('imports.create') }}" class="text-blue-600 dark:text-blue-400 font-medium">Upload Excel dulu</a>.</p>
                    @else
                        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                            <select wire:model.live="importId" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full sm:w-auto">
                                <option value="">-- Pilih file import --</option>
                                @foreach ($this->imports as $import)
                                    <option value="{{ $import['id'] }}">{{ $import['table_name'] }} ({{ number_format($import['total_rows']) }} baris){{ $this->isImportRecommended($import['table_name']) ? ' - Disarankan' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif

                @if ($this->importGuide && ! empty($this->imports) && ! collect($this->imports)->contains(fn ($import) => $this->isImportRecommended($import['table_name'])))
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                        File {{ $this->importGuide['label'] }} belum tersedia. <a href="{{ route('imports.create') }}" class="font-medium underline">Upload Excel dulu</a>.
                    </p>
                @endif
                @error('importId') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            @endif
        </div>
    </div>

    @if ($this->selectedTemplateId && (($this->isMergeMode && count($this->mergeImportIds) >= 2 && ! empty($this->autoJoinColumns)) || (! $this->isMergeMode && $this->importId && ! empty($this->columns))))
        {{-- 2. Filter & Urutan --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">2. Filter &amp; Urutan</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari..." class="border-gray-300 dark:border-gray-600 rounded-md text-sm" />
                <div class="flex gap-2">
                    <select wire:model.live="filterColumn" class="border-gray-300 dark:border-gray-600 rounded-md text-sm flex-1">
                        <option value="">Tanpa filter kolom</option>
                        @foreach ($this->columns as $column)
                            <option value="{{ $column }}">{{ $column }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model.live.debounce.300ms="filterValue" placeholder="Nilai" class="border-gray-300 dark:border-gray-600 rounded-md text-sm flex-1" />
                </div>
                <div class="flex gap-2">
                    <select wire:model.live="sortColumn" class="border-gray-300 dark:border-gray-600 rounded-md text-sm flex-1">
                        <option value="">Tanpa urutan khusus</option>
                        @foreach ($this->columns as $column)
                            <option value="{{ $column }}">{{ $column }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="sortDirection" class="border-gray-300 dark:border-gray-600 rounded-md text-sm">
                        <option value="asc">A → Z</option>
                        <option value="desc">Z → A</option>
                    </select>
                </div>
                @if ($this->format === 'print')
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter Bulan/Tahun</label>
                        <div class="grid grid-cols-2 gap-3">
                            <select wire:model.live="filterMonth" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full">
                                <option value="">Semua Bulan</option>
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                                @endfor
                            </select>
                            <select wire:model.live="filterYear" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full">
                                <option value="">Semua Tahun</option>
                                @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter Tanggal (Rentang)</label>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="date" wire:model.live="filterDateFrom" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" />
                            <input type="date" wire:model.live="filterDateTo" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Kosongkan untuk memakai seluruh bulan. Isi untuk membatasi rentang tanggal.</p>
                    </div>
                @endif
                <button type="button" wire:click="loadPreview" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-semibold rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">Muat Pratinjau</button>
            </div>
        </div>

        {{-- Pratinjau --}}
        @if ($this->preview)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Pratinjau ({{ $this->preview['total'] }} baris pertama)</h3>
                @if ($this->tableLayout)
                    <p class="text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-md px-3 py-2 mb-3">Format final akan menggunakan layout struktural (kop surat, tabel grup, tanda tangan, dll).</p>
                @endif
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                @foreach ($this->preview['headings'] as $heading)
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->preview['rows'] as $row)
                                <tr>
                                    @foreach ($this->preview['headings'] as $heading)
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row[$heading] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 3. Isi Formulir (L3 only) --}}
        @if ($this->preview && ($this->tableLayout['type'] ?? '') === 'formulir')
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">3. Isi Formulir</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Isi kolom manual. Keluar Jumlah dan Sisa Jumlah dihitung otomatis.</p>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">No</th>
                                <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Nama Formulir</th>
                                <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Masuk</th>
                                <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Keluar</th>
                                <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Sisa</th>
                                <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Keterangan</th>
                            </tr>
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Jumlah</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Seri (dari - sampai)</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Jumlah</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Seri (dari - sampai)</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Jumlah</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Seri (dari - sampai)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->manualData as $i => $row)
                                @php $isNaVersion = (($row['formulir'] ?? '') !== 'Model N' && ($row['formulir'] ?? '') !== 'Model DN' && ($row['formulir'] ?? '') !== 'Model NB'); @endphp
                                <tr>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">{{ $i + 1 }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isNaVersion)
                                            <div class="flex items-center gap-1">
                                                <input type="text" wire:model.live="manualData.{{ $i }}.formulir" class="flex-1 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5 font-medium" />
                                                <button wire:click="removeNaRow({{ $i }})" type="button" class="text-red-400 hover:text-red-600 shrink-0" title="Hapus baris">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-xs font-medium px-1">{{ $row['formulir'] }}</span>
                                        @endif
                                    </td>
                                    {{-- Masuk --}}
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.masuk_jumlah" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="0" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <div class="flex gap-1">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.masuk_seri_awal" class="w-1/2 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="dari" />
                                            <input type="text" wire:model.live="manualData.{{ $i }}.masuk_seri_akhir" class="w-1/2 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="sampai" />
                                        </div>
                                    </td>
                                    {{-- Keluar --}}
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isNaVersion)
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $this->manualData[$i]['keluar_jumlah'] ?? '' }}</span>
                                        @else
                                            <input type="text" wire:model.live="manualData.{{ $i }}.keluar_jumlah" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="0" />
                                        @endif
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isNaVersion)
                                            <div class="flex gap-1">
                                                <span class="w-1/2 text-xs text-gray-700 dark:text-gray-300 truncate">{{ $this->manualData[$i]['keluar_seri_awal'] ?? '' }}</span>
                                                <span class="w-1/2 text-xs text-gray-700 dark:text-gray-300 truncate">{{ $this->manualData[$i]['keluar_seri_akhir'] ?? '' }}</span>
                                            </div>
                                        @else
                                            <div class="flex gap-1">
                                                <input type="text" wire:model.live="manualData.{{ $i }}.keluar_seri_awal" class="w-1/2 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="dari" />
                                                <input type="text" wire:model.live="manualData.{{ $i }}.keluar_seri_akhir" class="w-1/2 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="sampai" />
                                            </div>
                                        @endif
                                    </td>
                                    {{-- Sisa --}}
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isNaVersion)
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $this->manualData[$i]['sisa_jumlah'] ?? '' }}</span>
                                        @else
                                            <input type="text" wire:model.live="manualData.{{ $i }}.sisa_jumlah" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="0" />
                                        @endif
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isNaVersion)
                                            <div class="flex gap-1">
                                                <span class="w-1/2 text-xs text-gray-700 dark:text-gray-300 truncate">{{ $this->manualData[$i]['sisa_seri_awal'] ?? '' }}</span>
                                                <span class="w-1/2 text-xs text-gray-700 dark:text-gray-300 truncate">{{ $this->manualData[$i]['sisa_seri_akhir'] ?? '' }}</span>
                                            </div>
                                        @else
                                            <div class="flex gap-1">
                                                <input type="text" wire:model.live="manualData.{{ $i }}.sisa_seri_awal" class="w-1/2 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="dari" />
                                                <input type="text" wire:model.live="manualData.{{ $i }}.sisa_seri_akhir" class="w-1/2 border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="sampai" />
                                            </div>
                                        @endif
                                    </td>
                                    {{-- Keterangan --}}
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.keterangan" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Keterangan" />
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="9" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">
                                    <button wire:click="addNaRow" type="button" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Tambah Baris
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 3b. Laporan NA Form --}}
        @if ($this->preview && ($this->tableLayout['type'] ?? '') === 'laporan_na')
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">3. Isi Formulir</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Baris pertama adalah Sisa Bulan Lalu. Baris selanjutnya dari data import.</p>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">No</th>
                                <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Uraian</th>
                                <th colspan="3" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Banyaknya</th>
                                <th colspan="3" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">NA, RA, atau DN</th>
                                <th rowspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Satuan</th>
                                <th colspan="2" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Nomor Bukti</th>
                            </tr>
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Masuk</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Keluar</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Sisa</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Model</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Dari</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Sampai</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Penerimaan</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs">Pengeluaran</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->manualData as $i => $row)
                                @php $isSisaBL = !empty($row['is_sisa_bulan_lalu']); @endphp
                                <tr>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">{{ $i + 1 }}</td>
                                    @if ($isSisaBL)
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.uraian" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Sisa bulan lalu" />
                                        </td>
                                    @else
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.uraian" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Uraian" />
                                        </td>
                                    @endif
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.masuk" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="0" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isSisaBL)
                                            <input type="text" wire:model.live="manualData.{{ $i }}.keluar" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="0" />
                                        @else
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $this->manualData[$i]['keluar'] ?? 0 }}</span>
                                        @endif
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5 text-center">
                                        @php
                                            $masukVal = (int) ($this->manualData[$i]['masuk'] ?? 0);
                                            $keluarVal = (int) ($this->manualData[$i]['keluar'] ?? 0);
                                            $sisaVal = $masukVal - $keluarVal;
                                        @endphp
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ $sisaVal < 0 ? 0 : $sisaVal }}</span>
                                    </td>
                                    @if ($isSisaBL)
                                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs font-medium text-gray-700 dark:text-gray-300">NA</td>
                                    @else
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.model" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="NA XXXXXX" />
                                        </td>
                                    @endif
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.seri_dari" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Dari" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.seri_sampai" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Sampai" />
                                    </td>
                                    @if ($isSisaBL)
                                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs font-medium text-gray-700 dark:text-gray-300">Buku</td>
                                    @else
                                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">
                                            <span class="text-xs">Buku</span>
                                        </td>
                                    @endif
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.penerimaan" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Penerimaan" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.pengeluaran" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Pengeluaran" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5 text-center">
                                        <button wire:click="removeLaporanNaRow({{ $i }})" type="button" class="text-red-400 hover:text-red-600 shrink-0" title="Hapus baris">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="11" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">
                                    <button wire:click="addLaporanNaRow" type="button" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Tambah Baris
                                    </button>
                                    <button wire:click="addSisaBulanLalu" type="button" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200 ml-3">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Tambah Sisa Bulan Lalu
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor Halaman Awal</label>
                        <input type="text" wire:model="pageStart" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full sm:w-64" placeholder="1" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nomor halaman pertama. Jika laporan lebih dari 1 halaman, halaman berikutnya otomatis +1.</p>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alasan Tutup Buku</label>
                        <select wire:model.live="closureReason" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full sm:w-64">
                            <option value="akhir_bulan">Akhir Bulan</option>
                            <option value="monev">Ada Monev</option>
                            <option value="ganti_kepala">Ganti Kepala</option>
                        </select>
                    </div>
                    @if ($closureReason === 'monev')
                        <div class="mt-4 border border-gray-200 dark:border-gray-700 rounded-md p-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Penandatangan Monev</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <p class="font-medium text-gray-700 dark:text-gray-300">Kepala KUA</p>
                                    <p>{{ auth()->user()->nama_kepala_kua ?? '-' }}</p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <p class="font-medium text-gray-700 dark:text-gray-300">Kepala Kantor Kemenag</p>
                                    <p>{{ auth()->user()->nama_kepala_kemenag ?? '-' }}</p>
                                </div>
                            </div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Anggota Monev</label>
                            @foreach ($monevMembers as $i => $member)
                                <div class="flex items-center gap-2 mb-2" wire:key="monev-{{ $member['id'] ?? $i }}">
                                    <input type="text" wire:model.live="monevMembers.{{ $i }}.nama" class="flex-1 border-gray-300 dark:border-gray-600 rounded text-xs px-2 py-1" placeholder="Nama anggota" />
                                    <button wire:click="removeMonevMember({{ $i }})" type="button" class="shrink-0 text-xs font-medium text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 rounded px-2 py-1">Hapus</button>
                                </div>
                            @endforeach
                            <button wire:click="addMonevMember" type="button" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 mt-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Tambah Anggota
                            </button>
                        </div>
                    @endif
                    @if ($closureReason === 'monev')
                        <div class="mt-3">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor ST</label>
                            <input type="text" wire:model="monevStNumber" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" placeholder="Nomor Surat Tugas" />
                        </div>
                    @endif
                    @if ($closureReason === 'ganti_kepala')
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SK Serah Terima Jabatan</label>
                            <input type="text" wire:model="gantiKepalaSk" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" placeholder="SK Kepala Kanwil ... nomor: ... Tanggal: ..." />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Contoh: SK Kepala Kanwil [Kota] nomor: [nomor SK] Tanggal: [tanggal SK]</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- 3. Isi Data Manual (L1 only) --}}
        @if ($this->preview && ($this->tableLayout['type'] ?? '') === 'laporan_l1')
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">3. Isi Data Manual</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Isi kolom yang tidak tersedia dari import data. Kosongkan jika tidak ada.</p>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Desa</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Poligami II</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Poligami III</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Poligami IV</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Miskin</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Bencana Alam</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Pencatatan LN</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Talak I</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Talak II</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Talak III</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Cerai</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Rujuk I</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Rujuk II</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Rujuk III</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->manualData as $i => $row)
                                <tr>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-0.5 text-xs font-medium">{{ $row['desa'] ?? '' }}</td>
                                    @foreach (['Poligami II', 'Poligami III', 'Poligami IV', 'Miskin', 'Bencana Alam', 'Pencatatan LN', 'Talak I', 'Talak II', 'Talak III', 'Cerai', 'Rujuk I', 'Rujuk II', 'Rujuk III'] as $col)
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="number" min="0" wire:model.live="manualData.{{ $i }}.{{ $col }}" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5 text-center" placeholder="0" />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 3b. Isi Data Manual (REKAP NTCR) --}}
        @if ($this->preview && ($this->tableLayout['type'] ?? '') === 'rekap_ntcr')
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">3. Isi Data Manual</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Isi data Talak, Cerai, Rujuk, Gagal, Tunda, Miskin, Bencana Alam per bulan.</p>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold">Bulan</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Talak</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Cerai</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Rujuk</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Gagal</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Tunda</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Miskin</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Bencana Alam</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->manualData as $i => $row)
                                <tr>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-0.5 text-xs font-medium">{{ $row['bulan'] ?? '' }}</td>
                                    @foreach (['Talak', 'Cerai', 'Rujuk', 'Gagal', 'Tunda', 'Miskin', 'Bencana Alam'] as $col)
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="number" min="0" wire:model.live="manualData.{{ $i }}.{{ $col }}" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5 text-center" placeholder="0" />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 3c. Isi Data Manual (Laporan NB) --}}
        @if ($this->preview && (($this->tableLayout['type'] ?? '') === 'laporan_nb' || ($this->tableLayout['type'] ?? '') === 'laporan_n'))
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">3. Isi Data Manual</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Isi data stok masuk dan penerimaan. Baris tanggal 1 untuk stok masuk. Kolom Keluar untuk baris manual (mis. Rusak) — mengurangi Sisa dan ikut terhitung di Jumlah.</p>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">#</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Tanggal</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Uraian</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Masuk</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Keluar</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs">Penerimaan</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center font-semibold text-xs"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->manualData as $i => $row)
                                @php $isSisaBL = !empty($row['is_sisa_bulan_lalu']); @endphp
                                <tr>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">{{ $i + 1 }}</td>
                                    @if ($isSisaBL)
                                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center text-xs font-medium text-gray-700 dark:text-gray-300">1</td>
                                    @else
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.tanggal" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="dd" />
                                        </td>
                                    @endif
                                    @if ($isSisaBL)
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.uraian" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Sisa bulan lalu" />
                                        </td>
                                    @else
                                        <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                            <input type="text" wire:model.live="manualData.{{ $i }}.uraian" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="Uraian" />
                                        </td>
                                    @endif
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="number" min="0" wire:model.live="manualData.{{ $i }}.masuk" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5 text-center" placeholder="0" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        @if ($isSisaBL)
                                            <span class="text-xs text-gray-700 dark:text-gray-300">0</span>
                                        @else
                                            <input type="number" min="0" wire:model.live="manualData.{{ $i }}.keluar" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5 text-center" placeholder="0" />
                                        @endif
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-1 py-0.5">
                                        <input type="text" wire:model.live="manualData.{{ $i }}.penerimaan" class="w-full border-gray-300 dark:border-gray-600 rounded text-xs px-1 py-0.5" placeholder="No. Penerimaan" />
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">
                                        <button wire:click="removeNbRow({{ $i }})" type="button" class="text-red-400 hover:text-red-600 shrink-0" title="Hapus baris">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="7" class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center">
                                    <button wire:click="addNbRow" type="button" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Tambah Baris
                                    </button>
                                    <button wire:click="addNbRusakRow" type="button" class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 ml-3">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Tambah Baris Rusak
                                    </button>
                                    <button wire:click="addSisaBulanLaluNb" type="button" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200 ml-3">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Tambah Sisa Bulan Lalu
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor Halaman Awal</label>
                        <input type="text" wire:model="pageStart" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full sm:w-64" placeholder="1" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nomor halaman pertama. Jika laporan lebih dari 1 halaman, halaman berikutnya otomatis +1.</p>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alasan Tutup Buku</label>
                        <select wire:model.live="closureReason" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full sm:w-64">
                            <option value="akhir_bulan">Akhir Bulan</option>
                            <option value="monev">Ada Monev</option>
                            <option value="ganti_kepala">Ganti Kepala</option>
                        </select>
                    </div>
                    @if ($closureReason === 'monev')
                        <div class="mt-4 border border-gray-200 dark:border-gray-700 rounded-md p-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Penandatangan Monev</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <p class="font-medium text-gray-700 dark:text-gray-300">Kepala KUA</p>
                                    <p>{{ auth()->user()->nama_kepala_kua ?? '-' }}</p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <p class="font-medium text-gray-700 dark:text-gray-300">Kepala Kantor Kemenag</p>
                                    <p>{{ auth()->user()->nama_kepala_kemenag ?? '-' }}</p>
                                </div>
                            </div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Anggota Monev</label>
                            @foreach ($monevMembers as $i => $member)
                                <div class="flex items-center gap-2 mb-2" wire:key="monev-{{ $member['id'] ?? $i }}">
                                    <input type="text" wire:model.live="monevMembers.{{ $i }}.nama" class="flex-1 border-gray-300 dark:border-gray-600 rounded text-xs px-2 py-1" placeholder="Nama anggota" />
                                    <button wire:click="removeMonevMember({{ $i }})" type="button" class="shrink-0 text-xs font-medium text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 rounded px-2 py-1">Hapus</button>
                                </div>
                            @endforeach
                            <button wire:click="addMonevMember" type="button" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 mt-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Tambah Anggota
                            </button>
                        </div>
                    @endif
                    @if ($closureReason === 'monev')
                        <div class="mt-3">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor ST</label>
                            <input type="text" wire:model="monevStNumber" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" placeholder="Nomor Surat Tugas" />
                        </div>
                    @endif
                    @if ($closureReason === 'ganti_kepala')
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SK Serah Terima Jabatan</label>
                            <input type="text" wire:model="gantiKepalaSk" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" placeholder="SK Kepala Kanwil ... nomor: ... Tanggal: ..." />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Contoh: SK Kepala Kanwil [Kota] nomor: [nomor SK] Tanggal: [tanggal SK]</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- 4. Judul & Format Output --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ ($this->tableLayout['type'] ?? '') === 'formulir' ? '4' : '3' }}. Judul &amp; Format Output</h3>
            <input type="text" wire:model="title" placeholder="Judul laporan" class="mt-3 border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" />
            @error('title') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <div class="grid grid-cols-1 sm:grid-cols-1 gap-3 mt-4">
                <label class="cursor-pointer border rounded-lg p-4 text-center border-gray-500 bg-gray-100 dark:bg-gray-700 dark:border-gray-400">
                    <input type="radio" wire:model.live="format" value="print" class="sr-only" />
                    <p class="font-bold text-gray-600 dark:text-gray-400">Print</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pratinjau cetak</p>
                </label>
            </div>

            @if (false)
                <label class="block mt-4 text-sm text-gray-700 dark:text-gray-300">
                    Orientasi PDF:
                    <select wire:model.live="orientation" class="ml-2 border-gray-300 dark:border-gray-600 rounded-md text-sm">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </label>
            @endif

            <div class="flex justify-end mt-6">
                <button type="button" wire:click="generate" wire:loading.attr="disabled" class="inline-flex items-center px-5 py-2 bg-blue-600 dark:bg-blue-500 text-white text-sm font-semibold rounded-md hover:bg-blue-700 dark:hover:bg-blue-600 disabled:opacity-50">
                    <span wire:loading.remove wire:target="generate">Buat Laporan</span>
                    <span wire:loading wire:target="generate">Memproses...</span>
                </button>
            </div>
        </div>
    @endif
</div>
