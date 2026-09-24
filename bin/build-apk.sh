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

# Kunci rilis di luar repo (repo publik). Isi: storeFile/storePassword/keyAlias/keyPassword.
# Kalau tidak ada, Gradle menolak build rilis — tidak ada jatuh diam-diam ke kunci debug.
export NETPULSE_KEY_PROPERTIES="${NETPULSE_KEY_PROPERTIES:-/root/.kv-keystores/netpulse-key.properties}"
if [ ! -f "${NETPULSE_KEY_PROPERTIES}" ]; then
  echo "ABORT: kunci rilis tidak ditemukan di ${NETPULSE_KEY_PROPERTIES}" >&2
  exit 1
fi

echo "=== [1/4] Navigasi ke ${MOBILE_DIR} ==="
cd "${MOBILE_DIR}"

echo "=== [2/4] Kompilasi Flutter APK Release (split-per-abi) ==="
flutter pub get
# --split-per-abi: satu APK per arsitektur. APK universal lama membawa tiga
# arsitektur sekaligus (termasuk x86_64 yang tidak dipakai HP mana pun) sehingga
# 56 MB; arm64 saja ±22 MB, sama dengan Billing dan NMS.
# arm64 = unduhan utama (hampir semua HP modern); arm32 = cadangan HP lama.
flutter build apk --release --split-per-abi \
  --target-platform android-arm,android-arm64

BUILD_OUT="${MOBILE_DIR}/build/app/outputs/flutter-apk"
ARM64="${BUILD_OUT}/app-arm64-v8a-release.apk"
ARM32="${BUILD_OUT}/app-armeabi-v7a-release.apk"
TARGET_APK_ARM32="${OUTPUT_DIR}/netpulse-arm32.apk"

echo "=== [3/4] Menggantikan APK lama dengan APK baru ==="
mkdir -p "${OUTPUT_DIR}"

# Hapus APK lama yang ada di downloads agar bersih
rm -f "${OUTPUT_DIR}"/*.apk

# netpulse.apk (arm64) dilayani /download/app; netpulse-arm32.apk untuk HP 32-bit lama
cp -f "${ARM64}" "${TARGET_APK}"
cp -f "${ARM32}" "${TARGET_APK_ARM32}"
touch "${TARGET_APK}" "${TARGET_APK_ARM32}"
chown -R www-data:www-data "${OUTPUT_DIR}"
chmod 644 "${TARGET_APK}" "${TARGET_APK_ARM32}"

echo "=== [4/4] Verifikasi Versi APK ==="
if [ -f "/opt/android-sdk/build-tools/35.0.0/aapt" ]; then
    /opt/android-sdk/build-tools/35.0.0/aapt dump badging "${TARGET_APK}" | grep -E "package: name=" || true
fi

echo "✅ Build selesai! arm64: ${TARGET_APK} · arm32: ${TARGET_APK_ARM32}"
ls -lh "${OUTPUT_DIR}"/*.apk
