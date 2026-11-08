<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use App\Rules\SimkahEndpoint;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OtomasiController extends Controller
{
    private const ENDPOINT_KEYS = ['cek', 'dialog', 'simpan', 'selesai'];

    public function index(): RedirectResponse
    {
        return redirect()->route('otomasi.bimwin');
    }

    public function bimwin(Request $request): View
    {
        $user = $request->user();

        $endpoints = $this->endpointsFor($user);
        $config = $this->configFor($user, $endpoints);

        return view('otomasi.bimwin', [
            'user' => $user,
            'endpoints' => $endpoints,
            'endpointsLengkap' => $this->endpointsComplete($endpoints),
            'configJson' => $this->encodeConfig($config),
        ]);
    }

    public function buktiBayar(Request $request): View
    {
        $user = $request->user();

        $importIds = $this->pdkImportIds($user->id);
        $pelaksanaan = $this->pdkLuarMap($user->id);

        $simponiIds = Import::where('user_id', $user->id)
            ->where('table_name', 'like', '%simponi%')
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id')
            ->toArray();

        $semuaSimponi = collect($simponiIds === [] ? [] : ImportData::whereIn('import_id', $simponiIds)
            ->get(['row_data'])
            ->pluck('row_data')
            ->filter(fn ($row) => is_array($row)))
            ->map(fn (array $row) => [
                'no_billing' => trim((string) ($row['No Billing'] ?? '')),
                'tanggal_billing' => trim((string) ($row['Tanggal Billing'] ?? '')),
                'nama' => trim((string) ($row['Nama Wajib Bayar'] ?? '')),
                'nominal' => trim((string) ($row['Nominal Setor'] ?? '')),
                'no_pendaftaran' => trim((string) ($row['No Pendaftaran'] ?? '')),
                'no_ntb' => trim((string) ($row['No NTB'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['no_billing'] !== '')
            ->values();

        $billings = $semuaSimponi
            ->filter(fn (array $row) => $pelaksanaan->has($row['no_pendaftaran']))
            ->map(function (array $row) use ($pelaksanaan) {
                $row['tanggal_pelaksanaan'] = $pelaksanaan->get($row['no_pendaftaran'])['tanggal'];

                return $row;
            })
            ->values();

        $tanpaPadanan = $semuaSimponi->count() - $billings->count();

        $tahunTersedia = $billings
            ->filter(fn (array $row) => $row['tanggal_pelaksanaan'] !== null)
            ->map(fn (array $row) => $row['tanggal_pelaksanaan']->year)
            ->unique()
            ->sortDesc()
            ->values();

        $bulan = $request->integer('bulan') ?: null;
        $tahun = $request->integer('tahun') ?: null;
        $q = trim((string) $request->query('q', ''));

        $billings = $billings->filter(function (array $row) use ($bulan, $tahun) {
            if ($bulan === null && $tahun === null) {
                return true;
            }

            $tanggal = $row['tanggal_pelaksanaan'];
            if ($tanggal === null) {
                return false;
            }

            return ($bulan === null || $tanggal->month === $bulan)
                && ($tahun === null || $tanggal->year === $tahun);
        });

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $billings = $billings->filter(fn (array $row) => mb_strpos(mb_strtolower($row['no_billing']), $needle) !== false
                || mb_strpos(mb_strtolower($row['nama']), $needle) !== false);
        }

        $billings = $billings
            ->sort(fn (array $a, array $b) => [$a['tanggal_pelaksanaan']?->getTimestamp() ?? PHP_INT_MAX, $a['no_billing']]
                <=> [$b['tanggal_pelaksanaan']?->getTimestamp() ?? PHP_INT_MAX, $b['no_billing']])
            ->values();

        $daftarSalin = $billings
            ->unique('no_billing')
            ->map(fn (array $row) => $row['nama'] !== '' ? $row['no_billing'].' - '.$row['nama'] : $row['no_billing'])
            ->values()
            ->all();

        $page = max(1, $request->integer('page'));
        $rows = new LengthAwarePaginator(
            array_slice($billings->all(), ($page - 1) * 10, 10),
            $billings->count(),
            10,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
        $rows->appends($request->query());

        return view('otomasi.bukti-bayar', [
            'user' => $user,
            'configJson' => $this->encodeConfig($this->configFor($user, $this->endpointsFor($user))),
            'importIds' => $importIds,
            'billings' => $rows,
            'totalBillings' => $billings->count(),
            'tanpaPadanan' => $tanpaPadanan,
            'daftarSalin' => $daftarSalin,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'q' => $q,
            'tahunTersedia' => $tahunTersedia,
        ]);
    }

    public function suratTugas(Request $request): View
    {
        $user = $request->user();

        $importIds = $this->pdkImportIds($user->id);

        $rows = $this->pdkLuarMap($user->id)
            ->map(function (array $info, string $nd) {
                return [
                    'nomor_daftar' => $nd,
                    'tanggal_pelaksanaan' => $info['tanggal'],
                    'suami' => $info['suami'],
                    'istri' => $info['istri'],
                ];
            });

        $tahunTersedia = $rows
            ->filter(fn (array $row) => $row['tanggal_pelaksanaan'] !== null)
            ->map(fn (array $row) => $row['tanggal_pelaksanaan']->year)
            ->unique()
            ->sortDesc()
            ->values();

        $bulan = $request->integer('bulan') ?: null;
        $tahun = $request->integer('tahun') ?: null;
        $q = trim((string) $request->query('q', ''));

        $rows = $rows->filter(function (array $row) use ($bulan, $tahun) {
            if ($bulan === null && $tahun === null) {
                return true;
            }

            $tanggal = $row['tanggal_pelaksanaan'];
            if ($tanggal === null) {
                return false;
            }

            return ($bulan === null || $tanggal->month === $bulan)
                && ($tahun === null || $tanggal->year === $tahun);
        });

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(fn (array $row) => mb_strpos(mb_strtolower($row['nomor_daftar']), $needle) !== false
                || mb_strpos(mb_strtolower($row['suami']), $needle) !== false
                || mb_strpos(mb_strtolower($row['istri']), $needle) !== false);
        }

        $rows = $rows
            ->sort(fn (array $a, array $b) => [$a['tanggal_pelaksanaan']?->getTimestamp() ?? PHP_INT_MAX, $a['nomor_daftar']]
                <=> [$b['tanggal_pelaksanaan']?->getTimestamp() ?? PHP_INT_MAX, $b['nomor_daftar']])
            ->values();

        $daftarSalin = $rows->map(function (array $row) {
            $nama = match (true) {
                $row['suami'] !== '' && $row['istri'] !== '' => $row['suami'].' dan '.$row['istri'],
                default => $row['suami'].$row['istri'],
            };

            return $nama !== '' ? $row['nomor_daftar'].' - '.$nama : $row['nomor_daftar'];
        })->values()->all();

        $page = max(1, $request->integer('page'));
        $paginator = new LengthAwarePaginator(
            array_slice($rows->all(), ($page - 1) * 10, 10),
            $rows->count(),
            10,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
        $paginator->appends($request->query());

        return view('otomasi.surat-tugas', [
            'user' => $user,
            'configJson' => $this->encodeConfig($this->configFor($user, $this->endpointsFor($user))),
            'importIds' => $importIds,
            'rows' => $paginator,
            'totalRows' => $rows->count(),
            'daftarSalin' => $daftarSalin,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'q' => $q,
            'tahunTersedia' => $tahunTersedia,
        ]);
    }

    public function updateEndpoints(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoints' => ['nullable', 'array'],
            'endpoints.cek' => ['nullable', 'string', 'max:2048', new SimkahEndpoint],
            'endpoints.dialog' => ['nullable', 'string', 'max:2048', new SimkahEndpoint],
            'endpoints.simpan' => ['nullable', 'string', 'max:2048', new SimkahEndpoint],
            'endpoints.selesai' => ['nullable', 'string', 'max:2048', new SimkahEndpoint],
        ]);

        $endpoints = [];
        foreach (self::ENDPOINT_KEYS as $key) {
            $endpoints[$key] = trim((string) ($validated['endpoints'][$key] ?? ''));
        }

        $filled = array_filter($endpoints, fn (string $value) => $value !== '');

        if ($filled !== [] && count($filled) !== count(self::ENDPOINT_KEYS)) {
            throw ValidationException::withMessages([
                'endpoints' => 'Lengkapi keempat endpoint atau kosongkan semuanya.',
            ]);
        }

        $request->user()->fill([
            'bimwin_endpoints' => $filled === [] ? null : $endpoints,
        ])->save();

        return redirect()
            ->route('otomasi.bimwin')
            ->with('status', 'Endpoint BIMWIN berhasil disimpan.');
    }

    public function resetEndpoints(Request $request): RedirectResponse
    {
        $request->user()->fill([
            'bimwin_endpoints' => null,
        ])->save();

        return redirect()
            ->route('otomasi.bimwin')
            ->with('status', 'Endpoint BIMWIN berhasil direset.');
    }

    /**
     * @param  array{cek: string, dialog: string, simpan: string, selesai: string}  $endpoints
     * @return array<string, mixed>
     */
    private function configFor(User $user, array $endpoints): array
    {
        $appUrl = rtrim(url('/'), '/');

        return [
            'appUrl' => $appUrl,
            'otomasiUrl' => $appUrl.'/otomasi',
            'bimwinUrl' => $appUrl.'/otomasi/bimwin',
            'endpoints' => $endpoints,
            'provinsi' => $user->provinsi ?: '',
            'kabkota' => $user->kota_kabupaten ?: '',
            'kecamatan' => $user->kecamatan ?: '',
            'fasilitatorSuami' => '',
            'fasilitatorIstri' => '',
            'maxCoba' => 10,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function encodeConfig(array $config): string
    {
        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{cek: string, dialog: string, simpan: string, selesai: string}
     */
    private function endpointsFor(User $user): array
    {
        $stored = is_array($user->bimwin_endpoints) ? $user->bimwin_endpoints : [];

        return [
            'cek' => (string) ($stored['cek'] ?? ''),
            'dialog' => (string) ($stored['dialog'] ?? ''),
            'simpan' => (string) ($stored['simpan'] ?? ''),
            'selesai' => (string) ($stored['selesai'] ?? ''),
        ];
    }

    /**
     * @param  array{cek: string, dialog: string, simpan: string, selesai: string}  $endpoints
     */
    private function endpointsComplete(array $endpoints): bool
    {
        foreach (self::ENDPOINT_KEYS as $key) {
            if (($endpoints[$key] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    private function parseTanggalPelaksanaan(string $value): ?Carbon
    {
        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        return null;
    }

    private function pdkImportIds(int $userId): array
    {
        return Import::where('user_id', $userId)
            ->where('table_name', 'laporan pendaftaran nikah')
            ->whereIn('status', ['success', 'appended'])
            ->pluck('id')
            ->toArray();
    }

    private function pdkLuarMap(int $userId): Collection
    {
        $importIds = $this->pdkImportIds($userId);

        return collect($importIds === [] ? [] : ImportData::whereIn('import_id', $importIds)
            ->get(['row_data'])
            ->pluck('row_data')
            ->filter(fn ($row) => is_array($row)))
            ->filter(fn (array $row) => mb_stripos((string) ($row['Nikah Di'] ?? ''), 'BEDOL') !== false)
            ->mapWithKeys(function (array $row) {
                $nd = trim((string) ($row['Nomor Daftar'] ?? ''));

                return $nd === '' ? [] : [$nd => [
                    'tanggal' => $this->parseTanggalPelaksanaan((string) ($row['Tanggal Nikah'] ?? '')),
                    'suami' => trim((string) ($row['Nama Suami'] ?? '')),
                    'istri' => trim((string) ($row['Nama Istri'] ?? '')),
                ]];
            });
    }
}
