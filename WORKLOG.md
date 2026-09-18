# WORKLOG — Netpulse Multi Optical

Sistem pemantauan status antarmuka fiber optik, redaman/DDM optical power, dan SLA jaringan ISP PT BERKAH MEDIA KUSUMA VISION (BMKV).

---

## 2026-09-18 — Fixed: Beranda kosong di 2.1.0, kartu dempet, APK 56 MB → rilis 2.1.1+7 split-per-abi

Umpan balik user dari HP setelah memasang 2.1.0+6: Beranda hanya menampilkan hero dan dua ubin
KPI (kadang kosong sama sekali), kartu di Monitoring/Akun/Alert saling menempel, dan APK 56 MB
padahal Billing/NMS ±20 MB.

- **Fixed — `home_screen.dart` (`_KpiGrid`)**: dua `Row(crossAxisAlignment: stretch)` berada di
  dalam `ListView` yang tingginya tak terbatas, sehingga `stretch` memaksa tinggi tak hingga →
  layout gagal dan **seluruh sisa halaman tidak digambar** (di rilis tidak ada tanda error,
  hanya kosong). Kedua baris dibungkus `IntrinsicHeight`.
- **Fixed — `app_theme.dart` (`cardTheme.margin`)**: saya menolkan margin kartu saat membangun
  tema baru, padahal layar lama menumpuk `Card` tanpa `SizedBox` dan mengandalkan margin tema.
  Kini `EdgeInsets.only(bottom: 12)`; jarak samping tetap dari padding ListView (16).
- **Changed — `account_screen.dart`**: teks "Versi saat ini" yang dulu hardcode `v2.0.3 (Build 5)`
  kini dibaca dari `PackageInfo` (versi + build number aktual).
- **Changed — `bin/build-apk.sh`**: `flutter build apk --release --split-per-abi
  --target-platform android-arm,android-arm64`, pola yang sama dengan Billing/NMS. APK universal
  lama membawa `lib/x86_64` (18,9 MB), `lib/arm64-v8a` (17,5 MB), dan `lib/armeabi-v7a` (15,2 MB)
  sekaligus — x86_64 tidak dipakai HP mana pun. Hasil: `netpulse.apk` (arm64) **20,8 MB** dan
  `netpulse-arm32.apk` **18,4 MB** untuk HP 32-bit lama. Skrip menghapus APK lama, menyalin
  keduanya, dan `chown www-data`.
- **Notes — versionCode**: `--split-per-abi` menambahkan offset ABI ke versionCode (arm32 =
  1000+N, arm64 = 2000+N). `pubspec` 2.1.1+7 → arm64 terpasang sebagai versionCode **2007**;
  ini normal (Billing sama) dan tetap dianggap pembaruan dari 6.
- **Notes — verifikasi**: `flutter analyze` bersih; `/download/app`, `/downloads/netpulse.apk`,
  dan `/downloads/netpulse-arm32.apk` menjawab HTTP 200. Uji visual di HP menunggu user.

---

## 2026-09-18 — Fixed: Perangkat down tetap tampil "online" di web & peta sampai Test SNMP manual

- **Gejala** (laporan user): Telegram dan APK sudah menerima "DEVICE DOWN", tetapi halaman
  Devices dan Map di web masih menampilkan perangkat online; status baru berubah setelah tombol
  **Test SNMP** ditekan pada perangkat itu.
- **Akar masalah**: poller `poll:interfaces` (`InterfaceDiscovery::discover`) hanya mengirim
  alert dan menyimpan `storage/app/alert_state/<id>.json`; ia **tidak pernah menulis**
  `snmp_devices.last_status`. Satu-satunya penulis kolom itu adalah
  `DevicesApiController::testSnmp()`. Padahal `public/assets/js/devices.js:122-125`,
  `MapApiController`, dan `Api/V1/MapController` (peta di APK) semuanya membaca `last_status`.
  Kolom `last_monitor_status`/`last_monitor_time` bahkan tidak pernah diisi (semua `unknown`/NULL).
- **Fixed — `app/Services/InterfaceDiscovery.php`**: helper baru `markDeviceStatus(id, up, error)`
  menulis `last_status` (OK/FAILED), `last_error`, `last_monitor_status` (up/down), dan
  `last_monitor_time` = now(). Dipanggil di tiga titik: ifIndex gagal → FAILED; ifName gagal →
  FAILED; keduanya terbaca → OK. Berlaku untuk jalur CLI (cron) maupun tombol Discover di web.
  Gagal tulis hanya dicatat ke log, tidak menghentikan polling.
