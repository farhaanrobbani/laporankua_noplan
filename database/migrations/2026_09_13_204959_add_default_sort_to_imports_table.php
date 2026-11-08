<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->string('default_sort_column')->nullable()->after('table_name');
            $table->string('default_sort_direction')->nullable()->default('asc')->after('default_sort_column');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['default_sort_column', 'default_sort_direction']);
        });
    }
};
