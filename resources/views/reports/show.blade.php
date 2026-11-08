<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $report->title }}
                </h2>
            </div>
            <a href="{{ route('reports.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Buat Laporan
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Ringkasan Laporan</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-gray-500 dark:text-gray-400">Judul</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $report->title }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Format</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ strtoupper($report->output_format) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Sumber data</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $report->import?->table_name ?? '-' }}</dd></div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="font-medium">
                                <x-status-badge :status="$report->status" />
                            </dd>
                        </div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Ukuran file</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $report->file_size ? number_format($report->file_size / 1024, 1).' KB' : '-' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Dibuat pada</dt><dd class="font-medium text-gray-900 dark:text-gray-100">{{ $report->generated_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                    </dl>

                    @if (in_array($report->status, ['pending', 'processing']))
                        <p class="mt-4 text-sm text-yellow-700 dark:text-yellow-300 bg-yellow-50 dark:bg-yellow-900/50 border border-yellow-200 dark:border-yellow-700 rounded-md px-4 py-3">Laporan sedang dibuat di background. Muat ulang halaman ini untuk status terbaru.</p>
                    @endif

                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($report->file_path)
                            <a href="{{ route('reports.download', $report) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700">Download</a>
                        @endif
                        @if ($report->output_format === 'print')
                            <a href="{{ route('reports.print', $report) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-semibold rounded-md hover:bg-gray-700">Buka Print</a>
                        @endif
                        <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-delete-report')">Hapus</x-danger-button>

                        <x-modal name="confirm-delete-report" :show="false" focusable>
                            <form method="POST" action="{{ route('reports.destroy', $report) }}" class="p-6">
                                @csrf
                                @method('DELETE')
                                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Hapus laporan?</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Laporan "{{ $report->title }}" beserta file-nya akan dihapus permanen.</p>
                                <div class="mt-6 flex justify-end gap-3">
                                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                                    <x-danger-button>Hapus</x-danger-button>
                                </div>
                            </form>
                        </x-modal>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
