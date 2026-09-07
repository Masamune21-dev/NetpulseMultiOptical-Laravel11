# WORKLOG — Netpulse Multi Optical

Sistem pemantauan status antarmuka fiber optik, redaman/DDM optical power, dan SLA jaringan ISP PT BERKAH MEDIA KUSUMA VISION (BMKV).

---

## 2026-09-07 — Migrasi Server ke Ekosistem KusumaVision & Rilis APK v2.0.1
1. **Migrasi Database & Backend**:
   - Memindahkan database `netpulse` (35 tabel, 398 interfaces, 23 snmp_devices) dari VM mandiri ke MariaDB lokal server ekosistem KusumaVision.
   - Mengalihkan runtime web ke PHP 8.3-FPM di bawah domain kanonis `https://netpulse.kusumavision.net`.
   - Vhost Nginx terpasang dengan SSL Wildcard KusumaVision (`/etc/nginx/ssl/kusumavision.wildcard.pem`).
   - Penjadwal telemetri antarmuka (`poll:interfaces` dan rollup) dipindahkan ke `/etc/cron.d/netpulse` berjalan di bawah user `www-data`.
   - Memperbaiki binding `FIREBASE_SERVICE_ACCOUNT_JSON` di `config/services.php` agar aman dari pemuatan config cache (`php artisan config:cache`), serta membersihkan pemanggilan fungsi deprecated `openssl_free_key()` di PHP 8.3.
2. **Pembaruan Mobile App (Flutter)**:
   - Mengubah default API endpoint di `mobile/lib/src/auth/session_store.dart` dari `netpulse.bmkv.net` menjadi `https://netpulse.kusumavision.net`.
   - Menyesuaikan dependensi `font_awesome_flutter` ke `^11.0.0` untuk kompatibilitas Flutter 3.44 (`IconData` final class).
   - Menambahkan limit alokasi memori compiler di `android/gradle.properties` (`-Xmx2048m -XX:MaxMetaspaceSize=512m`, daemon off, workers 2) untuk mencegah kehabisan memori server saat kompilasi.
   - Bump versi aplikasi menjadi `2.0.1+3`.
   - Kompilasi APK release selesai dan file binary dipublikasikan ke `public/downloads/netpulse.apk`.
   - Menyediakan skrip helper build `bin/build-apk.sh` untuk memudahkan kompilasi di masa depan.
