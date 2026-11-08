<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Riwayat Import') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('imports.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white text-sm font-semibold rounded-md hover:bg-blue-700 dark:hover:bg-blue-600">
                    Upload Excel
                </a>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    @if ($imports->isEmpty())
                        <x-empty-state title="Belum ada data import" message="Upload file Excel untuk mulai mengolah data." :action-url="route('imports.create')" action-label="Upload Excel" />
                    @else
                        <div class="overflow-x-auto">
                            <table class="table-sticky-first min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Tabel</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">File</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Berhasil</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Gagal</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Tanggal</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($imports as $group)
                                        <tr>
                                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $group->table_name ?? '-' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                @php($latestFileName = $group->file_names_list[0] ?? '-')
                                                @if ($group->file_count === 1)
                                                    <span class="block max-w-56 truncate" title="{{ $latestFileName }}">{{ $latestFileName }}</span>
                                                @else
                                                    <details class="group min-w-48">
                                                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md py-1 focus:outline-none focus:ring-2 focus:ring-indigo-300 [&::-webkit-details-marker]:hidden" title="Klik untuk melihat semua file">
                                                            <span class="max-w-40 truncate sm:max-w-56">{{ $latestFileName }}</span>
                                                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $group->file_count }} file</span>
                                                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </summary>
                                                        <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto border-t border-gray-100 pt-2 pr-1 text-xs dark:border-gray-700">
                                                            @foreach ($group->file_names_list as $fname)
                                                                <li class="truncate" title="{{ $fname }}">{{ $fname }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </details>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($group->imported_rows) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($group->failed_rows) }}</td>
                                            <td class="px-4 py-2">
                                                @foreach ($group->status_summary as $status => $count)
                                                    <x-status-badge :status="$status" /> <span class="text-xs text-gray-500 dark:text-gray-400">x{{ $count }}</span>
                                                    @if (! $loop->last)
                                                        <br>
                                                    @endif
                                                @endforeach
                                            </td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $group->latest_created_at->format('d M Y H:i') }}</td>
                                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                                <a href="{{ route('data.index', ['import_id' => $group->first_id]) }}" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 text-sm font-medium">Data</a>
                                                <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                                                <a href="{{ route('imports.show', $group->latest_id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium">Detail</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>
                        <div class="mt-4">{{ $imports->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
