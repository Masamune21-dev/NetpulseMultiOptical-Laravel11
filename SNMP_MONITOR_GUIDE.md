# SNMP Monitor Guide

Terakhir diperbarui: 8 April 2026

Dokumen ini menjadi panduan lengkap untuk modul `SNMP Monitor` di BMKV Network Dashboard. Isinya mencakup tujuan modul, alur polling, tab UI, route, API, worker scheduler, struktur data, vendor support, boundary akses, dan troubleshooting operasional.

## 1. Tujuan Modul

SNMP Monitor dipakai untuk:

- menyimpan inventaris perangkat SNMP
- memantau status device dan interface
- membaca traffic per interface dan menyimpan history
- menampilkan snapshot health device seperti uptime, CPU, memory, dan temperature
- membaca DOM optical switch yang mendukung
- memberi surface monitoring yang tetap owner-aware untuk partner workspace

Modul ini cocok untuk router, switch, dan perangkat jaringan lain yang expose data melalui SNMP, dengan optimasi tambahan untuk MikroTik, Huawei, dan sebagian flow Juniper.

## 2. Surface yang Tersedia

Halaman utama ada di `GET /snmp` dan saat ini memuat 5 tab utama:

### Devices

Menampilkan daftar device SNMP yang sudah terdaftar:

- hostname / IP
- status online/offline
- type, lokasi, deskripsi
- jumlah interface
- last poll
- aksi detail dan test SNMP

Role `administrator`, `admin`, `noc`, dan `operator` juga mendapat tombol `Add Device` serta aksi edit/hapus device.

### Interfaces

Menampilkan daftar interface hasil polling terakhir:

- interface index
- nama interface
- alias interface (`ifAlias`) jika tersedia
- status
- speed
- rate RX/TX saat ini
- jumlah error
- status `is_monitored`

Tab ini juga dipakai untuk memilih interface mana yang akan diikutkan ke analytics traffic. Pilihan disimpan ke `snmp_interfaces.is_monitored`.

### Traffic Analytics

Menampilkan chart traffic per interface yang dimonitor:

- sumber data dari `snmp_traffic_history`
- chart bisa dibuka untuk beberapa range waktu
- data downsample otomatis untuk range panjang
- spike palsu difilter agar counter rusak tidak merusak chart

Traffic chart memakai satuan Mbps dan membaca data `traffic_in` / `traffic_out` hasil konversi dari delta byte counter per interval polling.

### Health Status

Menampilkan snapshot kesehatan device dari hasil poll terakhir:

- uptime
- CPU load
- memory usage
- temperature
- freshness data
- estimasi reboot dari uptime
- coverage badge `Core Health`, `Uptime Only`, atau `No Data`

Tab ini membaca field snapshot device yang sudah tersimpan di `snmp_devices` serta record historis `snmp_health`.

### Optical Analytics

Khusus untuk device dengan `type` yang mengandung kata `switch`.

Menampilkan:

- port optical
- alias/comment port
- RX power
- TX power
- temperature
- voltage
- TX bias
- status freshness
- histori RX/TX per port

Surface ini memakai `snmp_optical` untuk snapshot terbaru dan `snmp_optical_history` untuk chart history.

## 3. Route Web dan API

### Web route utama

- `GET /snmp`
- `GET /snmp/interfaces/all`
- `GET /snmp/test/{device}`
- `POST /snmp/monitoring/update`
- `GET /snmp/traffic/data`
- `GET /snmp/traffic/analytics`
- `GET /snmp/traffic/charts`
- `GET /snmp/optical/data`
- `GET /snmp/optical/history`
- `GET /snmp/{device}`

### CRUD device

Hanya untuk `administrator`, `admin`, `noc`, `operator`:

- `POST /snmp/device`
- `PUT /snmp/device/{device}`
- `DELETE /snmp/device/{device}`

### API mobile/internal

- `GET /api/snmp/devices`
- `GET /api/snmp/interfaces`
- `POST /api/snmp/monitoring`
- `GET /api/snmp/test/{id}`
- `GET /api/snmp/traffic/charts`

API tetap dibatasi oleh autentikasi Sanctum, role middleware, dan scoping workspace.

## 4. Boundary Akses dan Security

Boundary akses SNMP saat ini:

- web SNMP dibuka untuk role yang lolos matrix modul `snmp`
- `viewer` selalu read-only
- `partner` tetap owner-aware, hanya melihat device dalam workspace miliknya
- write device SNMP dibatasi ke `administrator`, `admin`, `noc`, `operator`
- `customer` tidak mendapat akses ke modul SNMP internal

Proteksi yang relevan:

- route web memakai `auth`, `role.permission`, `viewer.workspace`, `partner.workspace`, dan `viewer.readonly`
- route API memakai `auth:sanctum`, `role.permission`, `viewer.workspace`, dan `viewer.readonly`
- akses detail device dicek lagi lewat `DeviceAccessService`
- device CRUD tidak dibuka ke `partner` atau `viewer`

## 5. Alur Data dan Arsitektur Polling

