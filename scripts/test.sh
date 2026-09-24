#!/usr/bin/env bash
#
# Jalankan test suite PHP Netpulse tanpa membaca maupun merusak cache bootstrap produksi.
#
# KENAPA FILE INI ADA
# Checkout ini melayani produksi (netpulse.kusumavision.net, MariaDB `netpulse`) dengan
# bootstrap/cache/config.php aktif. Cached config dibaca lebih dulu saat boot dan MENANG atas
# <env> di phpunit.xml — dan sampai 24 Sep 2026 env sqlite di phpunit.xml bahkan dikomentari —
# sehingga `php artisan test` polos resolve ke MariaDB produksi. Test ber-RefreshDatabase akan
# menjalankan migrate:fresh di sana. `php artisan config:clear` juga salah: cache config produksi
# terhapus dan tidak dipulihkan.
#
# Yang benar: arahkan path cache ke lokasi yang tidak ada, lalu BUKTIKAN koneksi jatuh ke
# sqlite :memory: sebelum satu test pun jalan. Pola ini sama dengan scripts/test.sh di app
# KusumaVision lain (SSO, NMS, MikroTik, Billing, TV).
#
# HANYA APP_CONFIG_CACHE, APP_ROUTES_CACHE, dan APP_EVENTS_CACHE yang boleh dialihkan —
# APP_SERVICES_CACHE / APP_PACKAGES_CACHE ditulis ulang saat boot; path tak-writable = exception.
#
# Pemakaian:
#   bash scripts/test.sh                        # seluruh suite
#   bash scripts/test.sh --filter=ExampleTest   # argumen diteruskan ke `artisan test`
#   composer test                               # sama saja, lewat composer

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

export APP_CONFIG_CACHE=/nonexistent/kv-test-config.php
export APP_ROUTES_CACHE=/nonexistent/kv-test-routes.php
export APP_EVENTS_CACHE=/nonexistent/kv-test-events.php

# Kredensial FCM sungguhan ada di .env server ini dan QUEUE_CONNECTION=sync membuat job push
# berjalan inline — tanpa pengalihan ini test bisa mengirim notifikasi ke HP teknisi.
# phpunit.xml memasang yang sama; ini lapis kedua.
export FIREBASE_SERVICE_ACCOUNT_JSON=/nonexistent/kv-test-fcm.json

# Log test (termasuk exception yang sengaja dipicu) jangan masuk laravel.log produksi.
export LOG_CHANNEL=null

# --- Pengaman 1: path pengalihan memang tidak boleh ada -----------------------
for p in "$APP_CONFIG_CACHE" "$APP_ROUTES_CACHE" "$APP_EVENTS_CACHE"; do
    if [ -e "$p" ]; then
        echo "ABORT: $p ternyata ada. Pengalihan cache gagal — test bisa memakai config produksi." >&2
        exit 1
    fi
done

# --- Pengaman 2: buktikan koneksi DB benar-benar sqlite sebelum test jalan ----
# Meniru persis <env> di phpunit.xml. Kalau hasilnya bukan sqlite, ada yang berubah
# (phpunit.xml, .env, atau cache) dan test TIDAK boleh diteruskan.
probe=$(APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
    php artisan tinker --execute="echo config('database.default').'|'.config('database.connections.'.config('database.default').'.database');" \
    2>/dev/null | tr -d '[:space:]')

if [ "$probe" != "sqlite|:memory:" ]; then
    echo "ABORT: test akan memakai '$probe', bukan 'sqlite|:memory:'." >&2
    echo "       Menjalankan test sekarang berisiko menghapus database produksi." >&2
    echo "       Periksa phpunit.xml, .env, dan bootstrap/cache/ sebelum mencoba lagi." >&2
    exit 1
fi

echo "Pengaman lolos: test memakai sqlite :memory:, cache produksi tidak disentuh."
echo

php artisan test "$@"
