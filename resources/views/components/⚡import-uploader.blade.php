<?php

use App\Jobs\ProcessExcelImport;
use App\Models\Import;
use App\Services\ExcelImportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?string $tmpPath = null;

    public ?string $originalName = null;

    public int $fileSize = 0;

    public ?string $sheet = null;

    /** @var string[] */
    public array $sheets = [];

    /** @var string[] */
    public array $headers = [];

    /** @var array<int, array<int, mixed>> */
    public array $rows = [];

    public int $total = 0;

    public ?string $dedupColumn = '';

    public ?string $tableName = null;

    public ?string $defaultSortColumn = null;

    public bool $isAppend = false;

    public function updatedFile(ExcelImportService $service): void
    {
        $this->reset(['tmpPath', 'originalName', 'sheet', 'sheets', 'headers', 'rows', 'total', 'tableName', 'defaultSortColumn', 'dedupColumn']);
        $this->fileSize = 0;

        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->originalName = $this->file->getClientOriginalName();
        $this->fileSize = $this->file->getSize();
        $this->tmpPath = $this->file->store('tmp/imports');
        $this->tableName = Import::parseTableName($this->originalName);

        $existingImport = Import::where('user_id', auth()->id())
            ->where('file_name', $this->originalName)
            ->latest()
            ->first();

        $this->isAppend = $existingImport !== null;

        try {
            $absolute = Storage::disk('local')->path($this->tmpPath);
            $this->sheets = $service->getSheetNames($absolute);
            $this->sheet = $this->sheets[0] ?? null;
            $this->loadPreview($service);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('file', 'File Excel tidak dapat dibaca. Pastikan file tidak rusak.');
        }

        if (empty($this->dedupColumn)) {
            $dedupByTable = [
                'laporan model l3' => 'Nomor Perforasi',
                'laporan simponi' => 'No Pendaftaran',
                'laporan akta nikah' => 'No. Daftar',
                'laporan model l5' => 'NIK Calon Suami',
            ];
            $fallback = $dedupByTable[$this->tableName] ?? null;
            if ($fallback !== null && in_array($fallback, $this->headers, true)) {
                $this->dedupColumn = $fallback;
            } elseif (in_array('Nomor Daftar', $this->headers, true) || in_array('No. Daftar', $this->headers, true)) {
                $this->dedupColumn = 'No. Daftar';
            }
        }
    }

    public function updatedSheet(ExcelImportService $service): void
    {
        if ($this->tmpPath) {
            $this->loadPreview($service);
        }
    }

    public function confirmImport(): void
    {
        $this->validate([
            'tmpPath' => 'required|string',
            'sheet' => 'required|string',
        ]);

        if (! str_starts_with($this->tmpPath, 'tmp/imports/')) {
            abort(403);
        }

        $absolute = Storage::disk('local')->path($this->tmpPath);

        if (! is_file($absolute)) {
            $this->addError('file', 'File sementara tidak ditemukan. Silakan upload ulang.');

            return;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $this->originalName) ?: 'data.xlsx';
        $permanentPath = 'imports/user_'.auth()->id().'/'.uniqid('import_', true).'_'.$safeName;

        Storage::disk('local')->move($this->tmpPath, $permanentPath);

        $import = Import::create([
            'user_id' => auth()->id(),
            'file_name' => (string) $this->originalName,
            'table_name' => $this->tableName,
            'is_append' => $this->isAppend,
            'default_sort_column' => $this->defaultSortColumn,
            'default_sort_direction' => $this->defaultSortColumn ? 'desc' : null,
            'file_path' => $permanentPath,
            'file_size' => $this->fileSize,
            'sheet_name' => $this->sheet,
            'dedup_column' => $this->dedupColumn !== '' ? $this->dedupColumn : null,
            'status' => 'pending',
        ]);

        if ($this->total < 1000) {
            app(ExcelImportService::class)->import($import->fresh());

            if (! Import::whereKey($import->id)->exists()) {
                $this->redirectRoute('imports.index');

                return;
            }
        } else {
            ProcessExcelImport::dispatch($import);

            if ($this->isAppend) {
                $this->redirectRoute('imports.index');

                return;
            }
        }

        $this->redirectRoute('imports.show', $import);
    }

    private function loadPreview(ExcelImportService $service): void
    {
        try {
            $absolute = Storage::disk('local')->path($this->tmpPath);
            $preview = $service->previewRows($absolute, $this->sheet);

            $this->headers = $preview['headers'];
            $this->rows = $preview['rows'];
            $this->total = $preview['total'];
            $this->sheet = $preview['sheet'];

            $this->defaultSortColumn = in_array('Tanggal Nikah', $this->headers) ? 'Tanggal Nikah' : null;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('file', 'Sheet tidak dapat dibaca.');
        }
    }
}
?>

<div>
    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-500">
        <p class="text-lg font-medium text-gray-700 dark:text-gray-300">Drag &amp; drop atau pilih file Excel</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Didukung: .xlsx, .xls &middot; Maks: 10MB</p>
        <input type="file" wire:model="file" accept=".xlsx,.xls" class="mt-4 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700" />
        <div wire:loading wire:target="file" class="mt-2 text-sm text-blue-600 dark:text-blue-400">Mengupload &amp; membaca file...</div>
        @error('file') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    @if ($tmpPath && empty($sheets) === false)
        <div class="mt-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <p class="text-sm text-gray-700 dark:text-gray-300"><span class="font-semibold">{{ $originalName }}</span> ({{ number_format($fileSize / 1024, 1) }} KB)</p>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:ml-auto">
                    Sheet:
                    <select wire:model.live="sheet" class="ml-2 border-gray-300 dark:border-gray-600 rounded-md text-sm">
                        @foreach ($sheets as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($tableName)
                <div class="bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-md p-4">
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Tabel: <span class="font-semibold">{{ $tableName }}</span></p>
                </div>
            @endif

            @if ($isAppend)
                <div class="bg-amber-50 dark:bg-amber-900/50 border border-amber-200 dark:border-amber-700 rounded-md p-4">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">File dengan nama sama terdeteksi. Baris baru (Nomor Daftar belum ada) akan ditambahkan. Status: <span class="font-semibold">Append</span></p>
                </div>
            @endif

            @if (! empty($headers))
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="text-sm text-gray-700 dark:text-gray-300">
                        Deduplikasi kolom:
                        <select wire:model.live="dedupColumn" class="ml-2 border-gray-300 dark:border-gray-600 rounded-md text-sm">
                            <option value="">Tidak ada</option>
                            @foreach ($headers as $header)
                                <option value="{{ $header }}">{{ $header }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($dedupColumn !== '')
                        <p class="text-xs text-amber-600 dark:text-amber-400">
                            Baris dengan {{ $dedupColumn }} yang sama di import lain akan otomatis ter-update.
                        </p>
                    @endif
                </div>
            @endif

            <p class="text-sm text-gray-500 dark:text-gray-400">Pratinjau {{ count($rows) }} dari {{ number_format($total) }} baris data.</p>

            @if (empty($headers))
                <p class="text-sm text-red-600 dark:text-red-400">Sheet ini tidak memiliki data.</p>
            @else
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                @foreach ($headers as $header)
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($headers as $i => $header)
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row[$i] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex justify-end">
                <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="confirmImport">Import Sekarang</span>
                    <span wire:loading wire:target="confirmImport">Memproses...</span>
                </button>
            </div>
        </div>
    @endif
</div>
