# Development Tasks

## Phase 0 — Project Setup

- [x] Create Laravel 13 project with PHP 8.5
- [x] Configure MySQL database
- [x] Configure `.env`
- [x] Set timezone Asia/Jakarta
- [x] Configure `php` binary to `/usr/bin/php85`
- [x] Install Laravel Breeze for authentication
- [x] Install Tailwind CSS
- [x] Install Livewire
- [x] Install Laravel Pint
- [x] Configure storage and symlink
- [x] Install Laravel Boost
- [x] Install maatwebsite/excel
- [x] Install barryvdh/laravel-dompdf
- [x] Install phpoffice/phpword
- [x] Configure queue connection
- [x] Configure testing
- [x] Create project repository

---

# Phase 1 — Authentication

- [x] Install Laravel Breeze with Blade + Tailwind
- [x] Registration
- [x] Login
- [x] Logout
- [x] Forgot password
- [x] Reset password
- [x] Profile page
- [x] Change password
- [x] Email verification (optional)
- [x] Authentication tests
- [x] Authorization policies setup

---

# Phase 2 — Dashboard

- [x] Dashboard migration
- [x] Dashboard controller
- [x] Dashboard view (Blade + Livewire)
- [x] Statistics cards component
- [x] Recent imports list component
- [x] Recent reports list component
- [x] Quick actions component
- [x] Dashboard tests

---

# Phase 3 — Excel Import

- [x] Upload migration (file storage)
- [x] Import model
- [x] Import factory
- [x] Import seeder
- [x] Import controller
- [x] Upload form (Livewire)
- [x] File validation
- [x] Excel reader (Maatwebsite)
- [x] Sheet selection UI
- [~] Column mapping UI — DILEWATKAN: pendekatan structured tabular, header Excel langsung jadi key JSON (normalisasi + deteksi duplikat) di ExcelImportService
- [x] Data preview component
- [x] Import processing job (ProcessExcelImport)
- [x] Import status tracking
- [x] Import history page
- [x] Import detail page
- [x] Import error log
- [x] Import tests

---

# Phase 4 — Data Management

- [x] ImportData model
- [x] Data table component (Livewire)
- [x] Data listing page
- [x] Data filtering
- [x] Data searching
- [x] Data sorting
- [x] Data pagination
- [x] Data detail view
- [x] Data delete functionality
- [x] Bulk delete functionality
- [x] Data export to Excel
- [x] Data tests

---

# Phase 5 — Report Generator

- [x] Report model
- [x] Report controller
- [x] Report builder UI (Livewire)
- [x] Data source selection
- [x] Field selection component
- [x] Filter builder component
- [x] Sorting configuration component
- [x] Output format selection
- [x] Report preview component
- [x] PDF generation service (PdfService)
- [x] Word generation service (WordService)
- [x] Excel export service (ExcelExportService)
- [x] Print preview with CSS (@media print)
- [x] Report generation job (GenerateReport)
- [x] Report storage
- [x] Report download
- [x] Report tests

---

# Phase 6 — Report Templates

- [x] ReportTemplate model
- [x] Template management controller
- [x] Template creation form
- [x] Template editing
- [x] Template listing
- [x] Template deletion
- [x] Template default setting
- [x] Template usage (create report from template)
- [x] Template tests

---

# Phase 7 — Print

- [x] Print CSS stylesheet (@media print)
- [x] Print layout component
- [x] Print preview page
- [x] Print header/footer configuration
- [~] Page number support — DomPDF: footer page number via @page CSS print; belum header/footer variabel PDF khusus
- [x] A4 page size configuration
- [x] Landscape/portrait support — orientasi PDF portrait/landscape (PdfService + report-builder)
- [x] Print tests

---

# Phase 8 — Security & Audit

- [x] Authorization policy review
- [x] CSRF protection audit
- [x] File upload security audit
- [x] SQL injection audit
- [x] XSS protection audit
- [x] Input sanitization
- [x] Rate limiting configuration
- [x] User data isolation verification
- [x] Security tests

