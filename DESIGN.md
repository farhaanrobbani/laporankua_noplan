# Design System

## 1. Design Direction

Gaya visual:

**Modern Report Dashboard**

Karakter:

- Clean
- Minimal
- Professional
- Modern
- Mudah dibaca
- Fokus pada tabel dan angka
- Tidak terlalu banyak dekorasi
- Prioritas pada content over chrome

Warna utama:

- Primary: Blue (#2563EB)
- Secondary: Gray (#64748B)
- Success: Green (#16A34A)
- Danger: Red (#DC2626)
- Warning: Amber (#F59E0B)
- Background: Gray-50 (#F9FAFB)
- Surface: White (#FFFFFF)

---

# 2. Layout

Desktop:

```text
┌──────────────────────────────────────────────────┐
│ Topbar (Logo, Search, User Profile, Notifications)│
├──────────┬───────────────────────────────────────┤
│          │                                       │
│ Sidebar  │ Main Content                          │
│          │                                       │
│ Dashboard│ Upload Excel                          │
│ Import   │ Data Management                       │
│ Reports  │ Report Generator                      │
│ Templates│ Reports List                          │
│ Settings │                                      │
│          │                                       │
├──────────┴───────────────────────────────────────┤
│ Footer (Copyright, Version, Links)              │
└──────────────────────────────────────────────────┘
```

Sidebar desktop sekitar 240px.

Mobile menggunakan:

- Responsive sidebar (off-canvas hamburger menu)
- Bottom navigation jika diperlukan
- Stacked layout untuk tabel

---

# 3. Navigation

## Dashboard

- Overview
- Statistics
- Recent activity

## Import

- Upload Excel
- Import History
- Import Settings

## Data

- All Data
- Filter/Search
- Data Detail

## Reports

- New Report
- Report List
- Report Templates

## Settings

- Profile
- Account Settings
- Application Settings

---

# 4. Dashboard

Dashboard harus menampilkan informasi terpenting terlebih dahulu.

Urutan:

```text
Upload Recent File
```

Kemudian:

```text
Statistics Cards: Total Imports, Total Records, Reports Generated
```

Kemudian:

```text
Recent Imports Table
```

Kemudian:

```text
Recent Reports
```

Kemudian:

```text
Quick Actions (New Import, New Report)
```

---

# 5. Upload Section

Upload harus menonjolkan:

```text
┌────────────────────────────────────┐
│                                    │
│   📁 Drag & Drop Area              │
│   Supported: .xlsx, .xls           │
│   Max: 10MB                        │
│                                    │
│   [Browse Files]                   │
│                                    │
└────────────────────────────────────┘
```

Setelah file dipilih:

```text
File: data_laporan.xlsx (2.3 MB)
Sheet: Sheet1, Sheet2
Rows: 150 records

[ Preview Data ]  [ Import Now ]
```

---

# 6. Data Table

Table harus:

- Responsive
- Sortable
- Searchable
- Filterable
- Pagination (10, 25, 50, 100 rows per page)
- Row selection untuk bulk actions
- Inline actions (view, edit, delete)

Contoh header:

```text
#  | Nama Kolom 1  | Nama Kolom 2  | Tanggal    | Aksi
```

Mobile menggunakan card/list view:

```text
┌──────────────────────────────┐
│ Record #1                    │
│ Kolom 1: Value A             │
│ Kolom 2: Value B             │
│ Tanggal: 2026-09-10          │
│ [View] [Edit] [Delete]       │
└──────────────────────────────┘
```

---

# 7. Report Generator

Report generator harus menonjolkan:

```text
┌──────────────────────────────────────┐
│                                      │
│  Buat Laporan Baru                   │
│                                      │
│  Data Source: [Pilih Import] ▼       │
│                                      │
│  Fields:                             │
│  ☑ Nama Kolom 1                      │
│  ☑ Nama Kolom 2                      │
│  ☐ Nama Kolom 3                      │
│                                      │
│  Filter:                             │
│  Tanggal: [From] s/d [To]            │
│                                      │
│  Sort by: [Kolom] [Asc/Desc] ▼       │
│                                      │
│  Output Format:                      │
│  ○ PDF  ○ Word  ○ Excel  ○ Print    │
│                                      │
│  [Generate Report]                   │
│                                      │
└──────────────────────────────────────┘
```

---

# 8. Report Preview

Preview sebelum download:

```text
┌──────────────────────────────────────┐
│ Header: Logo + Company Name          │
│ Title: Laporan [Judul]               │
│                                      │
│ ┌────────────────────────────────┐   │
│ │         Tabel Data             │   │
│ │  Col1 │ Col2 │ Col3           │   │
│ │  ...  │ ...  │ ...            │   │
│ │                              │   │
│ └────────────────────────────────┘   │
│                                      │
│ Footer: Page X of Y                  │
│ Generated: 2026-09-10 10:30          │
└──────────────────────────────────────┘

[◀ Print] [⬇ Download PDF] [⬇ Download Word] [⬇ Download Excel]
```

---

# 9. Report Cards

Contoh laporan yang sudah dibuat:

```text
┌──────────────────────────────┐
│ 📄 Laporan Data Karyawan     │
│ Format: PDF                  │
│ Data: 150 records            │
│ Generated: 10 Sep 2026       │
│ Status: ✓ Completed          │
│ [Download] [Share] [Delete]  │
└──────────────────────────────┘
```

Gunakan icon untuk format:
- PDF: merah (#DC2626)
- Word: biru (#2563EB)
- Excel: hijau (#16A34A)
- Print: abu-abu (#64748B)

---

# 10. Currency and Number Format

Format angka:

```text
Rp 1.250.000
1,250,000
```

Format tanggal:

```text
10 September 2026
2026-09-10
```

Format jumlah baris:

```text
150 records
1.500 items
```

---

# 11. Status Indicators

Gunakan badge/chip untuk status:

```text
Success:   bg-green-100 text-green-800
Warning:   bg-yellow-100 text-yellow-800
Danger:    bg-red-100 text-red-800
Info:      bg-blue-100 text-blue-800
Neutral:   bg-gray-100 text-gray-800
```

Contoh status import:

```text
[Pending]    [Processing]    [Success]    [Failed]
```

---

# 12. Forms

Form harus sederhana dan jelas.

Contoh upload form:

```text
Pilih File Excel
[ Browse... ]
Supported: .xlsx, .xls | Max: 10MB

Sheet Selection
┌────────────────────────────────┐
│ ☑ Sheet1 (150 rows)            │
│ ☐ Sheet2 (50 rows)             │
└────────────────────────────────┘

Column Mapping
┌──────────────┬──────────────────┐
│ Excel Column │ Map To Field     │
├──────────────┼──────────────────┤
│ A            │ [Nama ▼]         │
│ B            │ [Tanggal ▼]      │
│ C            │ [Nilai ▼]        │
└──────────────┴──────────────────┘

[ Preview ]  [ Import ]
```

---

# 13. Modal

Gunakan modal untuk:

- Delete confirmation
- Quick view data record
- Quick add/edit record
- Import settings

Contoh delete confirmation:

```text
┌──────────────────────────────────┐
│ Hapus Data?                      │
│                                  │
│ Apakah Anda yakin ingin menghapus│
│ data import "data_laporan.xlsx"? │
│                                  │
│ [ Batal ] [ Hapus ]              │
└──────────────────────────────────┘
```

---

# 14. Empty State

Setiap halaman harus memiliki empty state.

Contoh:

```text
📂 Belum ada data import

Upload file Excel untuk mulai mengolah data.

[ Upload Excel ]
```

Contoh laporan kosong:

```text
📄 Belum ada laporan

Buat laporan pertama dari data Anda.

[ Buat Laporan ]
```

---

# 15. Loading State

Gunakan:

- skeleton loader untuk tabel
- spinner untuk proses import
- disabled button selama processing
- progress bar untuk import besar

Contoh import progress:

```text
Importing data_laporan.xlsx...
████████████████░░░░░░░░░░░░ 60%
Processed: 90/150 rows
```

---

# 16. Error State

Error harus jelas dan actionable.

Contoh:

```text
❌ Gagal mengimpor file

File "data.xlsx" mengandung 5 baris yang gagal diproses.
Detail kesalahan ada di log import.

[ View Details ] [ Retry ] [ Cancel ]
```

Jangan menampilkan error teknis database kepada user.

---

# 17. Print Styles

CSS print khusus untuk semua halaman laporan:

```css
@media print {
  /* Hide sidebar, navbar, buttons */
  /* Full width content */
  /* A4 page size */
  /* Appropriate margins */
  /* Table borders */
  /* Page numbers */
}
```

Print preview harus:

- Menghilangkan sidebar dan navbar
- Full page content
- Header dengan logo dan judul
- Footer dengan halaman nomor
- Ukuran kertas A4
- Margin yang sesuai

---

# 18. Responsive

Prioritas:

```text
Mobile
Tablet
Desktop
```

Aplikasi harus nyaman digunakan dari HP karena user mungkin meng-upload data dan melihat laporan langsung.

Breakpoints:

- Mobile: < 640px
- Tablet: 640px - 1024px
- Desktop: > 1024px

---

# 19. Typography

Font family:

- Headings: Inter or similar sans-serif
- Body: Inter or similar sans-serif
- Code/monospace: JetBrains Mono (untuk file paths)

Font sizes:

```text
H1: 2rem (32px)
H2: 1.5rem (24px)
H3: 1.25rem (20px)
H4: 1.125rem (18px)
Body: 1rem (16px)
Small: 0.875rem (14px)
Caption: 0.75rem (12px)
```

---

# 20. Icons

Gunakan Heroicons (blade-heroicons) untuk ikon:

- Upload: cloud-arrow-up
- Download: arrow-down-tray
- Print: printer
- PDF: document-pdf
- Word: document-word
- Excel: spreadsheet
- Search: magnifying-glass
- Filter: funnel
- Settings: gear
- User: user
- Dashboard: squares-3x3
- Data: table-cells
- Report: chart-bar


---

## Environment & Security

- Gunakan environment variables (`.env`) untuk data sensitif. Jangan pernah simpan kredensial/API keys langsung di dalam kode.
- Setiap request harus memastikan user terautentikasi dan memiliki izin mengakses data.
- Validasi semua input pengguna untuk mencegah SQL Injection dan XSS.

---

## Code Quality & Security

- Tulis kode yang modular, mudah dibaca, dan aman dari kerentanan umum (SQL Injection & XSS).
- Sebelum menyelesaikan tugas, pastikan kode telah divalidasi dan bebas dari kesalahan sintaks (`php -l` atau unit test jika ada).

---

## Git Workflow & Mandatory CI/CD Trigger (WAJIB)

1. **Granular Commit:** Lakukan `git commit` untuk setiap 1 tugas/fitur kecil yang selesai dikerjakan. Gunakan format konvensi pesan commit (contoh: `feat: ...` atau `fix: ...`).
2. **Auto Push:** Setelah komit berhasil dan dipastikan bebas error, kamu **WAJIB** menjalankan perintah:
   `git push origin main`

   > ⚠️ **Catatan Penting:** Perintah `git push` ini adalah pemicu (*trigger*) otomatis untuk pipeline CI/CD (GitHub Actions) agar perubahan ter-deploy langsung ke VPS.

---

## Restrictions (Yang Dilarang)

- ❌ Dilarang melakukan `git push` jika kodingan masih bermasalah/error.
- ❌ Dilarang menjalankan perintah terminal berskala destruktif (`rm -rf /`, `DROP DATABASE`, dll) tanpa persetujuan.
- ❌ Dilarang mengubah struktur folder utama aplikasi tanpa instruksi spesifik.

---

## Project Context

- **Project:** Laporan — Web Pengolah Laporan
- **Laravel Version:** 13
- **PHP Version:** 8.5
- **Database:** MySQL
- **Key Packages:** maatwebsite/excel, barryvdh/laravel-dompdf, phpoffice/phpword, livewire/livewire
