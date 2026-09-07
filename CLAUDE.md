# CLAUDE.md — Netpulse Multi Optical

Netpulse Multi Optical — Sistem monitoring link fiber optik, DDM power optical, dan status antarmuka perangkat ISP (Laravel 11 + Bootstrap/Vite + Flutter Mobile). Dimiliki oleh PT BERKAH MEDIA KUSUMA VISION (BMKV). Domain: `netpulse.kusumavision.net`.

User berkomunikasi dalam bahasa Indonesia — jawab dalam bahasa Indonesia (bilingual saat membahas istilah teknis).

## Perintah Utama

```bash
php artisan serve --port=8000
npm run dev
npm run build
php artisan poll:interfaces          # Jalankan polling manual 23 perangkat SNMP
bash bin/build-apk.sh                # Kompilasi Flutter APK (hasil: public/downloads/netpulse.apk)
```

## Arsitektur & Lingkungan Server

- **Database**: MariaDB `netpulse` di `127.0.0.1:3306`.
- **Runtime**: PHP 8.3-FPM (`/run/php/php8.3-fpm.sock`).
- **Nginx & SSL**: `/etc/nginx/sites-available/netpulse.kusumavision.net` memakai wildcard SSL `/etc/nginx/ssl/kusumavision.wildcard.pem`.
- **Scheduler**: Cron di `/etc/cron.d/netpulse` (`* * * * * www-data php .../artisan schedule:run`). Poller men-dispatch paralel 23 router/switch via SNMP.
- **Mobile Toolchain**: Flutter 3.44 di `/opt/flutter` + Android SDK di `/opt/android-sdk`. Keystore/google-services diletakkan di `mobile/android/app/google-services.json`.

## Aturan Baku AI Agent

- **Wajib Catat di WORKLOG.md**: Setiap pekerjaan dan perubahan kode/fitur/tampilan/bugfix **WAJIB dicatat di berkas `WORKLOG.md`** sebelum commit. Cantumkan tanggal, kategori perubahan (Created/Changed/Fixed/Notes), dan penjelasan teknis secara akurat. Jangan pernah menyelesaikan task tanpa memperbarui `WORKLOG.md`.
