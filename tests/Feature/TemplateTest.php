<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function importWithRows(User $user): Import
    {
        $import = Import::factory()->for($user)->create(['status' => 'success']);

        foreach ([['Budi', 100], ['Siti', 200]] as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => ['Nama' => $row[0], 'Nilai' => $row[1]],
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    public function test_guest_tidak_bisa_akses_template(): void
    {
        $this->get('/admin/templates')->assertRedirect('/login');
    }

    public function test_non_admin_tidak_bisa_akses_template(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/admin/templates')->assertForbidden();
    }

    public function test_index_menampilkan_semua_template(): void
    {
        ReportTemplate::factory()->for($this->admin)->create(['name' => 'Template Satu', 'is_global' => true]);
        ReportTemplate::factory()->for($this->admin)->create(['name' => 'Template Dua', 'is_global' => false]);

        $this->actingAs($this->admin)->get(route('admin.templates.index'))
            ->assertOk();
    }

    public function test_global_template_bisa_dipakai_user_biasa(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'user']);
        $import = $this->importWithRows($user);

        $template = ReportTemplate::factory()->for($this->admin)->create([
            'name' => 'T Bulanan',
            'output_format' => 'print',
            'fields_json' => ['Nama', 'Nilai', 'KolomHantu'],
            'is_global' => true,
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('selectedTemplateId', $template->id)
            ->call('applySelectedTemplate')
            ->assertSet('format', 'print')
            ->assertSet('search', $template->filters_json['search'] ?? '');
    }

    public function test_builder_bisa_muat_template_default(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $template = ReportTemplate::factory()->for($this->admin)->create([
            'name' => 'Template Default',
            'output_format' => 'excel',
            'filters_json' => ['search' => 'Budi', 'filter_column' => null, 'filter_value' => null],
            'is_default' => true,
            'is_global' => true,
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->call('loadDefaultTemplate')
            ->assertSet('selectedTemplateId', $template->id)
            ->assertSet('format', 'excel')
            ->assertSet('search', 'Budi')
            ->assertSee('Laporan Peristiwa Nikah');
    }

    public function test_builder_meminta_template_sebelum_memilih_import(): void
    {
        $user = User::factory()->create();
        $this->importWithRows($user);

        ReportTemplate::factory()->for($this->admin)->create([
            'name' => 'Template Uji',
            'is_global' => true,
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->assertSee('Pilih template terlebih dahulu')
            ->assertDontSee('-- Pilih file import --');
    }

    public function test_panduan_template_nr1_mengarah_ke_pendaftaran_nikah(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan pendaftaran nikah',
        ]);
        ImportData::factory()->for($import)->create([
            'row_data' => ['Nomor Daftar' => 'ND001'],
            'row_number' => 2,
        ]);
        $template = ReportTemplate::factory()->for($this->admin)->create([
            'name' => 'REKAP NR1',
            'is_global' => true,
            'layout_json' => ['orientation' => 'portrait', 'table_layout' => ['type' => 'rekap_nr1']],
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('selectedTemplateId', $template->id)
            ->call('applySelectedTemplate')
            ->assertSee('Laporan Pendaftaran Nikah')
            ->assertSee('laporan-pendaftaran-nikah-08-2026.xlsx')
            ->assertSee('Disarankan');
    }

    public function test_panduan_template_l3_mengarah_ke_model_l3(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($this->admin)->create([
            'name' => 'Laporan L3',
            'is_global' => true,
            'layout_json' => ['orientation' => 'landscape', 'table_layout' => ['type' => 'formulir']],
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('selectedTemplateId', $template->id)
            ->call('applySelectedTemplate')
            ->assertSee('Laporan Model L3')
            ->assertSee('laporan-model-l3-08-2026.xlsx');
    }

    public function test_panduan_template_l4_mengarah_ke_simponi(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($this->admin)->create([
            'name' => 'Laporan L4 Simponi',
            'is_global' => true,
            'layout_json' => ['orientation' => 'portrait', 'table_layout' => ['type' => 'grouped_detail']],
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('selectedTemplateId', $template->id)
            ->call('applySelectedTemplate')
            ->assertSee('Laporan Simponi')
            ->assertSee('laporan-simponi-08-2026.xlsx');
    }
}