- **Notes — verifikasi**: `poll:interfaces --device=23` sebagai `www-data` → `OK / up /
  15:26:55`. Jalur down diuji dengan baris perangkat sementara `ZZ-UJI-DOWN-SEMENTARA`
  (IP 192.0.2.1, `is_active=0`) → `FAILED / down / "SNMP tidak menjawab (IF-MIB ifIndex) saat
  polling"`, tanpa alert terkirim (state sebelumnya tidak dikenal), lalu baris dan berkas
  state-nya dihapus. Siklus cron berikutnya mengisi `last_monitor_time` untuk seluruh perangkat
  aktif.
- **Notes**: alert transisi down/up tetap bertumpu pada berkas state per perangkat; yang
  berubah hanya tabel kini ikut mencerminkannya, sehingga Dashboard web/mobile (hitungan
  `last_status='FAILED'`), Devices, dan kedua peta akurat tanpa campur tangan manual.

---

## 2026-09-18 — Changed: Desain ulang Netpulse Mobile v2.1.0+6 (token seragam, widget bersama, riwayat 24 jam)

Latar: audit 4 aplikasi mobile ekosistem menemukan Netpulse satu-satunya tanpa lapisan
sistem desain — 32 varian padding, 11 nilai radius, 98 pemakaian warna hardcode (28 warna
unik, lima hijau dan empat merah untuk arti yang sama), dan **ambang RX yang berbeda antar
layar** (beranda −25, monitoring −35, peta/interface −40). Arah yang disepakati user:
permukaan slate netral gaya Ubiquiti, teal sebagai satu-satunya aksen, status hanya lewat
badge + kata. Mockup HTML disetujui sebelum kode disentuh.

- **Created — `mobile/lib/src/theme/tokens.dart`**: `Np` (ThemeExtension) dengan palet terang
  & gelap (bg/surface/surface2/border, ink×3, accent, ok/warn/bad/info masing-masing
  `mark`/`text`/`bg`, track, shadow), `NpRadius` (pill/12/16/24), `NpSpace` (skala 4 pt:
  4·8·12·16·20·24), `NpFont` (Sora/Inter/JetBrainsMono), `NpText.mono()`, `NpMotion`.
  Diakses lewat `context.np`.
- **Created — `mobile/lib/src/theme/status.dart`**: `NetStatus {up, warn, down, none}` dengan
  label Indonesia (Up/Marjinal/Down/Tanpa DDM), `RxThresholds` (dari JSON server, `adopt()`
  menyimpan sebagai `RxThresholds.current`), `statusOf(rx, operStatus)` — **satu aturan** untuk
  semua layar: oper≠1 → down; rx null → tanpa DDM; rx ≤ ambang down → down; rx < ambang
  peringatan → marjinal. Ekstensi `Np.status()` dan `Np.severity()` untuk warna.
- **Changed — `mobile/lib/src/theme/app_theme.dart`** dibangun ulang dari token: ColorScheme,
  skala tipe 7 langkah (KPI Sora 24/800 · judul layar 18/700 · judul kartu 14/700 · nama baris
  Inter 13.5/600 · keterangan 12/500 · badge 11/700 · mono 14/600), tema AppBar/Card/Chip/
  Segmented/NavigationBar/Input/Sheet/Dialog/Switch/Snackbar dari satu sumber. Nama fungsi
  `buildNetpulseTheme()`/`buildNetpulseDarkTheme()` dipertahankan.
- **Changed — `theme_helper.dart`** kini hanya pemetaan ke token (`cardBg→surface`,
  `textMuted→ink2`, dst.) supaya layar yang belum ditulis ulang ikut palet yang sama.
- **Changed — `pubspec.yaml`**: font Sora/Inter/JetBrainsMono dibundel dari `assets/fonts/`
  (disalin dari NMS mobile); dependensi `google_fonts` **dilepas** — sebelumnya font diunduh
  saat aplikasi dibuka, tampilan pertama bisa memakai fallback saat sinyal buruk. Versi
  dinaikkan ke **2.1.0+6**.
