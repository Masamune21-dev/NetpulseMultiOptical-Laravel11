# Netpulse Mobile (Android)

Flutter app for NetPulse MultiOptical.

## Current Version

- `2.0.0+2`

## Main Features

- Login via backend API (`/api/v1/auth/login`)
- Dashboard and monitoring views
- Network map with link color states
- Alert log access
- FCM push notification + tap-to-open alert logs
- Device token registration (`/api/v1/device-token`)
- Send current location (`/api/v1/location`)

## Build APK

```bash
cd mobile
flutter pub get
flutter build apk --debug
```

APK output:
- `build/app/outputs/flutter-apk/app-debug.apk`

Release build (still for internal/testing unless signing is configured):

```bash
flutter build apk --release
```

## Firebase Notes

Required local file:
- `android/app/google-services.json`

This file is ignored by git and should not be committed.

## API Base URL

Default base URL is stored in app session config and can be changed from app settings.

