<x-profile.layout>
    <x-slot name="header">Informasi Profil</x-slot>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Informasi Profil</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Perbarui informasi akun dan kontak KUA Anda.</p>
        </div>
        <form method="post" action="{{ route('profile.update') }}" class="p-6 space-y-5">
            @csrf
            @method('patch')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nama_kepala_kua" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kepala KUA</label>
                    <input type="text" id="nama_kepala_kua" name="nama_kepala_kua" value="{{ old('nama_kepala_kua', $user->nama_kepala_kua) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_kepala_kua') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nip_kepala" class="block text-sm font-medium text-gray-700 dark:text-gray-300">NIP Kepala KUA</label>
                    <input type="text" id="nip_kepala" name="nip_kepala" value="{{ old('nip_kepala', $user->nip_kepala) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nip_kepala') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nama_petugas_stok" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Petugas Stok</label>
                    <input type="text" id="nama_petugas_stok" name="nama_petugas_stok" value="{{ old('nama_petugas_stok', $user->nama_petugas_stok) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_petugas_stok') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nip_petugas_stok" class="block text-sm font-medium text-gray-700 dark:text-gray-300">NIP Petugas Stok</label>
                    <input type="text" id="nip_petugas_stok" name="nip_petugas_stok" value="{{ old('nip_petugas_stok', $user->nip_petugas_stok) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nip_petugas_stok') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nama_kepala_kua_lama" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kepala KUA Lama</label>
                    <input type="text" id="nama_kepala_kua_lama" name="nama_kepala_kua_lama" value="{{ old('nama_kepala_kua_lama', $user->nama_kepala_kua_lama) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_kepala_kua_lama') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nip_kepala_lama" class="block text-sm font-medium text-gray-700 dark:text-gray-300">NIP Kepala KUA Lama</label>
                    <input type="text" id="nip_kepala_lama" name="nip_kepala_lama" value="{{ old('nip_kepala_lama', $user->nip_kepala_lama) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nip_kepala_lama') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nama_kepala_kemenag" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kepala Kantor Kemenag</label>
                    <input type="text" id="nama_kepala_kemenag" name="nama_kepala_kemenag" value="{{ old('nama_kepala_kemenag', $user->nama_kepala_kemenag) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nama_kepala_kemenag') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nip_kepala_kemenag" class="block text-sm font-medium text-gray-700 dark:text-gray-300">NIP Kepala Kantor Kemenag</label>
                    <input type="text" id="nip_kepala_kemenag" name="nip_kepala_kemenag" value="{{ old('nip_kepala_kemenag', $user->nip_kepala_kemenag) }}" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-full" />
                    @error('nip_kepala_kemenag') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button type="submit" style="background-color: #111827; color: #fff;" class="px-4 py-2 text-sm font-semibold rounded-md hover:opacity-90">Simpan Perubahan</button>
                @if (session('status') === 'profile-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600 dark:text-gray-400">Tersimpan.</p>
                @endif
            </div>
        </form>
    </div>
</x-profile.layout>
