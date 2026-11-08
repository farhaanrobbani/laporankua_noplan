@props(['configJson'])

<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
    <div class="p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Otomasi SIMKAH4</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    Satu userscript untuk BIMWIN, unduh massal bukti bayar, dan surat tugas. Script memakai sesi login SIMKAH4 di browser; aplikasi Laporan tidak menyimpan kredensial.
                </p>
            </div>
            <a href="{{ url('/userscripts/simkah4-otomasi.user.js') }}" target="_blank" rel="noopener"
               class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-700 transition">
                <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                {{ __('Install Userscript') }}
            </a>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div>
                <label for="otomasi-config" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kode Konfigurasi Otomasi</label>
                <textarea id="otomasi-config" readonly rows="8"
                          class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 font-mono text-xs text-gray-800 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">{{ $configJson }}</textarea>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" id="otomasi-copy"
                            class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                        <x-heroicon-o-clipboard-document class="h-4 w-4" />
                        {{ __('Salin Kode') }}
                    </button>
                    <span id="otomasi-copy-status" class="text-sm font-medium text-gray-500 dark:text-gray-400"></span>
                </div>            </div>

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                <p class="font-semibold">Cara memasang</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs sm:text-sm">
                    <li>Pasang ekstensi <strong>Tampermonkey</strong> di Chrome, Edge, atau Firefox.</li>
                    <li>Klik <strong>Install Userscript</strong>, lalu setujui pemasangan.</li>
                    <li>Hapus userscript lama <strong>BIMWIN</strong> dan <strong>Unduh Massal</strong> jika masih terpasang agar tidak ada panel ganda.</li>
                    <li>Login ke SIMKAH4, buka panel <strong>Otomasi</strong>, lalu tempel kode konfigurasi di atas dan klik <strong>Muat</strong>.</li>
                </ol>
                <p class="mt-2 text-xs">Panel Otomasi memiliki tab <strong>BIMWIN</strong>, <strong>Bukti Bayar</strong>, dan <strong>Surat Tugas</strong>.</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('otomasi-copy')?.addEventListener('click', async function () {
            var textarea = document.getElementById('otomasi-config');
            var status = document.getElementById('otomasi-copy-status');
            var ok = false;

            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(textarea.value);
                    ok = true;
                } catch (e) {
                    ok = false;
                }
            }

            if (!ok) {
                textarea.focus();
                textarea.select();
                try {
                    ok = document.execCommand('copy');
                } catch (e) {
                    ok = false;
                }
            }

            if (status) {
                status.textContent = ok ? 'Kode tersalin.' : 'Gagal menyalin. Pilih teks lalu Ctrl+C.';
                setTimeout(function () { status.textContent = ''; }, 2500);
            }
        });
    </script>
</div>
