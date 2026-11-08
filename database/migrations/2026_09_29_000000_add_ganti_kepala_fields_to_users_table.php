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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_kepala_kua_lama')->nullable()->after('nip_petugas_stok');
            $table->string('nip_kepala_lama')->nullable()->after('nama_kepala_kua_lama');
            $table->string('nama_kepala_kemenag')->nullable()->after('nip_kepala_lama');
            $table->string('nip_kepala_kemenag')->nullable()->after('nama_kepala_kemenag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nama_kepala_kua_lama', 'nip_kepala_lama', 'nama_kepala_kemenag', 'nip_kepala_kemenag']);
        });
    }
};