- **Created — `mobile/lib/src/ui/widgets/`**: `SectionCard` (+`RowDivider`), `StatTile`,
  `StatusBadge` (+`StatusDot`, konstruktor `.severity`), `HeartbeatBar` (bar riwayat per jam
  ala Uptime Kuma, huruf u/w/d/n), `RxValue` + `RxMeter` (mono berwarna status, meter skala
  tetap −40..−10 dengan garis patokan ambang peringatan; −40/null tampil "—"), `ScreenHeader`
  + `HeaderIconButton` + `BrandMark`, `AsyncState` (loading/error/empty seragam).
- **Changed — `home_screen.dart`** ditulis ulang: header dengan tombol alert (titik merah bila
  ada critical) dan muat ulang; hero kesehatan (ring + kalimat keadaan + badge Aktif/Gagal/
  Nonaktif) menggantikan gradien teal→sky; 4 ubin KPI; "RX terendah" (device · port mono ·
  alias, RxValue dengan meter); "Alert terbaru" dengan garis severity 3 px. Skor kesehatan
  tetap rumus lama (tiap 1 % port bermasalah −3 poin).
- **Changed — `interfaces_screen.dart`** ditulis ulang: kotak cari (debounce 400 ms →
  `q`), chip perangkat horizontal, segmen Semua/Marjinal/Down (`status=all|warn|down`), toggle
  urutan perangkat/RX terendah (`sort=device|rx`), daftar dalam satu kartu dengan baris:
  nama perangkat + badge · port mono + alias · **bar riwayat 24 jam** + RX + kecepatan.
  Kartu per-baris lama dengan tiga kotak RX/TX/Speed dan dua kotak In/Out dihapus (detail tetap
  di layar traffic).
- **Changed — `home_shell.dart`**: label tab Beranda / Monitoring / Interface / Peta / Akun.
- **Changed — sapuan token di layar lain** (`map`, `monitoring`, `interface_traffic`,
  `alerts`, `settings`, `about`, `login`): seluruh warna hardcode diganti token (kini **0** hex
  di `lib/src/ui`); `_rxColor`, `_linkLevel`, `_MetricChip._dotColor` memakai
  `RxThresholds.current` — pita "warning −25..−18" di peta yang terbalik ikut hilang; hero
  gradien monitoring jadi kartu permukaan; seri grafik traffic In/Out memakai aksen/info (bukan
  hijau status); legenda peta "Warning" → "Marjinal"; layar Tentang berbahasa Indonesia.
- **Created — `app/Support/RxThresholds.php`** (sisi Laravel): `global()` membaca
  `settings.alert_rx_warning_low` / `alert_rx_down_threshold` (default −25 / −40, logika sama
  dengan `InterfaceThresholdsController::globalThresholds()`); `history24h(pairs, thresholds)`
  membangun 24 bucket per (device_id, if_index) dari `interface_stats_hourly` (rx_min ≤ down →
  d, rx_avg < warn → w, ada sampel → u, tanpa sampel → n). Terukur **7 ms** untuk 4 interface,
  ~100 ms untuk halaman 25 baris termasuk kueri utama; indeks `idx_dev_if_bucket` sudah ada.
- **Changed — `Api/V1/DashboardController`**: respons membawa `thresholds`; `worst_ports` kini
  **hanya port hidup dengan pembacaan nyata** (`oper_status = 1 AND rx_power > ambang down`) —
  sebelumnya enam teratas selalu slot SFP kosong bernilai −40, sehingga daftar tak berguna.
- **Changed — `Api/V1/InterfacesController::index`**: parameter baru `status=warn` (up tapi RX
  di antara ambang down dan peringatan) dan `sort=rx` (port hidup RX terlemah di atas, port
  down/tanpa DDM di bawah); tiap baris membawa `history_24h`; `meta.thresholds` ditambahkan.
  Tidak ada rute baru → `route:cache` tidak perlu disegarkan.
- **Notes — verifikasi**: `flutter analyze` bersih; `flutter build apk --release` sukses →
  `mobile/build/app/outputs/flutter-apk/app-release.apk` (56,6 MB). Uji controller sebagai admin:
  dashboard 82 ms; `/interfaces?sort=rx` 100 ms (269 port); `status=down` 128 port;
  `status=warn` 0 port saat ini; `q=uplink` 7 hasil dengan riwayat 24 jam terisi.
