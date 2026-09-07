#!/usr/bin/env bash
# ==============================================================================
# Helper Build APK Netpulse Multi Optical
# Output: public/downloads/netpulse.apk
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
MOBILE_DIR="${ROOT_DIR}/mobile"
OUTPUT_DIR="${ROOT_DIR}/public/downloads"

echo "=== [1/3] Navigasi ke ${MOBILE_DIR} ==="
cd "${MOBILE_DIR}"

echo "=== [2/3] Kompilasi Flutter APK Release ==="
flutter pub get
flutter build apk --release

echo "=== [3/3] Menyalin APK ke ${OUTPUT_DIR}/netpulse.apk ==="
mkdir -p "${OUTPUT_DIR}"
cp -f "${MOBILE_DIR}/build/app/outputs/flutter-apk/app-release.apk" "${OUTPUT_DIR}/netpulse.apk"
chown -R www-data:www-data "${OUTPUT_DIR}"

echo "✅ Build selesai! File APK tersedia di: ${OUTPUT_DIR}/netpulse.apk"
ls -lh "${OUTPUT_DIR}/netpulse.apk"
