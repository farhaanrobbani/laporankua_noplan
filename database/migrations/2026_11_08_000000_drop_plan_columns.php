<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'plan')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('plan');
            });
        }

        if (Schema::hasColumn('report_templates', 'plan_level')) {
            Schema::table('report_templates', function (Blueprint $table) {
                $table->dropColumn('plan_level');
            });
        }
    }

    public function down(): void
    {
        // Kolom plan sengaja tidak dikembalikan pada versi tanpa plan.
    }
};
