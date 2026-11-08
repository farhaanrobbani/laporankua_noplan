<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $header ?? 'Admin Panel' }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-md text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded-md text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
            <aside class="w-full sm:w-48 shrink-0">
                <nav class="space-y-1">
                    @php
                        $isActiveDashboard = request()->routeIs('admin.index');
                        $isActiveSettings = request()->routeIs('admin.settings.*');
                        $isActiveTemplates = request()->routeIs('admin.templates.*');
                    @endphp

                    <a href="{{ route('admin.index') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveDashboard ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Dashboard
                    </a>                    <a href="{{ route('admin.templates.index') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveTemplates ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Template
                    </a>
                    <a href="{{ route('admin.settings.index') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveSettings ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Pengaturan
                    </a>
                </nav>
            </aside>

            <div class="flex-1 min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
