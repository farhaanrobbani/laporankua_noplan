<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_data', function (Blueprint $table) {
            $table->string('dedup_key_value')->nullable()->after('row_number');
            $table->index(['dedup_key_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_data', function (Blueprint $table) {
            $table->dropIndex(['dedup_key_value']);
            $table->dropColumn('dedup_key_value');
        });
    }
};