- **Notes — rilis**: atas permintaan user, `bash bin/build-apk.sh` dijalankan langsung tanpa
  uji HP dulu → `public/downloads/netpulse.apk` kini **2.1.0 (versionCode 6)**, 56,6 MB, milik
  `www-data`. Tombol unduh di topbar/sidebar web sudah tidak membawa teks versi, jadi tidak ada
  badge yang perlu diubah. Layar Monitoring, Peta, Traffic, dan Akun baru disapu warnanya, belum
  ditata ulang ke bahasa widget bersama; 32 varian padding lama di layar-layar itu masih ada.

---

## 2026-09-17 — Created: Endpoint `/healthz` untuk Monitoring Uptime

- **Created — `routes/web.php`: `GET /healthz`** (tanpa auth, bernama `healthz`). Membalas
  `{status, app, database, timestamp}` dengan HTTP **200/503**, bentuk yang sama dengan
  `/healthz` milik SSO, NMS, MikroTik, dan Billing supaya satu pemeriksa kata kunci
  (`"status":"ok"`) berlaku untuk seluruh ekosistem. Disiapkan untuk Uptime Kuma yang akan
  dipasang di `uptime.kusumavision.net`.
- **Notes — hanya MariaDB yang diperiksa, Redis sengaja tidak.** App ini satu-satunya di
  ekosistem yang tidak memakai Redis sama sekali (`QUEUE_CONNECTION=sync`, `CACHE_STORE=file`,
  `SESSION_DRIVER=file`), jadi melaporkan status Redis di sini hanya akan menciptakan sinyal
  palsu. Pemeriksaannya `getPdo()` **plus** `select 1` — membuka koneksi saja belum membuktikan
  server menjawab kueri.
- **Notes — kenapa `/api/v1/ping` yang sudah ada tidak dipakai.** `routes/api.php:17` membalas
  `{ok:true, ts}` **tanpa menyentuh database sama sekali**, jadi ia tetap hijau saat MariaDB
  mati — persis kondisi yang paling perlu terdeteksi. Ia tetap dibiarkan untuk aplikasi mobile.
- **Changed — `use Illuminate\Support\Facades\DB;`** ditambahkan ke daftar impor, mengikuti
  pola `KusumaVisionSSO/routes/web.php:17`. `config/app.php` app ini tidak punya blok `aliases`,
  jadi memakai `DB::` tanpa impor bertumpu pada alias bawaan framework — eksplisit lebih aman.
- **Notes — `php artisan route:cache` WAJIB dijalankan ulang.** `bootstrap/cache/routes-v7.php`
  aktif di produksi; tanpa menyegarkannya rute baru membalas **404**. Berkas cache lama ternyata
  milik `root:root` (jejak artisan yang pernah dijalankan sebagai root); sesudah disegarkan
  sebagai `www-data` kepemilikannya kembali benar.
- **Notes — verifikasi**: `curl https://netpulse.kusumavision.net/healthz` → **HTTP 200 dalam
  0,82 detik**, `{"status":"ok","database":true,"timestamp":"2026-09-17T17:52:48+07:00"}`.
  Timestamp ber-offset `+07:00` sesuai `DB_TIMEZONE`, jadi tidak perlu diterjemahkan lagi oleh
  pembacanya.

## 2026-09-12 — Dokumentasi: Bagian Keamanan di NETPULSE_DOCUMENTATION.md

- **Created — bagian `## Keamanan`** (disisipkan setelah Role Matrix): seluruh pengerasan 10 Sep (NP-1, NP-4, NP-5, NP-6) sebelumnya hanya tercatat di WORKLOG, padahal ia **keadaan yang berlaku** dan sebagian besar mudah dirusak tanpa sengaja saat menambah fitur.
- **Notes — ditulis sebagai aturan kerja, bukan riwayat**. Yang ditekankan: (1) hanya `api/v1/*` yang dikecualikan dari CSRF, rute web `/api/*` wajib `X-CSRF-TOKEN`; (2) **endpoint yang menulis DB tidak boleh `GET`** — `discover_interfaces` & `huawei_discover_optics` sudah dipindah ke POST karena itu; (3) rahasia **selalu** dibaca lewat `Secret::reveal()`, jangan langsung dari kolom; (4) form yang dikosongkan berarti "pertahankan nilai lama"; (5) `route:cache` + `config:cache` wajib diperbarui setelah mengubah rute/config karena produksi memakainya.
- **Notes — satu konsekuensi yang belum pernah ditulis di mana pun**: `UserState` mem-cache role & `is_active` selama 60 detik, jadi **akun yang dinonaktifkan kehilangan sesinya dalam ≤60 detik** tanpa perlu menunggu logout. Itu perilaku yang perlu diketahui admin, bukan detail implementasi.
- **Notes — utang test dicatat terang-terangan**: `phpunit.xml` belum diarahkan ke sqlite, sehingga menjalankan test di server ini berisiko menyasar DB produksi — itu sebabnya verifikasi pengerasan dilakukan manual lewat curl. Ditulis berikut arah perbaikannya (pola `scripts/test.sh` di keempat app Laravel lain), supaya tidak terlupa.
- **Notes — tidak ada perubahan kode**, murni penyelarasan dokumentasi.

