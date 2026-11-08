<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function importWithRows(User $user): Import
    {
        $import = Import::factory()->for($user)->create(['status' => 'success']);
        ImportData::factory()->for($import)->create([
            'row_data' => ['Nama' => 'Budi'],
            'row_number' => 2,
        ]);

        return $import;
    }

    public function test_builder_menolak_import_milik_orang(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $importB = $this->importWithRows($userB);

        Livewire::actingAs($userA)
            ->test('report-builder')
            ->set('importId', $importB->id)
            ->set('title', 'Nekat')
            ->call('generate')
            ->assertHasErrors(['importId']);

        $this->assertSame(0, Report::count());
    }

    public function test_sort_injection_diabaikan(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->call('sortBy', 'row_number; DROP TABLE laporan_users; --')
            ->assertSee('Budi');

        $this->assertDatabaseHas('imports', ['id' => $import->id]);
    }

    public function test_export_sort_berbahaya_fallback_aman(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $this->actingAs($user)
            ->get('/data/export?import_id='.$import->id.'&sort_column=(SELECT 1)&sort_direction=desc')
            ->assertOk();
    }

    public function test_download_file_hilang_redirect_dengan_pesan(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $report = Report::factory()->for($user)->for($import)->create([
            'file_path' => 'reports/hilang.pdf',
            'status' => 'generated',
        ]);

        $this->actingAs($user)->get(route('reports.download', $report))
            ->assertRedirect(route('reports.show', $report))
            ->assertSessionHas('status');
    }

    public function test_rate_limit_aktif(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit');
    }
}
