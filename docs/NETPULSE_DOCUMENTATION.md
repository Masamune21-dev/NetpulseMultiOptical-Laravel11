# NetPulse MultiOptical Documentation

Last updated: 2026-05-06

Dokumen ini menjelaskan struktur aplikasi NetPulse MultiOptical saat ini: UI web, mobile app, routing, API, role, setting, scheduler, polling SNMP, deployment, dan troubleshooting.

## Ringkasan

NetPulse MultiOptical adalah platform monitoring jaringan optik/SFP berbasis Laravel dan Flutter. Aplikasi web dipakai untuk NOC/admin, sedangkan aplikasi mobile memakai API v1 dengan bearer token.

Komponen utama:

- Laravel 11 web dashboard dengan Blade views.
- Legacy web API di `/api/*` untuk halaman web.
- Mobile API v1 di `/api/v1/*` untuk Flutter app.
- SNMP discovery/polling via command `poll:interfaces`.
- Alert log Web UI, Telegram alert, dan FCM push notification.
- Interactive network map dengan node, link, status interface, dan path link.
- Role-based access: `admin`, `technician`, `viewer`.

## Struktur Repository

| Path | Fungsi |
| --- | --- |
| `app/Http/Controllers` | Controller web page dan legacy web API. |
| `app/Http/Controllers/Api/V1` | Controller REST API untuk mobile app. |
| `app/Http/Middleware` | Middleware session auth, role, dan bearer token API. |
| `app/Services/InterfaceDiscovery.php` | Discovery SNMP, simpan interface/statistik, kirim alert. |
| `app/Services/FcmService.php` | Kirim FCM push notification via Firebase HTTP v1. |
| `app/Console/Commands/PollInterfaces.php` | Artisan command polling semua device aktif. |
| `routes/web.php` | Route halaman web dan legacy web API. |
| `routes/api.php` | Route REST API v1 untuk mobile. |
| `routes/console.php` | Laravel scheduler. |
| `resources/views` | Blade UI: login, layout, dashboard, monitoring, devices, map, users, settings. |
| `public/assets/js` | JavaScript halaman web. |
| `public/assets/css` | Global CSS. Halaman memuat `style.min.css`. |
| `mobile/` | Flutter Android app. |
| `scripts/cron/laravel_schedule_run.sh` | Script cron untuk menjalankan scheduler Laravel per menit. |
| `storage/logs` | Laravel log, security log, schedule log. |
| `storage/app/alert_state.json` | State transisi alert polling SNMP. |

## Alur Akses

### Web Session

1. User login dari `/login`.
2. `AuthController` validasi `username`, `password`, dan `is_active`.
3. Session menyimpan `auth.logged_in` dan `auth.user`.
4. Route web dilindungi middleware `legacy.auth`.
5. Aksi tertentu dibatasi middleware `legacy.role`.

Security log web login ditulis ke:

```text
storage/logs/security.log
```

### API v1 Token

1. Client mobile memanggil `POST /api/v1/auth/login`.
2. Server membuat token di tabel `personal_access_tokens`.
3. Client mengirim header:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

4. Middleware `api.auth` memvalidasi token dan mengisi `$request->user()`.

### Role Matrix

| Modul | Admin | Technician | Viewer |
| --- | --- | --- | --- |
| Dashboard | Read | Read | Read dummy/demo data |
| Monitoring | Read | Read | Read dummy/demo data |
| Devices | Read/create/update/delete | Read | Read dummy/demo data |
| Interface discovery | Run | Run/read via UI behavior, write depends endpoint access | Dummy no-write |
| Map | Read/create/update/delete | Read | Read dummy/demo data |
| Users | Read/create/update/delete | Read | Dummy/read-limited |
| Settings | Read/write | Read | Dummy/read-limited |
| Alert logs | Read/delete | Read | Read dummy/demo data |
| Security logs | Read | Forbidden | Dummy/read-limited |
| Mobile push/location | Authenticated user | Authenticated user | Authenticated user |

