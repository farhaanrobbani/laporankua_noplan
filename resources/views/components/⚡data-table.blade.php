<?php

use App\Models\Import;
use App\Models\ImportData;
use App\Services\MergeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $importId = null;

    /** @var int[] */
    public array $importIds = [];

    public string $joinColumn = '';

    public bool $isMergeMode = false;

    /** @var string[] */
    public array $columns = [];

    public string $search = '';

    public string $filterColumn = '';

    public string $filterValue = '';

    public string $filterMode = 'contains';

    public string $sortColumn = 'row_number';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public int $mergePage = 1;

    /** @var int[] */
    public array $selected = [];

    /** @var string[] */
    public array $editableColumns = [];

    /** @var string[] */
    public array $desaOptions = [];

    public function mount(?int $importId = null, ?array $importIds = null, ?string $joinColumn = null, ?string $filterColumn = null, ?string $filterValue = null, ?string $filterMode = null, ?array $editableColumns = null, ?string $sortColumn = null, ?string $sortDirection = null): void
    {
        if ($importIds !== null && $importIds !== []) {
            $uniqueIds = array_values(array_unique($importIds));
            if (Import::whereIn('id', $uniqueIds)->where('user_id', auth()->id())->count() !== count($uniqueIds)) {
                throw (new ModelNotFoundException)->setModel(Import::class);
            }
        }

        if ($importIds !== null && count($importIds) >= 2 && $joinColumn !== null && $joinColumn !== '') {
            $this->isMergeMode = true;
            $this->importIds = $importIds;
            $this->joinColumn = $joinColumn;
            $this->columns = array_values(array_filter(app(MergeService::class)->getAllColumns($importIds), fn ($col) => $col !== 'No'));
            $this->sortColumn = $joinColumn;
        } elseif ($importIds !== null && count($importIds) >= 1) {
            $this->isMergeMode = true;
            $this->importIds = $importIds;
            $this->columns = array_values(array_filter(app(MergeService::class)->getAllColumns($importIds), fn ($col) => $col !== 'No'));
        } elseif ($importId !== null) {
            $import = Import::where('user_id', auth()->id())->findOrFail($importId);
            $this->importId = $import->id;
            $this->columns = array_values(array_filter($import->availableColumns(), fn ($col) => $col !== 'No'));

            if ($import->default_sort_column && in_array($import->default_sort_column, $this->columns, true)) {
                $this->sortColumn = $import->default_sort_column;
                $this->sortDirection = $import->default_sort_direction ?? 'asc';
            }
        }

        if ($filterColumn !== null) {
            $this->filterColumn = $filterColumn;
        }
        if ($filterValue !== null) {
            $this->filterValue = $filterValue;
        }
        if ($filterMode !== null) {
            $this->filterMode = $filterMode;
        }

        if ($editableColumns !== null && $editableColumns !== []) {
            $this->editableColumns = array_values($editableColumns);
            $this->placeEditableColumns();

            if (in_array('Desa', $this->editableColumns, true)) {
                $this->desaOptions = auth()->user()->daftar_desa ?? [];
            }
        }

        if ($sortColumn !== null && $sortColumn !== '') {
            $allowed = array_merge(['row_number'], $this->columns);

            if (in_array($sortColumn, $allowed, true)) {
                $this->sortColumn = $sortColumn;
                $this->sortDirection = strtolower((string) $sortDirection) === 'desc' ? 'desc' : 'asc';
            }
        }
    }

    private function placeEditableColumns(): void
    {
        foreach ($this->editableColumns as $editable) {
            $this->columns = array_values(array_filter($this->columns, fn ($col) => $col !== $editable));
            $idx = array_search('Keterangan', $this->columns, true);
            if ($idx !== false) {
                array_splice($this->columns, $idx + 1, 0, [$editable]);
            } else {
                $this->columns[] = $editable;
            }
        }
    }

    public function updateCell(int $recordId, string $column, string $value): void
    {
        if (! in_array($column, $this->editableColumns, true)) {
            return;
        }

        $record = ImportData::find($recordId);
        if ($record === null) {
            return;
        }

        $import = $record->import;
        if ($import === null || $import->user_id !== auth()->id()) {
            abort(403);
        }

        $value = mb_substr(trim($value), 0, 255);

        if ($column === 'Tanggal Cetak') {
            if ($value !== '') {
                try {
                    $value = Carbon::parse($value)->format('Y-m-d');
                } catch (\Throwable) {
                    session()->flash('status', 'Tanggal Cetak tidak berubah — format tanggal tidak dikenali.');

                    return;
                }
            }
        }

        $targets = collect([$record]);

        // Baris duplikat fisik (Nomor Perforasi sama di banyak file) ikut disimpan
        // agar konsisten dengan dedup last-wins di laporan L1.
        if (in_array($column, ['Desa', 'Tanggal Cetak'], true)) {
            $perforasi = trim((string) ($record->row_data['Nomor Perforasi'] ?? ''));
            if ($perforasi !== '') {
                $l3ImportIds = Import::where('user_id', auth()->id())
                    ->where('table_name', 'laporan model l3')
                    ->pluck('id');
                $siblings = ImportData::whereIn('import_id', $l3ImportIds)
                    ->where('row_data->Nomor Perforasi', $perforasi)
                    ->get();
                if ($siblings->isNotEmpty()) {
                    $targets = $siblings;
                }
            }
        }

        foreach ($targets as $target) {
            $row = $target->row_data ?? [];
            $row[$column] = $value;
            $target->row_data = $row;
            $target->save();
        }

        session()->flash('status', $column.' disimpan untuk '.$targets->count().' baris.');
    }

    private function toInputDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function updatingSearch(): void
    {
        $this->mergePage = 1;
        $this->resetPage();
    }

    public function updatingFilterColumn(): void
    {
        $this->mergePage = 1;
        $this->resetPage();
    }

    public function updatingFilterValue(): void
    {
        $this->mergePage = 1;
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->mergePage = 1;
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $allowed = array_merge(['row_number'], $this->columns);

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->mergePage = 1;
        $this->resetPage();
    }

    public function toggleSelectAll(bool $checked): void
    {
        if ($this->isMergeMode) {
            $this->selected = $checked ? range(1, count($this->filteredMergeRows())) : [];
        } else {
            $this->selected = $checked ? $this->baseQuery()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        }
    }

    public function deleteRecord(int $id): void
    {
        if ($this->isMergeMode) {
            return;
        }

        ImportData::forImport($this->importId)->whereKey($id)->firstOrFail()->delete();
        $this->selected = array_values(array_diff($this->selected, [$id]));
        session()->flash('status', 'Record berhasil dihapus.');
    }

    public function deleteSelected(): void
    {
        if ($this->isMergeMode) {
            return;
        }

        $this->validate([
            'selected' => 'required|array|min:1',
            'selected.*' => 'integer',
        ]);

        $count = ImportData::forImport($this->importId)->whereKey($this->selected)->delete();
        $this->selected = [];
        $this->resetPage();
        session()->flash('status', $count.' record berhasil dihapus.');
    }

    public function getExportUrlProperty(): string
    {
        if ($this->isMergeMode) {
            return '#';
        }

        return route('data.export', [
            'import_id' => $this->importId,
            'search' => $this->search ?: null,
            'filter_column' => $this->filterColumn ?: null,
            'filter_value' => $this->filterValue ?: null,
            'sort_column' => $this->sortColumn !== 'row_number' ? $this->sortColumn : null,
            'sort_direction' => $this->sortDirection,
        ]);
    }

    public function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ImportData::forImport($this->importId)
            ->search($this->search)
            ->filterColumn($this->filterColumn, $this->filterValue)
            ->sortBy($this->sortColumn, $this->sortDirection);
    }

    public function mergeQuery(): array
    {
        if ($this->joinColumn === '') {
            return app(MergeService::class)->concatImports($this->importIds);
        }

        return app(MergeService::class)->mergeByColumn($this->importIds, $this->joinColumn);
    }

    public function filteredMergeRows(): array
    {
        $mergeResult = $this->mergeQuery();
        $rows = $mergeResult['rows'];

        if ($this->search !== '') {
            $lowerSearch = mb_strtolower($this->search);
            $rows = array_filter($rows, function ($row) use ($lowerSearch) {
                foreach ($row as $key => $val) {
                    if (is_string($key) && str_starts_with($key, '_')) {
                        continue;
                    }
                    if ($val !== null && mb_strpos(mb_strtolower((string) $val), $lowerSearch) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($this->filterColumn !== '' && $this->filterValue !== '') {
            $lowerFilter = mb_strtolower($this->filterValue);
            $filterCol = $this->filterColumn;
            $filterMode = $this->filterMode;
            $rows = array_filter($rows, function ($row) use ($filterCol, $lowerFilter, $filterMode) {
                $val = $row[$filterCol] ?? null;

                if ($filterMode === 'not_contains') {
                    return $val === null || mb_strpos(mb_strtolower((string) $val), $lowerFilter) === false;
                }

                if ($filterMode === 'exact') {
                    return mb_strtolower((string) $val) === $lowerFilter;
                }

                return $val !== null && mb_strpos(mb_strtolower((string) $val), $lowerFilter) !== false;
            });
        }

        $rows = array_values($rows);

        if ($this->sortColumn !== '' && $this->sortColumn !== 'row_number') {
            $sortCol = $this->sortColumn;
            $dir = $this->sortDirection;
            usort($rows, function ($a, $b) use ($sortCol, $dir) {
                return $this->compareMergeSortValues(
                    $sortCol,
                    $a[$sortCol] ?? '',
                    $b[$sortCol] ?? '',
                    $dir,
                );
            });
        }

        return $rows;
    }

    private function compareMergeSortValues(string $column, mixed $a, mixed $b, string $direction): int
    {
        if (str_contains(mb_strtolower($column), 'tanggal')) {
            $dateA = $this->parseSortDate($a);
            $dateB = $this->parseSortDate($b);

            if ($dateA === null && $dateB === null) {
                return 0;
            }

            if ($dateA === null) {
                return 1;
            }

            if ($dateB === null) {
                return -1;
            }

            $cmp = $dateA <=> $dateB;

            return $direction === 'desc' ? -$cmp : $cmp;
        }

        $cmp = strcasecmp((string) $a, (string) $b);

        return $direction === 'desc' ? -$cmp : $cmp;
    }

    private function parseSortDate(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    public function mergePaginatedRows(): array
    {
        $rows = $this->filteredMergeRows();
        $offset = max(0, ($this->mergePage - 1) * $this->perPage);

        return array_slice($rows, $offset, $this->perPage);
    }

    public function gotoMergePage(int $page): void
    {
        $this->mergePage = max(1, $page);
    }

    public function nextMergePage(): void
    {
        $total = count($this->filteredMergeRows());
        $lastPage = max(1, (int) ceil($total / $this->perPage));
        $this->mergePage = min($this->mergePage + 1, $lastPage);
    }

    public function prevMergePage(): void
    {
        $this->mergePage = max(1, $this->mergePage - 1);
    }
};
?>

<div>
    @if ($this->isMergeMode)
        <div class="mb-4 bg-blue-50 border border-blue-200 rounded-md px-4 py-3 flex items-center gap-2 text-sm text-blue-800">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            @if ($this->joinColumn !== '')
                <span>Mode Gabung: JOIN berdasarkan kolom <strong>{{ $this->joinColumn }}</strong> dari {{ count($this->importIds) }} file import</span>
            @else
                <span>Mode Gabung: Semua data dari {{ count($this->importIds) }} file import</span>
            @endif
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari data..." class="border-gray-300 rounded-md text-sm lg:w-64" />
        <select wire:model.live="filterColumn" class="border-gray-300 rounded-md text-sm">
            <option value="">Semua kolom</option>
            @foreach ($this->columns as $column)
                <option value="{{ $column }}">{{ $column }}</option>
            @endforeach
        </select>
        <input type="text" wire:model.live.debounce.300ms="filterValue" placeholder="Nilai filter..." class="border-gray-300 rounded-md text-sm lg:w-48" />
        <select wire:model.live="perPage" class="border-gray-300 rounded-md text-sm">
            <option value="10">10 / halaman</option>
            <option value="25">25 / halaman</option>
            <option value="50">50 / halaman</option>
            <option value="100">100 / halaman</option>
        </select>
        @if (! $this->isMergeMode)
            <a href="{{ $this->exportUrl }}" class="lg:ml-auto inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700">
                Export Excel
            </a>
        @endif
    </div>

    @if (! $this->isMergeMode && ! empty($this->selected))
        <div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 rounded-md px-4 py-2 text-sm">
            <span class="text-red-800 font-medium">{{ count($this->selected) }} dipilih</span>
            <button type="button" wire:click="deleteSelected" wire:confirm="Hapus {{ count($this->selected) }} record yang dipilih?" class="px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded-md hover:bg-red-700">Hapus Terpilih</button>
            <button type="button" wire:click="$set('selected', [])" class="text-xs text-gray-600 underline">Batal</button>
        </div>
    @endif

    @if ($this->isMergeMode)
        @php
            $allMergeRows = $this->filteredMergeRows();
            $mergeTotal = count($allMergeRows);
            $mergeLastPage = max(1, (int) ceil($mergeTotal / $this->perPage));
            $mergePage = max(1, $this->mergePage);
            $mergeOffset = ($mergePage - 1) * $this->perPage;
            $mergeRows = array_slice($allMergeRows, $mergeOffset, $this->perPage);
        @endphp
        @if (empty($mergeRows))
            <div class="text-center py-8">
                <p class="text-gray-500 font-medium">Tidak ada data ditemukan</p>
                <p class="text-gray-400 text-sm mt-1">Ubah kata kunci pencarian atau filter.</p>
            </div>
        @else
            <div class="overflow-x-auto border border-gray-200 rounded-md">
                <table class="table-sticky-first min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach ($this->columns as $column)
                                <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('{{ $column }}')" class="hover:text-gray-800">
                                        {{ $column }}
                                        @if ($this->joinColumn !== '' && $column === $this->joinColumn)
                                            <span class="text-blue-600 text-xs">(JOIN)</span>
                                        @endif
                                        @if ($this->sortColumn === $column) {{ $this->sortDirection === 'asc' ? '↑' : '↓' }} @endif
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($mergeRows as $record)
                            <tr wire:key="row-{{ $record['_row_id'] ?? $loop->index }}">
                                @foreach ($this->columns as $column)
                                    <td class="px-3 py-2 text-gray-700 whitespace-nowrap max-w-64 truncate">
                                        @if (in_array($column, $this->editableColumns, true) && isset($record['_row_id']))
                                            @if ($column === 'Tanggal Cetak')
                                                <input
                                                    type="date"
                                                    value="{{ $this->toInputDate((string) ($record[$column] ?? '')) }}"
                                                    wire:change="updateCell({{ $record['_row_id'] }}, '{{ $column }}', $event.target.value)"
                                                    class="w-40 border-gray-300 dark:border-gray-600 rounded text-xs px-2 py-1"
                                                />
                                            @else
                                                <input
                                                    type="text"
                                                    list="options-{{ strtolower($column) }}"
                                                    value="{{ $record[$column] ?? '' }}"
                                                    wire:change="updateCell({{ $record['_row_id'] }}, '{{ $column }}', $event.target.value)"
                                                    class="w-36 border-gray-300 dark:border-gray-600 rounded text-xs px-2 py-1"
                                                    placeholder="Isi {{ $column }}..."
                                                />
                                            @endif
                                        @else
                                            <span title="{{ $record[$column] ?? '' }}">{{ $record[$column] ?? '' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>
            @if ($mergeLastPage > 1)
                <div class="mt-4 flex items-center justify-between">
                    <p class="text-sm text-gray-700">
                        Menampilkan <span class="font-medium">{{ $mergeOffset + 1 }}</span> - <span class="font-medium">{{ min($mergeOffset + $this->perPage, $mergeTotal) }}</span> dari <span class="font-medium">{{ $mergeTotal }}</span> data
                    </p>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="gotoMergePage(1)" @disabled($mergePage <= 1)
                            class="px-3 py-1 text-sm border rounded {{ $mergePage <= 1 ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                            &laquo;
                        </button>
                        <button type="button" wire:click="prevMergePage()" @disabled($mergePage <= 1)
                            class="px-3 py-1 text-sm border rounded {{ $mergePage <= 1 ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                            &lsaquo;
                        </button>
                        @php
                            $startPage = max(1, $mergePage - 2);
                            $endPage = min($mergeLastPage, $mergePage + 2);
                        @endphp
                        @for ($i = $startPage; $i <= $endPage; $i++)
                            <button type="button" wire:click="gotoMergePage({{ $i }})"
                                class="px-3 py-1 text-sm border rounded {{ $i === $mergePage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                {{ $i }}
                            </button>
                        @endfor
                        <button type="button" wire:click="nextMergePage()" @disabled($mergePage >= $mergeLastPage)
                            class="px-3 py-1 text-sm border rounded {{ $mergePage >= $mergeLastPage ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                            &rsaquo;
                        </button>
                        <button type="button" wire:click="gotoMergePage({{ $mergeLastPage }})" @disabled($mergePage >= $mergeLastPage)
                            class="px-3 py-1 text-sm border rounded {{ $mergePage >= $mergeLastPage ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                            &raquo;
                        </button>
                    </div>
                </div>
            @endif
        @endif
    @else
        @php($records = $this->baseQuery()->paginate($this->perPage))
        @if ($records->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500 font-medium">Tidak ada data ditemukan</p>
                <p class="text-gray-400 text-sm mt-1">Ubah kata kunci pencarian atau filter.</p>
            </div>
        @else
            <div class="overflow-x-auto border border-gray-200 rounded-md">
                <table class="table-sticky-first min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2">
                                <input type="checkbox" @checked(count($this->selected) > 0) wire:change="toggleSelectAll($event.target.checked)" class="rounded" />
                            </th>
                            @foreach ($this->columns as $column)
                                <th class="px-3 py-2 text-left font-medium text-gray-500 whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('{{ $column }}')" class="hover:text-gray-800">
                                        {{ $column }}
                                        @if ($this->sortColumn === $column) {{ $this->sortDirection === 'asc' ? '↑' : '↓' }} @endif
                                    </button>
                                </th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($records as $record)
                            <tr wire:key="row-{{ $record->id }}">
                                <td class="px-3 py-2"><input type="checkbox" wire:model.live="selected" value="{{ $record->id }}" class="rounded" /></td>
                                @foreach ($this->columns as $column)
                                    <td class="px-3 py-2 text-gray-700 whitespace-nowrap max-w-64 truncate">
                                        @if (in_array($column, $this->editableColumns, true))
                                            <input
                                                type="text"
                                                list="options-{{ strtolower($column) }}"
                                                value="{{ $record->row_data[$column] ?? '' }}"
                                                wire:change="updateCell({{ $record->id }}, '{{ $column }}', $event.target.value)"
                                                class="w-36 border-gray-300 dark:border-gray-600 rounded text-xs px-2 py-1"
                                                placeholder="Isi {{ $column }}..."
                                            />
                                        @else
                                            <span title="{{ $record->row_data[$column] ?? '' }}">{{ $record->row_data[$column] ?? '' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('data.show', $record) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Detail</a>
                                    <button type="button" wire:click="deleteRecord({{ $record->id }})" wire:confirm="Hapus record ini?" class="ml-2 text-red-600 hover:text-red-800 text-sm font-medium">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 sm:hidden">Geser ke samping untuk melihat kolom lainnya.</p>
            <div class="mt-4">{{ $records->links() }}</div>
        @endif
    @endif

    @if (! empty($this->editableColumns))
        @foreach ($this->editableColumns as $editableColumn)
            <datalist id="options-{{ strtolower($editableColumn) }}">
                @foreach ($this->desaOptions as $desaOption)
                    <option value="{{ $desaOption }}"></option>
                @endforeach
            </datalist>
        @endforeach
    @endif
</div>
