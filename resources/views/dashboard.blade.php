<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Quick upload CTA --}}
            <div class="bg-blue-600 dark:bg-blue-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold">Olah Data Excel Menjadi Laporan</h3>
                        <p class="text-blue-100 dark:text-blue-200 text-sm">Upload file .xlsx atau .xls, data otomatis tersimpan sebagai database.</p>
                    </div>
                    <a href="{{ route('imports.create') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 text-blue-700 dark:text-blue-400 font-semibold text-sm rounded-md hover:bg-blue-50 dark:hover:bg-gray-700">
                        Upload Excel
                    </a>
                </div>
            </div>

            {{-- Statistics cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total File Import</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalImports) }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Data Record</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalRecords) }}</p>
                    </div>
                </div>
                <div class="col-span-2 sm:col-span-1 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Laporan Dibuat</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalReports) }}</p>
                    </div>
                </div>
            </div>

            {{-- Statistik laporan --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Statistik Laporan</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Periode: {{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="filter-bulan" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Bulan</label>
                                <select id="filter-bulan" name="bulan" class="w-full sm:w-auto border-gray-300 dark:border-gray-600 rounded-md text-sm">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @selected($bulan === $m)>{{ \App\Http\Controllers\DashboardController::MONTH_NAMES[$m - 1] }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <label for="filter-tahun" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tahun</label>
                                <select id="filter-tahun" name="tahun" class="w-full sm:w-auto border-gray-300 dark:border-gray-600 rounded-md text-sm">
                                    @for ($y = now()->year; $y >= now()->year - 5; $y--)
                                        <option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white text-sm font-semibold rounded-md transition">Terapkan</button>
                        </form>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div class="col-span-2 sm:col-span-1 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Peristiwa Nikah &middot; Bulan</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['pn_bulan']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Peristiwa Nikah Kantor</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['pn_kantor']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Peristiwa Nikah Luar Kantor</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['pn_luar']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mt-4">
                        <div class="col-span-2 sm:col-span-1 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pendaftaran Nikah &middot; Bulan</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['pdk_bulan']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pendaftaran Nikah Kantor</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['pdk_kantor']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pendaftaran Nikah Luar Kantor</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['pdk_luar']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-4">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Duplikat &middot; Bulan</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['dup_bulan']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Duplikat &middot; Tahun</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['dup_tahun']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Buku Rusak &middot; Bulan</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['rusak_bulan']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $monthName }} {{ $tahun }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Buku Rusak &middot; Tahun</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['rusak_tahun']) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $tahun }}</p>
                        </div>
                    </div>

                    @if (count($stats['penghulu_bulan']) > 0)
                        <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Penghulu Hadir Nikah &middot; {{ $monthName }} {{ $tahun }}</p>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Penghulu</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kantor</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Luar</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($stats['penghulu_bulan'] as $item)
                                            <tr>
                                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $item['nama'] }}</td>
                                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['kantor']) }}</td>
                                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['luar']) }}</td>
                                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['jumlah']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent imports --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Import Terakhir</h3>
                    @if ($recentImports->isEmpty())
                        <x-empty-state title="Belum ada data import" message="Upload file Excel untuk mulai mengolah data." :action-url="route('imports.create')" action-label="Upload Excel" />
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">File</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Sheet</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Baris</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($recentImports as $import)
                                        <tr>
                                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $import->table_name }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $import->sheet_name ?? '-' }}</td>
                                            <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($import->import_data_count) }}</td>
                                            <td class="px-4 py-2">
                                                <x-status-badge :status="$import->status" />
                                            </td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $import->created_at->format('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent reports + quick actions --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Laporan Terbaru</h3>
                        @if ($recentReports->isEmpty())
                            <x-empty-state title="Belum ada laporan" message="Buat laporan pertama dari data Anda." :action-url="route('reports.create')" action-label="Buat Laporan" />
                        @else
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($recentReports as $report)
                                    <li class="py-3 flex items-center justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $report->title }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($report->output_format) }} &middot; {{ $report->created_at->format('d M Y') }}</p>
                                        </div>
                                        <x-status-badge :status="$report->status" class="shrink-0" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Aksi Cepat</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <a href="{{ route('imports.create') }}" class="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/50">
                                <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Upload Excel</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Import file .xlsx / .xls</p>
                            </a>
                            <a href="{{ route('reports.create') }}" class="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/50">
                                <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Buat Laporan</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">PDF, Word, Excel, Print</p>
                            </a>
                            <a href="{{ route('profile.edit') }}" class="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/50">
                                <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Profil</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pengaturan akun</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