Catatan: beberapa tombol create/edit/delete juga disembunyikan dari UI memakai `body[data-role]`, tetapi pembatasan utama tetap di controller/middleware.

## UI Web

### Layout Utama

File:

- `resources/views/layouts/app.blade.php`
- `public/assets/css/style.min.css`
- `public/assets/js/theme.js`
- `public/assets/js/script.js`

Elemen layout:

- Desktop sidebar: logo, navigasi, user info, logout.
- Mobile header: brand ringkas.
- Mobile bottom navigation: Dashboard, Monitor, Devices, Map, Settings.
- Topbar: title halaman, action slot, live clock, user chip.
- Global delete modal.
- Footer.

Navigasi desktop:

- `/dashboard`
- `/monitoring`
- `/devices`
- `/map`
- `/users`
- `/settings`
- `/logout`

### Login

Route:

- `GET /login`
- `POST /login`

File:

- `resources/views/auth/login.blade.php`
- `app/Http/Controllers/AuthController.php`

Fungsi:

- Login berbasis username/password.
- Auto re-hash password plaintext legacy jika password lama masih belum hash.
- Menolak akun `is_active = 0`.
- Mencatat `LOGIN_SUCCESS` dan `LOGIN_FAILED`.

### Dashboard

Route:

- `GET /dashboard`

File:

- `resources/views/dashboard/index.blade.php`
- `app/Http/Controllers/DashboardController.php`

Data utama:

- Device aktif dari `snmp_devices`.
- Total interface dari `interfaces`.
- Jumlah SFP/optical aktif.
- Optical critical dari `alert_logs` dan status interface.
- Total user.
- Device health.
- Interface up/down.
- Trend alert 7 hari.
- Worst optical ports.
- Recent alerts.

API pendukung:

- `GET /api/v1/dashboard` untuk refresh KPI mobile/API.

### Monitoring

Route:

- `GET /monitoring`

File:

- `resources/views/monitoring/index.blade.php`
- `public/assets/js/monitoring.js`
- `app/Http/Controllers/MonitoringApiController.php`

Fungsi UI:

- Pilih device aktif.
- Pilih interface SFP.
- Pilih range chart: `1h`, `1d`, `3d`, `7d`, `30d`, `1y`.
- Chart RX/TX/loss memakai Chart.js.
- Statistik RX: sekarang, rata-rata, minimum, maximum.

API pendukung:

- `GET /api/monitoring_devices`
- `GET /api/monitoring_interfaces?device_id=...`
- `GET /api/interface_chart?device_id=...&if_index=...&range=...`

### Devices

Route:

- `GET /devices`

File:

- `resources/views/devices/index.blade.php`
- `public/assets/js/devices.js`
- `app/Http/Controllers/DevicesApiController.php`
- `app/Http/Controllers/InterfacesApiController.php`
- `app/Http/Controllers/DiscoverInterfacesController.php`

Tab/Fungsi:

- SNMP Devices: list, add, edit, delete, test SNMP.
- Interface Discovery: pilih device, discover interface, tampilkan interface/SFP.
- Huawei discovery endpoint memakai controller yang sama dengan discovery umum.

API pendukung:

- `GET /api/devices`
- `GET /api/devices?test=<id>`
- `POST /api/devices`
- `DELETE /api/devices?id=<id>`
- `GET /api/interfaces?device_id=<id>`
- `GET /api/discover_interfaces?device_id=<id>`
- `GET /api/huawei_discover_optics?device_id=<id>`

### Map

Route:

- `GET /map`

File:

- `resources/views/map/index.blade.php`
- `public/assets/js/map.js`
- `app/Http/Controllers/MapApiController.php`

Fungsi UI:

- Leaflet network map.
- Tambah/edit/hapus node.
- Lock/unlock posisi node.
- Tambah/hapus link antar node/interface.
- Edit path link.
- Filter status/device.
- Detail sidebar node dan interface.

