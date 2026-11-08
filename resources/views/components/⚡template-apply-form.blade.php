<?php

use App\Models\Import;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\MergeService;
use Livewire\Component;

new class extends Component
{
    public ReportTemplate $template;

    /** @var array<int, array{id: int, table_name: string|null, total_rows: int, import_ids: int[]}> */
    public array $imports = [];

    public bool $isMergeMode = false;

    /** @var int[] */
    public array $mergeImportIds = [];

    public string $joinColumn = '';

    /** @var string[] */
    public array $sharedColumns = [];

    public string $title = '';

    public ?int $importId = null;

    public string $filterMonth = '';

    public string $filterYear = '';

    public function mount(ReportTemplate $template): void
    {
        if (! $template->is_active) {
            $this->dispatch('show-toast', message: 'Template tidak aktif.', type: 'error');

            return;
        }

        $this->template = $template;
        $this->title = $template->name;

        $this->imports = Import::where('user_id', auth()->id())
            ->latest()
            ->withCount('importData')
            ->get(['id', 'file_name', 'table_name'])
            ->groupBy('table_name')
            ->map(fn ($rows, $tableName) => [
                'id' => $rows->first()->id,
                'table_name' => $tableName,
                'total_rows' => $rows->sum('import_data_count'),
                'import_ids' => $rows->pluck('id')->toArray(),
            ])
            ->values()
            ->all();
    }

    public function updatedMergeImportIds(): void
    {
        $this->sharedColumns = app(MergeService::class)->getSharedColumns($this->mergeImportIds);
        $this->joinColumn = '';
    }

    public function apply(): void
    {
        if ($this->isMergeMode) {
            $this->applyMerge();

            return;
        }

        $this->validate([
            'importId' => 'required|integer',
            'title' => 'required|string|max:255',
        ]);

        $import = Import::where('user_id', auth()->id())->findOrFail($this->importId);

        $available = $import->availableColumns();
        $fields = array_values(array_intersect($this->template->fields_json ?? [], $available));

        if ($fields === []) {
            $fields = $available;
        }

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        if ($hasFilter && ! in_array('Tanggal Nikah', $fields, true)) {
            if (in_array('Tanggal Nikah', $available, true)) {
                $fields[] = 'Tanggal Nikah';
            }
        }

        $filters = $this->template->filters_json ?? [];
        $sorting = $this->template->sorting_json ?? [];
        $layout = $this->template->layout_json ?? [];
        $user = $this->getUserConfig();

        $report = Report::create([
            'user_id' => auth()->id(),
            'report_template_id' => $this->template->id,
            'import_id' => $import->id,
            'title' => $this->title,
            'output_format' => $this->template->output_format,
            'config_json' => array_merge([
                'fields' => $fields,
                'search' => $filters['search'] ?? null,
                'filter_column' => $filters['filter_column'] ?? null,
                'filter_value' => $filters['filter_value'] ?? null,
                'sort_column' => $sorting['column'] ?? null,
                'sort_direction' => $sorting['direction'] ?? 'asc',
                'orientation' => $layout['orientation'] ?? 'portrait',
                'filter_month' => $this->filterMonth ?: null,
                'filter_year' => $this->filterYear ?: null,
            ], $user, ! empty($layout['table_layout']) ? ['table_layout' => $layout['table_layout']] : []),
            'status' => $this->template->output_format === 'print' ? 'generated' : 'pending',
            'generated_at' => $this->template->output_format === 'print' ? now() : null,
        ]);

        if ($this->template->output_format === 'print') {
            $this->redirectRoute('reports.print', $report);

            return;
        }

        \App\Jobs\GenerateReport::dispatch($report);
        $this->redirectRoute('reports.index');
    }

    private function applyMerge(): void
    {
        $this->validate([
            'mergeImportIds' => 'required|array|min:2',
            'title' => 'required|string|max:255',
        ]);

        $firstImport = Import::where('id', $this->mergeImportIds[0])->first();

        if (! $firstImport) {
            $this->addError('mergeImportIds', 'Sumber data tidak valid.');

            return;
        }

        $allColumns = app(MergeService::class)->getAllColumns($this->mergeImportIds);
        $fields = array_values(array_intersect($this->template->fields_json ?? [], $allColumns));

        if ($fields === []) {
            $fields = $allColumns;
        }

        $hasFilter = $this->filterMonth !== '' || $this->filterYear !== '';
        if ($hasFilter && ! in_array('Tanggal Nikah', $fields, true)) {
            if (in_array('Tanggal Nikah', $allColumns, true)) {
                $fields[] = 'Tanggal Nikah';
            }
        }

        $filters = $this->template->filters_json ?? [];
        $sorting = $this->template->sorting_json ?? [];
        $layout = $this->template->layout_json ?? [];
        $user = $this->getUserConfig();

        $report = Report::create([
            'user_id' => auth()->id(),
            'report_template_id' => $this->template->id,
            'import_id' => $firstImport->id,
            'title' => $this->title,
            'output_format' => $this->template->output_format,
            'config_json' => array_merge([
                'fields' => $fields,
                'search' => $filters['search'] ?? null,
                'filter_column' => $filters['filter_column'] ?? null,
                'filter_value' => $filters['filter_value'] ?? null,
                'sort_column' => $sorting['column'] ?? null,
                'sort_direction' => $sorting['direction'] ?? 'asc',
                'orientation' => $layout['orientation'] ?? 'portrait',
                'is_merged' => true,
                'merged_import_ids' => $this->mergeImportIds,
                'filter_month' => $this->filterMonth ?: null,
                'filter_year' => $this->filterYear ?: null,
            ], $user, ! empty($layout['table_layout']) ? ['table_layout' => $layout['table_layout']] : []),
            'status' => $this->template->output_format === 'print' ? 'generated' : 'pending',
            'generated_at' => $this->template->output_format === 'print' ? now() : null,
        ]);

        if ($this->template->output_format === 'print') {
            $this->redirectRoute('reports.print', $report);

            return;
        }

        \App\Jobs\GenerateReport::dispatch($report);
        $this->redirectRoute('reports.index');
    }

    private function getUserConfig(): array
    {
        $user = auth()->user();

        return [
            'kecamatan' => $user->kecamatan ?? null,
            'nama_kepala_kua' => $user->nama_kepala_kua ?? null,
            'nip_kepala' => $user->nip_kepala ?? null,
            'nama_kementerian' => $user->nama_kementerian ?? null,
            'nama_kantor_kota' => $user->nama_kantor_kota ?? null,
            'nama_kantor' => $user->nama_kantor ?? null,
            'alamat_kantor' => $user->alamat_kantor ?? null,
            'telepon_kantor' => $user->telepon_kantor ?? null,
            'email_kantor' => $user->email_kantor ?? null,
            'logo_kantor' => $user->logo_kantor ?? null,
            'daftar_desa' => $user->daftar_desa ?? null,
            'font_size_kop_kementerian' => $user->font_size_kop_kementerian ?? null,
            'font_size_kop_kantor_kota' => $user->font_size_kop_kantor_kota ?? null,
            'font_size_kop_kantor' => $user->font_size_kop_kantor ?? null,
            'font_size_kop_alamat' => $user->font_size_kop_alamat ?? null,
            'font_size_kop_kontak' => $user->font_size_kop_kontak ?? null,
        ];
    }
}
?>

