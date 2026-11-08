<?php

use App\Models\ReportTemplate;
use Livewire\Component;

new class extends Component
{
    public $templates = [];

    public function boot(): void
    {
        $this->templates = ReportTemplate::orderBy('name')->get();
    }

    public function toggleActive(int $id): void
    {
        $template = ReportTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        $this->templates = ReportTemplate::orderBy('name')->get();
    }
}
?>

<div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400">Nama</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400">Format</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400">Kolom</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-500 dark:text-gray-400">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400">Dibuat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($templates as $template)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $template->name }}</div>
                            @if ($template->description)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Str::limit($template->description, 60) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $formatColors = [
                                    'pdf' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                    'word' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                                    'excel' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                    'print' => 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
                                ];
                                $colorClass = $formatColors[$template->output_format] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $colorClass }}">
                                {{ strtoupper($template->output_format) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ count($template->fields_json ?? []) }} kolom
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    wire:click="toggleActive({{ $template->id }})"
                                    type="button"
                                    class="relative inline-flex h-7 w-14 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 {{ $template->is_active ? 'bg-green-500' : 'bg-gray-400 dark:bg-gray-500' }}"
                                    role="switch"
                                    aria-checked="{{ $template->is_active ? 'true' : 'false' }}"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-1 ring-gray-200/50 transition duration-200 ease-in-out {{ $template->is_active ? 'translate-x-7' : 'translate-x-0' }}"
                                    />
                                </button>
                                <span class="text-xs font-medium {{ $template->is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ $template->created_at->format('d M Y') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Belum ada template.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