API pendukung:

- `ANY /api/map_nodes`
- `ANY /api/map_links`
- `GET /api/map_devices`

Tabel `map_nodes` dan `map_links` dibuat otomatis oleh `MapApiController` jika belum ada, tetapi lebih baik disiapkan lewat schema/migration saat deployment production.

### Users

Route:

- `GET /users`

File:

- `resources/views/users/index.blade.php`
- `public/assets/js/users.js`
- `app/Http/Controllers/UsersApiController.php`

Fungsi UI:

- List user.
- Add/edit user.
- Reset password saat edit.
- Aktif/nonaktif user.
- Delete user.

Proteksi:

- Read: admin/technician, viewer memakai dummy data.
- Write/delete: admin.
- Admin tidak bisa delete akun sendiri.
- Sistem mencegah delete admin terakhir.

### Settings

Route:

- `GET /settings`

File:

- `resources/views/settings/index.blade.php`
- `public/assets/js/settings.js`
- `app/Http/Controllers/SettingsApiController.php`

Tab/Fungsi:

- Telegram: `bot_token`, `chat_id`, test Telegram.
- Alert: channel toggle, event toggle, threshold RX.
- Theme: light/dark dan warna primary/sidebar.
- Logs: security log viewer.
- Alert logs: filter, refresh, clear.

API pendukung:

- `GET|POST /api/settings`
- `POST /api/telegram_test`
- `GET /api/logs?type=security`
- `GET /api/alert_logs`
- `DELETE /api/alert_logs`

## Mobile App

Path:

- `mobile/`

Versi:

- `2.0.0+2`

Fitur:

- Login API v1.
- Dashboard.
- Monitoring chart.
- Network map.
- Account/settings.
- Alert log access.
- Device token registration.
- FCM push notification.
- Send current location.

Default API base URL:

```text
https://netpulse.bmkv.net
```

User bisa mengubah API base URL dari screen account/settings mobile.

Build debug:

```bash
cd mobile
flutter pub get
flutter build apk --debug
```

Firebase file lokal yang diperlukan:

```text
mobile/android/app/google-services.json
```

File tersebut tidak boleh di-commit.

## Web Routing

Route list diverifikasi dengan `php artisan route:list`.

### Public Route

| Method | Path | Controller | Fungsi |
| --- | --- | --- | --- |
| GET | `/` | closure | Redirect ke `/login`. |
| GET | `/login` | `AuthController@showLogin` | Form login. |
| POST | `/login` | `AuthController@login` | Proses login. |
| GET | `/logout` | `AuthController@logout` | Logout session. |
| GET | `/up` | Laravel health route | Health check. |

### Protected Page Route

Semua route berikut memakai middleware `legacy.auth`.

| Method | Path | Controller | Fungsi |
| --- | --- | --- | --- |
| GET | `/dashboard` | `DashboardController@index` | Dashboard NOC. |
| GET | `/monitoring` | `MonitoringController@index` | Monitoring optical chart. |
| GET | `/devices` | `DevicesController@index` | Device management dan discovery. |
| GET | `/map` | `MapController@index` | Interactive network map. |
| GET | `/users` | `UsersController@index` | User management. |
| GET | `/settings` | `SettingsController@index` | Telegram, alert, theme, logs. |

### Legacy Web API

Semua legacy endpoint berikut berada dalam group `legacy.auth` dan dipakai oleh halaman web.

