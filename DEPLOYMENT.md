# Deployment — Laporan

Panduan deploy aplikasi **Laporan** ke VPS (Ubuntu, Nginx, PHP 8.5, MySQL/MariaDB). Satu instalasi untuk satu KUA dengan satu akun admin.

## 1. Persiapan server

```bash
sudo apt update
sudo apt install -y nginx mysql-server supervisor unzip git
# PHP 8.5 + ekstensi
sudo apt install -y php8.5-cli php8.5-fpm php8.5-mbstring php8.5-xml \
    php8.5-mysql php8.5-zip php8.5-gd php8.5-curl php8.5-bcmath php8.5-tokenizer
curl -sS https://getcomposer.org/installer | php8.5 -- --install-dir=/usr/local/bin --filename=composer
```

## 2. Ambil kode

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/farhaanrobbani/laporankua_noplan.git laporan
cd laporan
```

## 3. Konfigurasi `.env` produksi

```bash
cp .env.example .env
nano .env
```

Wajib diubah:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com        # atau http://host:7021
APP_TIMEZONE=Asia/Jakarta

DB_DATABASE=laporan
DB_USERNAME=laporan
DB_PASSWORD=<password-kuat>
DB_PREFIX=                            # isi bila DB dipakai bersama app lain

LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true            # setelah HTTPS aktif

# Akun tunggal admin KUA (wajib)
KUA_ADMIN_NAME="Admin KUA"
KUA_ADMIN_EMAIL=admin@domain.com
KUA_ADMIN_PASSWORD=<password-kuat>
KUA_NAME="KUA Kecamatan ..."
KUA_PROVINSI="..."
KUA_KABKOTA="..."
KUA_KECAMATAN="..."
```

> ⚠️ Jangan pernah menyimpan kredensial di kode/repo. `.env` sudah masuk `.gitignore`.

Jalankan:

```bash
php8.5 artisan key:generate
```

## 4. Build & migrasi

```bash
/usr/bin/php8.5 /usr/local/bin/composer install --no-dev --optimize-autoloader
npm ci && npm run build
php8.5 artisan migrate --force
php8.5 artisan storage:link
php8.5 artisan icons:cache
php8.5 artisan optimize          # config + route + view cache
```

**Akun admin KUA** (wajib):

```bash
php8.5 artisan kua:setup
```

Command ini membuat/memperbarui satu akun admin berdasarkan `KUA_ADMIN_*` dan mengisi identitas KUA dari `KUA_*`, lalu memasang template laporan bawaan. Jalankan ulang command yang sama untuk mengganti password atau identitas kantor.

Versi ini tidak memakai plan/premium maupun lisensi Otomasi. Registrasi publik nonaktif; akun hanya dibuat lewat command di atas.

## 5. Izin file

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 6. Nginx

```bash
sudo cp deploy/nginx/laporan.conf /etc/nginx/sites-available/laporan
sudo ln -s /etc/nginx/sites-available/laporan /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Konfigurasi mendengarkan **port 7021** dan meneruskan PHP ke `php8.5-fpm.sock`.
Sesuaikan `server_name` dan `root` bila perlu.

## 7. Queue worker (Supervisor)

Import Excel & generate laporan berjalan di queue — worker **wajib** hidup.

```bash
sudo cp deploy/supervisor/laporan-worker.conf /etc/supervisor/conf.d/
sudo nano /etc/supervisor/conf.d/laporan-worker.conf   # sesuaikan path /var/www/laporan
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status laporan-worker:*
```

## 8. Scheduler (cron)

```bash
sudo crontab -u www-data -e
# tempel isi deploy/cron/laporan-scheduler.cron
```

## 9. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d domain-anda.com
```

Setelah HTTPS: `SESSION_SECURE_COOKIE=true`, `APP_URL=https://...`, lalu `php8.5 artisan config:clear`.

## 10. Backup

- **Database** harian:
  ```bash
  mysqldump -u laporan -p"$DB_PASSWORD" laporan | gzip > /backup/laporan-$(date +%F).sql.gz
  ```
- **Storage** (`storage/app/imports`, `storage/app/reports`) — rsync ke luar server.
- Simpan 7–30 hari retensi.

## 11. Monitoring

- Log: `storage/logs/laravel.log`, `storage/logs/worker.log`
- `php8.5 artisan queue:failed` — cek job gagal
- Health: UptimeRobot / endpoint `/up` (Laravel default)
- Disk & MySQL: `df -h`, `mysqladmin status`

## 12. Update rilis

```bash
cd /var/www/laporan
php8.5 artisan down
git pull origin main
composer install --no-dev --optimize-autoloader
php8.5 artisan migrate --force
npm ci && npm run build
php8.5 artisan optimize:clear && php8.5 artisan optimize
sudo supervisorctl restart laporan-worker:*
php8.5 artisan up
```

## Checklist Go-Live

- [ ] `.env` produksi (`APP_DEBUG=false`, kredensial kuat)
- [ ] `migrate --force` sukses
- [ ] `storage:link` + izin `www-data`
- [ ] Nginx aktif, `nginx -t` lolos
- [ ] Supervisor worker `RUNNING`
- [ ] Cron scheduler terpasang
- [ ] HTTPS aktif + `SESSION_SECURE_COOKIE=true`
- [ ] Backup database & storage terjadwal
- [ ] `kua:setup` sukses dan akun admin bisa login
- [ ] Uji: login → upload Excel → import → buat laporan PDF/Word/Excel/Print
