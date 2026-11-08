<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Laporan') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('reports.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700">
                    Buat Laporan
                </a>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    @if ($reports->isEmpty())
                        <x-empty-state title="Belum ada laporan" message="Buat laporan pertama dari data Anda." :action-url="route('reports.create')" action-label="Buat Laporan" />
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($reports as $report)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $report->title }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                <x-status-badge :status="$report->output_format" />
                                                &middot; {{ $report->import?->table_name ?? '-' }}
                                                &middot; {{ $report->created_at->format('d M Y H:i') }}
                                            </p>
                                        </div>
                                        <x-status-badge :status="$report->status" class="shrink-0" />
                                    </div>
                                    <div class="mt-3 flex gap-3 text-sm">
                                        <a href="{{ route('reports.show', $report) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">Detail</a>
                                        @if ($report->file_path)
                                            <a href="{{ route('reports.download', $report) }}" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium">Download</a>
                                        @endif
                                        @if ($report->output_format === 'print')
                                            <a href="{{ route('reports.print', $report) }}" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">Print</a>
                                        @endif
                                        <a href="#" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-delete-report-{{ $report->id }}')" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium">Hapus</a>

                                        <x-modal name="confirm-delete-report-{{ $report->id }}" :show="false" focusable>
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
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $reports->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
