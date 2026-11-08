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
                    @else
                        <livewire:data-table
                            :import-ids="$importIds"
                            :filter-column="'Keterangan'"
                            :filter-value="'Duplikat'"
                            :filter-mode="'exact'"
                            :editable-columns="['Desa', 'Tanggal Cetak']"
                            :sort-column="'Tanggal Cetak'"
                            :sort-direction="'desc'"
                            :key="'duplikat'"
                        />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
