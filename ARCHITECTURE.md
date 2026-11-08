# Architecture

## 1. Overview

Aplikasi menggunakan monolithic Laravel architecture.

```text
Browser
    ↓
Laravel
    ├── Routes
    ├── Controllers
    ├── Form Requests
    ├── Policies
    ├── Services
    ├── Models
    ├── Jobs
    │
    ├── Imports (Excel → MySQL)
    │     ├── Excel Reader
    │     ├── Data Mapper
    │     └── Database Writer
    │
    ├── Reports (MySQL → Output)
    │     ├── PDF Generator
    │     ├── Word Generator
    │     ├── Excel Generator
    │     └── Print Renderer
    │
    └── Views (Blade + Livewire)
           ↓
        MySQL
```

Frontend dan backend berada dalam satu aplikasi Laravel.

---

# 2. Application Structure

Struktur utama:

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php
│   │   ├── ImportController.php
│   │   ├── DataController.php
│   │   ├── ReportController.php
│   │   ├── TemplateController.php
│   │   └── ProfileController.php
│   ├── Requests/
│   │   ├── UploadRequest.php
│   │   ├── ImportRequest.php
│   │   ├── ReportRequest.php
│   │   └── TemplateRequest.php
│   └── Resources/
│       ├── ImportResource.php
│       ├── ReportResource.php
│       └── DataResource.php
├── Models/
│   ├── Import.php
│   ├── ImportData.php
│   ├── Report.php
│   ├── ReportTemplate.php
│   └── User.php (Laravel default)
├── Services/
│   ├── ExcelImportService.php
│   ├── ReportGenerationService.php
│   ├── PdfService.php
│   ├── WordService.php
│   ├── ExcelExportService.php
│   └── PrintService.php
├── Jobs/
│   ├── ProcessExcelImport.php
│   ├── GenerateReport.php
│   └── CleanupOldImports.php
├── Policies/
│   ├── ImportPolicy.php
│   ├── ReportPolicy.php
│   └── TemplatePolicy.php
├── Console/
│   ├── Commands/
│   │   ├── CleanupImportsCommand.php
│   │   └── GenerateReportCommand.php
│   └── Kernel.php
└── Providers/
    └── AppServiceProvider.php

resources/
├── views/
│   ├── layouts/
│   │   ├── app.blade.php
│   │   ├── navigation.blade.php
│   │   └── footer.blade.php
│   ├── dashboard/
│   │   └── index.blade.php
│   ├── imports/
│   │   ├── upload.blade.php
│   │   ├── preview.blade.php
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── data/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── reports/
│   │   ├── create.blade.php
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── preview.blade.php
│   ├── templates/
│   │   ├── index.blade.php
│   │   └── create.blade.php
│   └── components/
│       ├── data-table.blade.php
│       ├── file-upload.blade.php
│       ├── report-builder.blade.php
│       ├── status-badge.blade.php
│       ├── print-layout.blade.php
│       └── progress-indicator.blade.php
├── js/
│   └── app.js
├── css/
│   ├── app.css
│   └── print.css
└── lang/
    └── id/
        └── messages.php

database/
├── migrations/
│   ├── 2025_01_01_000001_create_users_table.php
│   ├── 2025_01_01_000002_create_imports_table.php
│   ├── 2025_01_01_000003_create_import_data_table.php
│   ├── 2025_01_01_000004_create_report_templates_table.php
│   ├── 2025_01_01_000005_create_reports_table.php
│   └── 2025_01_01_000006_add_to_users_table.php
├── seeders/
│   ├── DatabaseSeeder.php
│   └── DefaultDataSeeder.php
└── factories/
    ├── ImportFactory.php
    ├── ImportDataFactory.php
    └── ReportFactory.php

routes/
├── web.php
├── api.php
└── console.php

storage/
├── app/
│   ├── imports/          ← Uploaded Excel files
│   └── reports/          ← Generated report files
└──/framework/

tests/
├── Feature/
│   ├── ImportTest.php
│   ├── ReportTest.php
│   ├── AuthenticationTest.php
│   └── DataTest.php
└── Unit/
    ├── ExcelImportServiceTest.php
    ├── ReportGenerationServiceTest.php
    └── PdfServiceTest.php
```

---

# 3. Layer Responsibilities

## Controller

Controller bertanggung jawab untuk:

- menerima request
- authorization (via Policy)
- memanggil service atau job
- mengembalikan response (view, redirect, JSON)

Controller tidak boleh berisi business logic kompleks.

Contoh:

```php
class ImportController extends Controller
{
    public function store(UploadImportRequest $request, ExcelImportService $service)
    {
        $import = $service->createImport($request->file('excel_file'));
        return redirect()->route('imports.show', $import);
    }
}
```

---

# 4. Form Request

Semua validasi input kompleks menggunakan Form Request.

Contoh:

```php
class UploadImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
        ];
    }
}
```

Daftar Form Request:

```text
UploadImportRequest
ImportRequest
ReportRequest
TemplateRequest
```

---

# 5. Service Layer

Business logic kompleks berada di Service.

Contoh:

```text
ExcelImportService
    → Membaca file Excel
    → Memvalidasi data
    → Menyimpan ke database
    → Mengembalikan statistik import

