<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CetakNbTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('cetak-nb.index'))->assertRedirect('/login');
    }

    public function test_index_otomatis_menampilkan_semua_data_peristiwa_nikah(): void
    {
        $user = User::factory()->create();

        $first = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan peristiwa nikah',
        ]);
        ImportData::factory()->for($first)->create([
            'row_data' => ['Nomor Akta Nikah' => 'NB-001'],
            'row_number' => 2,
        ]);

        $second = Import::factory()->for($user)->create([
            'status' => 'appended',
            'table_name' => 'laporan peristiwa nikah',
        ]);
        ImportData::factory()->for($second)->create([
            'row_data' => ['Nomor Akta Nikah' => 'NB-002'],
            'row_number' => 2,
        ]);

        $other = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan model l3',
        ]);
        ImportData::factory()->for($other)->create([
            'row_data' => ['Nomor Akta Nikah' => 'BUKAN-NB'],
            'row_number' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('cetak-nb.index'))
            ->assertOk()
            ->assertSee('Sumber data: Peristiwa Nikah')
            ->assertSee('2 file peristiwa nikah')
            ->assertSee('NB-001')
            ->assertSee('NB-002')
            ->assertDontSee('BUKAN-NB')
            ->assertDontSee('-- Pilih file import --');
    }

    public function test_index_empty_state_jika_belum_ada_peristiwa_nikah(): void
    {
        $user = User::factory()->create();

        $other = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan model l3',
        ]);
        ImportData::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('cetak-nb.index'))
            ->assertOk()
            ->assertSee('Belum ada data peristiwa nikah')
            ->assertDontSee('Sumber data: Peristiwa Nikah');
    }
}
