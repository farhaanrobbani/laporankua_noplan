<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Cetak NB') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if (! $selectedGroup)
                        <x-empty-state title="Belum ada data peristiwa nikah" message="Upload file laporan peristiwa nikah untuk mulai mencetak NB." :action-url="route('imports.create')" action-label="Upload Excel" />
                    @else
                        <div class="mb-6 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-700 dark:bg-blue-900/50">
                            <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">Sumber data: Peristiwa Nikah</p>
                            <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                                {{ number_format($selectedGroup['total_rows']) }} baris dari {{ count($selectedGroup['import_ids']) }} file peristiwa nikah.
                            </p>
                        </div>

                        <livewire:cetak-nb-table :import-ids="$selectedGroup['import_ids']" :key="'cetak-nb-table'" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