ReportGenerationService
    → Mengambil data dari database
    → Menerapkan filter, sorting, grouping
    → Menghasilkan output (PDF/Word/Excel/Print)
    → Menyimpan file report

PdfService
    → Menggunakan DomPDF
    → Mengatur template dan styling
    → Menghasilkan PDF

WordService
    → Menggunakan PHPWord
    → Mengatur template dan styling
    → Menghasilkan Word (.docx)

ExcelExportService
    → Menggunakan Maatwebsite
    → Mengatur sheet dan format
    → Menghasilkan Excel file

PrintService
    → Menyiapkan data untuk print
    → Mengatur CSS print
    → Menghasilkan HTML untuk print preview
```

---

# 6. Models

Model utama:

```text
User (Laravel default)
Import
ImportData
Report
ReportTemplate
```

### User

Has many:

```text
imports
reports
reportTemplates
```

### Import

Belongs to:

```text
user
```

Has many:

```text
importData (via import_id)
reports
```

### ImportData

Belongs to:

```text
import
```

### Report

Belongs to:

```text
user
reportTemplate (nullable)
import
```

### ReportTemplate

Belongs to:

```text
user
```

Has many:

```text
reports
```

---

# 7. Excel Import Flow

```text
User Upload File
       ↓
Validate File (mimes, size)
       ↓
Store File (storage/app/imports/)
       ↓
Create Import Record (status: pending)
       ↓
Dispatch Job (ProcessExcelImport)
       ↓
   ExcelImportService
       ↓
   Read Excel (Maatwebsite)
       ↓
   Select Sheet
       ↓
   Map Columns
       ↓
   Validate Data Rows
       ↓
   Insert to ImportData (batch)
       ↓
   Update Import Record (status: success, counts)
       ↓
Return to User (redirect to data view)
```

Proses import menggunakan Laravel Jobs untuk handle file besar dan tidak blocking.

---

# 8. Report Generation Flow

```text
User Creates Report Request
       ↓
Validate Report Request
       ↓
Fetch Data from ImportData
       ↓
   Apply Filters (per user configuration)
       ↓
   Apply Sorting
       ↓
   Apply Grouping
       ↓
   Select Fields
       ↓
   Generate Output Based on Format
       ↓
   ┌─────────────┬──────────────┬──────────────┬──────────┐
   │   PDF       │   Word       │   Excel      │  Print   │
   │ DomPDF      │ PHPWord      │ Maatwebsite  │ CSS      │
   └─────────────┴──────────────┴──────────────┴──────────┘
       ↓
Store Report File (storage/app/reports/)
       ↓
Create Report Record
       ↓
Return File to User (download/preview)
```

---

# 9. Job Processing

Proses import dan report generation menggunakan Jobs untuk:

- Performance (non-blocking)
- Reliability (retry on failure)
- Scalability (queue worker)

### ProcessExcelImport Job

```php
class ProcessExcelImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $service = new ExcelImportService($this->import);
        $service->process();
    }
}
```

### GenerateReport Job

```php
class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $service = new ReportGenerationService($this->report);
        $service->generate();
    }
}
```

---

# 10. File Storage

File Excel yang di-upload disimpan di:

```text
storage/app/imports/
```

File laporan yang di-generate disimpan di:

```text
storage/app/reports/
```

Gunakan Laravel Storage API.

Database hanya menyimpan metadata file (path, size, name).

Contoh struktur:

```text
storage/app/imports/
├── user_1/
│   ├── import_1/data_laporan.xlsx
│   └── import_2/keuangan.xlsx
└── user_2/
    └── import_1/sales.xlsx

storage/app/reports/
├── user_1/
│   ├── report_1/laporan_karyawan.pdf
│   └── report_2/laporan_bulanan.docx
└── user_2/
    └── report_1/data_sales.xlsx
```

Symlink `storage/app/public` → `public/storage` untuk akses langsung jika diperlukan.

---

# 11. Database Transactions

Gunakan:

```php
DB::transaction()
```

untuk operasi yang mengubah beberapa tabel.

Contoh import:

```text
Create Import Record
       ↓
Process Excel Data
       ↓
Insert ImportData Records
       ↓
Update Import Status
```

Semua harus berhasil atau semuanya dibatalkan.

Contoh report generation:

```text
Create Report Record
       ↓
