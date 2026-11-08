<x-admin.layout>
    <x-slot name="header">Pengaturan Website</x-slot>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 max-w-xl">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pengaturan Umum</h3>
        </div>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="site_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Website</label>
                <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" required class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm" />
                @error('site_name') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="site_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi Website</label>
                <input type="text" id="site_description" name="site_description" value="{{ old('site_description', $settings['site_description']) }}" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm" />
                @error('site_description') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Kontak</label>
                <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm" />
                @error('contact_email') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="footer_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teks Footer</label>
                <input type="text" id="footer_text" name="footer_text" value="{{ old('footer_text', $settings['footer_text']) }}" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md text-sm" />
                @error('footer_text') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="pt-2">
                <button type="submit" style="background-color: #111827; color: #fff;" class="px-4 py-2 text-sm font-semibold rounded-md hover:opacity-90">Simpan Pengaturan</button>
            </div>
        </form>
    </div>
</x-admin.layout>