---

# Phase 9 — Performance

- [x] Database index review
- [x] N+1 query fix
- [~] Query caching implementation — DILEWATKAN: daftar & statistik memakai index komposit + withCount, belum perlu cache (data kecil-per-user, risiko stale)
- [x] Eager loading optimization
- [x] Chunk processing for large datasets
- [x] Queue worker configuration — worker dev jalan (artisan queue:work); supervisor utk produksi masuk Phase 12
- [x] Page load speed optimization — Vite build minify, icons manifest cached, eager loading list
- [x] Frontend asset optimization — npm run build (app css+js berversi)
- [x] Performance tests — PerformanceTest: batas jumlah query (anti N+1) di dashboard/import/data + benchmark buildDataset 1000 baris

---

# Phase 10 — UI Polish

- [x] Responsive design review
- [x] Mobile sidebar navigation
- [x] Empty state components
- [x] Loading state components
- [x] Error state components
- [x] Toast notifications
- [x] Modal components
- [x] Status badge components
- [x] Print button components
- [x] UI consistency review
- [x] Design system alignment

---

# Phase 11 — Documentation & Testing

- [x] Update README.md
- [x] Update all documentation files
- [x] Unit tests completion
- [x] Feature tests completion
- [x] Test coverage 80%+ — 90.3% statement (pcov, `bin/coverage.sh`); 98 test unit+feature
- [x] PHP linting (Pint)
- [x] Code review
- [x] Documentation review

---

# Phase 12 — Deployment

- [x] Production `.env` configuration — template `.env.example` produksi + panduan `.env` (langkah server tidak dieksekusi di host ini)
- [x] `php artisan optimize:clear`
- [x] `composer install --optimize-autoloader`
- [x] `php artisan migrate --force`
- [x] `php artisan db:seed --force`
- [x] `npm run build`
- [x] Nginx configuration — `deploy/nginx/laporan.conf` (listen 7012, php8.5-fpm)
- [x] Supervisor for queue workers — `deploy/supervisor/laporan-worker.conf` (2 proc, max-time 3600)
- [x] Cron job setup — `deploy/cron/laporan-scheduler.cron`
- [x] SSL/HTTPS setup — didokumentasikan (certbot) di `DEPLOYMENT.md`
- [x] Domain configuration
- [x] Backup strategy
- [x] Monitoring setup
- [x] Deployment documentation — `DEPLOYMENT.md` (+ checklist go-live)

---

# Phase 13 — Post-Launch

- [ ] User acceptance testing (UAT)
- [ ] Bug fixes
- [ ] Performance monitoring
- [ ] Log review
- [ ] Error tracking
- [ ] User feedback collection
- [ ] Version 1.1 planning
- [ ] Advanced feature backlog

---

## Priority Matrix

### Critical Path (Must Have)

```text
Phase 0 → Phase 1 → Phase 3 → Phase 4 → Phase 5 → Phase 7 → Phase 8 → Phase 12
```

### Important (Should Have)

```text
Phase 2 → Phase 5 → Phase 6 → Phase 9 → Phase 10
```

### Nice to Have (Could Have)

```text
Phase 11 → Phase 13
```

---

## Acceptance Checklist

Aplikasi siap deploy apabila:

- [x] Authentication berjalan dengan benar
- [x] Excel upload dan import berjalan
- [x] Data management berfungsi
- [x] PDF report generation berfungsi
- [x] Word report generation berfungsi
- [x] Excel export berfungsi
- [x] Print preview berfungsi
- [x] Template management berfungsi
- [x] Data user terisolasi
- [x] Tidak ada critical bugs
- [x] Test coverage >= 80% — 90.3% statement
- [x] Code passes linting
- [ ] Production deployment ready
- [x] Documentation complete
- [x] Security audit passed
- [x] Performance targets met — N+1 dijaga test (<=12-16 query), buildDataset 1000 baris < 5s di test

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