| Method | Path | Role | Fungsi |
| --- | --- | --- | --- |
| GET | `/api/users` | admin/technician/viewer | List user, viewer mendapat dummy data. |
| POST | `/api/users` | admin | Create/update user. |
| DELETE | `/api/users?id=...` | admin | Delete user. |
| GET | `/api/devices` | logged-in | List SNMP device. |
| GET | `/api/devices?test=...` | logged-in | Test SNMP device. |
| POST | `/api/devices` | admin | Create/update device. |
| DELETE | `/api/devices?id=...` | admin | Delete device dan data terkait. |
| GET | `/api/interfaces?device_id=...` | logged-in | List interface device. |
| GET | `/api/monitoring_devices` | logged-in | List device aktif untuk monitoring. |
| GET | `/api/monitoring_interfaces?device_id=...` | logged-in | List interface SFP. |
| GET | `/api/interface_chart?device_id=...&if_index=...&range=...` | logged-in | Chart history interface. |
| ANY | `/api/map_nodes` | read semua role, write admin | CRUD node map. |
| ANY | `/api/map_links` | read semua role, write admin | CRUD link map/path. |
| GET | `/api/map_devices` | logged-in | Device yang belum terpasang di map. |
| GET | `/api/discover_interfaces?device_id=...` | logged-in | Discovery SNMP interface. |
| GET | `/api/huawei_discover_optics?device_id=...` | logged-in | Alias discovery untuk Huawei. |
| GET | `/api/settings` | admin/technician/viewer | Read settings, viewer dummy. |
| POST | `/api/settings` | admin | Upsert settings. |
| POST | `/api/telegram_test` | admin | Kirim pesan test Telegram. |
| GET | `/api/logs?type=security` | admin/viewer | Security log, viewer dummy. |
| GET | `/api/alert_logs` | admin/technician/viewer | List alert log. |
| DELETE | `/api/alert_logs` | admin | Clear alert log. |
| GET | `/api/data` | logged-in | Legacy placeholder realtime. |
| GET | `/api/test_connection?id=...` | logged-in | Legacy placeholder test connection. |
| GET | `/api/export?format=csv|json` | logged-in | Legacy placeholder export. |

## API v1

Base path:

```text
/api/v1
```

Response umum:

```json
{
  "success": true,
  "data": {}
}
```

Endpoint auth dapat mengembalikan `error` langsung untuk status 401/403.

### Public API

| Method | Path | Body/Query | Fungsi |
| --- | --- | --- | --- |
| GET | `/api/v1/ping` | none | Health check API JSON. |
| POST | `/api/v1/auth/login` | `username`, `password`, optional `device_name` | Login dan buat bearer token. |

Contoh login:

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret","device_name":"android"}'
```

### Authenticated API

Semua endpoint berikut memakai middleware `api.auth`.

| Method | Path | Role | Body/Query | Fungsi |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/auth/logout` | authenticated | none | Hapus token aktif. |
| GET | `/api/v1/dashboard` | admin/technician/viewer | none | KPI dashboard. |
| GET | `/api/v1/monitoring/devices` | admin/technician/viewer | none | Device aktif. |
| GET | `/api/v1/monitoring/interfaces` | admin/technician/viewer | `device_id` | Interface SFP. |
| GET | `/api/v1/monitoring/chart` | admin/technician/viewer | `device_id`, `if_index`, `range` | Chart optical history. |
| GET | `/api/v1/map/nodes` | admin/technician/viewer | optional `with_interfaces=1` | Node map. |
| GET | `/api/v1/map/links` | admin/technician/viewer | none | Link map. |
| GET | `/api/v1/alert-logs` | admin/technician/viewer | `limit`, `type`, `severity`, `q` | Alert log. |
| DELETE | `/api/v1/alert-logs` | admin | none | Clear alert log. |
| GET | `/api/v1/logs` | admin/viewer | `type=security` | Security log, viewer dummy. |
| GET | `/api/v1/settings` | admin/technician/viewer | none | Read settings. |
| POST | `/api/v1/settings` | admin | arbitrary key/value JSON | Upsert settings. |
| GET | `/api/v1/alert-preferences` | authenticated | none | Mobile alert preference per user. |
| POST | `/api/v1/alert-preferences` | authenticated | `push_enabled`, `severity_min` | Update preference push. |
| POST | `/api/v1/device-token` | authenticated | `token`, optional `platform`, `device_name` | Register FCM token. |
| POST | `/api/v1/location` | authenticated | `latitude`, `longitude`, optional `accuracy`, `recorded_at` | Simpan lokasi user. |
| POST | `/api/v1/push/test` | authenticated | optional `title`, `body`, `token` | Kirim test FCM. |

