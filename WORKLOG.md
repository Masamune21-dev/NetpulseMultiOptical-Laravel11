# WORKLOG — Netpulse Multi Optical

Sistem pemantauan status antarmuka fiber optik, redaman/DDM optical power, dan SLA jaringan ISP PT BERKAH MEDIA KUSUMA VISION (BMKV).

---

## 2026-09-07 — Pembaruan Menyeluruh Dark Mode, Cache-Busting APK v2.0.2
1. **Bypass Cache Cloudflare untuk File APK**:
   - Menambahkan konfigurasi Nginx `location ~* \.apk$` dengan header `Cache-Control: no-cache, no-store, must-revalidate, max-age=0` agar Cloudflare dan browser tidak menyajikan file APK versi lama (`cf-cache-status: BYPASS`).
   - Menyediakan link unduh langsung versi spesifik `/downloads/netpulse-v2.0.2.apk` dan `/downloads/netpulse.apk?v=2.0.2`.
2. **Penyelarasan Tampilan Dark Mode Dashboard & Monitoring**:
   - Memperbarui seluruh komponen card container (`_QuickMetric`, `_ActionPanel`, `_SectionBox`) di `HomeScreen` agar adaptif mengikuti tema aktif (menghapus hardcode `Colors.white` dan border abu-abu terang).
   - Menambahkan dot indikator warna status redaman optik pada `_MetricChip` di `MonitoringScreen` (Normal/Warning/Critical/LOS).
   - Memastikan seluruh teks, background, dan border di `HomeScreen`, `MonitoringScreen`, dan `InterfacesScreen` memiliki kontras yang tajam dan nyaman di mode malam.
3. **Kompilasi Ulang APK v2.0.2 (Build 4)**:
   - Bump versi aplikasi menjadi `v2.0.2+4`.
   - Menghasilkan binary APK rilis terbaru di `public/downloads/netpulse-v2.0.2.apk` dan `netpulse.apk` (56.1 MB).

---

## 2026-09-07 — Perbaikan Notifikasi Push Bergambar (FCM Image Notification)
1. **Pembersihan Token Kedaluwarsa & Auto-Prune**:
   - Membersihkan token perangkat basi (`UNREGISTERED`/`NotRegistered`) yang tertinggal dari instalasi APK lama di tabel `device_tokens`.
   - Menambahkan mekanisme auto-prune pada `SettingsApiController::sendManualPush`: saat FCM mengembalikan status `NotRegistered`, token otomatis dihapus dari database agar pengiriman berikutnya tidak terhambat.
2. **Pengiriman & Penanganan Gambar (BigPictureStyleInformation)**:
   - Menyertakan field `image` pada payload `data` di `SettingsApiController` dan `FcmService.php`.
   - Di sisi aplikasi mobile (`mobile/lib/src/push/fcm_service.dart`), fungsi `_showForegroundNotification` kini mengunduh gambar ke cache lokal dan membungkusnya ke dalam `BigPictureStyleInformation` (`FilePathAndroidBitmap`).
   - Notifikasi bergambar kini tampil sempurna di system tray Android baik saat aplikasi sedang dibuka di latar depan maupun saat berada di latar belakang.
   - APK release diperbarui ke `/public/downloads/netpulse.apk`.

---

## 2026-09-07 — Fitur Operasional Web & Peningkatan Mobile App v2.0.2
1. **Fitur Operasional Web (Tema Neo-Brutalism)**:
   - Menambahkan tombol unduh langsung APK Mobile Netpulse di topbar dan sidebar nav (`/downloads/netpulse.apk`) dengan badge versi `v2.0.2`.
   - Menambahkan App Switcher Ekosistem KusumaVision di topbar untuk memudahkan staf/NOC berpindah antar aplikasi (NMS, MikroTik, Billing, SSO, Portal Perusahaan).
   - Memperkaya visualisasi redaman optik di tabel Interfaces (`public/assets/js/interfaces.js` & `interfaces.css`): badge neo-brutalism dengan color-coded indicator (OK/WARN/CRIT/LOS).
   - Menambahkan badge level RX realtime di kartu monitoring (`statNowBadge` di `monitoring.blade.php`).
2. **Peningkatan Mobile App (Flutter v2.0.2)**:
   - Menambahkan dukungan penuh **Dark Mode** (`buildNetpulseDarkTheme()`) dengan tema navy slate yang nyaman untuk teknisi lapangan.
   - Pilihan pengaturan tema (Sistem / Terang / Gelap) di halaman Akun dengan reaktivitas instan via `themeModeNotifier` dan persistence di SharedPreferences.
   - Menambahkan kartu "Pembaruan Aplikasi" di halaman Akun lengkap dengan tombol dialog link unduh APK resmi.
   - Menambahkan visualisasi mini signal progress bar & dot indikator level redaman pada kartu interface (`_InterfaceCard` di `interfaces_screen.dart`), serta penyesuaian kontras warna adaptif untuk mode terang dan gelap.
   - Kompilasi build APK release `v2.0.2+3` sukses (56.1 MB).

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
