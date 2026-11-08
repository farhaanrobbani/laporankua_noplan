<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laporan') }} — Kelola Laporan dengan Mudah</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        (function() {
            try {
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="font-sans antialiased bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">

    {{-- Header --}}
    <header x-data="{ mobileOpen: false }" class="sticky top-0 z-50 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/" class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-auto text-blue-600" />
                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">Laporan</span>
                </a>

                <nav class="hidden md:flex items-center gap-8">
                    <a href="#fitur" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">Fitur</a>
                    <a href="#cara-kerja" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">Cara Kerja</a>
                </nav>

                <div class="flex items-center gap-3">
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 transition px-3 py-2">
                            Dashboard
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 transition px-3 py-2">
                                Masuk
                            </a>
                        @endif
                    @endauth

                    <button @click="mobileOpen = !mobileOpen" class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <div @click.away="mobileOpen = false" x-show="mobileOpen" x-cloak x-transition class="md:hidden border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 space-y-1">
                <a href="#fitur" @click="mobileOpen = false" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Fitur</a>
                <a href="#cara-kerja" @click="mobileOpen = false" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cara Kerja</a>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 dark:from-blue-950 via-white dark:via-gray-900 to-indigo-50 dark:to-indigo-950"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-blue-100/40 dark:bg-blue-900/40 rounded-full blur-3xl -translate-y-1/2"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-24 sm:pt-28 sm:pb-32">
            <div class="text-center max-w-3xl mx-auto">
                <div class="inline-flex items-center gap-2 bg-blue-50 dark:bg-blue-900/50 border border-blue-100 dark:border-blue-700 text-blue-700 dark:text-blue-300 text-xs font-semibold px-3 py-1 rounded-full mb-6">
                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                    Web Pengolah Laporan
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-gray-900 dark:text-gray-100 leading-tight">
                    Kelola Laporan
                    <span class="text-blue-600">dengan Mudah</span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-gray-600 dark:text-gray-400 leading-relaxed max-w-2xl mx-auto">
                    Upload file Excel, otomatis tersimpan di database, lalu generate laporan dalam format PDF, Word, Excel, atau cetak langsung.
                </p>

                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-xl transition shadow-lg shadow-blue-600/20">
                            Masuk
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    @endif
                    <a href="#fitur" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold px-8 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 transition">
                        Pelajari Lebih Lanjut
                    </a>
                </div>
            </div>

            {{-- Dashboard Preview --}}
            <div class="mt-16 sm:mt-20 max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl shadow-gray-200/60 dark:shadow-gray-800/60 border border-gray-200/60 dark:border-gray-700/60 p-4 sm:p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-6 sm:p-8 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="h-6 w-32 bg-gray-200 dark:bg-gray-600 rounded-md"></div>
                            <div class="h-8 w-24 bg-blue-600 rounded-lg"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-100 dark:border-gray-700">
                                <div class="h-3 w-16 bg-gray-200 dark:bg-gray-600 rounded mb-2"></div>
                                <div class="h-6 w-12 bg-blue-600 rounded"></div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-100 dark:border-gray-700">
                                <div class="h-3 w-20 bg-gray-200 dark:bg-gray-600 rounded mb-2"></div>
                                <div class="h-6 w-16 bg-blue-600 rounded"></div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-100 dark:border-gray-700">
                                <div class="h-3 w-14 bg-gray-200 dark:bg-gray-600 rounded mb-2"></div>
                                <div class="h-6 w-10 bg-gray-600 rounded"></div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-3 flex-1 bg-gray-100 dark:bg-gray-700 rounded"></div>
                                    <div class="h-3 w-16 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-3 w-12 bg-blue-200 dark:bg-blue-800 rounded"></div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-3 flex-1 bg-gray-100 dark:bg-gray-700 rounded"></div>
                                    <div class="h-3 w-20 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-3 w-12 bg-green-200 dark:bg-green-800 rounded"></div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-3 flex-1 bg-gray-100 dark:bg-gray-700 rounded"></div>
                                    <div class="h-3 w-14 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-3 w-12 bg-purple-200 dark:bg-purple-800 rounded"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Fitur --}}
    <section id="fitur" class="py-20 sm:py-28 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">Fitur Utama</h2>
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">Semua yang Anda butuhkan untuk mengolah laporan dari Excel hingga siap cetak.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                {{-- Upload & Import --}}
                <div class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 hover:border-blue-200 dark:hover:border-blue-700 hover:shadow-lg hover:shadow-blue-50 transition-all duration-300">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/50 rounded-xl flex items-center justify-center mb-5 group-hover:bg-blue-600 transition-colors duration-300">
                        <svg class="w-6 h-6 text-blue-600 group-hover:text-white transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Upload & Import</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">Drag & drop file Excel (.xlsx/.xls) dan data otomatis tersimpan ke database MySQL. Pratinjau data sebelum import.</p>
                </div>

                {{-- Generate Laporan --}}
                <div class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 hover:border-blue-200 dark:hover:border-blue-700 hover:shadow-lg hover:shadow-blue-50 transition-all duration-300">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/50 rounded-xl flex items-center justify-center mb-5 group-hover:bg-green-600 transition-colors duration-300">
                        <svg class="w-6 h-6 text-green-600 group-hover:text-white transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Generate Laporan</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">Buat laporan dalam berbagai format: PDF, Word, Excel, atau cetak langsung. Filter dan sortir data sesuai kebutuhan.</p>
                </div>

                {{-- Template --}}
                <div class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 hover:border-blue-200 dark:hover:border-blue-700 hover:shadow-lg hover:shadow-blue-50 transition-all duration-300">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/50 rounded-xl flex items-center justify-center mb-5 group-hover:bg-purple-600 transition-colors duration-300">
                        <svg class="w-6 h-6 text-purple-600 group-hover:text-white transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Template Laporan</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">Simpan format laporan sebagai template. Gunakan kembali untuk import data berikutnya tanpa perlu mengatur ulang.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Cara Kerja --}}
    <section id="cara-kerja" class="py-20 sm:py-28 bg-gray-50 dark:bg-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">Cara Kerja</h2>
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">Tiga langkah sederhana untuk membuat laporan.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 md:gap-12">
                <div class="text-center">
                    <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-5">1</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Upload Excel</h3>
                    <p class="text-gray-600 dark:text-gray-400">Unggah file Excel (.xlsx/.xls) melalui halaman import. Data akan otomatis tersimpan ke database.</p>
                </div>

                <div class="text-center">
                    <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-5">2</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Pilih & Filter</h3>
                    <p class="text-gray-600 dark:text-gray-400">Pilih data yang ingin dilaporkan. Gunakan filter, pencarian, dan template untuk mengatur laporan.</p>
                </div>

                <div class="text-center">
                    <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-5">3</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Generate & Download</h3>
                    <p class="text-gray-600 dark:text-gray-400">Buat laporan dalam format PDF, Word, Excel, atau cetak langsung. Download hasilnya kapan saja.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 sm:py-28 bg-white dark:bg-gray-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl p-10 sm:p-16 shadow-2xl shadow-blue-600/20">
                <h2 class="text-3xl sm:text-4xl font-bold text-white">Siap Mulai?</h2>
                <p class="mt-4 text-lg text-blue-100 max-w-xl mx-auto">Masuk untuk mulai mengolah laporan KUA Anda sekarang.</p>
                <div class="mt-8">
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-white text-blue-600 font-semibold px-8 py-3.5 rounded-xl hover:bg-blue-50 transition shadow-lg">
                            Masuk
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <x-application-logo class="h-6 w-auto text-blue-600" />
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Laporan</span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">&copy; {{ date('Y') }} Laporan. Hak cipta dilindungi.</p>
            </div>
        </div>
    </footer>

</body>
</html>
