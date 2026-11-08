<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('imports.show', $import) }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $import->file_name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mb-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-md px-4 py-3">
                            <div class="text-gray-500 dark:text-gray-400">Sheet</div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $import->sheet_name ?? '-' }}</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-md px-4 py-3">
                            <div class="text-gray-500 dark:text-gray-400">Total Baris</div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($import->total_rows) }}</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/50 rounded-md px-4 py-3">
                            <div class="text-green-600 dark:text-green-400">Berhasil</div>
                            <div class="font-semibold text-green-700 dark:text-green-300">{{ number_format($import->imported_rows) }}</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-md px-4 py-3">
                            <div class="text-gray-500 dark:text-gray-400">Status</div>
                            <div class="font-semibold"><x-status-badge :status="$import->status" /></div>
                        </div>
                    </div>

                    <livewire:data-table :import-id="$import->id" :key="'file-data-'.$import->id" />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