Range chart valid:

```text
1h, 1d, 3d, 7d, 30d, 1y
```

Severity alert valid:

```text
info, warning, critical
```

## Settings

Settings disimpan di tabel `settings` dengan kolom `name` dan `value`.

| Key | Default | Fungsi |
| --- | --- | --- |
| `bot_token` | empty | Telegram bot token. |
| `chat_id` | empty | Telegram chat/channel ID. |
| `alert_telegram_enabled` | `1` | Enable alert Telegram. |
| `alert_webui_enabled` | `1` | Enable simpan alert ke Web UI log. |
| `alert_interface_down` | `1` | Alert saat interface optical down. |
| `alert_interface_up` | `1` | Alert saat interface kembali up. |
| `alert_interface_warning` | `1` | Alert saat RX masuk warning/critical threshold. |
| `alert_device_down` | `1` | Alert saat device unreachable. |
| `alert_device_up` | `1` | Alert saat device kembali reachable. |
| `alert_rx_warning_high` | `-18.0` | Mulai warning jika RX <= nilai ini. |
| `alert_rx_warning_low` | `-25.0` | Batas bawah warning; di bawah ini jadi critical selama masih di atas down threshold. |
| `alert_rx_down_threshold` | `-40.0` | RX <= nilai ini dianggap down. |
| `theme` | `light` | Tema UI web. |
| `primary_color` | `#6366f1` | Warna primary UI. |
| `primary_soft` | `#8b5cf6` | Warna gradient kedua UI. |
| `mobile_alert_pref_user_<id>` | JSON default | Preference push per user mobile. |

Contoh update settings:

```bash
curl -X POST http://localhost/api/v1/settings \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"alert_webui_enabled":"1","alert_rx_warning_high":"-18.0"}'
```

## Scheduler dan Polling SNMP

Scheduler didefinisikan di:

```text
routes/console.php
```

Jadwal aktif:

```text
* * * * * php artisan poll:interfaces
```

Definisi Laravel:

```php
Schedule::command('poll:interfaces')->everyMinute()->withoutOverlapping();
```

Command:

```bash
php artisan poll:interfaces
php artisan poll:interfaces --device=1
php artisan schedule:list
php artisan schedule:run
```

Script cron:

```text
scripts/cron/laravel_schedule_run.sh
```

Contoh crontab:

```cron
* * * * * /var/www/NetpulseMultiOptical/scripts/cron/laravel_schedule_run.sh
```

Script cron:

- Pindah ke root project.
- Membuat `storage/logs` dan `storage/app` jika belum ada.
- Memakai `flock` jika tersedia.
- Menulis output ke `storage/logs/schedule-run.log`.

Polling melakukan:

1. Ambil semua device aktif dari `snmp_devices`.
2. Jalankan `InterfaceDiscovery::discover($deviceId, true)`.
3. Walk IF-MIB untuk index, name, description, alias, oper status.
4. Ambil optical power untuk MikroTik dan Huawei jika tersedia.
5. Upsert snapshot ke `interfaces`.
6. Insert history ke `interface_stats`.
7. Deteksi transisi device/interface up/down dan RX warning.
8. Simpan Web UI alert, kirim FCM push, dan kirim Telegram jika aktif.
9. Simpan state alert ke `storage/app/alert_state.json`.

SNMP function yang dibutuhkan:

- `snmp2_walk`
- `snmp2_get`
- `snmp2_real_walk`
- `snmp3_get` untuk test SNMP v3 sederhana.

## Database

### Tabel Inti yang Dipakai Aplikasi

