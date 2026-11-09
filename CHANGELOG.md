# Changelog

Semua perubahan penting dicatat di file ini. Format mengikuti
[Keep a Changelog](https://keepachangelog.com/) dan [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
