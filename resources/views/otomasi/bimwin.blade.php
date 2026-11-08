<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Otomasi') }}
            </h2>
        </div>
    </x-slot>

    @php
        $wilayahLengkap = filled($user->provinsi) && filled($user->kota_kabupaten) && filled($user->kecamatan);
        $endpointCount = count(array_filter($endpoints));
        $endpointFields = [
            'cek' => ['label' => 'Cek status catin', 'hint' => 'Permintaan cek BIMWIN per calon pengantin'],
            'dialog' => ['label' => 'Buka dialog BIMWIN', 'hint' => 'Permintaan form BIMWIN berdasarkan nomor daftar'],
            'simpan' => ['label' => 'Simpan jadwal', 'hint' => 'Permintaan simpan form BIMWIN'],
            'selesai' => ['label' => 'Tandai selesai', 'hint' => 'Permintaan status selesai (SS_ALL)'],
        ];
    @endphp

    <div class="py-6 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <x-otomasi-tabs />
            <x-otomasi-setup :config-json="$configJson" />
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Otomasi Pengisian BIMWIN</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                Script berjalan di browser pada halaman SIMKAH4 dan memakai sesi login Anda. Aplikasi Laporan tidak menyimpan kredensial SIMKAH4.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Provinsi</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $user->provinsi ?: 'Belum diisi' }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Kabupaten/Kota</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $user->kota_kabupaten ?: 'Belum diisi' }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Kecamatan</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $user->kecamatan ?: 'Belum diisi' }}</p>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Nilai provinsi, kabupaten/kota, dan kecamatan diambil dari
                        <a href="{{ route('profile.daftar-desa') }}" class="font-medium underline">Profil → Daftar Desa</a>.
                        Tidak ada wilayah bawaan di aplikasi.
                    </p>

                    @unless ($wilayahLengkap)
                        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                            Lengkapi provinsi, kabupaten/kota, dan kecamatan di
                            <a href="{{ route('profile.daftar-desa') }}" class="font-semibold underline">Profil → Daftar Desa</a>
                            sebelum menjalankan script.
                        </div>
                    @endunless
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Endpoint BIMWIN Akun Ini</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Endpoint berbeda untuk setiap akun SIMKAH4. Rekam dari akun Anda sendiri; jangan memakai endpoint milik akun lain.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($endpointCount > 0)
                                <form method="POST" action="{{ route('otomasi.reset-endpoints') }}"
                                      onsubmit="return confirm('Hapus semua endpoint BIMWIN yang tersimpan? Tindakan ini tidak bisa dibatalkan.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-700 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950/40">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                        Reset Endpoint
                                    </button>
                                </form>
                            @endif
                            @if ($endpointsLengkap)
                                <span class="inline-flex w-fit items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200">
                                    {{ $endpointCount }}/4 tersimpan
                                </span>
                            @else
                                <span class="inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-200">
                                    {{ $endpointCount }}/4 — belum lengkap
                                </span>
                            @endif
                        </div>
                    </div>
                    @if ($endpointCount > 0)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Reset hanya menghapus endpoint di server. Muat ulang kode konfigurasi di panel Otomasi SIMKAH4 untuk membersihkan endpoint di browser.
                        </p>
                    @endif
                </div>

                <form method="POST" action="{{ route('otomasi.update-endpoints') }}" class="p-4 sm:p-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach ($endpointFields as $key => $field)
                            <div>
                                <label for="endpoint-{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $field['label'] }}
                                </label>
                                <input type="url" id="endpoint-{{ $key }}" name="endpoints[{{ $key }}]"
                                       value="{{ old('endpoints.' . $key, $endpoints[$key]) }}"
                                       placeholder="https://simkah4.kemenag.go.id/..."
                                       autocomplete="off" spellcheck="false"
                                       class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 @error('endpoints.' . $key) border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $field['hint'] }}</p>
                                @error('endpoints.' . $key)
                                    <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    @error('endpoints')
                        <p class="mt-3 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                        <p class="font-semibold">Cara merekam otomatis</p>
                        <p class="mt-1 text-xs">
                            Tombol <strong>Rekam endpoint</strong> ada di panel <strong>Otomasi</strong> pada SIMKAH4
                            (tab <strong>BIMWIN</strong>, kanan bawah), bukan di halaman Laporan ini.
                        </p>
                        <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs sm:text-sm">
                            <li>Di halaman Laporan ini, klik <strong>Salin Kode</strong> pada bagian <strong>Kode Konfigurasi Otomasi</strong>.</li>
                            <li>Buka <a href="https://simkah4.kemenag.go.id/" target="_blank" rel="noopener" class="font-medium underline dark:text-indigo-300">SIMKAH4</a> dengan akun ini, tempel kode ke panel <strong>Otomasi</strong> (tab <strong>BIMWIN</strong>), lalu klik <strong>Muat</strong>.</li>
                            <li>Pada bagian <strong>Endpoint akun ini</strong>, klik <strong>Rekam endpoint</strong>.</li>
                            <li>Lakukan satu alur BIMWIN secara manual: cek catin, buka dialog, isi jadwal, lalu simpan/tandai selesai.</li>
                            <li>Setelah 4/4 endpoint terekam, klik <strong>Kirim ke Laporan</strong>. Form endpoint di halaman ini terisi otomatis; periksa lalu klik <strong>Simpan Endpoint</strong>.</li>
                        </ol>
                        <p class="mt-2 text-xs">
                            Jika endpoint sudah terekam tetapi URL Laporan belum ada, tempel kode konfigurasi lalu klik <strong>Muat</strong>; endpoint yang sudah terekam tidak hilang.
                        </p>
                        <p class="mt-2 text-xs">
                            Perekaman hanya mengamati permintaan dari alur manual. Proses simpan/selesai tetap menulis ke SIMKAH4, jadi gunakan data uji atau data yang memang akan diproses.
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">
                            <x-heroicon-o-check class="h-4 w-4" />
                            {{ __('Simpan Endpoint') }}
                        </button>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Isi keempatnya, atau kosongkan semuanya untuk menghapus.</span>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Cara Pakai</h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <ol class="list-decimal space-y-3 pl-5 text-sm text-gray-700 dark:text-gray-300">
                            <li>Pasang ekstensi <strong>Tampermonkey</strong> di Chrome, Edge, atau Firefox.</li>
                            <li>Klik <strong>Install Userscript</strong> pada bagian <strong>Otomasi SIMKAH4</strong> di atas, lalu setujui pemasangan. Hapus userscript BIMWIN/Unduh Massal lama jika masih ada.</li>
                            <li>Login ke SIMKAH4 dan buka halaman daftar nikah yang ingin diproses.</li>
                            <li>Klik tombol <strong>Otomasi</strong> di kanan bawah, buka tab <strong>BIMWIN</strong>, tempel kode konfigurasi, lalu klik <strong>Muat</strong>.</li>
                            <li>Jika endpoint belum 4/4, klik <strong>Rekam endpoint</strong> di bagian <strong>Endpoint akun ini</strong>, ikuti satu alur manual, lalu klik <strong>Kirim ke Laporan</strong>. Periksa dan simpan form endpoint di halaman ini.</li>
                            <li>Jalankan <strong>Cek saja</strong> lebih dulu. Pastikan nomor daftar dan status calon sudah benar.</li>
                            <li>Setelah yakin, pilih <strong>Isi jadwal</strong> atau <strong>Isi + tandai selesai</strong>, lalu konfirmasi.</li>
                        </ol>
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            Script hanya memproses baris yang sudah dimuat di grid SIMKAH4. Atur filter dan halaman di SIMKAH4 secara manual.
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                <p class="font-semibold">Peringatan</p>
                <p class="mt-1">
                    Mode <strong>Isi jadwal</strong> dan <strong>Isi + tandai selesai</strong> benar-benar menyimpan data ke SIMKAH4, bukan ke aplikasi Laporan.
                    Gunakan hanya dengan izin/SOP yang berlaku, uji satu data terlebih dahulu, dan periksa hasilnya di SIMKAH4.
                    Endpoint internal SIMKAH4 dapat berbeda antar akun dan dapat berubah sewaktu-waktu. Rekam ulang dari akun Anda jika alur BIMWIN berubah.
                </p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
            var payload = params.get('endpoints');
            if (!payload) return;

            try {
                var endpoints = JSON.parse(payload);
                ['cek', 'dialog', 'simpan', 'selesai'].forEach(function (key) {
                    var input = document.getElementById('endpoint-' + key);
                    if (input && typeof endpoints[key] === 'string') {
                        input.value = endpoints[key];
                    }
                });

                history.replaceState(null, '', window.location.pathname + window.location.search);
            } catch (e) {
                return;
            }
        })();
    </script>
</x-app-layout>
