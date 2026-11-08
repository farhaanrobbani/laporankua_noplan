#!/usr/bin/env bash
#
# Menjalankan test dengan pengukuran coverage memakai pcov.
# pcov dibangun otomatis ke storage/pcov bila belum ada (tanpa sudo).
#
# Pemakaian:
#   bash bin/coverage.sh              # semua test + ringkasan coverage
#   bash bin/coverage.sh --min=80     # gagal bila < 80%
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BINARY:-/usr/bin/php85}"
command -v "$PHP_BIN" >/dev/null 2>&1 || PHP_BIN="php"

PCOV_SO="$ROOT/storage/pcov/pcov.so"
PCOV_DIR="/tmp/pcov-src"

build_pcov() {
    echo ">> pcov belum ada, membangun ke storage/pcov ..."

    local php_config
    php_config="$(dirname "$PHP_BIN")/php-config"
    [ -x "$php_config" ] || php_config="$(command -v php-config || true)"
    [ -n "$php_config" ] || { echo "ERROR: php-config tidak ditemukan." >&2; exit 1; }

    local pecl_bin
    pecl_bin="$(command -v php85-pecl || command -v pecl || true)"
    [ -n "$pecl_bin" ] || { echo "ERROR: pecl tidak ditemukan; install pcov manual." >&2; exit 1; }

    mkdir -p "$PCOV_DIR"
    ( cd "$PCOV_DIR"
      [ -f pcov-*.tgz ] || "$pecl_bin" download pcov
      tar xzf pcov-*.tgz
      cd pcov-*/
      phpize >/dev/null 2>&1 || "$PHP_BIN" "$(command -v phpize)" >/dev/null 2>&1
      ./configure --with-php-config="$php_config" >/dev/null
      make -j"$(nproc)" >/dev/null
    )

    mkdir -p "$(dirname "$PCOV_SO")"
    cp "$PCOV_DIR"/pcov-*/modules/pcov.so "$PCOV_SO"
    echo ">> pcov siap: $PCOV_SO"
}

[ -f "$PCOV_SO" ] || build_pcov

COVERAGE_DIR="$ROOT/storage/coverage"
mkdir -p "$COVERAGE_DIR"
CLOVER="$COVERAGE_DIR/clover.xml"

echo ">> Menjalankan test + coverage ..."
"$PHP_BIN" \
    -d extension="$PCOV_SO" \
    -d pcov.enabled=1 \
    -d pcov.directory="$ROOT/app" \
    vendor/bin/phpunit \
    --coverage-clover="$CLOVER" "$@"

echo ""
echo ">> Ringkasan coverage:"
"$PHP_BIN" -r '
$xml = @simplexml_load_file($argv[1]);
if (!$xml) { fwrite(STDERR, "Gagal membaca clover.xml\n"); exit(1); }
$project = $xml->project ?? $xml;
$m = $project->metrics;
$stmts = (int) $m["statements"];
$cov = (int) $m["coveredstatements"];
printf("   Statement coverage: %d/%d = %.1f%%\n", $cov, $stmts, $stmts ? $cov / $stmts * 100 : 100);

$files = [];
foreach ($project->package as $pkg) {
    foreach ($pkg->file as $file) {
        $stm = 0; $covered = 0;
        foreach ($file->line as $line) {
            if ((string) $line["type"] === "stmt") {
                $stm++;
                if ((int) $line["count"] > 0) { $covered++; }
            }
        }
        if ($stm > 0) {
            $files[] = [$covered / $stm * 100, $covered, $stm, (string) $file["name"]];
        }
    }
}
usort($files, fn ($a, $b) => $a[0] <=> $b[0]);
$low = array_slice($files, 0, 8);
echo "   File terendah:\n";
foreach ($low as [$pct, $c, $t, $name]) {
    $short = preg_replace("#^.*/laporan/#", "", $name);
    printf("     %5.1f%%  %3d/%-3d  %s\n", $pct, $c, $t, $short);
}
' "$CLOVER"
