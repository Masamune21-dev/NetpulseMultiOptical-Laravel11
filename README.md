# NetPulse MultiOptical

NetPulse MultiOptical is a network monitoring platform for optical/SFP links with:
- Laravel web dashboard (NOC/ISP workflow)
- Flutter Android app (version `2.0.0`)
- Real-time alerting (Web UI logs, Telegram, FCM push)
- Interactive network map (nodes, links, line status)

## Main Features

- Device and interface monitoring (SNMP)
- Optical metrics (RX/TX/Loss) with chart history
- OLT monitoring (multi-PON)
- Network map with link color states (`green/orange/red`)
- Alert logs and push notifications
- Role-based access (`admin`, `technician`, `viewer`)

## Repository Structure

- `app/` Laravel controllers/services/models
- `resources/` Blade templates and frontend resources
- `routes/` web + API routes
- `mobile/` Flutter Android app
- `scripts/` helper scripts (cron / telnet / etc)

## Backend Requirements

- PHP `>= 8.2`
- Composer
- MySQL/MariaDB
- SNMP tools/extensions

## Backend Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=netpulse
DB_USERNAME=...
DB_PASSWORD=...
```

Run app:

```bash
php artisan serve
```

## OLT Config

Create local OLT config (ignored by git):

```bash
cp config/olt_example.php config/olt.php
```

Collector commands:

```bash
php artisan olt:collect <olt-id>
php artisan olt:collect-all
```

## Mobile App (Android)

Current app version:
- `versionName`: `2.0.0`
- `versionCode`: `2`

Build debug APK:

```bash
cd mobile
flutter pub get
flutter build apk --debug
```

Output:
- `mobile/build/app/outputs/flutter-apk/app-debug.apk`

## API (v1)

Main auth + app endpoints:
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/dashboard`
- `GET /api/v1/monitoring/devices`
- `GET /api/v1/monitoring/interfaces`
- `GET /api/v1/monitoring/chart`
- `GET /api/v1/map/nodes`
- `GET /api/v1/map/links`
- `GET /api/v1/alert-logs`
- `POST /api/v1/device-token`
- `POST /api/v1/location`

## Push Notifications

Mobile uses Firebase + local notifications.

Required local file (ignored by git):
- `mobile/android/app/google-services.json`

Do not commit Firebase secret/service account files.

## Git Ignore Policy (Updated)

Already ignored:
- `.env`, local config, cache, logs
- `vendor/`, `node_modules/`, build artifacts
- `config/olt.php`
- `mobile/android/app/google-services.json`
- `mobile/ios/Runner/GoogleService-Info.plist`
- SQLite local DB files (`database/*.sqlite`)

## Safe Push Checklist

Before push to GitHub, run:

```bash
git status --short --ignored
git check-ignore -v .env config/olt.php mobile/android/app/google-services.json
```

Then commit only project files you want:

```bash
git add -A
git status --short
```

If any secret/local file appears staged, unstage it first.

## License

MIT
