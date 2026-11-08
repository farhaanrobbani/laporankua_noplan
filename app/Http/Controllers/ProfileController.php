<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(): RedirectResponse
    {
        return Redirect::route('profile.info');
    }

    public function info(Request $request): View
    {
        return view('profile.info', [
            'user' => $request->user(),
        ]);
    }

    public function kopSurat(Request $request): View
    {
        return view('profile.kop-surat', [
            'user' => $request->user(),
        ]);
    }

    public function daftarDesa(Request $request): View
    {
        return view('profile.daftar-desa', [
            'user' => $request->user(),
        ]);
    }

    public function password(Request $request): View
    {
        return view('profile.password', [
            'user' => $request->user(),
        ]);
    }

    public function delete(Request $request): View
    {
        return view('profile.delete', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->only(['name', 'email', 'nama_kepala_kua', 'nip_kepala', 'nama_petugas_stok', 'nip_petugas_stok', 'nama_kepala_kua_lama', 'nip_kepala_lama', 'nama_kepala_kemenag', 'nip_kepala_kemenag']);

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.info')->with('status', 'profile-updated');
    }

    public function updateKop(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_kementerian' => ['nullable', 'string', 'max:255'],
            'nama_kantor_kota' => ['nullable', 'string', 'max:255'],
            'nama_kantor' => ['nullable', 'string', 'max:255'],
            'alamat_kantor' => ['nullable', 'string', 'max:255'],
            'telepon_kantor' => ['nullable', 'string', 'max:255'],
            'email_kantor' => ['nullable', 'string', 'max:255'],
            'logo_kantor' => ['nullable', 'image', 'max:2048'],
            'font_size_kop_kementerian' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_kantor_kota' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_kantor' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_alamat' => ['nullable', 'numeric', 'min:8', 'max:20'],
            'font_size_kop_kontak' => ['nullable', 'numeric', 'min:8', 'max:20'],
        ]);

        if ($request->hasFile('logo_kantor')) {
            $oldLogo = $request->user()->logo_kantor;
            $validated['logo_kantor'] = $request->file('logo_kantor')->store('logos', 'public');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
        } else {
            unset($validated['logo_kantor']);
        }

        $request->user()->fill($validated)->save();

        return Redirect::route('profile.kop-surat')->with('status', 'profile-kop-updated');
    }

    public function updateDesa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kota_kabupaten' => ['nullable', 'string', 'max:255'],
            'daftar_desa' => ['nullable', 'string'],
        ]);

        $desa = array_values(array_filter(array_map('trim', explode("\n", $validated['daftar_desa'] ?? ''))));

        $request->user()->fill([
            'provinsi' => ($validated['provinsi'] ?? null) ?: null,
            'kecamatan' => ($validated['kecamatan'] ?? null) ?: null,
            'kota_kabupaten' => ($validated['kota_kabupaten'] ?? null) ?: null,
            'daftar_desa' => empty($desa) ? null : $desa,
        ])->save();

        return Redirect::route('profile.daftar-desa')->with('status', 'profile-desa-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
