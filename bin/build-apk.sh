#!/usr/bin/env bash
# ==============================================================================
# Helper Build APK Netpulse Multi Optical
# Selalu menimpa langsung ke SATU file tunggal: public/downloads/netpulse.apk
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
MOBILE_DIR="${ROOT_DIR}/mobile"
OUTPUT_DIR="${ROOT_DIR}/public/downloads"
TARGET_APK="${OUTPUT_DIR}/netpulse.apk"

echo "=== [1/4] Navigasi ke ${MOBILE_DIR} ==="
cd "${MOBILE_DIR}"

echo "=== [2/4] Kompilasi Flutter APK Release ==="
flutter pub get
flutter build apk --release

echo "=== [3/4] Menggantikan APK lama dengan APK baru ==="
mkdir -p "${OUTPUT_DIR}"

# Hapus APK lama yang ada di downloads agar bersih
rm -f "${OUTPUT_DIR}"/*.apk

# Salin binary baru menggantikan netpulse.apk
cp -f "${MOBILE_DIR}/build/app/outputs/flutter-apk/app-release.apk" "${TARGET_APK}"
touch "${TARGET_APK}"
chown -R www-data:www-data "${OUTPUT_DIR}"
chmod 644 "${TARGET_APK}"

echo "=== [4/4] Verifikasi Versi APK ==="
if [ -f "/opt/android-sdk/build-tools/35.0.0/aapt" ]; then
    /opt/android-sdk/build-tools/35.0.0/aapt dump badging "${TARGET_APK}" | grep -E "package: name=" || true
fi

echo "✅ Build selesai! File APK tunggal diperbarui di: ${TARGET_APK}"
ls -lh "${TARGET_APK}"
