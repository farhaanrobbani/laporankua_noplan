<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $dataset['title'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            @page { size: {{ ($dataset['orientation'] ?? $dataset['table_layout']['orientation'] ?? 'portrait') === 'landscape' ? 'A4 landscape' : 'A4' }}; margin: 8mm 15mm 15mm 15mm; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
        @media screen {
            .print-sheet { max-width: {{ ($dataset['orientation'] ?? $dataset['table_layout']['orientation'] ?? 'portrait') === 'landscape' ? '297mm' : '210mm' }}; margin: 1.5rem auto; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.15); padding: 15mm; }
        }
        .th-rotate { writing-mode: vertical-rl; text-orientation: sideways; white-space: nowrap; transform: rotate(180deg); }
    </style>
</head>
<body class="bg-gray-100 text-gray-900" style="font-family: Arial, sans-serif;">
    <div class="no-print max-w-3xl mx-auto mt-4 flex gap-3 justify-end">
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700">Print / Simpan PDF</button>
        <button onclick="window.location='{{ route('reports.show', $report) }}'" class="px-4 py-2 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300">Tutup</button>
    </div>

    <div class="print-sheet overflow-x-auto">
        @if (! empty($dataset['table_layout']))
            @php
                $layout = $dataset['table_layout'];
                $columns = $layout['columns'] ?? [];
                $l2Title = 'LAPORAN';

                // Aggregation: group data if configured
                if (! empty($layout['aggregation']) && isset($layout['aggregation']['group_by']) && ($layout['type'] ?? '') !== 'grouped_detail' && ($layout['type'] ?? '') !== 'laporan_na' && ($layout['type'] ?? '') !== 'laporan_l1' && ($layout['type'] ?? '') !== 'rekap_ntcr' && ($layout['type'] ?? '') !== 'laporan_nb') {
                    $groupBy = $layout['aggregation']['group_by'];
                    $grouped = [];
                    foreach ($dataset['rows'] as $row) {
                        $key = $row[$groupBy] ?? 'Lainnya';
                        if (! isset($grouped[$key])) {
                            $grouped[$key] = [];
                        }
                        $grouped[$key][] = $row;
                    }

                    // Collect aggregate columns from layout (recursively, with parent group label)
                    $collectAggCols = function ($cols, $parentLabel = null) use (&$collectAggCols) {
                        $result = [];
                        foreach ($cols as $col) {
                            if ($col['type'] === 'aggregate_count' || $col['type'] === 'aggregate_total' || $col['type'] === 'aggregate_range') {
                                $compositeKey = $parentLabel ? $parentLabel . '|' . ($col['label'] ?? '') : ($col['label'] ?? '');
                                $result[] = array_merge($col, ['_key' => $compositeKey, '_parent' => $parentLabel]);
                            }
                            if (isset($col['children'])) {
                                $childParent = $col['label'] ?? $parentLabel;
                                $result = array_merge($result, $collectAggCols($col['children'], $childParent));
                            }
                        }
                        return $result;
                    };
                    $aggCols = $collectAggCols($columns);

                    foreach ($aggCols as $col) {
                        $field = $col['field'] ?? '';
                        if (str_contains($field, 'Pendidikan')) {
                            $l2Title = 'LAPORAN PENDIDIKAN PENGANTIN';
                            break;
                        }
                        if (str_contains($field, 'Usia')) {
                            $l2Title = 'LAPORAN USIA PENGANTIN';
                            break;
                        }
                    }

                    // Build aggregated rows
                    $staticValues = $dataset['daftar_desa'] ?? $layout['aggregation']['static_values'] ?? null;
                    $groupNames = $staticValues ?? array_keys($grouped);
                    sort($groupNames);

                    $aggRows = [];
                    foreach ($groupNames as $groupName) {
                        $rows = $grouped[$groupName] ?? [];
                        $aggRow = [$groupBy => $groupName];
                        foreach ($aggCols as $col) {
                            $key = $col['_key'];
                            if ($col['type'] === 'aggregate_total') {
                                $aggRow[$key] = count($rows);
                            } elseif ($col['type'] === 'aggregate_count') {
                                $count = 0;
                                $field = $col['field'] ?? '';
                                $matchList = $col['match'] ?? [];
                                foreach ($rows as $row) {
                                    $val = mb_strtolower((string) ($row[$field] ?? ''));
                                    foreach ($matchList as $m) {
                                        if (str_contains($val, mb_strtolower($m))) {
                                            $count++;
                                            break;
                                        }
                                    }
                                }
                                $aggRow[$key] = $count;
                            } elseif ($col['type'] === 'aggregate_range') {
                                $count = 0;
                                $field = $col['field'] ?? '';
                                $min = $col['min'] ?? null;
                                $max = $col['max'] ?? null;
                                foreach ($rows as $row) {
                                    $val = (int) ($row[$field] ?? 0);
                                    if ($min !== null && $val < $min) {
                                        continue;
                                    }
                                    if ($max !== null && $val >= $max) {
                                        continue;
                                    }
                                    $count++;
                                }
                                $aggRow[$key] = $count;
                            }
                        }
                        $aggRows[] = $aggRow;
                    }
                    $dataset['rows'] = $aggRows;

                    // Hitung total row untuk laporan L2
                    $totalRow = [$groupBy => 'Jumlah'];
                    foreach ($aggCols as $col) {
                        $key = $col['_key'];
                        $sum = 0;
                        foreach ($aggRows as $r) {
                            $sum += (int) ($r[$key] ?? 0);
                        }
                        $totalRow[$key] = $sum;
                    }
                }

                $applyTransform = function ($value, $transform) {
                    if ($transform === 'klinik_balai_nikah') {
                        $lower = mb_strtolower((string) $value);
                        return str_contains($lower, 'bedol') ? 'LK' : 'K';
                    }
                    return $value;
                };

                $getDataCells = function ($col) use ($applyTransform) {
                    if ($col['type'] === 'field') {
                        $field = $col['field'] ?? '';
                        $transform = $col['transform'] ?? null;
                        return [$field, $transform];
                    }
                    return [null, null];
                };

                $dateFilterField = $dataset['config_json']['table_layout']['aggregation']['date_filter_field'] ?? null;
                $tanggalNikahField = null;
                $nikahDiField = null;

                if ($dateFilterField) {
                    foreach ($columns as $col) {
                        if ($col['type'] === 'field' && ($col['field'] ?? '') === $dateFilterField) {
                            $tanggalNikahField = $col['field'];
                        }
                        if ($col['type'] === 'group') {
                            foreach ($col['children'] ?? [] as $child) {
                                if (($child['field'] ?? '') === $dateFilterField) {
                                    $tanggalNikahField = $child['field'];
                                }
                            }
                        }
                    }
                }

                if ($tanggalNikahField === null) {
                    foreach ($columns as $col) {
                        if ($col['type'] === 'field' && ($col['field'] ?? '') === 'Tanggal Nikah') {
                            $tanggalNikahField = $col['field'];
                        }
                        if ($col['type'] === 'field' && ($col['field'] ?? '') === 'Nikah Di') {
                            $nikahDiField = $col['field'];
                        }
                        if ($col['type'] === 'group') {
                            foreach ($col['children'] ?? [] as $child) {
                                if (($child['field'] ?? '') === 'Tanggal Nikah') {
                                    $tanggalNikahField = $child['field'];
                                }
                                if (($child['field'] ?? '') === 'Nikah Di') {
                                    $nikahDiField = $child['field'];
                                }
                            }
                        }
                    }
                } else {
                    foreach ($columns as $col) {
                        if ($col['type'] === 'field' && ($col['field'] ?? '') === 'Nikah Di') {
                            $nikahDiField = $col['field'];
                        }
                        if ($col['type'] === 'group') {
                            foreach ($col['children'] ?? [] as $child) {
                                if (($child['field'] ?? '') === 'Nikah Di') {
                                    $nikahDiField = $child['field'];
                                }
                            }
                        }
                    }
                }

                $lastDate = null;
                $countK = 0;
                $countLK = 0;
                $monthDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

                foreach ($dataset['rows'] as $row) {
                    if ($nikahDiField !== null) {
                        $nikahDi = mb_strtolower((string) ($row[$nikahDiField] ?? ''));
                        if (str_contains($nikahDi, 'bedol')) {
                            $countLK++;
                        } else {
                            $countK++;
                        }
                    }
                    if ($tanggalNikahField !== null && ! empty($row[$tanggalNikahField])) {
                        try {
                            $date = \Carbon\Carbon::parse($row[$tanggalNikahField]);
                            if ($lastDate === null || $date->gt($lastDate)) {
                                $lastDate = $date;
                            }
                        } catch (\Exception $e) {}
                    }
                }

                $countAll = $countK + $countLK;
                $hariName = $lastDate ? $monthDays[$lastDate->dayOfWeek] : '-';
                $tanggalFormatted = $lastDate ? $lastDate->day . ' ' . $monthNames[$lastDate->month] . ' ' . $lastDate->year : '-';
                $closureReasonText = match ($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') {
                    'monev' => 'ada monev',
                    'ganti_kepala' => 'ganti kepala',
                    default => 'akhir bulan',
                };
                $bulanName = $dataset['bulan_override'] ?? ($lastDate ? $monthNames[$lastDate->month] : '-');
                $tahunName = $dataset['tahun_override'] ?? ($lastDate ? $lastDate->year : '-');
                $isL2Report = !empty($dataset['table_layout']['aggregation']) && empty($dataset['table_layout']['aggregation']['date_filter_field']) && ($layout['type'] ?? '') !== 'grouped_detail' && ($layout['type'] ?? '') !== 'formulir' && ($layout['type'] ?? '') !== 'laporan_na' && ($layout['type'] ?? '') !== 'laporan_l1' && ($layout['type'] ?? '') !== 'rekap_ntcr' && ($layout['type'] ?? '') !== 'laporan_nb';
                $isL4Report = ($layout['type'] ?? '') === 'grouped_detail';
                $isL3Report = ($layout['type'] ?? '') === 'formulir';
                $isLaporanNA = ($layout['type'] ?? '') === 'laporan_na';
                $isL1Report = ($layout['type'] ?? '') === 'laporan_l1';
                $isNtcrReport = ($layout['type'] ?? '') === 'rekap_ntcr';
                $isNbReport = ($layout['type'] ?? '') === 'laporan_nb';
                $isNReport = ($layout['type'] ?? '') === 'laporan_n';
                if (($isLaporanNA || $isNbReport || $isNReport) && ($hariName === '-' || $tanggalFormatted === '-')) {
                    $filterMonth = (int) ($dataset['filter_month'] ?? 0);
                    $filterYear = (int) ($dataset['filter_year'] ?? 0);
                    if ($filterMonth > 0 && $filterYear > 0) {
                        $lastDate = \Carbon\Carbon::createFromDate($filterYear, $filterMonth, 1)->endOfMonth();
                        $hariName = $monthDays[$lastDate->dayOfWeek];
                        $tanggalFormatted = $lastDate->day . ' ' . $monthNames[$lastDate->month] . ' ' . $lastDate->year;
                    }
                } elseif (! $isLaporanNA && $lastDate !== null) {
                    $lastDate = $lastDate->endOfMonth();
                    $hariName = $monthDays[$lastDate->dayOfWeek];
                    $tanggalFormatted = $lastDate->day . ' ' . $monthNames[$lastDate->month] . ' ' . $lastDate->year;
                }
                $isL5Report = false;
                foreach ($columns as $col) {
                    if (($col['type'] ?? '') === 'group' && ($col['label'] ?? '') === 'Memiliki Sertifikat Suscatin') {
                        $isL5Report = true;
                        break;
                    }
                }

                $l4Groups = [];
                if ($isL4Report) {
                    $groupBy = $layout['aggregation']['group_by'] ?? null;
                    if ($groupBy) {
                        $grouped = [];
                        foreach ($dataset['rows'] as $row) {
                            $key = $row[$groupBy] ?? 'Lainnya';
                            if (! isset($grouped[$key])) {
                                $grouped[$key] = [];
                            }
                            $grouped[$key][] = $row;
                        }
                        ksort($grouped);

                        foreach ($grouped as &$gRows) {
                            usort($gRows, function ($a, $b) {
                                $dateA = strtotime($a['Tanggal Akad'] ?? '') ?: 0;
                                $dateB = strtotime($b['Tanggal Akad'] ?? '') ?: 0;

                                return $dateA - $dateB;
                            });
                        }
                        unset($gRows);

                        $rowNum = 1;
                        foreach ($grouped as $groupName => $rows) {
                            $l4Groups[] = [
                                'name' => $groupName,
                                'count' => count($rows),
                                'rows' => $rows,
                                'startRowNum' => $rowNum,
                            ];
                            $rowNum += count($rows);
                        }
                    }

                }
            @endphp

            <div class="mb-0">
                @if ($isL2Report && ! $isL5Report)
                    <div class="flex items-start mb-3">
                        <span style="font-size: 21px; font-weight: bold;">L2</span>
                        <div class="text-center flex-1">
                            <h1 class="font-bold uppercase" style="font-size: 14px;">{{ $l2Title }}</h1>
                            <p class="uppercase" style="font-size: 12px;">KANTOR URUSAN AGAMA KECAMATAN {{ strtoupper($dataset['kecamatan'] ?? '') }}</p>
                            <p style="font-size: 12px;">BULAN {{ strtoupper($bulanName) }} TAHUN {{ $tahunName }}</p>
                        </div>
                    </div>
                @elseif ($isL5Report)
                    <div class="flex items-start mb-3">
                        <span style="font-size: 21px; font-weight: bold;">L5</span>
                        <div class="text-center flex-1">
                            <h1 class="font-bold uppercase" style="font-size: 14px;">LAPORAN</h1>
                            <h2 class="font-bold uppercase" style="font-size: 13px;">KURSUS CALON PENGANTIN</h2>
                            <p class="uppercase" style="font-size: 12px;">KANTOR URUSAN AGAMA KECAMATAN {{ strtoupper($dataset['kecamatan'] ?? '') }}</p>
                            <p style="font-size: 12px;">BULAN {{ strtoupper($bulanName) }} TAHUN {{ $tahunName }}</p>
                        </div>
                    </div>
                @elseif ($isL3Report)
                    <div class="flex items-start mb-3">
                        <span style="font-size: 21px; font-weight: bold;">L3</span>
                        <div class="text-center flex-1">
                            <h1 class="font-bold uppercase" style="font-size: 14px;">LAPORAN</h1>
                            <h2 class="font-bold uppercase" style="font-size: 13px;">FORMULIR PERKAWINAN ATAU RUJUK</h2>
                            <p class="uppercase" style="font-size: 12px;">KANTOR URUSAN AGAMA KECAMATAN {{ strtoupper($dataset['kecamatan'] ?? '') }}</p>
                            <p style="font-size: 12px;">BULAN {{ strtoupper($bulanName) }} TAHUN {{ $tahunName }}</p>
                        </div>
                    </div>
                @elseif ($isLaporanNA)
                    @php
                        $bukuStokPageStart = (int) ($dataset['config_json']['page_start'] ?? 1) ?: 1;
                    @endphp
                    @include('reports.partials.buku-stok-header', [
                        'bulan' => strtoupper($bulanName),
                        'tahun' => $tahunName,
                        'model' => 'NA',
                        'pageDisplay' => $bukuStokPageStart + (isset($pageNumber) ? $pageNumber - 1 : 0),
                    ])
                @elseif ($isL4Report)
                    <div class="flex items-start mb-3">
                        <span style="font-size: 21px; font-weight: bold;">L4</span>
                        <div class="text-center flex-1">
                            <h1 class="font-bold uppercase" style="font-size: 14px;">LAPORAN</h1>
                            <h2 class="font-bold uppercase" style="font-size: 13px;">PNPB NIKAH ATAU RUJUK</h2>
                            <p class="uppercase" style="font-size: 12px;">KANTOR URUSAN AGAMA KECAMATAN {{ strtoupper($dataset['kecamatan'] ?? '') }}</p>
                            <p style="font-size: 12px;">BULAN {{ strtoupper($bulanName) }} TAHUN {{ $tahunName }}</p>
                        </div>
                    </div>
                @elseif ($isL1Report)
                    <div class="flex items-start mb-3">
                        <span style="font-size: 21px; font-weight: bold;">L1</span>
                        <div class="text-center flex-1">
                            <h1 class="font-bold uppercase" style="font-size: 14px;">LAPORAN PERISTIWA PERKAWINAN / RUJUK</h1>
                            <p class="uppercase" style="font-size: 12px;">KANTOR URUSAN AGAMA KECAMATAN {{ strtoupper($dataset['kecamatan'] ?? '') }}</p>
                            <p style="font-size: 12px;">BULAN {{ strtoupper($bulanName) }} TAHUN {{ $tahunName }}</p>
                        </div>
                    </div>
                @elseif ($isNtcrReport)
                    <div class="flex items-start mb-3">
                        <span style="font-size: 21px; font-weight: bold;">NTCR</span>
                        <div class="text-center flex-1">
                            <h1 class="font-bold uppercase" style="font-size: 14px;">REKAP NTCR</h1>
                            <p class="uppercase" style="font-size: 12px;">KANTOR URUSAN AGAMA KECAMATAN {{ strtoupper($dataset['kecamatan'] ?? '') }}</p>
                            <p style="font-size: 12px;">TAHUN {{ $dataset['filter_year'] ?? $tahunName }}</p>
                        </div>
                    </div>
                @elseif ($isNbReport)
                    @php
                        $bukuStokPageStart = (int) ($dataset['config_json']['page_start'] ?? 1) ?: 1;
                    @endphp
                    @include('reports.partials.buku-stok-header', [
                        'bulan' => strtoupper($monthNames[(int) ($dataset['filter_month'] ?? 1)] ?? ''),
                        'tahun' => $dataset['filter_year'] ?? $tahunName,
                        'model' => 'NB',
                        'bookTitle' => 'BUKU STOK UMUM',
                        'bsModel' => 'BS 2',
                        'pageDisplay' => $bukuStokPageStart + (isset($pageNumber) ? $pageNumber - 1 : 0),
                    ])
                @elseif ($isNReport)
                    @php
                        $bukuStokPageStart = (int) ($dataset['config_json']['page_start'] ?? 1) ?: 1;
                    @endphp
                    @include('reports.partials.buku-stok-header', [
                        'bulan' => strtoupper($monthNames[(int) ($dataset['filter_month'] ?? 1)] ?? ''),
                        'tahun' => $dataset['filter_year'] ?? $tahunName,
                        'model' => 'N Lembar',
                        'bsModel' => 'BS 2',
                        'pageDisplay' => $bukuStokPageStart,
                    ])
                @else
                        @if (! empty($dataset['logo_kantor']))
                            <table class="w-full mb-1" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="align-top pr-1" style="width: 80px;">
                                        <img src="{{ asset('storage/' . $dataset['logo_kantor']) }}" alt="Logo" style="margin-left: 64px; max-height: 80px; max-width: 80px;" />
                                    </td>
                                    <td class="text-center">
                                        <p class="font-bold uppercase" style="font-size: {{ $dataset['font_size_kop_kementerian'] ?? '12' }}px;">{{ $dataset['nama_kementerian'] ?? 'Kementerian Agama' }}</p>
                                        @if (! empty($dataset['nama_kantor_kota']))
                                            <p class="font-bold uppercase" style="font-size: {{ $dataset['font_size_kop_kantor_kota'] ?? '12' }}px;">{{ $dataset['nama_kantor_kota'] }}</p>
                                        @endif
                                        @if (! empty($dataset['nama_kantor']))
                                            <p class="font-bold uppercase" style="font-size: {{ $dataset['font_size_kop_kantor'] ?? '12' }}px;">{{ $dataset['nama_kantor'] }}</p>
                                        @endif
                                        @if (! empty($dataset['alamat_kantor']))
                                            <p style="font-size: {{ $dataset['font_size_kop_alamat'] ?? '10' }}px;">{{ $dataset['alamat_kantor'] }}</p>
                                        @endif
                                        @if (! empty($dataset['telepon_kantor']) || ! empty($dataset['email_kantor']))
                                            <p style="font-size: {{ $dataset['font_size_kop_kontak'] ?? '10' }}px;">
                                                @if (! empty($dataset['telepon_kantor']))
                                                    Telp: {{ $dataset['telepon_kantor'] }}
                                                @endif
                                                @if (! empty($dataset['telepon_kantor']) && ! empty($dataset['email_kantor']))
                                                    |
                                                @endif
                                                @if (! empty($dataset['email_kantor']))
                                                    Email: {{ $dataset['email_kantor'] }}
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @else
                            <div class="text-center mb-1">
                                <p class="font-bold uppercase" style="font-size: {{ $dataset['font_size_kop_kementerian'] ?? '12' }}px;">{{ $dataset['nama_kementerian'] ?? 'Kementerian Agama' }}</p>
                                @if (! empty($dataset['nama_kantor_kota']))
                                    <p class="font-bold uppercase" style="font-size: {{ $dataset['font_size_kop_kantor_kota'] ?? '12' }}px;">{{ $dataset['nama_kantor_kota'] }}</p>
                                @endif
                                @if (! empty($dataset['nama_kantor']))
                                    <p class="font-bold uppercase" style="font-size: {{ $dataset['font_size_kop_kantor'] ?? '12' }}px;">{{ $dataset['nama_kantor'] }}</p>
                                @endif
                                @if (! empty($dataset['alamat_kantor']))
                                    <p style="font-size: {{ $dataset['font_size_kop_alamat'] ?? '10' }}px;">{{ $dataset['alamat_kantor'] }}</p>
                                @endif
                                @if (! empty($dataset['telepon_kantor']) || ! empty($dataset['email_kantor']))
                                    <p style="font-size: {{ $dataset['font_size_kop_kontak'] ?? '10' }}px;">
                                        @if (! empty($dataset['telepon_kantor']))
                                            Telp: {{ $dataset['telepon_kantor'] }}
                                        @endif
                                        @if (! empty($dataset['telepon_kantor']) && ! empty($dataset['email_kantor']))
                                            |
                                        @endif
                                        @if (! empty($dataset['email_kantor']))
                                            Email: {{ $dataset['email_kantor'] }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endif
                        <hr class="border-t border-gray-900 mb-1">

                    <h1 class="font-bold text-center" style="font-size: 14px;">REKAP PENDAFTARAN NIKAH/RUJUK</h1>
                    <div class="mt-2 mb-3" style="font-size: 12px;">
                        <p><span style="display:inline-block; width:5.5ch; font-weight: bold;">Bulan</span> : {{ $bulanName }}</p>
                        <p><span style="display:inline-block; width:5.5ch; font-weight: bold;">Tahun</span> : {{ $tahunName }}</p>
                    </div>
                @endif
            </div>

            <table id="table-na" class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                @if ($isL1Report)
                <thead>
                    {{-- Row 1 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="4" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                        <th rowspan="4" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">DESA</th>
                        <th colspan="14" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">PERKAWINAN</th>
                        <th rowspan="4" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">{!! 'Pencatatan<br>Perkawinan Luar<br>Negeri' !!}</th>
                        <th rowspan="4" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">{!! 'Duplikat<br>Buku Perkawinan' !!}</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">TALAK KE</th>
                        <th rowspan="4" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">CERAI</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">RUJUK KE</th>
                    </tr>
                    {{-- Row 2 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Jumlah Seluruhnya</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">WALI NIKAH</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Itsbat Nikah</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">CAMPURAN</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">POLIGAMI</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Kantor</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Luar Kantor</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">BEBAS BIAYA</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">I</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">II</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">III</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">I</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">II</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">III</th>
                    </tr>
                    {{-- Row 3 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Nasab</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Hakim</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Laki-laki</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Perempuan</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">II</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">III</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">IV</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Miskin</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Bencana Alam</th>
                    </tr>
                    {{-- Row 4 --}}
                    <tr class="bg-gray-100">
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Adhal</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Lain-lain</th>
                    </tr>
                </thead>
                @elseif ($isNtcrReport)
                <thead>
                    {{-- Row 1 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Bulan</th>
                        <th colspan="6" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nikah, Talak, Cerai &amp; Rujuk</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pendaftaran</th>
                        <th colspan="16" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Rincian Pendaftaran</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Jumlah</th>
                        <th rowspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Setor PNBP NR</th>
                    </tr>
                    {{-- Row 2 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">LK</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">K</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Nikah</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">T</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">C</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">R</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">K</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">LK</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Jml</th>
                        <th colspan="16" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Untuk Pelaksanaan Nikah Bulan</th>
                    </tr>
                    {{-- Row 3 --}}
                    <tr class="bg-gray-100">
                        @for ($i = 1; $i <= 12; $i++)
                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $i }}</th>
                        @endfor
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Gagal</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Tunda</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">Miskin</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold th-rotate">{!! 'Bencana<br>Alam' !!}</th>
                    </tr>
                </thead>
                @elseif ($isNbReport)
                <thead>
                    {{-- Row 1 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                    </tr>
                    {{-- Row 2 --}}
                    <tr class="bg-gray-100">
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                    </tr>
                </thead>
                @elseif ($isNReport)
                <thead>
                    {{-- Row 1 --}}
                    <tr class="bg-gray-100">
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                    </tr>
                    {{-- Row 2 --}}
                    <tr class="bg-gray-100">
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                    </tr>
                </thead>
                @elseif ($isLaporanNA)
                <thead>
                    <tr class="bg-gray-100">
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">NA, RA, atau DN</th>
                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                    </tr>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Model</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Seri/Nomor</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                    </tr>
                </thead>
                @else
                <thead>
                    <tr class="bg-gray-100">
                    @foreach ($columns as $col)
                        @if ($col['type'] === 'row_number')
                            <th rowspan="{{ $col['rowspan'] ?? 1 }}" @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $col['label'] ?? '#' }}</th>
                        @elseif ($col['type'] === 'group')
                            <th colspan="{{ $col['colspan'] ?? 1 }}" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $col['label'] ?? '' }}</th>
                        @elseif ($col['type'] === 'field' || $col['type'] === 'aggregate_total' || $col['type'] === 'aggregate_count' || $col['type'] === 'aggregate_range' || $col['type'] === 'static' || $col['type'] === 'static_value' || $col['type'] === 'manual')
                            <th rowspan="{{ $col['rowspan'] ?? 1 }}" @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $col['label'] ?? $col['field'] ?? '' }}</th>
                        @endif
                    @endforeach
                    </tr>
                    <tr class="bg-gray-100">
                    @foreach ($columns as $col)
                        @if ($col['type'] === 'group')
                            @foreach ($col['children'] ?? [] as $child)
                                @if ($child['type'] === 'group')
                                    <th colspan="{{ $child['colspan'] ?? 1 }}" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $child['label'] ?? '' }}</th>
                                @else
                                    <th @if(!empty($child['width']))style="max-width:{{ $child['width'] }};width:{{ $child['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $child['label'] ?? $child['field'] ?? '' }}</th>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                    </tr>
                    @php
                        // Third header row: render children of nested groups
                        $hasNestedGroups = false;
                        foreach ($columns as $col) {
                            if ($col['type'] === 'group') {
                                foreach ($col['children'] ?? [] as $child) {
                                    if ($child['type'] === 'group') {
                                        $hasNestedGroups = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    @endphp
                    @if ($hasNestedGroups)
                    <tr class="bg-gray-100">
                        @foreach ($columns as $col)
                            @if ($col['type'] === 'group')
                                @foreach ($col['children'] ?? [] as $child)
                                    @if ($child['type'] === 'group')
                                        @foreach ($child['children'] ?? [] as $grandchild)
                                            <th @if(!empty($grandchild['width']))style="max-width:{{ $grandchild['width'] }};width:{{ $grandchild['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center font-semibold">{{ $grandchild['label'] ?? $grandchild['field'] ?? '' }}</th>
                                        @endforeach
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    </tr>
                    @endif
                </thead>
                @endif
                <tbody style="font-size: 10px;">
                    @if ($isL4Report)
                        @php $l4GroupNum = 1; @endphp
                        @foreach ($l4Groups as $group)
                            @foreach ($group['rows'] as $i => $row)
                                <tr>
                                    @foreach ($columns as $col)
                                        @if ($col['type'] === 'row_number')
                                            @if ($i === 0)
                                                <td rowspan="{{ $group['count'] }}" class="border border-gray-700 px-1 py-0.5 text-center">{{ $l4GroupNum++ }}</td>
                                            @endif
                                        @elseif ($col['type'] === 'field')
                                            @if (($col['field'] ?? '') === ($layout['aggregation']['group_by'] ?? ''))
                                                @if ($i === 0)
                                                    <td rowspan="{{ $group['count'] }}" class="border border-gray-700 px-1 py-0.5 text-center">{{ $group['name'] }}</td>
                                                @endif
                                            @else
                                                @php
                                                    $value = $row[$col['field']] ?? '';
                                                    if (($col['format'] ?? '') === 'date_id' && $value !== '' && $value !== null) {
                                                        try {
                                                            $d = \Carbon\Carbon::parse($value);
                                                            $value = $d->format('d-m-Y');
                                                        } catch (\Exception $e) {}
                                                    }
                                                @endphp
                                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                            @endif
                                        @elseif ($col['type'] === 'aggregate_total')
                                            @if ($i === 0)
                                                <td rowspan="{{ $group['count'] }}" class="border border-gray-700 px-1 py-0.5 text-center">{{ $group['count'] }}</td>
                                            @endif
                                        @elseif ($col['type'] === 'static')
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $col['value'] ?? '' }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                            @if ($group['count'] === 0)
                                <tr>
                                    @foreach ($columns as $col)
                                        @if ($col['type'] === 'row_number')
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l4GroupNum++ }}</td>
                                        @elseif ($col['type'] === 'field')
                                            @if (($col['field'] ?? '') === ($layout['aggregation']['group_by'] ?? ''))
                                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $group['name'] }}</td>
                                            @else
                                                <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            @endif
                                        @elseif ($col['type'] === 'aggregate_total')
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">0</td>
                                        @elseif ($col['type'] === 'static')
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $col['value'] ?? '' }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                        @php
                            $l4TotalPerkawinan = 0;
                            foreach ($l4Groups as $g) {
                                $l4TotalPerkawinan += $g['count'];
                            }
                            $l4TotalSetor = $l4TotalPerkawinan * 600000;
                        @endphp
                        <tr style="font-weight: bold;">
                            @foreach ($columns as $col)
                                @if ($col['type'] === 'row_number')
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                @elseif ($col['type'] === 'field')
                                    @if (($col['field'] ?? '') === ($layout['aggregation']['group_by'] ?? ''))
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">Jumlah</td>
                                    @else
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    @endif
                                @elseif ($col['type'] === 'aggregate_total')
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l4TotalPerkawinan }}</td>
                                @elseif ($col['type'] === 'static')
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ number_format($l4TotalSetor, 0, ',', '.') }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @elseif ($isL1Report)
                        @php
                            $l1RowNum = 1;
                            $l1Totals = array_fill_keys([
                                'Jumlah Nikah', 'Nasab', 'Adhal', 'Lain-lain', 'Itsbat Nikah',
                                'Campuran Laki-laki', 'Campuran Perempuan',
                                'Poligami II', 'Poligami III', 'Poligami IV',
                                'Kantor', 'Luar Kantor', 'Miskin', 'Bencana Alam',
                                'Pencatatan LN', 'Duplikat',
                                'Talak I', 'Talak II', 'Talak III', 'Cerai',
                                'Rujuk I', 'Rujuk II', 'Rujuk III',
                            ], 0);
                        @endphp
                        @foreach ($dataset['rows'] as $row)
                            <tr>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1RowNum++ }}</td>
                                <td class="border border-gray-700 px-1 py-0.5">{{ $row['Kelurahan'] ?? '' }}</td>
                                {{-- Jumlah Seluruhnya --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Jumlah Nikah'] ?? 0 }}</td>
                                {{-- Wal nikah: Nasab, Adhal, Lain-lain --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Nasab'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Adhal'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Lain-lain'] ?? 0 }}</td>
                                {{-- Itsbat Nikah --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Itsbat Nikah'] ?? 0 }}</td>
                                {{-- Campuran: Laki, Perempuan --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Campuran Laki-laki'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Campuran Perempuan'] ?? 0 }}</td>
                                {{-- Poligami: II, III, IV --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Poligami II'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Poligami III'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Poligami IV'] ?? '' }}</td>
                                {{-- Kantor, Luar Kantor --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Kantor'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Luar Kantor'] ?? 0 }}</td>
                                {{-- Bebas Biaya: Miskin, Bencana --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Miskin'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Bencana Alam'] ?? '' }}</td>
                                {{-- Pencatatan LN, Duplikat --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Pencatatan LN'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Duplikat'] ?? 0 }}</td>
                                {{-- Talak: I, II, III --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Talak I'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Talak II'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Talak III'] ?? '' }}</td>
                                {{-- Cerai --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Cerai'] ?? '' }}</td>
                                {{-- Rujuk: I, II, III --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Rujuk I'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Rujuk II'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Rujuk III'] ?? '' }}</td>
                            </tr>
                            @php
                                foreach ($l1Totals as $key => &$val) {
                                    $val += (int) ($row[$key] ?? 0);
                                }
                                unset($val);
                            @endphp
                        @endforeach
                        {{-- Total row --}}
                        <tr style="font-weight: bold;">
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">Jumlah</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Jumlah Nikah'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Nasab'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Adhal'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Lain-lain'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Itsbat Nikah'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Campuran Laki-laki'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Campuran Perempuan'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Poligami II'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Poligami III'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Poligami IV'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Kantor'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Luar Kantor'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Miskin'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Bencana Alam'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Pencatatan LN'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Duplikat'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Talak I'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Talak II'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Talak III'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Cerai'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Rujuk I'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Rujuk II'] ?? 0 }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $l1Totals['Rujuk III'] ?? 0 }}</td>
                        </tr>
                    @elseif ($isL3Report)
                        @php
                            $manualRaw = $dataset['config_json']['manual_data'] ?? null;
                            $manualData = is_array($manualRaw) && isset($manualRaw['rows']) ? $manualRaw['rows'] : $manualRaw;
                            $staticRows = $dataset['config_json']['table_layout']['static_rows'] ?? $layout['static_rows'] ?? [];
                        @endphp
                        @foreach ($staticRows as $sr)
                            @php
                                $idx = $sr['row_num'] - 1;
                                $md = is_array($manualData) ? ($manualData[$idx] ?? null) : null;
                            @endphp
                            <tr>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $sr['row_num'] }}</td>
                                <td class="border border-gray-700 px-1 py-0.5">{{ $md['formulir'] ?? $sr['formulir'] ?? '' }}</td>
                                {{-- Masuk --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['masuk_jumlah'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['masuk_seri'] ?? '' }}</td>
                                {{-- Keluar --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['keluar_jumlah'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['keluar_seri'] ?? '' }}</td>
                                {{-- Sisa --}}
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['sisa_jumlah'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['sisa_seri'] ?? '' }}</td>
                                {{-- Keterangan --}}
                                <td class="border border-gray-700 px-1 py-0.5">{{ $md['keterangan'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    @elseif ($isLaporanNA)
                        @php
                            $manualRaw = $dataset['config_json']['manual_data'] ?? null;
                            $sisaBLRaw = is_array($manualRaw) ? ($manualRaw['sisa_bulan_lalu'] ?? []) : [];
                            $sisaBLEntries = [];
                            if (! empty($sisaBLRaw)) {
                                if (isset($sisaBLRaw['masuk']) || isset($sisaBLRaw['seri_dari'])) {
                                    $sisaBLEntries = [$sisaBLRaw];
                                } else {
                                    $sisaBLEntries = is_array($sisaBLRaw) ? $sisaBLRaw : [];
                                }
                            }
                            $prefixLength = 6;

                            // Calculate running sisa from sisa bulan lalu
                            $runningSisa = [];
                            foreach ($sisaBLEntries as $sbl) {
                                $sblMasuk = (int) ($sbl['masuk'] ?? 0);
                                $sblKeluar = (int) ($sbl['keluar'] ?? 0);
                                $sblSisa = max(0, $sblMasuk - $sblKeluar);
                                $sblSeri = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($sbl['seri_dari'] ?? ''))));
                                $pf = substr($sblSeri, 0, $prefixLength);
                                $runningSisa[$pf] = $sblSisa;
                            }
                            $sisaState = $runningSisa;

                            // Group data rows by date
                            $rows = $dataset['rows'] ?? [];
                            $grouped = [];
                            foreach ($rows as $row) {
                                $key = $row['Tanggal Cetak'] ?? 'Lainnya';
                                try { $key = \Carbon\Carbon::parse($key)->format('Y-m-d'); } catch (\Exception $e) {}
                                if (! isset($grouped[$key])) {
                                    $grouped[$key] = [];
                                }
                                $grouped[$key][] = $row;
                            }
                            ksort($grouped);

                            // Deteksi nomor perforasi hilang (Rusak) per prefix.
                            // Sumber data rusak: celah interior pada rentang nomor
                            // yang benar-benar ada di baris laporan bulan ini.
                            $rusakByPrefix = $dataset['config_json']['manual_data']['rusak_by_prefix'] ?? app(\App\Services\RusakDetectionService::class)->detectMissing($rows, $prefixLength);
                            // Tanggal terakhir tiap prefix (grouped sudah ksort naik).
                            $lastDateByPrefix = [];
                            foreach ($grouped as $dateKey => $dateRows) {
                                foreach ($dateRows as $r) {
                                    $p = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($r['Nomor Perforasi'] ?? ''))));
                                    $lastDateByPrefix[substr((string) $p, 0, $prefixLength)] = $dateKey;
                                }
                            }
                            $totalRusak = 0;
                            foreach ($rusakByPrefix as $missingNums) {
                                $totalRusak += count($missingNums);
                            }

                            // Pre-calculate sisa per date/prefix
                            $dateSisaBD = [];
                            $dateSisaD = [];
                            $dateSisaRusak = [];
                            foreach ($grouped as $dateKey => $dateRows) {
                                $byPrefix = [];
                                foreach ($dateRows as $r) {
                                    $p = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($r['Nomor Perforasi'] ?? ''))));
                                    $pf = substr((string) $p, 0, $prefixLength);
                                    if (! isset($byPrefix[$pf])) {
                                        $byPrefix[$pf] = [];
                                    }
                                    $byPrefix[$pf][] = $r;
                                }
                                $dateSisaBD[$dateKey] = [];
                                $dateSisaD[$dateKey] = [];
                                $dateSisaRusak[$dateKey] = [];
                                foreach ($byPrefix as $pf => $pfRows) {
                                    $ketOf = fn (array $r): string => mb_strtolower(trim((string) ($r['Keterangan'] ?? '')));
                                    $statusRusakCount = count(array_values(array_filter($pfRows, fn ($r) => $ketOf($r) === 'rusak')));
                                    $dCount = count(array_values(array_filter($pfRows, fn ($r) => $ketOf($r) === 'duplikat')));
                                    $bdCount = count($pfRows) - $dCount - $statusRusakCount;
                                    if (! isset($runningSisa[$pf])) {
                                        $runningSisa[$pf] = 0;
                                    }
                                    $runningSisa[$pf] -= $bdCount;
                                    $dateSisaBD[$dateKey][$pf] = max(0, $runningSisa[$pf]);
                                    $runningSisa[$pf] -= $dCount;
                                    $dateSisaD[$dateKey][$pf] = max(0, $runningSisa[$pf]);
                                    $runningSisa[$pf] -= $statusRusakCount;
                                    $dateSisaRusak[$dateKey][$pf] = max(0, $runningSisa[$pf]);
                                    // Celah nomor rusak mengurangi sisa tepat di tanggal terakhir data prefix.
                                    if (isset($rusakByPrefix[$pf]) && ($lastDateByPrefix[$pf] ?? null) === $dateKey) {
                                        $runningSisa[$pf] -= count($rusakByPrefix[$pf]);
                                        $dateSisaRusak[$dateKey][$pf] = max(0, $runningSisa[$pf]);
                                    }
                                }
                            }

                            // Build flat rows array
                            $flatRows = [];
                            foreach ($sisaBLEntries as $sblEntry) {
                                $flatRows[] = ['type' => 'sbl', 'data' => $sblEntry];
                            }
                            foreach ($grouped as $dateKey => $dateRows) {
                                $byPrefix = [];
                                foreach ($dateRows as $r) {
                                    $p = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($r['Nomor Perforasi'] ?? ''))));
                                    $pf = substr((string) $p, 0, $prefixLength);
                                    if (! isset($byPrefix[$pf])) {
                                        $byPrefix[$pf] = ['bd' => [], 'd' => [], 'rusak' => []];
                                    }
                                    $ket = mb_strtolower(trim((string) ($r['Keterangan'] ?? '')));
                                    if ($ket === 'duplikat') {
                                        $byPrefix[$pf]['d'][] = $r;
                                    } elseif ($ket === 'rusak') {
                                        $byPrefix[$pf]['rusak'][] = $r;
                                    } else {
                                        $byPrefix[$pf]['bd'][] = $r;
                                    }
                                }
                                $dateDisplay = $dateKey;
                                try { $dateDisplay = \Carbon\Carbon::parse($dateKey)->format('d/m/Y'); } catch (\Exception $e) {}
                                foreach ($byPrefix as $pf => $pfData) {
                                    $bukanDuplikat = $pfData['bd'];
                                    $duplikat = $pfData['d'];
                                    $rusakStatus = $pfData['rusak'];
                                    $keluarBD = count($bukanDuplikat);
                                    $keluarD = count($duplikat);
                                    $sisaBD = $dateSisaBD[$dateKey][$pf] ?? 0;
                                    $sisaD = $dateSisaD[$dateKey][$pf] ?? 0;
                                    if ($keluarBD > 0) {
                                        $flatRows[] = ['type' => 'bd', 'date' => $dateDisplay, 'pf' => $pf, 'bukanDuplikat' => $bukanDuplikat, 'keluarBD' => $keluarBD, 'sisaBD' => $sisaBD];
                                    }
                                    if ($keluarD > 0) {
                                        $flatRows[] = ['type' => 'd', 'date' => $dateDisplay, 'pf' => $pf, 'duplikat' => $duplikat, 'keluarD' => $keluarD, 'sisaD' => $sisaD];
                                    }
                                    $isLastDate = isset($rusakByPrefix[$pf]) && ($lastDateByPrefix[$pf] ?? null) === $dateKey;
                                    $rusakNumbers = $isLastDate ? $rusakByPrefix[$pf] : [];
                                    foreach ($rusakStatus as $rusakRow) {
                                        $rusakNomor = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($rusakRow['Nomor Perforasi'] ?? ''))));
                                        if ($rusakNomor !== '' && ctype_digit($rusakNomor)) {
                                            $rusakNumbers[] = (int) $rusakNomor;
                                        }
                                    }
                                    if ($rusakNumbers !== []) {
                                        $rusakNumbers = array_values(array_unique($rusakNumbers));
                                        sort($rusakNumbers);
                                        $flatRows[] = [
                                            'type' => 'rusak',
                                            'date' => $dateDisplay,
                                            'pf' => $pf,
                                            'missing' => $rusakNumbers,
                                            'keluarRusak' => count($rusakNumbers),
                                            'sisaRusak' => $dateSisaRusak[$dateKey][$pf] ?? 0,
                                        ];
                                    }
                                }
                            }

                            $rowNum = 1;
                            $pageIndex = 0;
                            $pageRows = 25;
                            $closureReason = $dataset['config_json']['closure_reason'] ?? 'akhir_bulan';
                            $extraPageThreshold = in_array($closureReason, ['monev', 'ganti_kepala']) ? 8 : 15;
                            $pageNumber = 1;
                            $prevPageSisa = null;
                            $prevPageMasuk = null;
                            $prevPageKeluar = null;
                            $needsPageBreak = false;
                            $flatCount = count($flatRows);
                            $runningMasuk = 0;
                            $runningKeluar = 0;

                            // Calculate total sisa for Jumlah row
                            $totalSisaVal = max(0, array_sum($runningSisa));
                            $totalMasukVal = 0;
                            foreach ($sisaBLEntries as $sbl) {
                                $totalMasukVal += (int) ($sbl['masuk'] ?? 0);
                            }
                            $totalKeluarVal = count($rows) + $totalRusak;
                        @endphp
                        @foreach ($flatRows as $fi => $flatRow)
                                @if ($needsPageBreak)
                                    @php
                                        $pageNumber++;
                                        $pageRows = 24;
                                        $pageIndex = 0;
                                        $needsPageBreak = false;
                                        $prevPageSisa = $totalSisaVal;
                                        $prevPageMasuk = $runningMasuk;
                                        $prevPageKeluar = $runningKeluar;
                                    @endphp
                            </tbody></table>
                            <div style="page-break-before: always;"></div>
                            @include('reports.partials.buku-stok-header', [
                                'bulan' => strtoupper($bulanName),
                                'tahun' => $tahunName,
                                'model' => 'NA',
                                'pageDisplay' => $bukuStokPageStart + $pageNumber - 1,
                            ])
                            <table class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">NA, RA, atau DN</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                                    </tr>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Model</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Seri/Nomor</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                                    </tr>
                                </thead>
                                <tbody style="font-size: 10px;">
                                    {{-- Jumlah Pindahan dari halaman sebelumnya row (top of new page) --}}
                                <tr>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah Pindahan dari halaman sebelumnya</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $prevPageMasuk }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $prevPageKeluar }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $prevPageSisa }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                </tr>
                            @endif
                            @if ($flatRow['type'] === 'sbl')
                                @php
                                    $sblEntry = $flatRow['data'];
                                    $sblMasuk = (int) ($sblEntry['masuk'] ?? 0);
                                    $sblKeluar = (int) ($sblEntry['keluar'] ?? 0);
                                    $sblSisa = max(0, $sblMasuk - $sblKeluar);
                                    $sblDari = preg_replace('/^JT\s*/i', '', trim((string) ($sblEntry['seri_dari'] ?? '')));
                                    $sblSampai = preg_replace('/^JT\s*/i', '', trim((string) ($sblEntry['seri_sampai'] ?? '')));
                                    $sblSeriDisplay = $sblDari ? 'JT '.$sblDari.($sblSampai && $sblSampai !== $sblDari ? ' - '.$sblSampai : '') : '';
                                @endphp
                                @php
                                    $sblDate = '01/'.str_pad((string) ($dataset['filter_month'] ?? ''), 2, '0', STR_PAD_LEFT).'/'.($dataset['filter_year'] ?? '');
                                    if (!empty($dataset['filter_date_from'])) {
                                        try { $sblDate = \Carbon\Carbon::parse($dataset['filter_date_from'])->format('d/m/Y'); } catch (\Exception $e) {}
                                    }
                                @endphp
                                <tr>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $rowNum++ }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblDate }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >{{ $sblEntry['uraian'] !== '' && $sblEntry['uraian'] !== null ? $sblEntry['uraian'] : 'Sisa bulan lalu' }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblEntry['masuk'] ?? '' }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblEntry['keluar'] ?? '' }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblSisa }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >NA</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblSeriDisplay }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >Buku</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblEntry['penerimaan'] ?? '' }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $sblEntry['pengeluaran'] ?? '' }}</td>
                                </tr>
                                @php
                                    $pageIndex++;
                                    $runningMasuk += $sblMasuk;
                                    $runningKeluar += $sblKeluar;
                                    if (($pageIndex >= $pageRows && $fi < $flatCount - 1) || ($fi === $flatCount - 1 && $pageIndex > $extraPageThreshold && $pageIndex <= 25)) {
                                        $totalSisaVal = max(0, array_sum($sisaState));
                                        $prevPageSisa = $totalSisaVal;
                                        $needsPageBreak = true;
                                    }
                                @endphp
                                @if ($needsPageBreak)
                                    {{-- Jumlah Dipindahkan row (bottom of current page) --}}
                                    <tr>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah Dipindahkan</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalSisaVal }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    </tr>
                                @endif
                            @elseif ($flatRow['type'] === 'bd')
                                @php
                                    $bukanDuplikat = $flatRow['bukanDuplikat'];
                                    $keluarBD = $flatRow['keluarBD'];
                                    $sisaBD = $flatRow['sisaBD'];
                                    $porforasiNums = array_map(function ($r) {
                                        $p = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($r['Nomor Perforasi'] ?? ''))));
                                        return (int) $p;
                                    }, $bukanDuplikat);
                                    $porforasiNums = array_filter($porforasiNums);
                                    $seriRange = $porforasiNums ? 'JT '.min($porforasiNums).' - '.max($porforasiNums) : '';
                                    $aktaList = array_filter(array_map(fn ($r) => $r['_nomor_akta'] ?? null, $bukanDuplikat));
                                    $aktaNums = array_map(fn ($a) => (int) $a, $aktaList);
                                    $aktaMin = $aktaNums ? min($aktaNums) : null;
                                    $aktaMax = $aktaNums ? max($aktaNums) : null;
                                    $aktaRange = $aktaMin !== null ? ($aktaMin === $aktaMax ? (string) $aktaMin : $aktaMin.' - '.$aktaMax) : '';
                                    $uraian = '';
                                    $names = [];
                                    foreach ($bukanDuplikat as $bd) {
                                        $n = trim($bd['Nama Catin'] ?? '');
                                        if ($n !== '' && ! in_array($n, $names)) {
                                            $names[] = $n;
                                        }
                                    }
                                    $uraian = count($names) > 1 ? $names[0].' Cs.' : ($names[0] ?? '');
                                @endphp
                                <tr>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $rowNum++ }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >{{ $flatRow['date'] }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >{{ $uraian }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $keluarBD ?: '' }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $sisaBD < 0 ? 0 : $sisaBD }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >NA</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $seriRange }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >Buku</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $aktaRange }}</td>
                                </tr>
                                @php
                                    $pageIndex++;
                                    $runningKeluar += $keluarBD;
                                    $sisaState[$flatRow['pf']] = $sisaBD;
                                    if (($pageIndex >= $pageRows && $fi < $flatCount - 1) || ($fi === $flatCount - 1 && $pageIndex > $extraPageThreshold && $pageIndex <= 25)) {
                                        $totalSisaVal = max(0, array_sum($sisaState));
                                        $prevPageSisa = $totalSisaVal;
                                        $needsPageBreak = true;
                                    }
                                @endphp
                                @if ($needsPageBreak)
                                    {{-- Jumlah Dipindahkan row --}}
                                    <tr>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah Dipindahkan</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalSisaVal }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    </tr>
                                @endif
                            @elseif ($flatRow['type'] === 'd')
                                @php
                                    $duplikat = $flatRow['duplikat'];
                                    $keluarD = $flatRow['keluarD'];
                                    $sisaD = $flatRow['sisaD'];
                                    $dupliPorforasiNums = array_map(function ($r) {
                                        $p = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($r['Nomor Perforasi'] ?? ''))));
                                        return (int) $p;
                                    }, $duplikat);
                                    $dupliPorforasiNums = array_filter($dupliPorforasiNums);
                                    $dupliSeriRange = '';
                                    if (count($dupliPorforasiNums) === 1) {
                                        $dupliSeriRange = 'JT '.reset($dupliPorforasiNums);
                                    } elseif (count($dupliPorforasiNums) > 1) {
                                        $dupliSeriRange = 'JT '.min($dupliPorforasiNums).' - '.max($dupliPorforasiNums);
                                    }
                                @endphp
                                <tr>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $rowNum++ }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >{{ $flatRow['date'] }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >Duplikat</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $keluarD }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $sisaD < 0 ? 0 : $sisaD }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >NA</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $dupliSeriRange }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >Buku</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >                                    </td>
                                </tr>
                                @php
                                    $pageIndex++;
                                    $runningKeluar += $keluarD;
                                    $sisaState[$flatRow['pf']] = $sisaD;
                                    if (($pageIndex >= $pageRows && $fi < $flatCount - 1) || ($fi === $flatCount - 1 && $pageIndex > $extraPageThreshold && $pageIndex <= 25)) {
                                        $totalSisaVal = max(0, array_sum($sisaState));
                                        $prevPageSisa = $totalSisaVal;
                                        $needsPageBreak = true;
                                    }
                                @endphp
                                @if ($needsPageBreak)
                                    {{-- Jumlah Dipindahkan row --}}
                                    <tr>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah Dipindahkan</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalSisaVal }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    </tr>
                                @endif
                            @elseif ($flatRow['type'] === 'rusak')
                                @php
                                    $missingNums = $flatRow['missing'];
                                    $keluarRusak = $flatRow['keluarRusak'];
                                    $sisaRusak = $flatRow['sisaRusak'];
                                    // Gabung nomor berurut jadi rentang, mis. "JT 116741470 - 116741471".
                                    $rusakRuns = [];
                                    $runStart = $missingNums[0];
                                    $runPrev = $missingNums[0];
                                    for ($mi = 1; $mi < count($missingNums); $mi++) {
                                        $m = $missingNums[$mi];
                                        if ($m === $runPrev + 1) {
                                            $runPrev = $m;
                                            continue;
                                        }
                                        $rusakRuns[] = $runStart === $runPrev ? 'JT '.$runStart : 'JT '.$runStart.' - '.$runPrev;
                                        $runStart = $m;
                                        $runPrev = $m;
                                    }
                                    $rusakRuns[] = $runStart === $runPrev ? 'JT '.$runStart : 'JT '.$runStart.' - '.$runPrev;
                                    $rusakSeriRange = implode("\n", $rusakRuns);
                                @endphp
                                <tr>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $rowNum++ }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >{{ $flatRow['date'] }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5" >Rusak</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >{{ $keluarRusak }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $sisaRusak < 0 ? 0 : $sisaRusak }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >NA</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" style="white-space:pre-line;">{{ $rusakSeriRange }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" >Buku</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                </tr>
                                @php
                                    $pageIndex++;
                                    $runningKeluar += $keluarRusak;
                                    $sisaState[$flatRow['pf']] = $sisaRusak;
                                    if (($pageIndex >= $pageRows && $fi < $flatCount - 1) || ($fi === $flatCount - 1 && $pageIndex > $extraPageThreshold && $pageIndex <= 25)) {
                                        $totalSisaVal = max(0, array_sum($sisaState));
                                        $prevPageSisa = $totalSisaVal;
                                        $needsPageBreak = true;
                                    }
                                @endphp
                                @if ($needsPageBreak)
                                    {{-- Jumlah Dipindahkan row --}}
                                    <tr>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah Dipindahkan</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $runningKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalSisaVal }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    </tr>
                                @endif
                            @endif
                        @endforeach
                        {{-- Buka halaman baru jika break terjadi di baris terakhir (data 18-28 baris) --}}
                        @if ($needsPageBreak)
                            @php
                                $pageNumber++;
                                $pageRows = 24;
                                $pageIndex = 0;
                                $needsPageBreak = false;
                                $prevPageSisa = $totalSisaVal;
                                $prevPageMasuk = $runningMasuk;
                                $prevPageKeluar = $runningKeluar;
                            @endphp
                            </tbody></table>
                            <div style="page-break-before: always;"></div>
                            @include('reports.partials.buku-stok-header', [
                                'bulan' => strtoupper($bulanName),
                                'tahun' => $tahunName,
                                'model' => 'NA',
                                'pageDisplay' => $bukuStokPageStart + $pageNumber - 1,
                            ])
                            <table class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">NA, RA, atau DN</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                                    </tr>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Model</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Seri/Nomor</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                                    </tr>
                                </thead>
                                <tbody style="font-size: 10px;">
                                    {{-- Jumlah Pindahan dari halaman sebelumnya row (top of new page) --}}
                                    <tr>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah Pindahan dari halaman sebelumnya</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $prevPageMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $prevPageKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $prevPageSisa }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                                    </tr>
                        @endif
                        {{-- Final Jumlah row (always at the end) --}}
                        @php
                            $totalSisaVal = max(0, array_sum($sisaState));
                            $totalMasuk = 0;
                            foreach ($sisaBLEntries as $sbl) {
                                $totalMasuk += (int) ($sbl['masuk'] ?? 0);
                            }
                            $totalKeluar = count($rows) + $totalRusak;
                            $lastKeluarPerPrefix = [];
                            foreach ($grouped as $dateKey => $dateRows) {
                                foreach ($dateRows as $r) {
                                    $p = preg_replace('/\s*-\s*\d+$/', '', preg_replace('/^JT\s*/i', '', trim((string) ($r['Nomor Perforasi'] ?? ''))));
                                    $pf = substr((string) $p, 0, $prefixLength);
                                    $num = (int) $p;
                                    if (! isset($lastKeluarPerPrefix[$pf]) || $num > $lastKeluarPerPrefix[$pf]) {
                                        $lastKeluarPerPrefix[$pf] = $num;
                                    }
                                }
                            }
                            $remainingRanges = [];
                            foreach ($sisaBLEntries as $sbl) {
                                $sblSeriDari = preg_replace('/^JT\s*/i', '', trim((string) ($sbl['seri_dari'] ?? '')));
                                $sblSeriSampai = preg_replace('/^JT\s*/i', '', trim((string) ($sbl['seri_sampai'] ?? '')));
                                $pf = substr($sblSeriDari, 0, $prefixLength);
                                $lastKeluar = $lastKeluarPerPrefix[$pf] ?? 0;
                                if ($lastKeluar > 0 && $sblSeriSampai !== '') {
                                    $nextNum = $lastKeluar + 1;
                                    if ((int) $nextNum <= (int) $sblSeriSampai) {
                                        if ((int) $nextNum === (int) $sblSeriSampai) {
                                            $remainingRanges[] = 'JT '.$nextNum;
                                        } else {
                                            $remainingRanges[] = 'JT '.$nextNum.' - '.$sblSeriSampai;
                                        }
                                    }
                                } elseif ($sblSeriSampai !== '' && $sblSeriDari !== '') {
                                    if ((int) $sblSeriDari === (int) $sblSeriSampai) {
                                        $remainingRanges[] = 'JT '.$sblSeriDari;
                                    } else {
                                        $remainingRanges[] = 'JT '.$sblSeriDari.' - '.$sblSeriSampai;
                                    }
                                }
                            }
                        @endphp
                        <tr>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                            <td class="border border-gray-700 px-1 py-0.5" ></td>
                            <td class="border border-gray-700 px-1 py-0.5" style="font-weight:bold;">Jumlah</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalMasukVal ?: '' }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalKeluarVal }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">{{ $totalSisaVal }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">NA</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold; white-space:pre-line;">{{ implode("\n", $remainingRanges) }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" style="font-weight:bold;">Buku</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center" ></td>
                        </tr>
                    @elseif ($isNtcrReport)
                        @php
                            $ntcrManualData = $dataset['config_json']['manual_data'] ?? [];
                            $ntcrManualMap = [];
                            foreach ($ntcrManualData as $md) {
                                $ntcrManualMap[$md['bulan'] ?? ''] = $md;
                            }
                        @endphp
                        @foreach ($dataset['rows'] as $rowIndex => $row)
                            @php $isTotal = ($row['Bulan'] ?? '') === 'TOTAL'; @endphp
                            <tr @if($isTotal)style="font-weight: bold;"@endif>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $isTotal ? '' : $rowIndex + 1 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Bulan'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['LK'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['K'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Nikah'] ?? 0 }}</td>
                                @php
                                    $bulanName = $row['Bulan'] ?? '';
                                    $md = $ntcrManualMap[$bulanName] ?? [];
                                @endphp
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Talak'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Cerai'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Rujuk'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Pdk_K'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Pdk_LK'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Pdk_Jml'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R1'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R2'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R3'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R4'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R5'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R6'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R7'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R8'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R9'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R10'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R11'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['R12'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Gagal'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Tunda'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Miskin'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $md['Bencana Alam'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Jumlah'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['Setor'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    @elseif ($isNbReport)
                        @php
                            $nbRowCount = count($dataset['rows']);
                            $pageIndex = 0;
                            $pageRows = 25;
                            $closureReason = $dataset['config_json']['closure_reason'] ?? 'akhir_bulan';
                            $extraPageThreshold = in_array($closureReason, ['monev', 'ganti_kepala']) ? 8 : 15;
                            $pageNumber = 1;
                            $prevPageMasuk = 0;
                            $prevPageKeluar = 0;
                            $prevPageSisa = '';
                            $needsPageBreak = false;
                            $runningMasuk = 0;
                            $runningKeluar = 0;
                            $runningSisa = '';
                        @endphp
                        @foreach ($dataset['rows'] as $ri => $row)
                            @if ($needsPageBreak)
                                @php
                                    $pageNumber++;
                                    $pageRows = 24;
                                    $pageIndex = 0;
                                    $needsPageBreak = false;
                                    $prevPageMasuk = $runningMasuk;
                                    $prevPageKeluar = $runningKeluar;
                                    $prevPageSisa = $runningSisa;
                                @endphp
                                </tbody></table>
                                <div style="page-break-before: always;"></div>
                                @include('reports.partials.buku-stok-header', [
                                    'bulan' => strtoupper($monthNames[(int) ($dataset['filter_month'] ?? 1)] ?? ''),
                                    'tahun' => $dataset['filter_year'] ?? $tahunName,
                                    'model' => 'NB',
                                    'bookTitle' => 'BUKU STOK UMUM',
                                    'bsModel' => 'BS 2',
                                    'pageDisplay' => $bukuStokPageStart + $pageNumber - 1,
                                ])
                                <table class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                                            <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                                            <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                                        </tr>
                                        <tr class="bg-gray-100">
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 10px;">
                                        {{-- Jumlah Pindahan dari halaman sebelumnya row (top of new page) --}}
                                        <tr style="font-weight: bold;">
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            <td class="border border-gray-700 px-1 py-0.5">Jumlah Pindahan dari halaman sebelumnya</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageMasuk }}</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageKeluar }}</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageSisa }}</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        </tr>
                            @endif
                            <tr>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['no'] ?? '' }}</td>
                                @if (($row['is_manual'] ?? false) && ! ($row['is_sisa_bulan_lalu'] ?? false) && ! empty($row['tanggal']))
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['tanggal'] }}</td>
                                @elseif ($row['is_manual'] ?? false)
                                    @php
                                        $sblDate = '01/'.str_pad((string) ($dataset['filter_month'] ?? ''), 2, '0', STR_PAD_LEFT).'/'.($dataset['filter_year'] ?? '');
                                        if (!empty($dataset['filter_date_from'])) {
                                            try { $sblDate = \Carbon\Carbon::parse($dataset['filter_date_from'])->format('d/m/Y'); } catch (\Exception $e) {}
                                        }
                                    @endphp
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $sblDate }}</td>
                                @else
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['tanggal'] ?? '' }}</td>
                                @endif
                                <td class="border border-gray-700 px-1 py-0.5">{{ $row['uraian'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['masuk'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['keluar'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['sisa'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['penerimaan'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['pengeluaran'] ?? '' }}</td>
                            </tr>
                            @php
                                $pageIndex++;
                                $runningMasuk += (int) ($row['masuk'] ?? 0);
                                $runningKeluar += (int) ($row['keluar'] ?? 0);
                                if (isset($row['sisa']) && $row['sisa'] !== '') {
                                    $runningSisa = $row['sisa'];
                                }
                                if (($pageIndex >= $pageRows && $ri < $nbRowCount - 1) || ($ri === $nbRowCount - 1 && $pageIndex > $extraPageThreshold && $pageIndex <= 25)) {
                                    $needsPageBreak = true;
                                }
                            @endphp
                            @if ($needsPageBreak)
                                {{-- Jumlah Dipindahkan row (bottom of current page) --}}
                                <tr style="font-weight: bold;">
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    <td class="border border-gray-700 px-1 py-0.5">Jumlah Dipindahkan</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $runningMasuk }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $runningKeluar }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $runningSisa }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                </tr>
                            @endif
                        @endforeach
                        {{-- Buka halaman baru jika break terjadi di baris terakhir (data 18-28 baris) --}}
                        @if ($needsPageBreak)
                            @php
                                $pageNumber++;
                                $pageRows = 24;
                                $pageIndex = 0;
                                $needsPageBreak = false;
                                $prevPageMasuk = $runningMasuk;
                                $prevPageKeluar = $runningKeluar;
                                $prevPageSisa = $runningSisa;
                            @endphp
                            </tbody></table>
                            <div style="page-break-before: always;"></div>
                            @include('reports.partials.buku-stok-header', [
                                'bulan' => strtoupper($monthNames[(int) ($dataset['filter_month'] ?? 1)] ?? ''),
                                'tahun' => $dataset['filter_year'] ?? $tahunName,
                                'model' => 'NB',
                                'bookTitle' => 'BUKU STOK UMUM',
                                'bsModel' => 'BS 2',
                                'pageDisplay' => $bukuStokPageStart + $pageNumber - 1,
                            ])
                            <table class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                                    </tr>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                                    </tr>
                                </thead>
                                <tbody style="font-size: 10px;">
                                    {{-- Jumlah Pindahan dari halaman sebelumnya row (top of new page) --}}
                                    <tr style="font-weight: bold;">
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        <td class="border border-gray-700 px-1 py-0.5">Jumlah Pindahan dari halaman sebelumnya</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageSisa }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    </tr>
                        @endif
                        @php
                            $totalMasuk = 0;
                            $totalKeluar = 0;
                            $lastSisa = '';
                            foreach ($dataset['rows'] as $r) {
                                $totalMasuk += (int) ($r['masuk'] ?? 0);
                                $totalKeluar += (int) ($r['keluar'] ?? 0);
                                if (isset($r['sisa']) && $r['sisa'] !== '') {
                                    $lastSisa = $r['sisa'];
                                }
                            }
                        @endphp
                        <tr style="font-weight: bold;">
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5">Jumlah</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $totalMasuk }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $totalKeluar }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $lastSisa }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                        </tr>
                    @elseif ($isNReport)
                        @php
                            $nRowCount = count($dataset['rows']);
                            $pageIndex = 0;
                            $pageRows = 25;
                            $closureReason = $dataset['config_json']['closure_reason'] ?? 'akhir_bulan';
                            $extraPageThreshold = in_array($closureReason, ['monev', 'ganti_kepala']) ? 8 : 15;
                            $pageNumber = 1;
                            $prevPageMasuk = 0;
                            $prevPageKeluar = 0;
                            $prevPageSisa = '';
                            $needsPageBreak = false;
                            $runningMasuk = 0;
                            $runningKeluar = 0;
                            $runningSisa = '';
                        @endphp
                        @foreach ($dataset['rows'] as $ri => $row)
                            @if ($needsPageBreak)
                                @php
                                    $pageNumber++;
                                    $pageRows = 24;
                                    $pageIndex = 0;
                                    $needsPageBreak = false;
                                    $prevPageMasuk = $runningMasuk;
                                    $prevPageKeluar = $runningKeluar;
                                    $prevPageSisa = $runningSisa;
                                @endphp
                                </tbody></table>
                                <div style="page-break-before: always;"></div>
                                @include('reports.partials.buku-stok-header', [
                                    'bulan' => strtoupper($monthNames[(int) ($dataset['filter_month'] ?? 1)] ?? ''),
                                    'tahun' => $dataset['filter_year'] ?? $tahunName,
                                    'model' => 'N Lembar',
                                    'bookTitle' => 'BUKU STOK KHUSUS',
                                    'bsModel' => 'BS 2',
                                    'pageDisplay' => $bukuStokPageStart + $pageNumber - 1,
                                ])
                                <table class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                                            <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                                            <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                                            <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                                        </tr>
                                        <tr class="bg-gray-100">
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                                            <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 10px;">
                                        {{-- Jumlah Pindahan dari halaman sebelumnya row (top of new page) --}}
                                        <tr style="font-weight: bold;">
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            <td class="border border-gray-700 px-1 py-0.5">Jumlah Pindahan dari halaman sebelumnya</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageMasuk }}</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageKeluar }}</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageSisa }}</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        </tr>
                            @endif
                            <tr>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['no'] ?? '' }}</td>
                                @if (($row['is_manual'] ?? false) && ! ($row['is_sisa_bulan_lalu'] ?? false) && ! empty($row['tanggal']))
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['tanggal'] }}</td>
                                @elseif ($row['is_manual'] ?? false)
                                    @php
                                        $sblDate = '01/'.str_pad((string) ($dataset['filter_month'] ?? ''), 2, '0', STR_PAD_LEFT).'/'.($dataset['filter_year'] ?? '');
                                        if (!empty($dataset['filter_date_from'])) {
                                            try { $sblDate = \Carbon\Carbon::parse($dataset['filter_date_from'])->format('d/m/Y'); } catch (\Exception $e) {}
                                        }
                                    @endphp
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $sblDate }}</td>
                                @else
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['tanggal'] ?? '' }}</td>
                                @endif
                                <td class="border border-gray-700 px-1 py-0.5">{{ $row['uraian'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['masuk'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['keluar'] ?? 0 }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['sisa'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['penerimaan'] ?? '' }}</td>
                                <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $row['pengeluaran'] ?? '' }}</td>
                            </tr>
                            @php
                                $pageIndex++;
                                $runningMasuk += (int) ($row['masuk'] ?? 0);
                                $runningKeluar += (int) ($row['keluar'] ?? 0);
                                if (isset($row['sisa']) && $row['sisa'] !== '') {
                                    $runningSisa = $row['sisa'];
                                }
                                if (($pageIndex >= $pageRows && $ri < $nRowCount - 1) || ($ri === $nRowCount - 1 && $pageIndex > $extraPageThreshold && $pageIndex <= 25)) {
                                    $needsPageBreak = true;
                                }
                            @endphp
                            @if ($needsPageBreak)
                                {{-- Jumlah Dipindahkan row (bottom of current page) --}}
                                <tr style="font-weight: bold;">
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    <td class="border border-gray-700 px-1 py-0.5">Jumlah Dipindahkan</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $runningMasuk }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $runningKeluar }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $runningSisa }}</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                </tr>
                            @endif
                        @endforeach
                        {{-- Buka halaman baru jika break terjadi di baris terakhir (data 16-25 baris) --}}
                        @if ($needsPageBreak)
                            @php
                                $pageNumber++;
                                $pageRows = 24;
                                $pageIndex = 0;
                                $needsPageBreak = false;
                                $prevPageMasuk = $runningMasuk;
                                $prevPageKeluar = $runningKeluar;
                                $prevPageSisa = $runningSisa;
                            @endphp
                            </tbody></table>
                            <div style="page-break-before: always;"></div>
                            @include('reports.partials.buku-stok-header', [
                                'bulan' => strtoupper($monthNames[(int) ($dataset['filter_month'] ?? 1)] ?? ''),
                                'tahun' => $dataset['filter_year'] ?? $tahunName,
                                'model' => 'N Lembar',
                                'bookTitle' => 'BUKU STOK KHUSUS',
                                'bsModel' => 'BS 2',
                                'pageDisplay' => $bukuStokPageStart + $pageNumber - 1,
                            ])
                            <table class="w-full border-collapse border border-gray-700" style="font-size: 12px;">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">No</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Tanggal</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Uraian</th>
                                        <th colspan="3" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Banyaknya</th>
                                        <th rowspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Satuan</th>
                                        <th colspan="2" class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Nomor Bukti</th>
                                    </tr>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Masuk</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Keluar</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Sisa</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Penerimaan</th>
                                        <th class="border border-gray-700 px-1 py-0.5 text-center font-semibold">Pengeluaran</th>
                                    </tr>
                                </thead>
                                <tbody style="font-size: 10px;">
                                    {{-- Jumlah Pindahan dari halaman sebelumnya row (top of new page) --}}
                                    <tr style="font-weight: bold;">
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        <td class="border border-gray-700 px-1 py-0.5">Jumlah Pindahan dari halaman sebelumnya</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageMasuk }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageKeluar }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $prevPageSisa }}</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                    </tr>
                        @endif
                        @php
                            $totalMasuk = 0;
                            $totalKeluar = 0;
                            $lastSisa = '';
                            foreach ($dataset['rows'] as $r) {
                                $totalMasuk += (int) ($r['masuk'] ?? 0);
                                $totalKeluar += (int) ($r['keluar'] ?? 0);
                                if (isset($r['sisa']) && $r['sisa'] !== '') {
                                    $lastSisa = $r['sisa'];
                                }
                            }
                        @endphp
                        <tr style="font-weight: bold;">
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5">Jumlah</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $totalMasuk }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $totalKeluar }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">{{ $lastSisa }}</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center">Lembar</td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                            <td class="border border-gray-700 px-1 py-0.5 text-center"></td>
                        </tr>
                    @else
                        @foreach ($dataset['rows'] as $rowIndex => $row)
                            <tr>
                                @foreach ($columns as $col)
                                    @if ($col['type'] === 'row_number')
                                        <td @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $rowIndex + 1 }}</td>
                                    @elseif ($col['type'] === 'group')
                                        @foreach ($col['children'] ?? [] as $child)
                                            @if ($child['type'] === 'group')
                                                @foreach ($child['children'] ?? [] as $grandchild)
                                                    @php
                                                        $compositeKey = ($child['label'] ?? '') . '|' . ($grandchild['label'] ?? '');
                                                        $value = $row[$compositeKey] ?? $row[$grandchild['label']] ?? $row[$grandchild['field'] ?? ''] ?? '';
                                                    @endphp
                                                    <td @if(!empty($grandchild['width']))style="max-width:{{ $grandchild['width'] }};width:{{ $grandchild['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                                @endforeach
                                            @elseif ($child['type'] === 'aggregate_count' || $child['type'] === 'aggregate_total' || $child['type'] === 'aggregate_range')
                                                @php
                                                    $compositeKey = ($col['label'] ?? '') . '|' . ($child['label'] ?? '');
                                                    $value = $row[$compositeKey] ?? $row[$child['label']] ?? '';
                                                @endphp
                                                <td @if(!empty($child['width']))style="max-width:{{ $child['width'] }};width:{{ $child['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                            @else
                                                @php
                                                    $cellData = $getDataCells($child);
                                                    $value = $row[$cellData[0]] ?? '';
                                                    if ($cellData[1] !== null) {
                                                        $value = $applyTransform($value, $cellData[1]);
                                                    }
                                                @endphp
                                                <td @if(!empty($child['width']))style="max-width:{{ $child['width'] }};width:{{ $child['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 @if(($child['align'] ?? '') === 'center')text-center @endif">{{ $value }}</td>
                                            @endif
                                        @endforeach
                                    @elseif ($col['type'] === 'field')
                                        @php
                                            $cellData = $getDataCells($col);
                                            $value = $row[$cellData[0]] ?? '';
                                            if ($cellData[1] !== null) {
                                                $value = $applyTransform($value, $cellData[1]);
                                            }
                                        @endphp
                                        <td @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                    @elseif ($col['type'] === 'aggregate_total' || $col['type'] === 'aggregate_count' || $col['type'] === 'aggregate_range')
                                        @php
                                            $value = $row[$col['label']] ?? '';
                                        @endphp
                                        <td @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
            @if ($isL2Report)
                        <tr style="font-weight: bold;">
                            @foreach ($columns as $col)
                                @if ($col['type'] === 'row_number')
                                    <td @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                @elseif ($col['type'] === 'group')
                                    @foreach ($col['children'] ?? [] as $child)
                                        @if ($child['type'] === 'group')
                                            @foreach ($child['children'] ?? [] as $grandchild)
                                                @php
                                                    $compositeKey = ($child['label'] ?? '') . '|' . ($grandchild['label'] ?? '');
                                                    $value = $totalRow[$compositeKey] ?? 0;
                                                @endphp
                                                <td @if(!empty($grandchild['width']))style="max-width:{{ $grandchild['width'] }};width:{{ $grandchild['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                            @endforeach
                                        @elseif ($child['type'] === 'aggregate_count' || $child['type'] === 'aggregate_total' || $child['type'] === 'aggregate_range')
                                            @php
                                                $compositeKey = ($col['label'] ?? '') . '|' . ($child['label'] ?? '');
                                                $value = $totalRow[$compositeKey] ?? 0;
                                            @endphp
                                            <td @if(!empty($child['width']))style="max-width:{{ $child['width'] }};width:{{ $child['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                        @else
                                            <td @if(!empty($child['width']))style="max-width:{{ $child['width'] }};width:{{ $child['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center"></td>
                                        @endif
                                    @endforeach
                                @elseif ($col['type'] === 'field')
                                    <td @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">Jumlah</td>
                                @elseif ($col['type'] === 'aggregate_total' || $col['type'] === 'aggregate_count' || $col['type'] === 'aggregate_range')
                                    @php
                                        $value = $totalRow[$col['label']] ?? 0;
                                    @endphp
                                    <td @if(!empty($col['width']))style="max-width:{{ $col['width'] }};width:{{ $col['width'] }};"@endif class="border border-gray-700 px-1 py-0.5 text-center">{{ $value }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>

            @if ($isL2Report)
                <div class="mt-6 leading-relaxed" style="font-size: 12px; page-break-inside: avoid;">
                    <div class="flex justify-end">
                        <div class="text-center">
                            <p>{{ $dataset['kecamatan'] ?? '-' }}, {{ \Carbon\Carbon::now()->day . ' ' . $monthNames[\Carbon\Carbon::now()->month] . ' ' . \Carbon\Carbon::now()->year }}</p>
                            <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                            <div class="h-16"></div>
                            <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                            <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            @elseif ($isL4Report)
                <div class="mt-6 leading-relaxed" style="font-size: 12px; page-break-inside: avoid;">
                    <div class="flex justify-end">
                        <div class="text-center">
                            <p>{{ $dataset['kecamatan'] ?? '-' }}, {{ \Carbon\Carbon::now()->day . ' ' . $monthNames[\Carbon\Carbon::now()->month] . ' ' . \Carbon\Carbon::now()->year }}</p>
                            <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                            <div class="h-16"></div>
                            <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                            <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            @elseif ($isL3Report)
                <div class="mt-6 leading-relaxed" style="font-size: 12px; page-break-inside: avoid;">
                    <div class="flex justify-end">
                        <div class="text-center">
                            <p>{{ $dataset['kecamatan'] ?? '-' }}, {{ \Carbon\Carbon::now()->day . ' ' . $monthNames[\Carbon\Carbon::now()->month] . ' ' . \Carbon\Carbon::now()->year }}</p>
                            <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                            <div class="h-16"></div>
                            <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                            <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            @elseif ($isLaporanNA)
                <div class="mt-6 leading-relaxed" style="font-size: 12px; page-break-inside: avoid;">
                    @if (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'ganti_kepala')
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Khusus Model NA di tutup karena adanya Serah Terima Jabatan berdasarkan <strong>{{ $dataset['config_json']['ganti_kepala_sk'] ?? '' }}</strong> dengan sisa <strong>{{ $totalSisaVal }}</strong> buku.</p>
                    @elseif (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'monev')
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Khusus Model NA di tutup karena adanya Pemeriksaan berdasarkan ST nomor: <strong>{{ $dataset['config_json']['monev_st_number'] ?? '' }}</strong> dengan keadaan mengurus <strong>{{ $totalSisaVal }}</strong> buku.</p>
                    @else
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Khusus Model NA di tutup karena {{ $closureReasonText }} dengan keadaan mengurus <strong>{{ $totalSisaVal }}</strong> buku.</p>
                    @endif
                    @if (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'monev')
                        <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                            <div class="flex justify-between max-w-2xl mx-auto">
                                <div class="text-center">
                                    <p>&nbsp;</p>
                                    <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                                </div>
                                <div class="text-center">
                                    <p>Mengetahui,</p>
                                    <p>Kasi Bimas Islam</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kemenag'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_kemenag'] ?? '-' }}</p>
                                </div>
                            </div>
                            @php
                                $monevMembers = $dataset['config_json']['monev_members'] ?? [];
                                $monevHalf = ceil(count($monevMembers) / 2);
                                $monevLeft = array_slice($monevMembers, 0, $monevHalf);
                                $monevRight = array_slice($monevMembers, $monevHalf);
                            @endphp
                            <div class="mt-8 flex justify-between max-w-sm mx-auto">
                                <div class="flex gap-4">
                                    <p class="font-medium">Anggota:</p>
                                    <div>
                                        @foreach ($monevLeft as $i => $member)
                                            <p>{{ $i + 1 }}. {{ $member }}</p>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    @foreach ($monevRight as $i => $member)
                                        <p>{{ $i + 1 + count($monevLeft) }}. {{ $member }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'ganti_kepala')
                        <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                            <div class="flex justify-between max-w-2xl mx-auto">
                                <div class="text-center">
                                    <p>Yang menerima,</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                                </div>
                                <div class="text-center">
                                    <p>Yang menyerahkan,</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua_lama'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_lama'] ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="flex justify-center mt-8">
                                <div class="text-center">
                                    <p>Mengetahui,</p>
                                    <p>A.n. Kepala Kankemenag {{ $dataset['kota_kabupaten'] ?? '' }}</p>
                                    <p>Kasi Bimas Islam</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kemenag'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_kemenag'] ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-between max-w-2xl mx-auto mt-6">
                            <div class="text-center">
                                <p>Mengetahui,</p>
                                <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                                <div class="h-16"></div>
                                <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                            </div>
                            <div class="text-center">
                                <br>
                                <p>Petugas Stok</p>
                                <div class="h-16"></div>
                                <p class="font-semibold">{{ $dataset['nama_petugas_stok'] ?? '-' }}</p>
                                <p>NIP {{ $dataset['nip_petugas_stok'] ?? '-' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($isL1Report)
                <div class="mt-4 flex justify-end" style="font-size: 12px; page-break-inside: avoid;">
                    <div class="text-center">
                        <p>{{ $dataset['kecamatan'] ?? '-' }}, {{ \Carbon\Carbon::now()->day . ' ' . $monthNames[\Carbon\Carbon::now()->month] . ' ' . \Carbon\Carbon::now()->year }}</p>
                        <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                        <div class="h-16"></div>
                        <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                        <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                    </div>
                </div>
            @elseif ($isNtcrReport)
                <div class="mt-4 flex justify-end" style="font-size: 12px; page-break-inside: avoid;">
                    <div class="text-center">
                        <p>{{ $dataset['kecamatan'] ?? '-' }}, {{ \Carbon\Carbon::now()->day . ' ' . $monthNames[\Carbon\Carbon::now()->month] . ' ' . \Carbon\Carbon::now()->year }}</p>
                        <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                        <div class="h-16"></div>
                        <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                        <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                    </div>
                </div>
            @elseif ($isNbReport)
                @php
                    $lastSisa = '';
                    foreach ($dataset['rows'] as $r) {
                        if (isset($r['sisa']) && $r['sisa'] !== '') {
                            $lastSisa = $r['sisa'];
                        }
                    }
                @endphp
                <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                    @if (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'ganti_kepala')
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Umum Model NB di tutup karena adanya Serah Terima Jabatan berdasarkan <strong>{{ $dataset['config_json']['ganti_kepala_sk'] ?? '' }}</strong> dengan sisa <strong>{{ $lastSisa }}</strong> lembar.</p>
                    @elseif (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'monev')
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Umum Model NB di tutup karena adanya Pemeriksaan berdasarkan ST nomor: <strong>{{ $dataset['config_json']['monev_st_number'] ?? '' }}</strong> dengan keadaan mengurus <strong>{{ $lastSisa }}</strong> lembar.</p>
                    @else
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Umum Model NB di tutup karena {{ $closureReasonText }} dengan keadaan mengurus <strong>{{ $lastSisa }}</strong> lembar.</p>
                    @endif
                    @if (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'monev')
                        <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                            <div class="flex justify-between max-w-2xl mx-auto">
                                <div class="text-center">
                                    <p>&nbsp;</p>
                                    <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                                </div>
                                <div class="text-center">
                                    <p>Mengetahui,</p>
                                    <p>Kasi Bimas Islam</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kemenag'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_kemenag'] ?? '-' }}</p>
                                </div>
                            </div>
                            @php
                                $monevMembers = $dataset['config_json']['monev_members'] ?? [];
                                $monevHalf = ceil(count($monevMembers) / 2);
                                $monevLeft = array_slice($monevMembers, 0, $monevHalf);
                                $monevRight = array_slice($monevMembers, $monevHalf);
                            @endphp
                            <div class="mt-8 flex justify-between max-w-sm mx-auto">
                                <div class="flex gap-4">
                                    <p class="font-medium">Anggota:</p>
                                    <div>
                                        @foreach ($monevLeft as $i => $member)
                                            <p>{{ $i + 1 }}. {{ $member }}</p>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    @foreach ($monevRight as $i => $member)
                                        <p>{{ $i + 1 + count($monevLeft) }}. {{ $member }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'ganti_kepala')
                        <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                            <div class="flex justify-between max-w-2xl mx-auto">
                                <div class="text-center">
                                    <p>Yang menerima,</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                                </div>
                                <div class="text-center">
                                    <p>Yang menyerahkan,</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua_lama'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_lama'] ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="flex justify-center mt-8">
                                <div class="text-center">
                                    <p>Mengetahui,</p>
                                    <p>A.n. Kepala Kankemenag {{ $dataset['kota_kabupaten'] ?? '' }}</p>
                                    <p>Kasi Bimas Islam</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kemenag'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_kemenag'] ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-between" style="padding-left: 90px; padding-right: 90px;">
                            <div class="text-center">
                                <p>Mengetahui,</p>
                                <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                                <div class="h-16"></div>
                                <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                            </div>
                            <div class="text-center">
                                <br>
                                <p>Petugas Stok</p>
                                <div class="h-16"></div>
                                <p class="font-semibold">{{ $dataset['nama_petugas_stok'] ?? '-' }}</p>
                                <p>NIP {{ $dataset['nip_petugas_stok'] ?? '-' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($isNReport)
                @php
                    $lastSisa = '';
                    foreach ($dataset['rows'] as $r) {
                        if (isset($r['sisa']) && $r['sisa'] !== '') {
                            $lastSisa = $r['sisa'];
                        }
                    }
                @endphp
                <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                    @if (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'ganti_kepala')
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Khusus Model N Lembar di tutup karena adanya Serah Terima Jabatan berdasarkan <strong>{{ $dataset['config_json']['ganti_kepala_sk'] ?? '' }}</strong> dengan sisa <strong>{{ $lastSisa }}</strong> lembar.</p>
                    @elseif (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'monev')
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Khusus Model N Lembar di tutup karena adanya Pemeriksaan berdasarkan ST nomor: <strong>{{ $dataset['config_json']['monev_st_number'] ?? '' }}</strong> dengan keadaan mengurus <strong>{{ $lastSisa }}</strong> lembar.</p>
                    @else
                        <p class="mb-4">Pada hari ini <strong>{{ $hariName }}</strong> tanggal <strong>{{ $tanggalFormatted }}</strong> Buku Stok Khusus Model N Lembar di tutup karena {{ $closureReasonText }} dengan keadaan mengurus <strong>{{ $lastSisa }}</strong> lembar.</p>
                    @endif
                    @if (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'monev')
                        <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                            <div class="flex justify-between max-w-2xl mx-auto">
                                <div class="text-center">
                                    <p>&nbsp;</p>
                                    <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                                </div>
                                <div class="text-center">
                                    <p>Mengetahui,</p>
                                    <p>Kasi Bimas Islam</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kemenag'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_kemenag'] ?? '-' }}</p>
                                </div>
                            </div>
                            @php
                                $monevMembers = $dataset['config_json']['monev_members'] ?? [];
                                $monevHalf = ceil(count($monevMembers) / 2);
                                $monevLeft = array_slice($monevMembers, 0, $monevHalf);
                                $monevRight = array_slice($monevMembers, $monevHalf);
                            @endphp
                            <div class="mt-8 flex justify-between max-w-sm mx-auto">
                                <div class="flex gap-4">
                                    <p class="font-medium">Anggota:</p>
                                    <div>
                                        @foreach ($monevLeft as $i => $member)
                                            <p>{{ $i + 1 }}. {{ $member }}</p>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    @foreach ($monevRight as $i => $member)
                                        <p>{{ $i + 1 + count($monevLeft) }}. {{ $member }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif (($dataset['config_json']['closure_reason'] ?? 'akhir_bulan') === 'ganti_kepala')
                        <div class="mt-6" style="font-size: 12px; page-break-inside: avoid;">
                            <div class="flex justify-between max-w-2xl mx-auto">
                                <div class="text-center">
                                    <p>Yang menerima,</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                                </div>
                                <div class="text-center">
                                    <p>Yang menyerahkan,</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kua_lama'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_lama'] ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="flex justify-center mt-8">
                                <div class="text-center">
                                    <p>Mengetahui,</p>
                                    <p>A.n. Kepala Kankemenag {{ $dataset['kota_kabupaten'] ?? '' }}</p>
                                    <p>Kasi Bimas Islam</p>
                                    <div class="h-16"></div>
                                    <p class="font-semibold">{{ $dataset['nama_kepala_kemenag'] ?? '-' }}</p>
                                    <p>NIP {{ $dataset['nip_kepala_kemenag'] ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-between" style="padding-left: 90px; padding-right: 90px;">
                            <div class="text-center">
                                <p>Mengetahui,</p>
                                <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                                <div class="h-16"></div>
                                <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                                <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                            </div>
                            <div class="text-center">
                                <br>
                                <p>Petugas Stok</p>
                                <div class="h-16"></div>
                                <p class="font-semibold">{{ $dataset['nama_petugas_stok'] ?? '-' }}</p>
                                <p>NIP {{ $dataset['nip_petugas_stok'] ?? '-' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="mt-6 leading-relaxed" style="font-size: 12px; page-break-inside: avoid;">
                    <p>Pada hari ini <strong>{{ $hariName }}</strong>, tanggal <strong>{{ $tanggalFormatted }}</strong>, buku rekap pendaftaran di tutup dengan keadaan sebagai berikut :</p>
                    <p class="mt-2 ml-4"><span style="display:inline-block; width:25ch;">Jumlah Nikah Kantor</span> : <strong>{{ $countK }}</strong> N</p>
                    <p class="ml-4"><span style="display:inline-block; width:25ch;">Jumlah Nikah Luar Kantor</span> : <strong>{{ $countLK }}</strong> N</p>
                    <p class="ml-4"><span style="display:inline-block; width:25ch;">Jumlah Keseluruhan</span> : <strong>{{ $countAll }}</strong> N</p>

                    <div class="mt-6 flex justify-end">
                        <div class="text-center">
                            <p>{{ $dataset['kecamatan'] ?? '-' }}, {{ \Carbon\Carbon::now()->day . ' ' . $monthNames[\Carbon\Carbon::now()->month] . ' ' . \Carbon\Carbon::now()->year }}</p>
                            <p>Kepala KUA {{ $dataset['kecamatan'] ?? '' }}</p>
                            <div class="h-16"></div>
                            <p class="font-semibold">{{ $dataset['nama_kepala_kua'] ?? '-' }}</p>
                            <p>NIP {{ $dataset['nip_kepala'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="border-b-2 border-gray-900 pb-3 mb-4">
                <h1 class="text-2xl font-bold">{{ $dataset['title'] }}</h1>
                <p class="text-sm text-gray-500">Total {{ number_format($dataset['total']) }} baris &middot; Dibuat {{ $dataset['generated_at'] }}</p>
            </div>
            <table class="w-full text-sm border-collapse border border-gray-700">
                <thead>
                    <tr class="bg-gray-100">
                        @foreach ($dataset['headings'] as $heading)
                            <th class="border border-gray-700 px-1 py-0.5 text-left font-semibold">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dataset['rows'] as $row)
                        <tr>
                            @foreach ($dataset['headings'] as $heading)
                                <td class="border border-gray-700 px-1 py-0.5">{{ $row[$heading] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    </div>
</body>
</html>