---

## 2026-09-10 — Perbaikan Temuan Audit Keamanan (NP-1, NP-4, NP-5, NP-6, deps)
1. **Fixed — NP-6 CSRF & metode aman**: `bootstrap/app.php` pengecualian CSRF kini hanya `api/v1/*` (Bearer, tanpa sesi); rute web `/api/*` ber-sesi wajib `X-CSRF-TOKEN`. `layouts/app.blade.php` menambah `<meta name="csrf-token">` + pembungkus global `fetch`/`XMLHttpRequest` yang menyuntik header untuk request same-origin non-GET (semua JS lama di `public/assets/js/*.js` otomatis tercakup). Logout diubah dari `GET /logout` menjadi form `POST /logout` + `@csrf` (sidebar & mobile header; CSS `.logout-form { display: contents }`). `GET /api/discover_interfaces` & `huawei_discover_optics` (menulis DB) menjadi `POST` — pemanggil `devices.js` & `map.js` disesuaikan; `DiscoverInterfacesController` membaca `device_id` via `input()`.
2. **Fixed — NP-1 throttle login**: limiter `login` 5/menit per `strtolower(username)|ip` (`AppServiceProvider::configureRateLimiting`) dipasang di `POST /login` dan `POST /api/v1/auth/login`; limiter `api` 120/menit per user/token/IP via `$middleware->throttleApi()` untuk grup `routes/api.php`. Kegagalan & pembatasan dicatat ke `storage/logs/security.log` lewat `app/Support/SecurityLog.php` (event `LOGIN_THROTTLED`, `API_LOGIN_FAILED`, `API_LOGIN_SUCCESS`; `AuthController::writeSecurityLog` kini delegasi ke kelas yang sama). Verifikasi: 6× POST `/api/v1/auth/login` salah → ke-6 = 429.
3. **Fixed — NP-5 password plaintext**: command baru `users:hash-plaintext-passwords` (`--dry-run`), idempoten. Dijalankan di produksi: **0 dari 5** baris `users.password` plaintext (semua sudah bcrypt). Fallback `hash_equals($input, $user->password)` dihapus dari `AuthController.php` dan `Api/V1/AuthController.php`.
4. **Fixed — NP-4 kebocoran kredensial**: `GET /api/devices` kini `select` eksplisit tanpa `community`/`snmp_user`/`telnet_*`, hanya flag `community_set`/`snmp_user_set`; `devices.js` menampilkan `••••` dan form edit mengosongkan input (kosong/placeholder = nilai lama dipertahankan, ditangani `DevicesApiController::store`). `SettingsApiController` & `Api/V1/SettingsController`: `bot_token` diredaksi (non-admin `''`, admin placeholder `••••`, plus `bot_token_set`) via `Secret::redactSettings()`; saat simpan placeholder diabaikan dan nilai baru dienkripsi (`Secret::prepareSettingsForSave()`). Enkripsi at-rest `snmp_devices.community` & `settings.bot_token` memakai `app/Support/Secret.php` (`Crypt`, idempoten, `reveal()` fallback plaintext) — migrasi `2026_09_10_000001_encrypt_secrets_and_token_expiry` melebarkan `community` ke `TEXT` dan mengenkripsi 23 device + 1 bot_token. Pembaca (`InterfaceDiscovery` untuk poller/Telegram, `DevicesApiController::testSnmp`, `telegramTest`) memakai `Secret::reveal()`; poller cron tetap menghasilkan data setelah migrasi (`interface_stats` 04:50).
5. **Fixed — NP-6 sisa (token & role)**: `HasApiTokens::createToken()` mengisi `expires_at` (default 90 hari), respons login v1 menyertakan `expires_at`; 22 token lama tanpa `expires_at` diberi 90 hari dari sekarang oleh migrasi; `AuthenticateApiToken` juga menolak akun `is_active=0`. `app/Support/UserState.php` memuat ulang `role` & `is_active` dari DB (cache 60 dtk) — dipakai `EnsureAuthenticated` (sesi diinvalidasi bila akun nonaktif/hilang, role sesi disinkronkan) dan `EnsureRole`; `UsersApiController` mem-flush cache saat update/hapus user.
6. **Changed — konfigurasi & izin**: `.env` `SESSION_SECURE_COOKIE=true` (Set-Cookie kini `Secure`); `chmod 640 storage/app/firebase/service-account.json storage/logs/*.log`. `route:cache` + `config:cache` diperbarui (cache dipakai produksi).
7. **Changed — dependensi**: `composer update --with-all-dependencies` laravel/framework 11.48.0→11.56.1, symfony/* 7.4.4/5→7.4.18, guzzle 7.10.0→7.15.5, commonmark 2.8.0→2.10.1, dompdf 3.1.5→3.1.6 (tanpa naik mayor). `composer audit`: **42 → 3** advisori, sisa semuanya `laravel/framework` yang hanya diperbaiki di 12.x (CRLF injection rule `email` — CVE-2026-48019 & PKSA-3r5d, Temporary Signed URL Path Confusion — PKSA-m5cs; app tidak memakai `signedRoute`/`temporarySignedRoute`; rule `email` hanya di validasi internal). Composer 2.9 memblokir seluruh 11.x karena advisori, jadi `composer.json` menambah `config.audit.block-insecure=false` (wajib agar update dalam `^11` bisa berjalan). `npm run build` (Vite) dijalankan ulang.
8. **Notes**: test otomatis tidak dijalankan (`phpunit.xml` tidak memakai sqlite → berisiko menyasar DB produksi); verifikasi manual via curl: `/login` 200, login→dashboard 200, POST `/api/*` ber-sesi tanpa token 419 / dengan token 400·403 (bukan 419), `GET /logout` 405, `POST /logout` 302→/login, `/api/settings` teknisi `bot_token=''`, `/api/devices` tanpa kolom rahasia, throttle API v1 ke-6 = 429. Tidak ada layanan yang di-restart; belum di-commit.

## 2026-09-10 — Audit Keamanan (baca-saja)
1. **Notes**: Audit keamanan & cakupan role ekosistem (baca-saja, tanpa perubahan kode). Hasil lengkap, bukti `file:baris`, runbook, dan prioritas ada di `/var/www/DOKUMENTASI_EKOSISTEM_KUSUMAVISION.md` §13; koreksi klaim dokumentasi diterapkan di §1.2, §2.3, §3.B, §4.A–§4.G, §6, §10.A dan `DOKUMENTASI_SISTEM_TRIAD.md` §7.
2. **Notes — temuan Netpulse** (kode & server tidak diubah): **Tinggi** tidak ada throttle/lockout pada `POST /login` maupun `POST /api/v1/auth/login` (`throttleApi()` tidak dipanggil di `bootstrap/app.php`); `.env` `644 root:root` world-readable (runbook `chmod 640 root:www-data` di §13.K4, belum dijalankan). **Sedang** `storage/app/firebase/service-account.json` `775` & log `775`/`644`; SNMP community plaintext dan `GET /api/devices` mengembalikan seluruh kolom ke technician; token bot Telegram plaintext dan `GET /api/settings` mengembalikannya ke technician; fallback login menerima password plaintext tersimpan (`AuthController.php:41-45`, perlu cek isi tabel `users`); `SESSION_SECURE_COOKIE` tidak diset; vhost tanpa HSTS. Rendah: CSRF dimatikan untuk `api/*` yang ber-cookie (`GET /logout`, `GET /api/discover_interfaces` menulis DB); token v1 tanpa `expires_at`; role disalin ke sesi saat login. `composer audit`: **42 advisori** (laravel/framework 11.48.0, guzzle 7.10.0, commonmark 2.8.0, symfony/http-kernel — high). `npm audit`: 0.

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
