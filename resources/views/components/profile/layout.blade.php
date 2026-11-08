<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $header ?? 'Profile' }}</h2>
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
                        $isActiveInfo = request()->routeIs('profile.info');
                        $isActiveKop = request()->routeIs('profile.kop-surat');
                        $isActiveDesa = request()->routeIs('profile.daftar-desa');
                        $isActivePassword = request()->routeIs('profile.password');
                        $isActiveDelete = request()->routeIs('profile.delete');
                    @endphp

                    <a href="{{ route('profile.info') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveInfo ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Informasi Profil
                    </a>
                    <a href="{{ route('profile.kop-surat') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveKop ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Data Kop Surat
                    </a>
                    <a href="{{ route('profile.daftar-desa') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveDesa ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Daftar Desa
                    </a>
                    <a href="{{ route('profile.password') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActivePassword ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Ubah Password
                    </a>
                    <a href="{{ route('profile.delete') }}"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActiveDelete ? 'bg-gray-900 dark:bg-gray-700 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        Hapus Akun
                    </a>
                </nav>
            </aside>

            <div class="flex-1 min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