Repo ini dapat berjalan di atas database monitoring existing. Beberapa migration hanya membuat tabel pendukung Laravel/mobile/alert, sedangkan tabel monitoring inti perlu tersedia pada database deployment.

Tabel inti yang dipakai kode:

| Tabel | Fungsi | Kolom penting yang dibaca/ditulis |
| --- | --- | --- |
| `users` | Login web/API dan role | `id`, `username`, `full_name`, `password`, `role`, `is_active`, `created_at` |
| `snmp_devices` | Inventory SNMP | `id`, `device_name`, `ip_address`, `snmp_version`, `community`, `snmp_user`, `is_active`, `last_status`, `last_error` |
| `interfaces` | Snapshot interface | `id`, `device_id`, `if_index`, `if_name`, `if_alias`, `if_description`, `if_type`, `optical_index`, `rx_power`, `tx_power`, `oper_status`, `last_seen`, `is_sfp`, `is_monitored`, `interface_type`, `updated_at` |
| `interface_stats` | History chart | `device_id`, `if_index`, `tx_power`, `rx_power`, `loss`, `created_at` |
| `settings` | Key/value settings | `name`, `value` |
| `map_nodes` | Node map | `id`, `device_id`, `node_name`, `node_type`, `x_position`, `y_position`, `icon_type`, `is_locked` |
| `map_links` | Link map | `id`, `node_a_id`, `node_b_id`, `interface_a_id`, `interface_b_id`, `attenuation_db`, `notes`, `path_json` |
| `alert_logs` | Web UI alert feed | `event_type`, `severity`, `device_*`, `if_*`, `rx_power`, `tx_power`, `message`, `context`, `fingerprint` |
| `personal_access_tokens` | API bearer token | Custom Sanctum-style token table. |
| `device_tokens` | FCM token mobile | `user_id`, `token`, `platform`, `device_name`, `last_seen_at` |
| `user_locations` | Mobile location report | `user_id`, `latitude`, `longitude`, `accuracy`, `recorded_at` |

Catatan penting untuk fresh install:

- Pastikan schema inti `users`, `snmp_devices`, `interfaces`, `interface_stats`, dan `settings` tersedia sebelum aplikasi dipakai penuh.
- Migration bawaan Laravel di repo ini memakai guard `Schema::hasTable(...)` supaya aman untuk database existing.
- Jangan mengandalkan seeder default Laravel untuk membuat akun NetPulse production. Buat user admin sesuai schema NetPulse (`username`, `full_name`, `role`, `is_active`).

## Instalasi Backend

### Requirement

- PHP 8.2 atau lebih baru.
- Composer.
- MySQL/MariaDB.
- Node.js dan npm untuk build asset jika diperlukan.
- PHP extension: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `xml`, `zip`, `snmp`.
- SNMP tools/library pada server.
- Web server Nginx/Apache.

Contoh package Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y php8.2-cli php8.2-fpm php8.2-mysql php8.2-curl php8.2-xml php8.2-mbstring php8.2-zip php8.2-snmp snmp composer unzip git nginx mysql-server
```

### Setup Project

```bash
cd /var/www
git clone <repo-url> NetpulseMultiOptical
cd NetpulseMultiOptical
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Konfigurasi `.env` minimal:

```env
APP_NAME="NetPulse MultiOptical"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=netpulse
DB_USERNAME=netpulse
DB_PASSWORD=strong-password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

FIREBASE_SERVICE_ACCOUNT_JSON=/var/www/NetpulseMultiOptical/storage/app/firebase-service-account.json
```

Jalankan migration pendukung:

```bash
php artisan migrate
```

