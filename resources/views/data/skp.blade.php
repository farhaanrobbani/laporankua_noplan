<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Data') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <x-data-tabs />

                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Statistik SKP') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Periode') }}: {{ $periodeLabel }}</p>
                        </div>
                        <form method="GET" action="{{ route('data.skp') }}" class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="tw" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Triwulan') }}</label>
                                <select id="tw" name="tw" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                    @for ($i = 1; $i <= 4; $i++)
                                        <option value="{{ $i }}" @selected($tw === $i)>TW{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <label for="tahun" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Tahun') }}</label>
                                <select id="tahun" name="tahun" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                    @foreach ($tahunTersedia as $tahunOption)
                                        <option value="{{ $tahunOption }}" @selected($tahun === $tahunOption)>{{ $tahunOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition">{{ __('Terapkan') }}</button>
                                <a href="{{ route('data.skp') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition">{{ __('Reset') }}</a>
                            </div>
                        </form>
                    </div>

                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Pendaftaran mengikuti Tanggal Daftar, pelaksanaan mengikuti Tanggal Nikah; pecahan kantor/luar diambil dari kolom Nikah Di pada data pendaftaran; duplikat unik per Nomor Perforasi dengan Tanggal Cetak; buku rusak = baris Status RUSAK + celah nomor perforasi — konsisten dengan Statistik Laporan di dashboard. Total tahunan dihitung Januari–Desember.</p>

                    @php
                        $twLabel = 'TW' . $tw . ' ' . $tahun;
                        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        $card = fn (string $label, int $nilai, string $periode) => '<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"><p class="text-xs text-gray-500 dark:text-gray-400">' . e($label) . '</p><p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">' . number_format($nilai) . '</p><p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">' . e($periode) . '</p></div>';
                    @endphp

                    {{-- Pelaksanaan Nikah --}}
                    <x-skp-section :title="__('Pelaksanaan Nikah') . ' · ' . $twLabel" :summary="number_format($stats['triwulan']['pelaksanaan'])" :open="true">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            {!! $card(__('Pelaksanaan Nikah') . ' ' . $twLabel, $stats['triwulan']['pelaksanaan'], $periodeLabel) !!}
                            {!! $card(__('Pelaksanaan Nikah Kantor') . ' ' . $twLabel, $stats['triwulan']['pelaksanaan_kantor'], $periodeLabel) !!}
                            {!! $card(__('Pelaksanaan Nikah Luar Kantor') . ' ' . $twLabel, $stats['triwulan']['pelaksanaan_luar'], $periodeLabel) !!}
                        </div>
                        @foreach ($bulanList as $m)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 last:mb-0">
                                {!! $card(__('Pelaksanaan Nikah') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['pelaksanaan'], $namaBulan[$m] . ' ' . $tahun) !!}
                                {!! $card(__('Pelaksanaan Nikah Kantor') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['pelaksanaan_kantor'], $namaBulan[$m] . ' ' . $tahun) !!}
                                {!! $card(__('Pelaksanaan Nikah Luar Kantor') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['pelaksanaan_luar'], $namaBulan[$m] . ' ' . $tahun) !!}
                            </div>
                        @endforeach
                    </x-skp-section>

                    {{-- Pendaftaran Nikah --}}
                    <x-skp-section :title="__('Pendaftaran Nikah') . ' · ' . $twLabel" :summary="number_format($stats['triwulan']['pendaftaran'])">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            {!! $card(__('Pendaftaran Nikah') . ' ' . $twLabel, $stats['triwulan']['pendaftaran'], $periodeLabel) !!}
                            {!! $card(__('Pendaftaran Nikah Kantor') . ' ' . $twLabel, $stats['triwulan']['pendaftaran_kantor'], $periodeLabel) !!}
                            {!! $card(__('Pendaftaran Nikah Luar Kantor') . ' ' . $twLabel, $stats['triwulan']['pendaftaran_luar'], $periodeLabel) !!}
                        </div>
                        @foreach ($bulanList as $m)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 last:mb-0">
                                {!! $card(__('Pendaftaran Nikah') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['pendaftaran'], $namaBulan[$m] . ' ' . $tahun) !!}
                                {!! $card(__('Pendaftaran Nikah Kantor') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['pendaftaran_kantor'], $namaBulan[$m] . ' ' . $tahun) !!}
                                {!! $card(__('Pendaftaran Nikah Luar Kantor') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['pendaftaran_luar'], $namaBulan[$m] . ' ' . $tahun) !!}
                            </div>
                        @endforeach
                    </x-skp-section>

                    {{-- Duplikat --}}
                    <x-skp-section :title="__('Duplikat')" :summary="number_format($stats['triwulan']['duplikat'])">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @foreach ($bulanList as $m)
                                {!! $card(__('Duplikat') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['duplikat'], $namaBulan[$m] . ' ' . $tahun) !!}
                            @endforeach
                            {!! $card(__('Duplikat') . ' ' . $twLabel, $stats['triwulan']['duplikat'], $periodeLabel) !!}
                        </div>
                    </x-skp-section>

                    {{-- Buku Rusak --}}
                    <x-skp-section :title="__('Buku Rusak')" :summary="number_format($stats['triwulan']['rusak'])">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @foreach ($bulanList as $m)
                                {!! $card(__('Buku Rusak') . ' ' . $namaBulan[$m], $stats['bulan'][$m]['rusak'], $namaBulan[$m] . ' ' . $tahun) !!}
                            @endforeach
                            {!! $card(__('Buku Rusak') . ' ' . $twLabel, $stats['triwulan']['rusak'], $periodeLabel) !!}
                        </div>
                    </x-skp-section>

                    {{-- Total Tahunan: 3 3 2 --}}
                    <x-skp-section :title="__('Total Tahunan') . ' ' . $tahun">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                            {!! $card(__('Pelaksanaan Nikah') . ' ' . $tahun, $stats['tahunan']['pelaksanaan'], (string) $tahun) !!}
                            {!! $card(__('Pelaksanaan Nikah Kantor') . ' ' . $tahun, $stats['tahunan']['pelaksanaan_kantor'], (string) $tahun) !!}
                            {!! $card(__('Pelaksanaan Nikah Luar Kantor') . ' ' . $tahun, $stats['tahunan']['pelaksanaan_luar'], (string) $tahun) !!}
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                            {!! $card(__('Pendaftaran Nikah') . ' ' . $tahun, $stats['tahunan']['pendaftaran'], (string) $tahun) !!}
                            {!! $card(__('Pendaftaran Nikah Kantor') . ' ' . $tahun, $stats['tahunan']['pendaftaran_kantor'], (string) $tahun) !!}
                            {!! $card(__('Pendaftaran Nikah Luar Kantor') . ' ' . $tahun, $stats['tahunan']['pendaftaran_luar'], (string) $tahun) !!}
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {!! $card(__('Duplikat') . ' ' . $tahun, $stats['tahunan']['duplikat'], (string) $tahun) !!}
                            {!! $card(__('Buku Rusak') . ' ' . $tahun, $stats['tahunan']['rusak'], (string) $tahun) !!}
                        </div>
                    </x-skp-section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