Mulai 8 April 2026, worker SNMP dipisah per scope agar poll berat tidak saling menahan:

- `snmp:poll-health-due`
- `snmp:poll-traffic-due`
- `snmp:poll-optical-due`

Command lama `snmp:poll-due` tetap ada untuk full poll manual atau kompatibilitas.

### Interval

- default `polling_interval` device adalah `60` detik
- scheduler Laravel menjalankan ketiga worker tiap menit
- setiap worker memutuskan device yang sudah jatuh tempo berdasarkan scope-nya sendiri

### Scope health

Worker health:

- membuka sesi SNMP ke device
- membaca `sysDescr`, `sysUpTime`, `sysContact`, `sysLocation`
- membaca health vendor-specific bila tersedia
- mengupdate snapshot di `snmp_devices`
- menulis histori ke `snmp_poll_history` dan `snmp_health`

### Scope traffic

Worker traffic:

- membaca tabel interface
- menghitung delta byte counter dari poll sebelumnya
- menulis snapshot ke `snmp_interfaces`
- menulis sample rate ke `snmp_traffic_history`
- hanya menulis history analytics untuk interface yang `is_monitored = 1`

Guard traffic yang aktif:

- skip update bila sample counter hilang
- skip update bila counter turun tidak wajar tanpa reboot
- skip update bila rate mustahil terhadap speed interface

### Scope optical

Worker optical:

- hanya berjalan untuk device bertipe switch
- membaca DOM optical vendor-aware
- menyimpan snapshot terakhir ke `snmp_optical`
- menyimpan histori RX/TX ke `snmp_optical_history`

Freshness optical dihitung dari `last_update` dan `polling_interval` device.

## 6. Vendor Support Saat Ini

### Generic / standar

Dipakai hampir untuk semua device:

- `sysDescr`, `sysName`, `sysUpTime`
- `ifName`, `ifDescr`, `ifOperStatus`
- `ifSpeed`, `ifHighSpeed`
- `ifHCInOctets`, `ifHCOutOctets`
- `ifInErrors`, `ifOutErrors`
- `ifAlias`
- `HOST-RESOURCES-MIB` untuk fallback CPU / memory bila tersedia

### MikroTik

Support tambahan:

- info lisensi `mtxrLicSoftwareId`, `mtxrLicLevel`
- health board/cpu dan beberapa voltage board
- traffic interface dengan fallback counter MikroTik bila perlu
- optical DOM dari subtree `1.3.6.1.4.1.14988.1.1.19.*`

MikroTik optical saat ini bisa mengisi:

- wavelength
- temperature
- voltage
- TX bias
- TX power
- RX power

### Huawei

Health Huawei memakai `HUAWEI-ENTITY-EXTENT-MIB`:

- `hwEntityCpuUsage`
- `hwEntityMemUsage`
- `hwEntityMemSize`
- `hwEntityTemperature`

Collector akan memilih entity aktif/master yang paling masuk akal sebelum fallback ke `HOST-RESOURCES-MIB`.

Optical Huawei memakai tabel optical vendor Huawei yang dimapping ke nama port melalui `ENTITY-MIB`.

Peta numeric OID Huawei yang dipakai collector optical, termasuk catatan unit raw vs normalisasi BMKV, tersedia di `docs/SNMP_HUAWEI_OPTICAL_OID_MAP.md`.

### Juniper

Juniper belum punya collector health khusus, tetapi interface display name sudah menyesuaikan `ifAlias` / `ifDescr` agar penamaan port lebih rapi pada tabel interface dan analytics.

## 7. Tabel Database yang Dipakai

### `snmp_devices`

Menyimpan master device dan snapshot summary, termasuk:

- identitas device: `ip`, `hostname`, `snmp_version`, `community`, `port`, `type`
- metadata: `location`, `description`, `owner_username`, `is_demo`
- polling: `polling_interval`, `status`, `last_poll`
- snapshot system: `sysDescr`, `sysContact`, `sysLocation`, `uptime`, `uptime_seconds`
- snapshot health summary: `cpu`, `memory`, `temperature`

### `snmp_interfaces`

Menyimpan snapshot interface terakhir:

- `device_id`, `interface_index`
- `name`, `alias`
- `status`, `speed`
- `tx_bytes`, `rx_bytes`
- `tx_rate`, `rx_rate`
- `errors`
- `is_monitored`
- `last_update`

### `snmp_traffic_history`

Menyimpan histori traffic analytics per interface:

- `device_id`
- `interface_index`
- `traffic_in`
- `traffic_out`
- `timestamp`

### `snmp_health`

Menyimpan histori health:

- temperature CPU / board
- voltage board
- CPU load
- memory usage
- memory total
- timestamp

### `snmp_poll_history`

Menyimpan histori system poll:

- `sysDescr`
- `uptime_seconds`
- `sysContact`
- `sysLocation`
- timestamp

### `snmp_optical`

Snapshot optical terakhir per port:

- `interface_name`
- `tx_power`, `rx_power`
- `tx_bias`
- `temperature`
- `voltage`
- `rx_loss`, `tx_fault`
- `wavelength`
- `last_update`

