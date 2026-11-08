<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('data.index', ['import_id' => $record->import_id]) }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Detail Record') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Baris {{ $record->row_number }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Dari file: {{ $record->import->table_name }}</p>

                    <dl class="divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-md">
                        @foreach ($record->row_data ?? [] as $key => $value)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 px-4 py-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 sm:col-span-2 break-words">{{ $value ?? '-' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Dibuat: {{ $record->created_at->format('d M Y H:i') }} &middot; Diubah: {{ $record->updated_at->format('d M Y H:i') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