<div class="space-y-5">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Template <span class="font-semibold">{{ $template->name }}</span>
        ({{ strtoupper($template->output_format) }}, {{ count($template->fields_json ?? []) }} kolom)
        akan diterapkan ke file yang Anda pilih. Kolom yang tidak ada di file target dilewati otomatis.
    </p>

    {{-- Mode Toggle --}}
    @if (count($imports) >= 2)
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1 w-fit">
            <button type="button" wire:click="$set('isMergeMode', false)"
                class="px-4 py-2 text-sm font-medium rounded-md transition {{ ! $this->isMergeMode ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                Single Import
            </button>
            <button type="button" wire:click="$set('isMergeMode', true)"
                class="px-4 py-2 text-sm font-medium rounded-md transition {{ $this->isMergeMode ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                Gabung Data
            </button>
        </div>
    @endif

    {{-- Single Import Mode --}}
    @if (! $this->isMergeMode)
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">File target</label>
            <select wire:model.live="importId" class="mt-1 border-gray-300 dark:border-gray-600 rounded-md text-sm w-full">
                <option value="">-- Pilih file import --</option>
                @foreach ($imports as $import)
                    <option value="{{ $import['id'] }}">{{ $import['table_name'] }} ({{ number_format($import['total_rows']) }} baris)</option>
                @endforeach
            </select>
            @error('importId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    @endif

    {{-- Merge Mode --}}
    @if ($this->isMergeMode)
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pilih file import (minimal 2)</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($imports as $import)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 cursor-pointer hover:border-blue-400">
                        <input type="checkbox" wire:model.live="mergeImportIds" value="{{ $import['id'] }}" class="rounded text-blue-600 dark:text-blue-400" />
                        {{ $import['table_name'] }} ({{ number_format($import['total_rows']) }} baris)
                    </label>
                @endforeach
            </div>
            @error('mergeImportIds') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    @endif

    {{-- Filter Bulan/Tahun (print only) --}}
    @if ($template->output_format === 'print')
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter Akad/Pelaksanaan</label>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Bulan</label>
                    <select wire:model.live="filterMonth" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full">
                        <option value="">Semua Bulan</option>
                        <option value="1">Januari</option>
                        <option value="2">Februari</option>
                        <option value="3">Maret</option>
                        <option value="4">April</option>
                        <option value="5">Mei</option>
                        <option value="6">Juni</option>
                        <option value="7">Juli</option>
                        <option value="8">Agustus</option>
                        <option value="9">September</option>
                        <option value="10">Oktober</option>
                        <option value="11">November</option>
                        <option value="12">Desember</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tahun</label>
                    <select wire:model.live="filterYear" class="border-gray-300 dark:border-gray-600 rounded-md text-sm w-full">
                        <option value="">Semua Tahun</option>
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>
        </div>
    @endif

    {{-- Title --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Judul laporan</label>
        <input type="text" wire:model.live="title" class="mt-1 border-gray-300 dark:border-gray-600 rounded-md text-sm w-full" />
        @error('title') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    {{-- Actions --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('reports.create') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-semibold rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">Batal</a>
        <button type="button" wire:click="apply" class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700">Buat Laporan</button>
    </div>
</div>
