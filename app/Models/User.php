<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'role', 'status', 'email', 'password', 'provinsi', 'bimwin_endpoints', 'kecamatan', 'kota_kabupaten', 'nama_kepala_kua', 'nip_kepala', 'nama_petugas_stok', 'nip_petugas_stok', 'nama_kepala_kua_lama', 'nip_kepala_lama', 'nama_kepala_kemenag', 'nip_kepala_kemenag', 'nama_kementerian', 'nama_kantor_kota', 'nama_kantor', 'alamat_kantor', 'telepon_kantor', 'email_kantor', 'logo_kantor', 'daftar_desa', 'font_size_kop_kementerian', 'font_size_kop_kantor_kota', 'font_size_kop_kantor', 'font_size_kop_alamat', 'font_size_kop_kontak'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'daftar_desa' => 'array',
            'bimwin_endpoints' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function importCount(): int
    {
        return $this->imports()->count();
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }
}
