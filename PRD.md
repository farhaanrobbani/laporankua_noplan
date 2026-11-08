# Product Requirements Document

## 1. Nama Produk

**Laporan** — Web Pengolah Laporan

Aplikasi web untuk mengolah data dari file Excel dan menghasilkan laporan dalam berbagai format: PDF, Word, Excel, dan Print.

---

# 2. Tujuan Produk

Aplikasi digunakan sebagai alat bantu untuk:

- Mengubah file Excel menjadi data terstruktur di database MySQL
- Mengelola data import dari Excel
- Membuat laporan dalam berbagai format sesuai kebutuhan user

User dapat:

- Upload file Excel (.xlsx, .xls)
- Melihat pratinjau data sebelum import
- Mengelola data yang sudah di-import
- Membuat laporan dalam format PDF, Word, Excel, dan Print
- Mendownload laporan yang sudah dibuat
- Menyimpan template laporan untuk penggunaan berulang

---

# 3. Target Pengguna

Target pengguna meliputi:

- Pegawai/karyawan yang perlu membuat laporan dari data Excel
- Manajer yang membutuhkan laporan dalam berbagai format
- Admin yang mengelola data import

Setiap user memiliki data dan laporan yang terpisah.

---

# 4. Teknologi

## Backend

- **Laravel 13** — Framework utama
- **PHP 8.5** — Bahasa pemrograman
- **MySQL** — Database

## Package Utama

- **maatwebsite/excel** — Import Excel ke MySQL
- **barryvdh/laravel-dompdf** — Generate PDF
- **phpoffice/phpword** — Generate Word (.docx)
- **livewire/livewire** — Interaksi dinamis di frontend
- **laravel/breeze** — Authentication scaffolding
- **tailwindcss** — Styling
- **laravel/pint** — Code style consistency

## Frontend

- Laravel Blade sebagai fondasi
- Livewire untuk interaksi dinamis
- Alpine.js untuk komponen kecil
- Tailwind CSS untuk styling
- CSS print khusus untuk fitur print

---

# 5. Modul Aplikasi

## Modul 1 — Dashboard

Dashboard menampilkan:

- Jumlah file Excel yang sudah di-upload
- Jumlah data yang sudah di-import
- Ringkasan data terakhir yang di-import
- Laporan terbaru yang dibuat
- Navigasi cepat ke modul utama
- Statistik penggunaan

---

# 6. Modul Upload Excel

User dapat meng-upload file Excel.

### Fitur Upload

- Supported format: `.xlsx`, `.xls`
- Max file size: configurable (default 10MB)
- Validasi format file (mIME type, ekstensi)
- Pratinjau data sebelum import (first 100 rows)
- Pilihan sheet yang akan di-import
- Mapping kolom untuk structured tabular data
- Status import: pending, processing, success, failed
- Log import (jumlah baris sukses/gagal)

### Proses Import

1. User upload file Excel
2. Sistem membaca file menggunakan Maatwebsite Excel
3. Sistem menampilkan pratinjau data (sheet selection, column mapping)
4. User memilih sheet dan mengkonfirmasi import
5. Sistem menyimpan data ke tabel `import_data` di MySQL
6. Metadata disimpan di tabel `imports`
7. User dapat melihat data yang sudah di-import

---

# 7. Modul Manajemen Data

Setelah data di-import, user dapat:

- Melihat semua data import dalam bentuk tabel
- Filter data berdasarkan kolom
- Search data (global search)
- Sort data (ascending/descending)
- Delete data import (dengan konfirmasi)
- Detail data per record
- Export data ke Excel untuk diedit
- Bulk actions (delete multiple)

---

# 8. Modul Laporan

User dapat membuat laporan dari data yang sudah di-import.

### Jenis Laporan

1. **Laporan PDF**
   - Generate PDF menggunakan DomPDF
   - Template laporan yang dapat dikustomisasi
   - Header, footer, logo perusahaan
   - Pagination otomatis
   - Support landscape/portrait

2. **Laporan Word**
   - Generate .docx menggunakan PHPWord
   - Template Word dengan styling
   - Heading, table, paragraph formatting
   - Page setup

3. **Laporan Excel**
   - Export data ke format Excel menggunakan Maatwebsite
   - Multiple sheet jika diperlukan
   - Format number, date, dan styling sel
   - Header styling

4. **Print**
   - Preview print di browser
   - CSS print khusus (@media print)
   - Layout optimal untuk printer A4
   - Auto-fit content

### Konfigurasi Laporan

User dapat mengatur:

- Filter data (per kolom, tanggal, nilai)
- Field yang ditampilkan (select columns)
- Urutan sorting (column + direction)
- Grouping data (berdasarkan kolom tertentu)
- Format angka dan tanggal
- Header dan footer laporan
- Template laporan (simpan sebagai template)
- Judul dan deskripsi laporan

---

# 9. Modul Template Laporan

User dapat menyimpan konfigurasi laporan sebagai template untuk penggunaan berulang.

Data template:

- Nama template
- Deskripsi
- Format output (pdf, word, excel, print)
- Fields yang dipilih (JSON)
- Filter default (JSON)
- Sorting default (JSON)
- Layout styling (JSON)
- Header/footer configuration
- User owner
- Is default

Template dapat diedit, dihapus, dan digunakan kembali untuk membuat laporan baru dengan konfigurasi yang sama.

---

# 10. Modul Authentication

Menggunakan Laravel Breeze untuk authentication scaffolding.

User dapat:

- Register akun baru
- Login
- Logout
- Reset password via email
- Mengubah profil
- Mengubah password

Setiap user hanya dapat mengakses data miliknya sendiri. Data terisolasi berdasarkan `user_id`.

---

# 11. Keamanan

Wajib menggunakan:

- Laravel authentication (Breeze)
- CSRF protection
- Form Request validation
- Authorization Policy
- Mass assignment protection (fillable/guarded)
- SQL injection protection dari Eloquent ORM
- Validasi upload file (tipe, ukuran, ekstensi)
- Validasi ownership data (user hanya akses data sendiri)
- Rate limiting untuk upload dan report generation
- XSS protection
- Input sanitization

---

# 12. Struktur Database

## Tabel `imports`

```
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id BIGINT UNSIGNED FOREIGN KEY -> users(id)
file_name VARCHAR(255)
file_path VARCHAR(500)
file_size INT
sheet_name VARCHAR(255)
total_rows INT
imported_rows INT
failed_rows INT
status ENUM('pending', 'processing', 'success', 'failed')
imported_at DATETIME NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

## Tabel `import_data`

Struktur dinamis berdasarkan kolom Excel. Menggunakan JSON untuk fleksibilitas:

```
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
import_id BIGINT UNSIGNED FOREIGN KEY -> imports(id)
row_data JSON
row_number INT
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indeks:
- `import_id`
- `row_number`
- Full-text index pada `row_data` untuk pencarian

## Tabel `report_templates`

```
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id BIGINT UNSIGNED FOREIGN KEY -> users(id)
name VARCHAR(255)
description TEXT NULL
output_format VARCHAR(50)
fields_json JSON
filters_json JSON
sorting_json JSON
layout_json JSON
is_default BOOLEAN DEFAULT FALSE
created_at TIMESTAMP
updated_at TIMESTAMP
```

## Tabel `reports`

```
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id BIGINT UNSIGNED FOREIGN KEY -> users(id)
report_template_id BIGINT UNSIGNED NULL FOREIGN KEY -> report_templates(id)
import_id BIGINT UNSIGNED FOREIGN KEY -> imports(id)
title VARCHAR(255)
description TEXT NULL
output_format VARCHAR(50)
file_path VARCHAR(500) NULL
file_size INT NULL
status ENUM('pending', 'generated', 'downloaded')
generated_at DATETIME NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

---

# 13. Acceptance Criteria

Aplikasi dianggap memenuhi MVP apabila:

1. User dapat mendaftar dan login.
2. User dapat meng-upload file Excel (.xlsx, .xls) dengan validasi.
3. Sistem dapat membaca dan menampilkan pratinjau data Excel.
4. User dapat meng-import data Excel ke MySQL.
5. User dapat melihat data yang sudah di-import dalam bentuk tabel.
6. User dapat memfilter dan mencari data import.
7. User dapat membuat laporan dalam format PDF.
8. User dapat membuat laporan dalam format Word (.docx).
9. User dapat membuat laporan dalam format Excel.
10. User dapat menjalankan print preview.
11. User dapat menyimpan template laporan.
12. User dapat menggunakan template laporan untuk membuat laporan baru.
13. Data user terisolasi — user A tidak bisa melihat data user B.
14. File upload divalidasi (tipe, ukuran, ekstensi).
15. Laporan dapat di-download.
16. Dashboard menampilkan ringkasan data dan statistik.
17. Validasi form berjalan dengan benar.
18. Test utama aplikasi berhasil.

---

# 14. Non-Goals MVP

Tidak perlu dibuat pada tahap pertama:

- Multi-language (kecuali Bahasa Indonesia sebagai default)
- Real-time data sync
- API REST lengkap
- Mobile application
- AI-powered data analysis
- Email notification
- Scheduled report generation
- Multi-tenant architecture
- Advanced data modeling (relations antar tabel import)
- WebSocket / broadcasting

Fitur tersebut dapat dipertimbangkan pada versi berikutnya.

---

# 15. Timeline

## Phase 1 — Foundation (Week 1-2)
- Project setup dengan Laravel 13
- PHP 8.5 configuration
- Authentication (Laravel Breeze)
- Dashboard
- Tailwind CSS setup

## Phase 2 — Excel Import (Week 3-4)
- Upload Excel (Maatwebsite)
- Pratinjau data
- Import ke MySQL
- Data management (CRUD)

## Phase 3 — Report Generation (Week 5-6)
- PDF export (DomPDF)
- Word export (PHPWord)
- Excel export (Maatwebsite)
- Print preview (CSS @media print)

## Phase 4 — Template & Polish (Week 7-8)
- Template management
- Security audit
- Performance optimization
- Testing
- Production deployment

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
