<x-admin.layout>
    <x-slot name="header">Template Management</x-slot>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Daftar Template</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Aktifkan atau nonaktifkan template yang tersedia untuk pengguna.</p>
        </div>

        <div class="p-6">
            <livewire:template-toggle-list />
        </div>
    </div>
</x-admin.layout>
