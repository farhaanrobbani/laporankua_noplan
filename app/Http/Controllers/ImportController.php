<?php

namespace App\Http\Controllers;

use App\Models\Import;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $imports = Import::where('user_id', auth()->id())
            ->withCount('importData')
            ->orderByDesc('id')
            ->get();

        $grouped = $imports->groupBy('table_name')->map(function (Collection $rows, string $tableName) {
            $latest = $rows->first();

            $fileNames = $rows->pluck('file_name')->toArray();
            $statusCounts = $rows->pluck('status')->countBy()->toArray();

            return (object) [
                'table_name' => $tableName,
                'latest_id' => $latest->id,
                'first_id' => $rows->last()->id,
                'file_names_list' => $fileNames,
                'file_count' => count($fileNames),
                'total_rows' => $rows->sum('total_rows'),
                'imported_rows' => $rows->sum('imported_rows'),
                'failed_rows' => $rows->sum('failed_rows'),
                'status_summary' => $statusCounts,
                'latest_created_at' => $latest->created_at,
            ];
        });

        $perPage = 10;
        $page = request()->get('page', 1);
        $paginated = new LengthAwarePaginator(
            $grouped->slice(($page - 1) * $perPage, $perPage),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => route('imports.index')],
        );

        return view('imports.index', ['imports' => $paginated]);
    }

    public function create(): View
    {
        return view('imports.upload');
    }

    public function show(Import $import): View
    {
        $this->authorize('view', $import);

        $imports = Import::where('user_id', auth()->id())
            ->where('table_name', $import->table_name)
            ->withCount('importData', 'reports')
            ->orderBy('id')
            ->get();

        return view('imports.show', [
            'tableName' => $import->table_name,
            'imports' => $imports,
        ]);
    }

    public function data(Import $import): View
    {
        $this->authorize('view', $import);

        return view('imports.data', [
            'import' => $import,
        ]);
    }

    public function destroy(Import $import): RedirectResponse
    {
        $this->authorize('delete', $import);

        if ($import->reports()->count() > 0) {
            return back()->withErrors([
                'import' => 'Import ini masih digunakan oleh '.$import->reports()->count().' laporan. Hapus laporan terlebih dahulu.',
            ]);
        }

        foreach ($import->reports as $report) {
            if ($report->file_path) {
                Storage::disk('local')->delete($report->file_path);
            }
        }

        Storage::disk('local')->delete($import->file_path);
        $import->delete();

        return redirect()->route('imports.index')
            ->with('status', 'Data import berhasil dihapus.');
    }
}
