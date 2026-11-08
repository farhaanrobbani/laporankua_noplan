<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_kementerian')->nullable()->after('nip_kepala');
            $table->string('nama_kantor')->nullable()->after('nama_kementerian');
            $table->string('alamat_kantor')->nullable()->after('nama_kantor');
            $table->string('telepon_kantor')->nullable()->after('alamat_kantor');
            $table->string('email_kantor')->nullable()->after('telepon_kantor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nama_kementerian', 'nama_kantor', 'alamat_kantor', 'telepon_kantor', 'email_kantor']);
        });
    }
};
