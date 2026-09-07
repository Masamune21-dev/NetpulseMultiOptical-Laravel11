# WORKLOG — Netpulse Multi Optical

Sistem pemantauan status antarmuka fiber optik, redaman/DDM optical power, dan SLA jaringan ISP PT BERKAH MEDIA KUSUMA VISION (BMKV).

---

## 2026-09-07 — Pemulihan Interval Polling 1 Menit & Eliminasi Flapping Alert
1. **Stabilisasi Parameter SNMP Timeout & Retries**:
   - Menyetel timeout SNMP ke **2.0 detik** (`2000000` microsecond) dengan **2 kali retries** (toleransi ~6 detik) pada probe awal `$ifIndex` di `InterfaceDiscovery.php`.
   - Timeout ini memberikan toleransi yang cukup untuk link wireless/lossy (latency 15-30ms) sehingga perangkat online tidak akan pernah salah dideteksi sebagai offline (menghilangkan false-alarm down/up flapping).
   - Pada saat yang sama, jika perangkat benar-benar offline (seperti `SW-BMKV-DAMARWULAN`), probe gagal dalam tepat 6 detik dan langsung me-return status `SKIP` tanpa mencoba 9 pemanggilan walk berikutnya yang membuang waktu 50+ detik.
2. **Kinerja & Hasil Verifikasi**:
   - Waktu polling paralel seluruh 23 perangkat turun dari 67 detik menjadi **7.1 detik**.
   - Jadwal `routes/console.php` dipasang `poll:interfaces --timeout=30` dengan `withoutOverlapping(10)`.
   - **Hasil di Database**: Timestamp `interface_stats` terverifikasi masuk **setiap 60-61 detik (1 menit persis)** tanpa pernah ter-skip lagi, dan log alert tetap stabil tanpa duplikasi alert palsu.

---

## 2026-09-07 — Perataan Dark Mode Mobile & Perampingan Kartu Filter Peta
1. **Perataan Tema Gelap Menyeluruh (Fixed)**:
   - Melengkapi `buildNetpulseDarkTheme()` (`navigationBar`, `bottomSheet`, `dialog`, `switch`, `dropdown`, divider) + helper baru `theme_helper.dart` (`cardBg/cardBorder/subtleBg/textPrimary/textMuted/chartGrid`).
   - `HomeScreen`: teks grow menjadi adaptif (`textPrimary/textMuted/textFaint`), skeleton mengikuti tema.
   - `InterfaceTrafficScreen`: kartu header/chart/summary, chip range, grid & label chart mengikuti tema.
   - `MonitoringScreen`: grid & label chart, chip range mengikuti tema.
   - `InterfacesScreen`: teks counter + empty-state mengikuti tema.
   - `MapScreen`: background mengikuti tema.
2. **Perampingan Kartu Line Filter Peta (Fixed)**:
   - `_TopPanel` diubah dari kartu besar bertumpuk (judul + Wrap 2 baris) menjadi satu baris horizontal scrollable yang ramping (ikon filter + 4 chip compact), tinggi kartu turun drastis sehingga peta terlihat lega.
   - `_LegendCard` diubah menjadi pill ramping satu baris yang bisa di-tap untuk expand/collapse legenda, margin overlay diperketat (10px).
3. **Rilis APK v2.0.3 (Build 5)**:
   - Bump `mobile/pubspec.yaml` ke `2.0.3+5`, teks versi di `account_screen.dart` diselaraskan.
   - Build release sukses via `bin/build-apk.sh`, `aapt` terverifikasi `versionCode=5 versionName=2.0.3`, tersedia di `/download/app`.

---

## 2026-09-07 — Endpoint Unduh APK Kanonis Tunggal & Otomasi Replace
1. **Endpoint Unduh Dinamis `/download/app`**:
   - Menyediakan rute download dinamis di `routes/web.php` (`/download/app`) yang menyajikan binary APK langsung dari backend dengan header `Content-Disposition: attachment; filename="netpulse.apk"` dan `Cache-Control: no-cache, no-store`.
   - Mengatasi issue caching Cloudflare Edge: Cloudflare memperlakukan rute ini sebagai `cf-cache-status: DYNAMIC`, sehingga pengguna selalu mendapatkan APK versi paling baru yang ada di disk tanpa perlu mengganti nama file atau URL unduhan.
2. **Automasi Script `bin/build-apk.sh`**:
   - Memperbarui skrip `bin/build-apk.sh`: setiap build APK selesai, skrip otomatis membersihkan APK lama di `public/downloads/` dan menaruh binary baru menggantikan `public/downloads/netpulse.apk` secara atomik, lalu memverifikasi versi dengan `aapt`.
   - Menghubungkan seluruh tombol unduh di web topbar, sidebar nav, dan layar Akun mobile ke endpoint kanonis `/download/app`.

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
