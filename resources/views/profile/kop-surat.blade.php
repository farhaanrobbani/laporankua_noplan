<x-profile.layout>
    <x-slot name="header">Data Kop Surat</x-slot>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Data Kop Surat</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Konfigurasi kop surat untuk laporan cetak.</p>
        </div>
        <form method="post" action="{{ route('profile.update-kop') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            @method('patch')

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_60px] gap-4 items-end">
                <div>
                    <label for="nama_kementerian" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kementerian</label>
                    <input type="text" id="nama_kementerian" name="nama_kementerian" value="{{ old('nama_kementerian', $user->nama_kementerian ?? 'Kementerian Agama') }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_kementerian') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="font_size_kop_kementerian" class="block text-sm font-medium text-gray-700 dark:text-gray-300">px</label>
                    <input type="number" id="font_size_kop_kementerian" name="font_size_kop_kementerian" min="8" max="20" value="{{ old('font_size_kop_kementerian', $user->font_size_kop_kementerian ?? '12') }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_60px] gap-4 items-end">
                <div>
                    <label for="nama_kantor_kota" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kantor Kemenag Kota</label>
                    <input type="text" id="nama_kantor_kota" name="nama_kantor_kota" value="{{ old('nama_kantor_kota', $user->nama_kantor_kota) }}" placeholder="Contoh: Kantor Kementerian Agama Kota X" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_kantor_kota') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="font_size_kop_kantor_kota" class="block text-sm font-medium text-gray-700 dark:text-gray-300">px</label>
                    <input type="number" id="font_size_kop_kantor_kota" name="font_size_kop_kantor_kota" min="8" max="20" value="{{ old('font_size_kop_kantor_kota', $user->font_size_kop_kantor_kota ?? '12') }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_60px] gap-4 items-end">
                <div>
                    <label for="nama_kantor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kantor (KUA)</label>
                    <input type="text" id="nama_kantor" name="nama_kantor" value="{{ old('nama_kantor', $user->nama_kantor) }}" placeholder="Contoh: Kantor Urusan Agama Kecamatan X" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_kantor') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="font_size_kop_kantor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">px</label>
                    <input type="number" id="font_size_kop_kantor" name="font_size_kop_kantor" min="8" max="20" value="{{ old('font_size_kop_kantor', $user->font_size_kop_kantor ?? '12') }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_60px] gap-4 items-end">
                <div>
                    <label for="alamat_kantor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat Kantor</label>
                    <input type="text" id="alamat_kantor" name="alamat_kantor" value="{{ old('alamat_kantor', $user->alamat_kantor) }}" placeholder="Jl. ..." class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('alamat_kantor') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="font_size_kop_alamat" class="block text-sm font-medium text-gray-700 dark:text-gray-300">px</label>
                    <input type="number" id="font_size_kop_alamat" name="font_size_kop_alamat" min="8" max="20" value="{{ old('font_size_kop_alamat', $user->font_size_kop_alamat ?? '10') }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_60px] gap-4 items-end">
                <div>
                    <label for="telepon_kantor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Telepon Kantor</label>
                    <input type="text" id="telepon_kantor" name="telepon_kantor" value="{{ old('telepon_kantor', $user->telepon_kantor) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('telepon_kantor') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email_kantor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Kantor</label>
                    <input type="email" id="email_kantor" name="email_kantor" value="{{ old('email_kantor', $user->email_kantor) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('email_kantor') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="font_size_kop_kontak" class="block text-sm font-medium text-gray-700 dark:text-gray-300">px</label>
                    <input type="number" id="font_size_kop_kontak" name="font_size_kop_kontak" min="8" max="20" value="{{ old('font_size_kop_kontak', $user->font_size_kop_kontak ?? '10') }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                </div>
            </div>

            <div>
                <label for="logo_kantor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Logo Kop Surat</label>
                <div class="mt-1 flex items-center gap-4">
                    @if ($user->logo_kantor)
                        <img src="{{ asset('storage/' . $user->logo_kantor) }}" alt="Logo" class="h-16 w-16 object-contain border rounded" />
                    @else
                        <div class="h-16 w-16 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded flex items-center justify-center text-gray-400 dark:text-gray-500 text-xs">Logo</div>
                    @endif
                    <div>
                        <input type="file" id="logo_kantor" name="logo_kantor" accept="image/*" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 dark:hover:file:bg-gray-600" onchange="document.getElementById('logo-preview').src = window.URL.createObjectURL(this.files[0]); document.getElementById('logo-preview').classList.remove('hidden');" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">PNG, JPG, atau SVG. Maks 2MB.</p>
                        <img id="logo-preview" src="" alt="Preview" class="h-16 w-16 object-contain border rounded mt-2 hidden" />
                    </div>
                </div>
                @error('logo_kantor') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button type="submit" style="background-color: #111827; color: #fff;" class="px-4 py-2 text-sm font-semibold rounded-md hover:opacity-90">Simpan Perubahan</button>
                @if (session('status') === 'profile-kop-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600 dark:text-gray-400">Tersimpan.</p>
                @endif
            </div>
        </form>
    </div>
</x-profile.layout>