Permission storage/cache:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rw storage bootstrap/cache
```

Build asset jika memakai Vite pipeline:

```bash
npm install
npm run build
```

Optimasi production:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Nginx Contoh

```nginx
server {
    listen 80;
    server_name your-domain.example;
    root /var/www/NetpulseMultiOptical/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Cron Scheduler

```bash
chmod +x /var/www/NetpulseMultiOptical/scripts/cron/laravel_schedule_run.sh
crontab -e
```

Isi crontab:

```cron
* * * * * /var/www/NetpulseMultiOptical/scripts/cron/laravel_schedule_run.sh
```

Verifikasi:

```bash
php artisan schedule:list
tail -f storage/logs/schedule-run.log
tail -f storage/logs/laravel.log
```

## Instalasi Mobile Android

```bash
cd mobile
flutter pub get
flutter build apk --debug
```

Output:

```text
mobile/build/app/outputs/flutter-apk/app-debug.apk
```

Untuk Firebase:

- Web/backend membutuhkan `FIREBASE_SERVICE_ACCOUNT_JSON` di `.env`.
- Android app membutuhkan `mobile/android/app/google-services.json`.
- Jangan commit file Firebase secret.

## Operasional

### Menambah Device

1. Login sebagai admin.
2. Buka `/devices`.
3. Tambahkan SNMP device: name, IP, SNMP version, community/user.
4. Test SNMP.
5. Jalankan discovery interface.
6. Pastikan interface SFP muncul.
7. Scheduler akan mulai menyimpan `interface_stats`.

### Menyiapkan Alert

1. Buka `/settings`.
2. Isi Telegram bot token dan chat ID.
3. Test Telegram.
4. Buka tab Alert.
5. Atur channel, event type, dan threshold RX.
6. Jalankan `php artisan poll:interfaces --device=<id>` untuk verifikasi.

### Menyiapkan Map

1. Buka `/map`.
2. Tambahkan node dari device yang tersedia.
3. Pilih interface untuk link antar node.
4. Atur path link bila perlu.
5. Gunakan lock agar posisi node tidak berubah.

## Log dan File Runtime

| Path | Fungsi |
| --- | --- |
| `storage/logs/laravel.log` | Error aplikasi Laravel. |
| `storage/logs/security.log` | Login success/failed web. |
| `storage/logs/schedule-run.log` | Output scheduler cron. |
| `storage/app/alert_state.json` | State transisi alert polling. |
| `storage/app/firebase-service-account.json` | Contoh lokasi Firebase service account lokal. |

## Troubleshooting

### `SNMP extension not installed`

Install PHP SNMP extension dan restart PHP-FPM:

```bash
sudo apt install php8.2-snmp snmp
sudo systemctl restart php8.2-fpm
php -m | grep snmp
```

### Chart kosong

Cek:

- Device aktif di `snmp_devices`.
- Interface terdeteksi di `interfaces`.
- Polling mengisi `interface_stats`.
- Query memakai `device_id`, `if_index`, dan `range` yang benar.

Command:

```bash
php artisan poll:interfaces --device=1
php artisan schedule:list
tail -f storage/logs/schedule-run.log
```

### Telegram tidak terkirim

Cek:

- `bot_token` dan `chat_id` di Settings.
- Server bisa akses `https://api.telegram.org`.
- `alert_telegram_enabled = 1`.

### FCM push gagal

Cek:

- `.env` punya `FIREBASE_SERVICE_ACCOUNT_JSON`.
- File service account ada dan readable oleh user web server.
- Mobile app sudah register token via `/api/v1/device-token`.
- User preference `mobile_alert_pref_user_<id>` tidak mematikan push.

### 401 API mobile

Cek:

- Header `Authorization: Bearer <token>`.
- Token masih ada di `personal_access_tokens`.
- User masih aktif.

### 403 Web/API

Cek role user:

- Write action umumnya butuh `admin`.
- `technician` banyak mendapat read-only.
- `viewer` memakai dummy/demo data di banyak endpoint.

## Checklist Release

```bash
php artisan route:list
php artisan schedule:list
php artisan test
npm run build
git status --short
```

Pastikan file berikut tidak ikut commit:

- `.env`
- Firebase service account JSON.
- `mobile/android/app/google-services.json`
- `storage/logs/*`
- `storage/app/alert_state.json`