### `snmp_optical_history`

Histori optical per port:

- `device_id`
- `interface_name`
- `rx_power`
- `tx_power`
- `sampled_at`

## 8. File Penting di Codebase

### Controller

- `app/Http/Controllers/SnmpController.php`
- `app/Http/Controllers/Api/SnmpApiController.php`

### Service

- `app/Services/SNMPManager.php`

### Model

- `app/Models/SnmpDevice.php`
- `app/Models/SnmpInterface.php`

### View dan asset

- `resources/views/snmp/index.blade.php`
- `resources/views/snmp/show.blade.php`
- `resources/views/snmp/interfaces.blade.php`
- `resources/views/snmp/traffic.blade.php`
- `public/assets/js/pages/snmp-index.js`
- `public/assets/css/pages/snmp-index.css`

## 9. Workflow Operasional yang Disarankan

### Menambah device baru

1. buka `SNMP Monitor`
2. klik `Add Device`
3. isi hostname, IP, version, community, type, lokasi, deskripsi, dan port bila non-161
4. simpan
5. jalankan `Test SNMP` dari UI atau route `/snmp/test/{device}`

### Menyiapkan analytics traffic

1. pastikan worker traffic berjalan
2. buka tab `Interfaces`
3. centang interface yang ingin dianalisis
4. simpan monitoring selection
5. buka tab `Traffic Analytics`

### Memakai health status

1. buka tab `Health Status`
2. gunakan filter search, status, coverage, freshness
3. prioritaskan device `stale` atau `no data`
4. bila perlu bandingkan `last poll` dengan status scheduler

### Memakai optical analytics

1. pastikan `type` device berisi `switch`
2. pastikan switch mendukung DOM/DDM monitoring
3. buka tab `Optical Analytics`
4. gunakan filter switch/vendor/freshness
5. klik action pada port untuk membuka histori RX/TX

## 10. Scheduler dan Command Operasional

Command utama:

```bash
php artisan snmp:poll-due --limit=100
php artisan snmp:poll-health-due --limit=100
php artisan snmp:poll-traffic-due --limit=100
php artisan snmp:poll-optical-due --limit=100
```

Verifikasi scheduler:

```bash
php artisan schedule:list
php artisan list --raw | rg '^snmp:poll'
```

Verifikasi data:

```bash
php artisan tinker --execute="dump(DB::table('snmp_devices')->select('id','hostname','status','last_poll')->orderBy('id')->limit(10)->get());"
php artisan tinker --execute="dump(DB::table('snmp_optical')->selectRaw('MAX(last_update) as last_update, COUNT(*) as total')->first());"
php artisan tinker --execute="dump(DB::table('snmp_interfaces')->selectRaw('MAX(last_update) as last_update, COUNT(*) as total')->first());"
```

## 11. Troubleshooting

### Device tidak berubah status

Cek:

- SNMP extension PHP terpasang
- IP/community/version/port benar
- route `snmp/test/{device}` berhasil
- worker health benar-benar berjalan

### Traffic chart kosong

Cek:

- interface sudah dicentang `is_monitored`
- worker `snmp:poll-traffic-due` berjalan
- `snmp_traffic_history` bertambah
- counter interface memang tersedia dari device

### Optical stale / tidak update

Cek:

- device `type` mengandung `switch`
- worker `snmp:poll-optical-due` berjalan
- device mendukung DOM/DDM
- collector vendor sesuai dengan perangkat
- `snmp_optical.last_update` berubah setelah manual poll

### Health kosong atau uptime only

Kemungkinan:

- device hanya expose `sysUpTime`
- tidak ada `HOST-RESOURCES-MIB`
- collector vendor health tidak cocok
- akses SNMP hanya membuka OID dasar

### Partner tidak melihat semua device

Itu perilaku normal. Partner hanya melihat device dengan `owner_username` sesuai workspace miliknya.

## 12. Checklist Verifikasi Setelah Perubahan SNMP

Saat mengubah modul SNMP, minimum jalankan:

```bash
php -l app/Services/SNMPManager.php
php -l app/Http/Controllers/SnmpController.php
php -l app/Http/Controllers/Api/SnmpApiController.php
php artisan test tests/Unit/SnmpHuaweiHealthTest.php tests/Unit/SnmpMikroTikOpticalTest.php tests/Unit/SnmpTrafficCounterGuardTest.php
php artisan view:cache
composer check:ui-dashboard
```

Tambahan bila worker/scheduler berubah:

```bash
php -l routes/console.php
php artisan schedule:list
```

## 13. Ringkasan Singkat

SNMP Monitor di BMKV sekarang bukan hanya daftar device, tetapi satu surface monitoring terintegrasi untuk:

- inventory SNMP
- monitoring interface
- analytics traffic
- health snapshot
- DOM optical switch
- histori polling

Karena worker sudah dipisah per scope, gangguan pada satu jenis polling tidak lagi otomatis menahan semua surface lainnya. Pendekatan ini membuat monitoring lebih stabil, lebih mudah di-debug, dan lebih aman untuk diperluas vendor per vendor.
