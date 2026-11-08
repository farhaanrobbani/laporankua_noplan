<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_active');
        });

        DB::table('report_templates')->where('name', 'Laporan L1')->update(['sort_order' => 1]);
        DB::table('report_templates')->where('name', 'Laporan L2 Usia')->update(['sort_order' => 2]);
        DB::table('report_templates')->where('name', 'Laporan L2 Pendidikan')->update(['sort_order' => 3]);
        DB::table('report_templates')->where('name', 'Laporan L3')->update(['sort_order' => 4]);
        DB::table('report_templates')->where('name', 'Laporan L4 Simponi')->update(['sort_order' => 5]);
        DB::table('report_templates')->where('name', 'Laporan L5')->update(['sort_order' => 6]);
        DB::table('report_templates')->where('name', 'Laporan NA')->update(['sort_order' => 7]);
        DB::table('report_templates')->where('name', 'REKAP NR1')->update(['sort_order' => 8]);
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
