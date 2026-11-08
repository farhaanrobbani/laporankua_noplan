<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kota_kabupaten' => ['nullable', 'string', 'max:255'],
            'nama_kepala_kua' => ['nullable', 'string', 'max:255'],
            'nip_kepala' => ['nullable', 'string', 'max:255'],
            'nama_petugas_stok' => ['nullable', 'string', 'max:255'],
            'nip_petugas_stok' => ['nullable', 'string', 'max:255'],
            'nama_kepala_kua_lama' => ['nullable', 'string', 'max:255'],
            'nip_kepala_lama' => ['nullable', 'string', 'max:255'],
            'nama_kepala_kemenag' => ['nullable', 'string', 'max:255'],
            'nip_kepala_kemenag' => ['nullable', 'string', 'max:255'],
            'nama_kementerian' => ['nullable', 'string', 'max:255'],
            'nama_kantor_kota' => ['nullable', 'string', 'max:255'],
            'nama_kantor' => ['nullable', 'string', 'max:255'],
            'alamat_kantor' => ['nullable', 'string', 'max:255'],
            'telepon_kantor' => ['nullable', 'string', 'max:255'],
            'email_kantor' => ['nullable', 'string', 'max:255'],
            'logo_kantor' => ['nullable', 'image', 'max:2048'],
            'daftar_desa' => ['nullable', 'string'],
            'font_size_kop_kementerian' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_kantor_kota' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_kantor' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_alamat' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_kontak' => ['nullable', 'numeric', 'min:8', 'max:20'],
        ];
    }
}
