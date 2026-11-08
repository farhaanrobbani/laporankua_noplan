<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Otomasi') }}
            </h2>
        </div>
    </x-slot>

    @php
        $bulanNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    @endphp

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-otomasi-tabs />
            <div class="mb-6">
                <x-otomasi-setup :config-json="$configJson" />
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">

                    @if (empty($importIds))
                        <x-empty-state title="Belum ada data" message="Upload laporan peristiwa nikah dan pendaftaran nikah terlebih dahulu." :action-url="route('imports.create')" action-label="Upload Excel" />
                    @else
                        <form method="GET" action="{{ route('otomasi.surat-tugas') }}" class="mb-6 flex flex-wrap items-end gap-3">
                            <div>
                                <label for="bulan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Bulan Pelaksanaan') }}</label>
                                <select id="bulan" name="bulan" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach ($bulanNames as $angka => $nama)
                                        <option value="{{ $angka }}" @selected((int) $bulan === $angka)>{{ $nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="tahun" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Tahun Pelaksanaan') }}</label>
                                <select id="tahun" name="tahun" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                    <option value="">{{ __('Semua') }}</option>
                                    @foreach ($tahunTersedia as $tahunOption)
                                        <option value="{{ $tahunOption }}" @selected($tahun === $tahunOption)>{{ $tahunOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <label for="q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cari nama / nomor daftar') }}</label>
                                <input type="text" id="q" name="q" value="{{ $q }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm" />
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition">{{ __('Filter') }}</button>
                                @if ($bulan !== null || $tahun !== null || $q !== '')
                                    <a href="{{ route('otomasi.surat-tugas') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition">{{ __('Reset') }}</a>
                                @endif
                            </div>
                        </form>

                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Daftar nomor daftar pelaksanaan luar kantor (laporan pendaftaran nikah, Nikah Di = BEDOL) pada bulan/tahun pelaksanaan terpilih — untuk unduh massal surat tugas manten di SIMKAH4.</p>

                        @if ($totalRows === 0)
                            <x-empty-state title="Tidak ada data" message="Tidak ada pelaksanaan luar kantor yang cocok dengan filter." />
                        @else
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-4 rounded-lg bg-blue-600 px-5 py-4 text-white shadow-md">
                                <div>
                                    <p class="text-base font-semibold">
                                        <strong>{{ $totalRows }}</strong> nomor daftar pelaksanaan luar kantor
                                    </p>
                                    <p class="mt-0.5 text-xs text-blue-100">Klik tombol untuk menyalin daftar (nomor daftar - nama suami dan istri), lalu tempel ke panel Otomasi tab Surat Tugas di SIMKAH4.</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span id="salin-status" class="text-sm font-medium"></span>
                                    <button type="button" id="salin-btn" data-list='@json($daftarSalin)' class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-blue-700 shadow hover:bg-blue-50 transition">
                                        <x-heroicon-o-clipboard-document class="h-5 w-5" />
                                        {{ __('Salin Daftar Nomor Daftar') }}
                                    </button>
                                </div>
                            </div>

                            <div id="salin-fallback" hidden class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                                <p class="mb-2 text-sm font-medium text-amber-800">Salin manual: kolom di bawah sudah terpilih — tekan <kbd class="rounded bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 font-mono text-xs">Ctrl+C</kbd> (mac: <kbd class="rounded bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 font-mono text-xs">Cmd+C</kbd>).</p>
                                <textarea id="salin-fallback-textarea" readonly rows="6" class="w-full rounded-md border-amber-300 font-mono text-sm text-gray-800 focus:border-amber-400 focus:ring-amber-300"></textarea>
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-md">
                                <table class="table-sticky-first min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">No</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Nomor Daftar</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Tanggal Pelaksanaan</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Nama Suami</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">Nama Istri</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php($no = $rows->firstItem() ?? 1)
                                        @foreach ($rows as $row)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $no++ }}</td>
                                                <td class="px-3 py-2 text-gray-900 font-mono whitespace-nowrap">{{ $row['nomor_daftar'] }}</td>
                                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $row['tanggal_pelaksanaan']?->format('d-m-Y') ?? '-' }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ $row['suami'] }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ $row['istri'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>
                            <div class="mt-4">{{ $rows->links() }}</div>
                        @endif

                        <details class="mt-6 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            <summary class="cursor-pointer font-medium">{{ __('Cara pakai unduh massal surat tugas manten (SIMKAH4)') }}</summary>
                            <ol class="list-decimal ml-5 mt-2 space-y-1">
                                <li>Di laptop/PC, pasang ekstensi <strong>Tampermonkey</strong> di browser (Chrome/Edge/Firefox).</li>
                                <li>Klik <strong>Install Userscript</strong> pada bagian <strong>Otomasi SIMKAH4</strong> di atas, lalu setujui pemasangan. Hapus userscript BIMWIN/Unduh Massal lama jika masih ada.</li>
                                <li>Login ke SIMKAH4 (<span class="font-mono">https://simkah4.kemenag.go.id</span>).</li>
                                <li>Klik tombol <strong>Otomasi</strong> di kanan bawah, lalu buka tab <strong>Surat Tugas</strong>.</li>
                                <li>Sekali saja: tempel URL surat tugas apa pun (mis. dari hasil klik manual, param <span class="font-mono">no_daftar</span>) ke kolom <strong>UUID</strong> — otomatis tersimpan per jenis dokumen.</li>
                                <li>Tempel daftar nomor daftar (hasil tombol <strong>"Salin Daftar Nomor Daftar"</strong>) ke kolom daftar, lalu klik <strong>Mulai</strong>.</li>
                                <li>Browser mungkin meminta izin "unduh multiple files" — izinkan sekali. Hasil: <span class="font-mono">STM - nama suami dan istri.pdf</span>.</li>
                            </ol>
                            <p class="mt-2 text-xs text-gray-500">Script memakai sesi login SIMKAH4-mu di browser; tidak ada kredensial yang disimpan aplikasi.</p>
                        </details>

                        <script>
                            document.getElementById('salin-btn')?.addEventListener('click', async function () {
                                var items = JSON.parse(this.dataset.list || '[]');
                                var text = items.join('\n');
                                var status = document.getElementById('salin-status');
                                var fallback = document.getElementById('salin-fallback');
                                var fallbackTextarea = document.getElementById('salin-fallback-textarea');
                                var ok = false;

                                if (navigator.clipboard && window.isSecureContext) {
                                    try {
                                        await navigator.clipboard.writeText(text);
                                        ok = true;
                                    } catch (e) {
                                        ok = false;
                                    }
                                }

                                if (!ok) {
                                    fallback.hidden = false;
                                    fallbackTextarea.value = text;
                                    fallbackTextarea.focus();
                                    fallbackTextarea.select();
                                    try {
                                        ok = document.execCommand('copy');
                                    } catch (e) {
                                        ok = false;
                                    }
                                    if (ok) {
                                        fallback.hidden = true;
                                    }
                                }

                                if (ok) {
                                    status.className = 'text-sm font-semibold text-white';
                                    status.textContent = '✓ Tersalin (' + items.length + ' nomor)';
                                    setTimeout(function () { status.textContent = ''; }, 4000);
                                } else {
                                    status.className = 'text-sm font-semibold text-amber-200';
                                    status.textContent = 'Gagal otomatis — salin manual dari kolom di bawah.';
                                }
                            });
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
