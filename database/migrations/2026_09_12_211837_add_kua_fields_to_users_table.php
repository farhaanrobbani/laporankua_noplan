<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kecamatan')->nullable()->after('email');
            $table->string('nama_kepala_kua')->nullable()->after('kecamatan');
            $table->string('nip_kepala')->nullable()->after('nama_kepala_kua');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kecamatan', 'nama_kepala_kua', 'nip_kepala']);
        });
    }
};
