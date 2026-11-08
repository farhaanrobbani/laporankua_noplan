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

                    @if ($grouped->isEmpty())
                        <x-empty-state title="Belum ada data import" message="Upload file Excel untuk mulai mengolah data." :action-url="route('imports.create')" action-label="Upload Excel" />
                    @else
                        <form method="GET" action="{{ route('data.index') }}" class="mb-6 flex flex-col sm:flex-row gap-3 sm:items-center">
                            <label class="text-sm text-gray-700 dark:text-gray-300">
                                Sumber data:
                                <select name="import_id" onchange="this.form.submit()" class="ml-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm">
                                    <option value="">-- Pilih file import --</option>
                                    @foreach ($grouped as $group)
                                        <option value="{{ $group['import_ids'][0] }}" @selected($selectedGroup && $selectedGroup['table_name'] === $group['table_name'])>
                                            {{ $group['table_name'] }} ({{ number_format($group['total_rows']) }} baris)
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </form>

                        @if ($selectedGroup)
                            <livewire:data-table :import-ids="$selectedGroup['import_ids']" :key="'data-table-'.$selectedGroup['table_name']" />
                        @else
                            <x-empty-state title="Pilih file import untuk melihat datanya" />
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
