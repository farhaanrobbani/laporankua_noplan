#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

log() {
    printf '[entrypoint] %s\n' "$*"
}

fail() {
    printf '[entrypoint] ERROR: %s\n' "$*" >&2
    exit 1
}

is_default_secret() {
    local value="${1:-}"
    value="${value,,}"

    case "$value" in
        ''|password|password123|admin|admin123|changeme|change-me|secret|postgres|laporan|ubah-password-*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

: "${DB_HOST:=db}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:=laporan}"
: "${DB_USERNAME:=laporan}"

if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE="/var/www/html/storage/app/.app_key"

    if [ ! -s "$KEY_FILE" ]; then
        mkdir -p "$(dirname "$KEY_FILE")"
        printf 'base64:%s' "$(openssl rand -base64 32)" > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi

    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
    log "APP_KEY dimuat dari storage/app/.app_key"
fi

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ "${APP_ENV:-production}" = "production" ]; then
    if is_default_secret "${DB_PASSWORD:-}"; then
        fail "DB_PASSWORD masih kosong/default. Ganti di .env.docker sebelum start."
    fi

    if [ "${APP_DEBUG:-false}" = "true" ]; then
        log "PERINGATAN: APP_DEBUG=true di production."
    fi
fi

case "${APP_URL:-}" in
    https://*)
        if [ "${SESSION_SECURE_COOKIE:-false}" != "true" ]; then
            log "PERINGATAN: APP_URL HTTPS tetapi SESSION_SECURE_COOKIE bukan true."
        fi
        ;;
esac

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    log "Menunggu database ${DB_HOST}:${DB_PORT}..."

    for attempt in $(seq 1 60); do
        if pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" >/dev/null 2>&1; then
            log "Database siap."
            break
        fi

        if [ "$attempt" -eq 60 ]; then
            fail "Database tidak siap setelah 120 detik."
        fi

        sleep 2
    done
fi

run_artisan() {
    gosu www-data php artisan "$@"
}

run_artisan package:discover --ansi

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    run_artisan migrate --force
fi

if [ "${AUTO_KUA_SETUP:-true}" = "true" ] \
    && { [ ! -f storage/app/.kua-setup-done ] || [ "${KUA_FORCE_SETUP:-false}" = "true" ]; }; then
    if [ -z "${KUA_ADMIN_EMAIL:-}" ]; then
        fail "KUA_ADMIN_EMAIL wajib diisi saat kua:setup dijalankan."
    fi

    if is_default_secret "${KUA_ADMIN_PASSWORD:-}"; then
        fail "KUA_ADMIN_PASSWORD masih kosong/default. Ganti di .env.docker."
    fi

    run_artisan kua:setup
    touch storage/app/.kua-setup-done
    chown www-data:www-data storage/app/.kua-setup-done
fi

run_artisan optimize

log "Menjalankan supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
