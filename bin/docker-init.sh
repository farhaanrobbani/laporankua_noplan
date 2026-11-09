#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    cp .env.docker.example .env
    echo "Dibuat .env dari .env.docker.example"
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    if ! command -v openssl >/dev/null 2>&1; then
        echo "openssl tidak ditemukan. Isi APP_KEY secara manual di .env." >&2
        exit 1
    fi

    key="base64:$(openssl rand -base64 32)"

    if grep -q '^APP_KEY=' .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" .env
    else
        printf '\nAPP_KEY=%s\n' "$key" >> .env
    fi

    echo "APP_KEY dibuat."
fi

echo
echo "Edit .env terlebih dahulu:"
echo "  - KUA_ADMIN_EMAIL / KUA_ADMIN_PASSWORD"
echo "  - DB_PASSWORD"
echo "  - APP_URL (dan SESSION_SECURE_COOKIE=true jika HTTPS)"
echo "  - APP_IMAGE (pin ke versi rilis, mis. ghcr.io/farhaanrobbani/laporankua_noplan:1.1.2)"
echo
echo "Lalu jalankan:"
echo "  docker compose up -d --build"
