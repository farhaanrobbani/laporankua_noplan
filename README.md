# Laporan — Web Pengolah Laporan

Aplikasi web untuk mengolah data dari file Excel menjadi laporan dalam format **PDF, Word, Excel, dan Print**. User mengupload file Excel, data otomatis tersimpan ke database MySQL/MariaDB atau PostgreSQL, lalu diolah menjadi laporan.

Versi ini **tanpa plan/premium** dan dirancang untuk **satu KUA = satu instalasi + satu akun admin**. Registrasi publik dinonaktifkan.

## Stack

- Laravel 13 · PHP 8.5 · MySQL/MariaDB/PostgreSQL
- Livewire 4 · Alpine.js · Tailwind CSS 3
- maatwebsite/excel (export) · PhpSpreadsheet (import) · DomPDF · PHPWord

## Persyaratan

- PHP 8.5 dengan extension: `mbstring`, `xml`, `pdo_mysql`/`pdo_pgsql`, `zip`, `gd`, `curl`, `bcmath`
- Composer 2 · Node.js · MySQL/MariaDB atau PostgreSQL

## Instalasi

```bash
git clone https://github.com/farhaanrobbani/laporankua_noplan.git laporan
cd laporan

# Gunakan PHP 8.5 (sesuaikan path bila perlu)
/usr/bin/php85 /usr/local/bin/composer install

cp .env.example .env
php85 artisan key:generate
# Sesuaikan DB_DATABASE / DB_USERNAME / DB_PASSWORD / DB_PREFIX di .env

php85 artisan migrate --force

# Buat akun admin KUA + template laporan
# Isi KUA_ADMIN_EMAIL dan KUA_ADMIN_PASSWORD di .env, atau pakai opsi command
php85 artisan kua:setup

php85 artisan storage:link
php85 artisan icons:cache
php85 artisan livewire:publish --assets

npm install && npm run build
```

## Docker (PostgreSQL)

Deploy production cukup dengan Docker Compose. Container `app` berisi Nginx,
PHP-FPM, queue worker, dan scheduler; container `db` berisi PostgreSQL 17.

```bash
cp .env.docker.example .env
bash bin/docker-init.sh          # membuat APP_KEY
# edit .env: KUA_ADMIN_*, DB_PASSWORD, APP_URL, APP_IMAGE

docker compose up -d --build
docker compose ps
curl -fsS http://localhost:8080/up
```

Data persisten disimpan di volume `storage` dan `db_data`. Jangan gunakan
`docker compose down -v` di production karena akan menghapus volume tersebut.

### Update & rollback

```bash
# backup database
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' \
  > backup-$(date +%F).dump

# pin versi baru di .env: APP_IMAGE=ghcr.io/farhaanrobbani/laporankua_noplan:1.1.0
docker compose pull
docker compose up -d
```

Rollback: ganti `APP_IMAGE` ke tag sebelumnya lalu `docker compose up -d`.
Jika migrasi tidak kompatibel, restore dump database. Detail alur rilis ada di
`DEPLOYMENT.md` dan `CHANGELOG.md`.

## Menjalankan

```bash
# Web server (port default 7021)
php85 artisan serve --port=7021

# Queue worker — WAJIB jalan untuk proses import Excel & generate laporan
php85 artisan queue:work --tries=3 --sleep=3 --timeout=120
```

## Testing

```bash
php85 artisan test        # 203 test: unit service, auth, dashboard, import, data, report, template, otomasi, policy, security, performa, UI polish
vendor/bin/pint --test    # code style

# Coverage (membangun pcov otomatis bila belum ada, tanpa sudo)
bash bin/coverage.sh
bash bin/coverage.sh --min=80   # gagal bila coverage < 80%
```

Coverage saat ini: **~90% statement** (file scaffolding auth Breeze adalah penyumbang utama yang belum tertutup).

## Struktur Modul

| Modul | Route | Controller | Komponen |
|---|---|---|---|
| Dashboard | `/dashboard` | DashboardController | status-badge, empty-state, toast |
| Upload/Import | `/imports/*` | ImportController | ⚡import-uploader |
| Data | `/data*` | DataController | ⚡data-table |
| Laporan | `/reports/*` | ReportsController | ⚡report-builder, reports/pdf, reports/print |
| Template | `/templates/*` | TemplateController | — |
| Otomasi | `/otomasi/*` | OtomasiController | userscript SIMKAH4 |

Dokumentasi lengkap: `PRD.md`, `ARCHITECTURE.md`, `DESIGN.md`, `TASKS.md`, `AGENTS.md`.

## Catatan Produksi

- Simpan kredensial hanya di `.env` (jangan commit `.env`).
- Tidak ada plan/premium atau lisensi Otomasi; semua fitur tersedia untuk akun admin KUA.
- Registrasi publik nonaktif. Akun dibuat/diperbarui dengan `php artisan kua:setup`.
- `config/database.php` menggunakan `DB_PREFIX` (mis. `laporan_`) dan `DB_MIGRATIONS_TABLE` bila database dipakai bersama aplikasi lain.
- Gunakan Supervisor/systemd untuk `queue:work` dan cron untuk task terjadwal.

## Deploy ke Production

```bash
# 1. Clone & install
git clone https://github.com/farhaanrobbani/laporankua_noplan.git laporan && cd laporan
cp .env.example .env
php85 artisan key:generate
composer install --optimize-autoloader
npm install && npm run build

# 2. Update .env (WAJIB)
#    APP_URL=https://domain-anda.com
#    APP_ENV=production
#    APP_DEBUG=false
#    SESSION_SECURE_COOKIE=true ← wajib untuk HTTPS
#    KUA_ADMIN_*, KUA_*        ← akun admin & identitas KUA
#    DB_*, MAIL_*, TURNSTILE_*  ← sesuaikan

# 3. Database & assets
php85 artisan migrate --force
php85 artisan livewire:publish --assets
php85 artisan icons:cache

# 4. Cache config (setelah .env final)
php85 artisan config:cache && php85 artisan route:cache && php85 artisan view:cache
```

### Nginx

Contoh config ada di `deploy/nginx/laporan.conf`. Jika menggunakan **BT Panel / Baota Panel**, pastikan blok `location ~ .*\.(js|css)?$` punya `try_files`:

```nginx
location ~ .*\.(js|css)?$ {
    try_files $uri $uri/ /index.php?$query_string;
    expires 12h;
}
```

Tanpa `try_files`, Livewire JS akan 404 (nginx coba serve statis → gagal → status tetap 404 meskipun body file lengkap).

### Cloudflare

Setelah deploy pertama, **purge cache** untuk path `livewire-*` agar Cloudflare tidak meng-cache 404 lama:

```
https://domain-anda.com/livewire-*/livewire.min.js
```
