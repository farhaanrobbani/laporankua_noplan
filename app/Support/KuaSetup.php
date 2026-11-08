<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class KuaSetup
{
    /**
     * @param  array{name?: string, email?: string, password?: string, kua_name?: string, provinsi?: string, kabkota?: string, kecamatan?: string}  $input
     */
    public static function ensureAdmin(array $input = []): User
    {
        $production = app()->environment('production');
        $email = trim((string) ($input['email'] ?? config('kua.admin_email') ?? ($production ? '' : 'admin@example.com')));
        $password = (string) ($input['password'] ?? config('kua.admin_password') ?? ($production ? '' : 'password'));
        $name = trim((string) ($input['name'] ?? config('kua.admin_name', 'Admin KUA')));

        if ($email === '') {
            throw new RuntimeException('KUA_ADMIN_EMAIL wajib diisi.');
        }

        if ($password === '') {
            throw new RuntimeException('KUA_ADMIN_PASSWORD wajib diisi.');
        }

        $attributes = [
            'name' => $name !== '' ? $name : 'Admin KUA',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ];

        foreach (['kua_name' => 'name', 'provinsi' => 'provinsi', 'kabkota' => 'kabkota', 'kecamatan' => 'kecamatan'] as $key => $configKey) {
            $value = trim((string) ($input[$key] ?? config('kua.'.$configKey) ?? ''));
            if ($value !== '') {
                $attributes[match ($key) {
                    'kua_name' => 'nama_kantor',
                    'kabkota' => 'kota_kabupaten',
                    default => $key,
                }] = $value;
            }
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->fill($attributes);
            $user->password = Hash::make($password);
            $user->save();

            return $user;
        }

        return User::create(array_merge($attributes, [
            'email' => $email,
            'password' => Hash::make($password),
        ]));
    }
}
