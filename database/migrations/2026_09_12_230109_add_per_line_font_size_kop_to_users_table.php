<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('font_size_kop');
            $table->string('font_size_kop_kementerian')->nullable()->default('12')->after('logo_kantor');
            $table->string('font_size_kop_kantor_kota')->nullable()->default('12')->after('font_size_kop_kementerian');
            $table->string('font_size_kop_kantor')->nullable()->default('12')->after('font_size_kop_kantor_kota');
            $table->string('font_size_kop_alamat')->nullable()->default('10')->after('font_size_kop_kantor');
            $table->string('font_size_kop_kontak')->nullable()->default('10')->after('font_size_kop_alamat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'font_size_kop_kementerian',
                'font_size_kop_kantor_kota',
                'font_size_kop_kantor',
                'font_size_kop_alamat',
                'font_size_kop_kontak',
            ]);
            $table->string('font_size_kop')->nullable()->after('logo_kantor');
        });
    }
};