Generate Output File
       ↓
Update Report with File Path
```

Ketiganya harus berhasil atau semuanya dibatalkan.

---

# 12. Reporting Engine

Laporan tidak menyimpan hasil agregasi sebagai data utama.

Hitung berdasarkan import_data records kecuali ada kebutuhan performa yang jelas.

Contoh:

```text
import_data
      ↓
SUM, COUNT, AVG
      ↓
monthly report
```

Untuk performa, gunakan:

- Database indexing
- Query caching
- Materialized views (untuk data sangat besar)
- Queue untuk report yang berat

---

# 13. Performance

Gunakan index pada:

```text
imports.user_id
import_data.import_id
import_data.row_number
reports.user_id
reports.import_id
report_templates.user_id
created_at
updated_at
```

Gunakan eager loading jika diperlukan untuk mencegah N+1 queries:

```php
Import::with('importData')->where('user_id', $userId)->get();
```

Gunakan chunking untuk data besar:

```php
ImportData::where('import_id', $importId)->chunk(500, function ($records) {
    // Process in batches
});
```

---

# 14. Security

Setiap request harus memastikan:

```text
Authenticated User
       ↓
Owns Resource
       ↓
Authorized Action
       ↓
Execute
```

Jangan percaya ID dari request tanpa authorization.

Setiap resource menggunakan Policy:

```php
class ImportPolicy
{
    public function view(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }
}
```

Validasi upload file:

- Tipe file: hanya xlsx, xls
- Ukuran: max 10MB
- Ekstensi: dicek via MIME type
- File name: di-sanitize
- Virus scan: opsional

---

# 15. API

Untuk MVP, aplikasi menggunakan web routes + Blade/Livewire.

API dapat ditambahkan kemudian jika aplikasi membutuhkan:

- Mobile app
- External integration
- Third-party clients

Jangan membuat REST API lengkap jika belum diperlukan.

---

# 16. Package Integration Points

### maatwebsite/excel

- Import: `Excel::import(new ImportFromExcel, $request->file('excel_file'))`
- Export: `Excel::download(new ReportExport, 'laporan.xlsx')`

### barryvdh/laravel-dompdf

- PDF: `PDF::loadView('reports.template', $data)->download('laporan.pdf')`

### phpoffice/phpword

- Word: `Word::loadTemplate(...)` → generate → save

### livewire/livewire

- Interactive upload and preview components
- Real-time filtering dan search
- Report builder UI

---

# 17. Configuration

File `.env` penting:

```env
APP_NAME=Laporan
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laporan
DB_USERNAME=root
DB_PASSWORD=

PHP_BINARY=/usr/bin/php85

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

IMPORT_MAX_FILE_SIZE=10240
IMPORT_ALLOWED_TYPES=xlsx,xls
```

---

# 18. Error Handling

Global error handling:

- `App\Exceptions\Handler` untuk custom error handling
- Log error ke `storage/logs/laravel.log`
- User-friendly error messages di halaman error
- Toast notifications untuk errors

Import errors:

- Track failed rows per import
- Export error log untuk import gagal
- Retry mechanism untuk batch import

Report generation errors:

- Queue retry (3x)
- Fallback untuk format yang gagal
- User notification jika report gagal

---

# 19. Testing Strategy

Unit Tests:

```text
ExcelImportServiceTest
PdfServiceTest
WordServiceTest
ExcelExportServiceTest
```

Feature Tests:

```text
ImportTest
ReportTest
AuthenticationTest
AuthorizationTest
```

Coverage target: 80%+

Testing approach:

- Mock external services (DomPDF, PHPWord)
- Use in-memory SQLite for database tests
- Test file upload with fake files
- Test queue processing

---

# 20. Deployment

Environment: Linux / VPS (Ubuntu 22.04, Nginx, PHP 8.5, MySQL)

Deployment steps:

1. Clone repository
2. Install dependencies: `composer install --optimize-autoloader`
3. Configure `.env`
4. Generate key: `php artisan key:generate`
5. Run migrations: `php artisan migrate --force`
6. Build frontend: `npm install && npm run build`
7. Setup queue workers: `php artisan queue:work --daemon`
8. Configure Nginx
9. Setup supervisor for queue workers
10. Setup cron for scheduled tasks

Required PHP extensions:

```text
- php85-cli
- php85-mbstring
- php85-xml
- php85-mysql
- php85-zip
- php85-gd
- php85-curl
- php85-bcmath
- php85-tokenizer
```

Required composer packages:

```text
- maatwebsite/excel
- barryvdh/laravel-dompdf
- phpoffice/phpword
- livewire/livewire
- laravel/breeze
- tailwindcss
- laravel/pint
```

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
