<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_kantor_kota')->nullable()->after('nama_kementerian');
            $table->string('logo_kantor')->nullable()->after('email_kantor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nama_kantor_kota', 'logo_kantor']);
        });
    }
};
