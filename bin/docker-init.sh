#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

ENV_FILE=".env.docker"

if [ ! -f "$ENV_FILE" ]; then
    cp .env.docker.example "$ENV_FILE"
    chmod 600 "$ENV_FILE"
    echo "Dibuat $ENV_FILE dari .env.docker.example"
fi

if ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
    if ! command -v openssl >/dev/null 2>&1; then
        echo "openssl tidak ditemukan. Isi APP_KEY secara manual di $ENV_FILE." >&2
        exit 1
    fi

    key="base64:$(openssl rand -base64 32)"

    if grep -q '^APP_KEY=' "$ENV_FILE"; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" "$ENV_FILE"
    else
        printf '\nAPP_KEY=%s\n' "$key" >> "$ENV_FILE"
    fi

    echo "APP_KEY dibuat di $ENV_FILE."
fi

if grep -qE '^(DB_PASSWORD|KUA_ADMIN_PASSWORD)=(ubah-password-.*|password|password123|admin|admin123|changeme|change-me|secret|postgres|laporan|)$' "$ENV_FILE"; then
    echo
    echo "PERINGATAN: DB_PASSWORD dan/atau KUA_ADMIN_PASSWORD masih default/kosong."
    echo "Startup production akan ditolak sampai nilainya diganti."
fi

echo
echo "Edit $ENV_FILE terlebih dahulu:"
echo "  - KUA_ADMIN_EMAIL / KUA_ADMIN_PASSWORD"
echo "  - DB_PASSWORD"
echo "  - APP_URL (dan SESSION_SECURE_COOKIE=true jika HTTPS)"
echo "  - APP_IMAGE (pin ke versi rilis, mis. ghcr.io/farhaanrobbani/laporankua_noplan:1.1.3)"
echo "  - APP_BIND (default 127.0.0.1; ubah hanya jika reverse proxy bukan di host yang sama)"
echo
echo "Lalu jalankan:"
echo "  docker compose --env-file .env.docker up -d --build"
