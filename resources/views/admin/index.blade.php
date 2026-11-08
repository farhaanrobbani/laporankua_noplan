<x-admin.layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="space-y-6">

        {{-- Welcome --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Selamat Datang, {{ Auth::user()->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola akun dan pantau aktivitas website dari sini.</p>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/50 flex items-center justify-center">
                        <x-heroicon-o-users class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['users'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Users</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/50 flex items-center justify-center">
                        <x-heroicon-o-arrow-up-tray class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['imports'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Imports</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/50 flex items-center justify-center">
                        <x-heroicon-o-document-text class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['reports'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Laporan</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/50 flex items-center justify-center">
                        <x-heroicon-o-table-cells class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['records']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Records</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Recent Imports --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Import Terbaru</h2>
                        <a href="{{ route('imports.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">Lihat semua</a>
                    </div>
                </div>
                @if ($recentImports->isEmpty())
                    <div class="p-8 text-center">
                        <x-heroicon-o-arrow-up-tray class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                        <p class="text-sm text-gray-400 dark:text-gray-500">Belum ada import</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-50 dark:divide-gray-700">
                        @foreach ($recentImports as $import)
                            <div class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $import->table_name }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $import->sheet_name ?? '-' }} &middot; {{ $import->created_at->diffForHumans() }}</p>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 text-xs font-medium rounded-full {{ $import->status === 'success' ? 'bg-green-50 dark:bg-green-900/50 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/50 text-red-700 dark:text-red-300' }}">
                                    {{ $import->status }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Reports --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Laporan Terbaru</h2>
                        <a href="{{ route('reports.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">Lihat semua</a>
                    </div>
                </div>
                @if ($recentReports->isEmpty())
                    <div class="p-8 text-center">
                        <x-heroicon-o-document-text class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                        <p class="text-sm text-gray-400 dark:text-gray-500">Belum ada laporan</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-50 dark:divide-gray-700">
                        @foreach ($recentReports as $report)
                            <div class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $report->title }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ strtoupper($report->output_format) }} &middot; {{ $report->created_at->diffForHumans() }}</p>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 text-xs font-medium rounded-full {{ $report->status === 'completed' ? 'bg-green-50 dark:bg-green-900/50 text-green-700 dark:text-green-300' : ($report->status === 'failed' ? 'bg-red-50 dark:bg-red-900/50 text-red-700 dark:text-red-300' : 'bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300') }}">
                                    {{ $report->status }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    </div>
</x-admin.layout>
