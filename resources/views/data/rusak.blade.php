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

                    @if (empty($importIds))
                        <x-empty-state title="Belum ada data" message="Upload laporan model L3 terlebih dahulu." :action-url="route('imports.create')" action-label="Upload Excel" />
                    @elseif ($totalRusak === 0 && $totalRusakStatus === 0)
                        <x-empty-state title="Tidak ada nomor rusak terdeteksi" message="Semua nomor perforasi dalam rentang lengkap." />
                    @else
                        @if ($totalRusak > 0)
                            <div class="mb-4 bg-amber-50 border border-amber-200 rounded-md px-4 py-3 text-sm text-amber-800">
                                <strong>{{ $totalRusak }}</strong> nomor terdeteksi rusak (hilang di tengah rentang) dari
                                <strong>{{ $prefixCount }}</strong> prefix.
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-md">
                                <table class="table-sticky-first min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">No</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Prefix</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Nomor JT</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Bulan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php($no = $missingRows->firstItem() ?? 1)
                                        @foreach ($missingRows as $row)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $no++ }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $row['prefix'] }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">JT {{ $row['number'] }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $row['month'] !== '' ? $row['month'] : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>
                            <div class="mt-4">{{ $missingRows->links() }}</div>
                        @endif

                        @if ($totalRusakStatus > 0)
                            <div class="{{ $totalRusak > 0 ? 'mt-6 ' : '' }}mb-4 bg-amber-50 border border-amber-200 rounded-md px-4 py-3 text-sm text-amber-800">
                                <strong>{{ $totalRusakStatus }}</strong> buku berstatus <strong>RUSAK</strong> pada data laporan L3.
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-md">
                                <table class="table-sticky-first min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">No</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Nomor JT</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Tanggal Cetak</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php($no = $rusakStatusRows->firstItem() ?? 1)
                                        @foreach ($rusakStatusRows as $row)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $no++ }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">JT {{ $row['number'] }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $row['tanggal'] }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $row['status'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>
                            <div class="mt-4">{{ $rusakStatusRows->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
