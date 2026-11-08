<x-profile.layout>
    <x-slot name="header">Hapus Akun</x-slot>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Hapus Akun</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Setelah akun dihapus, semua data dan resource akan dihapus secara permanen. Pastikan Anda sudah mencadangkan data yang ingin disimpan.</p>
        </div>
        <div class="p-6">
            <button
                type="button"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                style="background-color: #DC2626; color: #fff;"
                class="px-4 py-2 text-sm font-semibold rounded-md hover:opacity-90"
            >Hapus Akun</button>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Yakin ingin menghapus akun?</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Semua data dan resource akan dihapus secara permanen. Masukkan password untuk konfirmasi.</p>

            <div class="mt-6">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 sr-only">Password</label>
                <input type="password" id="password" name="password" placeholder="Password" class="mt-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm w-3/4" />
                @error('password', 'userDeletion') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-semibold rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">Batal</button>
                <button type="submit" style="background-color: #DC2626; color: #fff;" class="px-4 py-2 text-sm font-semibold rounded-md hover:opacity-90">Hapus Akun</button>
            </div>
        </form>
    </x-modal>
</x-profile.layout>
