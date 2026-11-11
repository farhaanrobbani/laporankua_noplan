# Changelog

Semua perubahan penting dicatat di file ini. Format mengikuti
[Keep a Changelog](https://keepachangelog.com/) dan [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.3] - 2026-11-11

### Added

- Command `l3:reprocess` untuk mendeteksi dan memperbaiki duplikat L3 lintas bulan (`--user`, `--all`, `--dry-run`).
- Test regresi `L3ReportServiceTest` untuk kasus duplikat L3 lintas bulan.
- Template env Docker terpisah `.env.docker.example` dan generator `.env.docker` pada `bin/docker-init.sh`.
- Variabel `APP_BIND` untuk membatasi port host ke loopback secara default.

### Fixed

- Deteksi duplikat L3 lintas bulan pada import dan reprocess data lama.
- Entrypoint menolak startup production jika `DB_PASSWORD` atau `KUA_ADMIN_PASSWORD` masih kosong/default.
- Peringatan saat `APP_URL` HTTPS tetapi `SESSION_SECURE_COOKIE` bukan `true`.
- Graceful shutdown queue worker (`stopwaitsecs` 150s) dan `stop_grace_period` app 300s agar job import tidak terpotong.

### Changed

- Deployment Docker memakai `.env.docker` dan `docker compose --env-file .env.docker` agar tidak menimpa `.env` pengembangan. Pengguna Docker lama perlu memindahkan env deployment dari `.env` ke `.env.docker`.
- Dokumentasi deploy, update, backup, rollback, dan reprocess L3 diperbarui.

### Migration

- Tidak ada perubahan skema. Jalankan `php artisan migrate --force` seperti biasa.

## [1.1.2] - 2026-11-09

### Fixed

- `kua:setup` dan `DatabaseSeeder` memakai akun admin default di non-production saat `KUA_ADMIN_EMAIL`/`KUA_ADMIN_PASSWORD` kosong.

## [1.1.1] - 2026-11-09

### Fixed

- Menaikkan `memory_limit` PHPUnit ke 512M agar test ekspor ZIP tidak kehabisan memori di CI.

## [1.1.0] - 2026-11-08

### Added

- Deployment Docker Compose dengan PostgreSQL.
- Image production PHP 8.5 + Nginx + queue worker + scheduler.
- Workflow GitHub Actions untuk test, build, dan push image ke GHCR.
- Endpoint `/version` dan metadata versi image.

### Changed

- Query pencarian, filter kolom, dan sorting tanggal pada `ImportData` mendukung PostgreSQL.
- `config/database.php` menambahkan koneksi `pgsql`.

### Migration

- Tidak ada perubahan skema. Jalankan `php artisan migrate --force` seperti biasa.

## [1.0.0] - 2026-11-08

### Added

- Rilis baseline: laporan KUA tanpa plan/premium dengan satu akun admin per KUA.
