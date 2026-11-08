<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $query = DB::table('imports')->where(function ($query) {
            $query->where('status', 'empty')
                ->orWhere(function ($query) {
                    $query->whereIn('status', ['success', 'appended'])
                        ->where('imported_rows', 0);
                });
        });

        $paths = (clone $query)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }

        $query->delete();
    }

    public function down(): void
    {
        //
    }
};
