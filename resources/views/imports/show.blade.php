<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('imports.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $tableName }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Daftar File</h3>
                        <a href="{{ route('imports.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Upload File
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-sticky-first min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">File</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Sheet</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Baris</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Berhasil</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Tanggal</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($imports as $index => $import)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $import->file_name }}</td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $import->sheet_name ?? '-' }}</td>
                                        <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($import->total_rows) }}</td>
                                        <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($import->imported_rows) }}</td>
                                        <td class="px-4 py-2">
                                            <x-status-badge :status="$import->status" />
                                        </td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $import->created_at->format('d M Y H:i') }}</td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            @if (in_array($import->status, ['success', 'appended'], true))
                                                <a href="{{ route('imports.data', $import) }}" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 text-sm font-medium">Data</a>
                                                <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                                            @endif
                                            @if ($import->reports_count > 0)
                                                <span class="text-gray-400 dark:text-gray-500 text-sm cursor-not-allowed" title="Digunakan oleh {{ $import->reports_count }} laporan">Hapus</span>
                                            @else
                                                <span class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm font-medium cursor-pointer"
                                                      x-data=""
                                                      x-on:click.prevent="$dispatch('open-modal', 'confirm-delete-import-{{ $import->id }}')">
                                                    Hapus
                                                </span>
                                                <x-modal name="confirm-delete-import-{{ $import->id }}" :show="false" focusable>
                                                    <form method="POST" action="{{ route('imports.destroy', $import) }}" class="p-6">
                                                        @csrf
                                                        @method('DELETE')
                                                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Hapus file ini?</h2>
                                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">File "{{ $import->file_name }}" beserta {{ number_format($import->import_data_count) }} record-nya akan dihapus permanen.</p>
                                                        <div class="mt-6 flex justify-end gap-3">
                                                            <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                                                            <x-danger-button>Hapus</x-danger-button>
                                                        </div>
                                                    </form>
                                                </x-modal>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada file ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>

                    @if ($imports->isNotEmpty())
                        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-md px-4 py-3">
                                <div class="text-gray-500 dark:text-gray-400">Total File</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $imports->count() }}</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-md px-4 py-3">
                                <div class="text-gray-500 dark:text-gray-400">Total Baris</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($imports->sum('total_rows')) }}</div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/50 rounded-md px-4 py-3">
                                <div class="text-green-600 dark:text-green-400">Berhasil</div>
                                <div class="font-semibold text-green-700 dark:text-green-300">{{ number_format($imports->sum('imported_rows')) }}</div>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/50 rounded-md px-4 py-3">
                                <div class="text-red-600 dark:text-red-400">Gagal</div>
                                <div class="font-semibold text-red-700 dark:text-red-300">{{ number_format($imports->sum('failed_rows')) }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
