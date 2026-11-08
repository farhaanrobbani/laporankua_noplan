<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak NB{{ isset($report->title) ? ' — '.$report->title : '' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page { size: A4; margin: 10mm 18mm; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }

        @media screen {
            body { background: #e5e7eb; font-family: Arial, sans-serif; }
            .no-print {
                position: fixed; top: 0; left: 0; right: 0; z-index: 10;
                background: #fff; border-bottom: 1px solid #d1d5db;
                padding: 10px 20px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px;
            }
            .no-print a, .no-print button {
                padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none;
            }
            .no-print .nav-btn { background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; }
            .no-print .nav-btn:hover { background: #d1d5db; }
            .no-print .nav-btn:disabled { opacity: 0.4; cursor: not-allowed; }
            .no-print .print-btn { background: #2563eb; color: #fff; border: none; }
            .no-print .print-btn:hover { background: #1d4ed8; }
            .no-print .close-btn { background: #6b7280; color: #fff; border: none; }
            .no-print .close-btn:hover { background: #4b5563; }
            .no-print .record-info { font-size: 14px; color: #6b7280; }
            .page {
                max-width: 210mm; min-height: 297mm; margin: 60px auto 20px; background: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,.15); padding: 10mm 18mm; position: relative;
            }
        }

        @media screen and (max-width: 800px) {
            .page { margin: 60px 10px 20px; min-height: auto; padding: 10mm; overflow-x: auto; }
            .no-print .nav-btn, .no-print .print-btn, .no-print .close-btn { padding: 6px 10px; font-size: 12px; }
        }

        .field { position: absolute; }
        .field-label { font-weight: bold; font-size: 16px; }
        .field-value { font-size: 16px; }
    </style>
</head>
<body>
    @php
        $showNav = $showNav ?? false;
        $records = $records ?? ($recordData ? [$recordData] : []);
    @endphp

    <div class="no-print">
        <div class="flex gap-2">
            @if ($showNav)
                @if ($prevId !== null)
                    @if (($entryMode ?? 'report') === 'cetak-nb')
                        <a href="{{ route('cetak-nb.print', ['import_id' => $importId, 'record' => $prevId]) }}" class="nav-btn">&larr; Sebelumnya</a>
                    @elseif (($entryMode ?? 'report') === 'data')
                        <a href="{{ route('data.cetak-nb', ['import_id' => $importId, 'record' => $prevId]) }}" class="nav-btn">&larr; Sebelumnya</a>
                    @else
                        <a href="{{ route('reports.cetak-nb', ['report' => $report->id, 'record' => $prevId]) }}" class="nav-btn">&larr; Sebelumnya</a>
                    @endif
                @else
                    <button class="nav-btn" disabled>&larr; Sebelumnya</button>
                @endif

                <span class="record-info">Record {{ $currentPosition }} dari {{ $totalRecords }}</span>

                @if ($nextId !== null)
                    @if (($entryMode ?? 'report') === 'cetak-nb')
                        <a href="{{ route('cetak-nb.print', ['import_id' => $importId, 'record' => $nextId]) }}" class="nav-btn">Berikutnya &rarr;</a>
                    @elseif (($entryMode ?? 'report') === 'data')
                        <a href="{{ route('data.cetak-nb', ['import_id' => $importId, 'record' => $nextId]) }}" class="nav-btn">Berikutnya &rarr;</a>
                    @else
                        <a href="{{ route('reports.cetak-nb', ['report' => $report->id, 'record' => $nextId]) }}" class="nav-btn">Berikutnya &rarr;</a>
                    @endif
                @else
                    <button class="nav-btn" disabled>Berikutnya &rarr;</button>
                @endif
            @endif
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="print-btn">Print / Simpan PDF</button>
            <button onclick="@if(($entryMode ?? 'report') === 'cetak-nb')window.location='{{ route('cetak-nb.index') }}'@elseif(($entryMode ?? 'report') === 'data')window.location='{{ route('data.index') }}'@elsewindow.location='{{ route('reports.index') }}'@endif" class="close-btn">Tutup</button>
        </div>
    </div>

    @foreach ($records as $rd)
        <div class="page">
            <div class="field" style="top: 0mm; left: 0;">
                <span class="field-label" style="font-size: 20px;">Nomor Akta:</span>
                <span class="field-value" style="font-size: 20px;">{{ $rd['Nomor Akta Nikah'] ?? '-' }}</span>
            </div>

            <div class="field" style="top: 30mm; right: 20mm;">
                <span class="field-value">JT {{ $rd['No Porforasi Suami'] ?? '-' }} & {{ $rd['No Porforasi Istri'] ?? '-' }}</span>
            </div>
        </div>
    @endforeach
</body>
</html>
