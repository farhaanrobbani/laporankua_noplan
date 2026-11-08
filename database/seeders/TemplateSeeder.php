<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('role', 'admin')->orderBy('id')->first();

        if (! $owner) {
            throw new RuntimeException('Buat akun admin terlebih dahulu dengan php artisan kua:setup.');
        }

        // REKAP NR1
        ReportTemplate::firstOrCreate(
            ['name' => 'REKAP NR1'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 8,
                'output_format' => 'print',
                'description' => 'Rekapitulasi nikah/rujuk',
                'fields_json' => ['Nama Suami', 'Nama Istri', 'Tanggal Nikah', 'Kelurahan', 'Tanggal Daftar', 'Nikah Di'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Tanggal Daftar', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'portrait',
                    'table_layout' => [
                        'type' => 'rekap_nr1',
                        'aggregation' => [
                            'date_filter_field' => 'Tanggal Daftar',
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No', 'rowspan' => 2],
                            ['type' => 'group', 'label' => 'Nama', 'colspan' => 2, 'children' => [
                                ['type' => 'field', 'field' => 'Nama Suami', 'label' => 'Suami'],
                                ['type' => 'field', 'field' => 'Nama Istri', 'label' => 'Istri'],
                            ]],
                            ['type' => 'field', 'field' => 'Kelurahan', 'label' => 'Alamat', 'rowspan' => 2],
                            ['type' => 'group', 'label' => 'Tanggal', 'colspan' => 2, 'children' => [
                                ['type' => 'field', 'field' => 'Tanggal Daftar', 'label' => 'Daftar', 'width' => '100px', 'align' => 'center'],
                                ['type' => 'field', 'field' => 'Tanggal Nikah', 'label' => 'Nikah', 'width' => '100px', 'align' => 'center'],
                            ]],
                            ['type' => 'field', 'field' => 'Nikah Di', 'label' => 'K/LK', 'rowspan' => 2, 'transform' => 'klinik_balai_nikah'],
                        ],
                    ],
                ],
            ]
        );

        // Laporan L2 Pendidikan
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan L2 Pendidikan'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 3,
                'output_format' => 'print',
                'description' => 'Laporan rekapitulasi pendidikan pengantin berdasarkan desa',
                'fields_json' => ['Kelurahan', 'Pendidikan Suami', 'Pendidikan Istri'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Kelurahan', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'aggregation' => [
                            'group_by' => 'Kelurahan',
                            'static_values' => [
                                'ARGOYUWONO', 'LEBAKHARJO', 'MULYOASRI', 'PURWOHARJO',
                                'SIDORENGGO', 'SIMOJAYAN', 'SONOWANGI', 'TAMANASRI',
                                'TAMANSARI', 'TAWANGAGUNG', 'TIRTOMARTO', 'TIRTOMOYO', 'WIROTAMAN',
                            ],
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No', 'rowspan' => 3],
                            ['type' => 'field', 'field' => 'Kelurahan', 'label' => 'Desa', 'rowspan' => 3],
                            ['type' => 'aggregate_total', 'label' => 'Jumlah Perkawinan', 'rowspan' => 3],
                            ['type' => 'group', 'label' => 'Pendidikan Pengantin', 'colspan' => 12, 'children' => [
                                ['type' => 'group', 'label' => 'Laki-laki', 'colspan' => 6, 'children' => [
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Suami', 'match' => ['SD'], 'label' => 'SD'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Suami', 'match' => ['SLTP'], 'label' => 'SMP'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Suami', 'match' => ['SLTA'], 'label' => 'SMA'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Suami', 'match' => ['SARJANA MUDA', 'STRATA I', 'DIPLOMA III', 'DIPLOMA IV'], 'label' => 'S1'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Suami', 'match' => ['STRATA II'], 'label' => 'S2'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Suami', 'match' => ['STRATA III'], 'label' => 'S3'],
                                ]],
                                ['type' => 'group', 'label' => 'Perempuan', 'colspan' => 6, 'children' => [
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Istri', 'match' => ['SD'], 'label' => 'SD'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Istri', 'match' => ['SLTP'], 'label' => 'SMP'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Istri', 'match' => ['SLTA'], 'label' => 'SMA'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Istri', 'match' => ['SARJANA MUDA', 'STRATA I', 'DIPLOMA III', 'DIPLOMA IV'], 'label' => 'S1'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Istri', 'match' => ['STRATA II'], 'label' => 'S2'],
                                    ['type' => 'aggregate_count', 'field' => 'Pendidikan Istri', 'match' => ['STRATA III'], 'label' => 'S3'],
                                ]],
                            ]],
                        ],
                    ],
                ],
            ]
        );

        // Laporan L2 Usia
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan L2 Usia'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 2,
                'output_format' => 'print',
                'description' => 'Laporan rekapitulasi usia pengantin berdasarkan desa',
                'fields_json' => ['Kelurahan', 'Usia Suami', 'Usia Istri'],
                'filters_json' => ['Tanggal Nikah'],
                'sorting_json' => ['Kelurahan' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'aggregation' => [
                            'group_by' => 'Kelurahan',
                            'static_values' => [
                                'ARGOYUWONO', 'LEBAKHARJO', 'MULYOASRI', 'PURWOHARJO',
                                'SIDORENGGO', 'SIMOJAYAN', 'SONOWANGI', 'TAMANASRI',
                                'TAMANSARI', 'TAWANGAGUNG', 'TIRTOMARTO', 'TIRTOMOYO', 'WIROTAMAN',
                            ],
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No', 'rowspan' => 3],
                            ['type' => 'field', 'field' => 'Kelurahan', 'label' => 'Desa', 'rowspan' => 3],
                            ['type' => 'aggregate_total', 'label' => 'Jumlah Perkawinan', 'rowspan' => 3],
                            ['type' => 'group', 'label' => 'Usia Pengantin', 'colspan' => 8, 'children' => [
                                ['type' => 'group', 'label' => 'Laki-laki', 'colspan' => 4, 'children' => [
                                    ['type' => 'aggregate_range', 'field' => 'Usia Suami', 'min' => null, 'max' => 19, 'label' => '<19'],
                                    ['type' => 'aggregate_range', 'field' => 'Usia Suami', 'min' => 19, 'max' => 21, 'label' => '19-20'],
                                    ['type' => 'aggregate_range', 'field' => 'Usia Suami', 'min' => 21, 'max' => 31, 'label' => '21-30'],
                                    ['type' => 'aggregate_range', 'field' => 'Usia Suami', 'min' => 31, 'max' => null, 'label' => '>30'],
                                ]],
                                ['type' => 'group', 'label' => 'Perempuan', 'colspan' => 4, 'children' => [
                                    ['type' => 'aggregate_range', 'field' => 'Usia Istri', 'min' => null, 'max' => 19, 'label' => '<19'],
                                    ['type' => 'aggregate_range', 'field' => 'Usia Istri', 'min' => 19, 'max' => 21, 'label' => '19-20'],
                                    ['type' => 'aggregate_range', 'field' => 'Usia Istri', 'min' => 21, 'max' => 31, 'label' => '21-30'],
                                    ['type' => 'aggregate_range', 'field' => 'Usia Istri', 'min' => 31, 'max' => null, 'label' => '>30'],
                                ]],
                            ]],
                        ],
                    ],
                ],
            ]
        );

        // Laporan L4 Simponi
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan L4 Simponi'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 5,
                'output_format' => 'print',
                'description' => 'Laporan rekapitulasi setoran simponi berdasarkan desa',
                'fields_json' => ['Nama Kelurahan', 'Tanggal dan Jam Setor', 'Nominal Setor', 'Tanggal Akad', 'Nama Suami'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Nama Kelurahan', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'portrait',
                    'table_layout' => [
                        'type' => 'grouped_detail',
                        'aggregation' => [
                            'group_by' => 'Nama Kelurahan',
                            'date_filter_field' => 'Tanggal Akad',
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No'],
                            ['type' => 'field', 'field' => 'Nama Kelurahan', 'label' => 'Desa'],
                            ['type' => 'aggregate_total', 'label' => 'Jumlah Perkawinan'],
                            ['type' => 'field', 'field' => 'Tanggal dan Jam Setor', 'label' => 'Tanggal Setor', 'format' => 'date_id'],
                            ['type' => 'static', 'value' => '600000', 'label' => 'Jumlah Setor'],
                            ['type' => 'field', 'field' => 'Tanggal Akad', 'label' => 'Tanggal Perkawinan', 'format' => 'date_id'],
                            ['type' => 'field', 'field' => 'Nama Suami', 'label' => 'Penyetor'],
                        ],
                    ],
                ],
            ]
        );

        // Laporan L5
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan L5'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 6,
                'output_format' => 'print',
                'description' => 'Laporan rekapitulasi sertifikat suscatin berdasarkan desa',
                'fields_json' => ['Kelurahan'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Kelurahan', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'portrait',
                    'table_layout' => [
                        'aggregation' => [
                            'group_by' => 'Kelurahan',
                            'static_values' => [
                                'ARGOYUWONO', 'LEBAKHARJO', 'MULYOASRI', 'PURWOHARJO',
                                'SIDORENGGO', 'SIMOJAYAN', 'SONOWANGI', 'TAMANASRI',
                                'TAMANSARI', 'TAWANGAGUNG', 'TIRTOMARTO', 'TIRTOMOYO', 'WIROTAMAN',
                            ],
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No', 'rowspan' => 3],
                            ['type' => 'field', 'field' => 'Kelurahan', 'label' => 'Desa', 'rowspan' => 3],
                            ['type' => 'aggregate_total', 'label' => 'Jumlah Perkawinan', 'rowspan' => 3],
                            ['type' => 'group', 'label' => 'Memiliki Sertifikat Suscatin', 'colspan' => 2, 'children' => [
                                ['type' => 'aggregate_total', 'label' => 'Laki-laki'],
                                ['type' => 'aggregate_total', 'label' => 'Perempuan'],
                            ]],
                        ],
                    ],
                ],
            ]
        );
        // Laporan L3
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan L3'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 4,
                'output_format' => 'print',
                'description' => 'Laporan Formulir Perkawinan atau Rujuk',
                'fields_json' => ['Tanggal Cetak', 'Keterangan', 'Status', 'Nama Catin'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Tanggal Cetak', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'type' => 'formulir',
                        'na_version_prefix_length' => 6,
                        'aggregation' => [
                            'date_filter_field' => 'Tanggal Cetak',
                        ],
                        'static_rows' => [
                            ['formulir' => 'Model N', 'row_num' => 1, 'dynamic' => false],
                            ['formulir' => 'Model NA', 'row_num' => 2, 'dynamic' => true, 'dynamic_type' => 'na_version'],
                            ['formulir' => 'Model DN', 'row_num' => 3, 'dynamic' => false],
                            ['formulir' => 'Model NB', 'row_num' => 4, 'dynamic' => false],
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No', 'rowspan' => 2],
                            ['type' => 'static_value', 'field' => 'formulir', 'label' => 'Nama Formulir', 'rowspan' => 2],
                            ['type' => 'group', 'label' => 'Masuk', 'colspan' => 2, 'children' => [
                                ['type' => 'manual', 'label' => 'Jumlah'],
                                ['type' => 'manual', 'label' => 'Seri Porporasi'],
                            ]],
                            ['type' => 'group', 'label' => 'Keluar', 'colspan' => 2, 'children' => [
                                ['type' => 'stok_keluar', 'label' => 'Jumlah'],
                                ['type' => 'porporasi_range', 'label' => 'Seri Porporasi'],
                            ]],
                            ['type' => 'group', 'label' => 'Sisa', 'colspan' => 2, 'children' => [
                                ['type' => 'stok_sisa', 'label' => 'Jumlah'],
                                ['type' => 'manual', 'label' => 'Seri Porporasi'],
                            ]],
                            ['type' => 'manual', 'label' => 'Keterangan', 'rowspan' => 2],
                        ],
                    ],
                ],
            ]
        );
        // Laporan NA
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan NA'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 7,
                'output_format' => 'print',
                'description' => 'Laporan stok formulir NA/RA/DN',
                'fields_json' => ['Tanggal Nikah', 'Keterangan', 'Nama Catin', 'Nomor Perforasi'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Tanggal Nikah', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'type' => 'laporan_na',
                        'aggregation' => [
                            'group_by' => 'Tanggal Nikah',
                        ],
                        'columns' => [
                            ['type' => 'field', 'field' => 'Tanggal Nikah', 'label' => 'Tanggal', 'rowspan' => 2, 'format' => 'date_id'],
                            ['type' => 'field', 'field' => 'Uraian', 'label' => 'Uraian', 'rowspan' => 2],
                            ['type' => 'group', 'label' => 'Banyaknya', 'colspan' => 3, 'children' => [
                                ['type' => 'manual', 'label' => 'Masuk'],
                                ['type' => 'aggregate_total', 'label' => 'Keluar'],
                                ['type' => 'stok_sisa', 'label' => 'Sisa'],
                            ]],
                            ['type' => 'group', 'label' => 'NA, RA, atau DN', 'colspan' => 2, 'children' => [
                                ['type' => 'na_model', 'label' => 'Model'],
                                ['type' => 'field', 'field' => 'Nomor Perforasi', 'label' => 'Seri/Nomor'],
                            ]],
                            ['type' => 'static', 'value' => 'Buku', 'label' => 'Satuan', 'rowspan' => 2],
                            ['type' => 'group', 'label' => 'Nomor Bukti', 'colspan' => 2, 'children' => [
                                ['type' => 'manual', 'label' => 'Penerimaan'],
                                ['type' => 'manual', 'label' => 'Pengeluaran'],
                            ]],
                        ],
                    ],
                ],
            ]
        );

        // Laporan L1
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan L1'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 1,
                'output_format' => 'print',
                'description' => 'Laporan Rekapitulasi Perkawinan',
                'fields_json' => ['Kelurahan', 'Status Wali', 'Tanggal Isbat', 'NIK Suami', 'NIK Istri', 'Nikah Di', 'Nomor Daftar', 'Nama Suami', 'Nama Istri', 'Desa/Kelurahan/Kecamatan'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Kelurahan', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'type' => 'laporan_l1',
                        'aggregation' => [
                            'group_by' => 'Kelurahan',
                        ],
                        'columns' => [
                            ['type' => 'row_number', 'label' => 'No', 'rowspan' => 4],
                            ['type' => 'field', 'field' => 'Kelurahan', 'label' => 'DESA', 'rowspan' => 4],
                            ['type' => 'group', 'label' => 'PERKAWINAN', 'colspan' => 14, 'children' => [
                                ['type' => 'aggregate_total', 'label' => 'Jumlah Seluruhnya', 'rowspan' => 3],
                                ['type' => 'group', 'label' => 'WALI NIKAH', 'colspan' => 3, 'children' => [
                                    ['type' => 'aggregate_count', 'field' => 'Status Wali', 'match' => ['NASAB'], 'label' => 'Nasab', 'rowspan' => 2],
                                    ['type' => 'group', 'label' => 'Hakim', 'colspan' => 2, 'children' => [
                                        ['type' => 'aggregate_count', 'field' => 'Status Wali', 'match' => ['HAKIM'], 'label' => 'Adhal'],
                                        ['type' => 'manual', 'label' => 'Lain-lain'],
                                    ]],
                                ]],
                                ['type' => 'manual', 'label' => 'Itsbat Nikah', 'rowspan' => 3],
                                ['type' => 'group', 'label' => 'CAMPURAN', 'colspan' => 2, 'children' => [
                                    ['type' => 'manual', 'label' => 'Laki-laki', 'rowspan' => 2],
                                    ['type' => 'manual', 'label' => 'Perempuan', 'rowspan' => 2],
                                ]],
                                ['type' => 'group', 'label' => 'POLIGAMI', 'colspan' => 3, 'children' => [
                                    ['type' => 'manual', 'label' => 'II', 'rowspan' => 2],
                                    ['type' => 'manual', 'label' => 'III', 'rowspan' => 2],
                                    ['type' => 'manual', 'label' => 'IV', 'rowspan' => 2],
                                ]],
                                ['type' => 'manual', 'label' => 'Kantor', 'rowspan' => 3],
                                ['type' => 'manual', 'label' => 'Luar Kantor', 'rowspan' => 3],
                                ['type' => 'group', 'label' => 'BEBAS BIAYA', 'colspan' => 2, 'children' => [
                                    ['type' => 'manual', 'label' => 'Miskin', 'rowspan' => 2],
                                    ['type' => 'manual', 'label' => 'Bencana Alam', 'rowspan' => 2],
                                ]],
                            ]],
                            ['type' => 'manual', 'label' => 'Pencatatan PN', 'rowspan' => 4],
                            ['type' => 'manual', 'label' => 'Duplikat', 'rowspan' => 4],
                            ['type' => 'group', 'label' => 'TALAK KE', 'colspan' => 3, 'children' => [
                                ['type' => 'manual', 'label' => 'I', 'rowspan' => 3],
                                ['type' => 'manual', 'label' => 'II', 'rowspan' => 3],
                                ['type' => 'manual', 'label' => 'III', 'rowspan' => 3],
                            ]],
                            ['type' => 'manual', 'label' => 'CERAI', 'rowspan' => 4],
                            ['type' => 'group', 'label' => 'RUJUK KE', 'colspan' => 3, 'children' => [
                                ['type' => 'manual', 'label' => 'I', 'rowspan' => 3],
                                ['type' => 'manual', 'label' => 'II', 'rowspan' => 3],
                                ['type' => 'manual', 'label' => 'III', 'rowspan' => 3],
                            ]],
                        ],
                    ],
                ],
            ]
        );

        // REKAP NTCR
        ReportTemplate::firstOrCreate(
            ['name' => 'REKAP NTCR'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 9,
                'output_format' => 'print',
                'description' => 'Rekapitulasi Nikah, Talak, Cerai & Rujuk',
                'fields_json' => ['Tanggal Daftar', 'Tanggal Nikah', 'Nikah Di'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Tanggal Daftar', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'type' => 'rekap_ntcr',
                        'aggregation' => [
                            'date_filter_field' => 'Tanggal Daftar',
                        ],
                        'manual_columns' => [
                            'Talak',
                            'Cerai',
                            'Rujuk',
                            'Gagal',
                            'Tunda',
                            'Miskin',
                            'Bencana Alam',
                        ],
                        'columns' => [],
                    ],
                ],
            ]
        );

        // Laporan NB
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan NB'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 10,
                'output_format' => 'print',
                'description' => 'Laporan NB - Catatan Pendaftaran Nikah',
                'fields_json' => ['Tanggal Daftar', 'Nama Suami', 'Nama Istri', 'Nomor Daftar'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Tanggal Daftar', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'type' => 'laporan_nb',
                        'aggregation' => [
                            'date_filter_field' => 'Tanggal Daftar',
                        ],
                        'manual_columns' => [
                            'Masuk',
                            'Penerimaan',
                        ],
                        'columns' => [],
                    ],
                ],
            ]
        );

        // Laporan N
        ReportTemplate::firstOrCreate(
            ['name' => 'Laporan N'],
            [
                'user_id' => $owner->id,
                'is_global' => true,
                'is_active' => true,
                'sort_order' => 11,
                'output_format' => 'print',
                'description' => 'Laporan N - Catatan Peristiwa Nikah',
                'fields_json' => ['Tanggal Nikah', 'Nama Suami', 'Nama Istri', 'Nomor Akta Nikah'],
                'filters_json' => ['search' => null, 'filter_column' => null, 'filter_value' => null],
                'sorting_json' => ['column' => 'Tanggal Nikah', 'direction' => 'asc'],
                'layout_json' => [
                    'orientation' => 'landscape',
                    'table_layout' => [
                        'type' => 'laporan_n',
                        'aggregation' => [
                            'date_filter_field' => 'Tanggal Nikah',
                        ],
                        'manual_columns' => [
                            'Masuk',
                            'Penerimaan',
                        ],
                        'columns' => [],
                    ],
                ],
            ]
        );
    }
}
