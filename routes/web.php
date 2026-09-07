<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicesApiController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\DiscoverInterfacesController;
use App\Http\Controllers\InterfacesApiController;
use App\Http\Controllers\InterfacesController;
use App\Http\Controllers\InterfacesListApiController;
use App\Http\Controllers\InterfaceThresholdsController;
use App\Http\Controllers\MapApiController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MonitoringApiController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\LegacyApiController;
use App\Http\Controllers\SettingsApiController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersApiController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\AlertLogsApiController;
use App\Http\Controllers\AlertMutesController;
use App\Http\Controllers\SlaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->to('/login');
});

// Endpoint unduh APK kanonis — dynamic no-cache, file tersimpan di HP selalu netpulse.apk
Route::get('/download/app', function () {
    $path = public_path('downloads/netpulse.apk');
    if (!file_exists($path)) {
        abort(404, 'File APK belum tersedia.');
    }
    return response()->download($path, 'netpulse.apk', [
        'Content-Type'        => 'application/vnd.android.package-archive',
        'Cache-Control'       => 'no-cache, no-store, must-revalidate, max-age=0',
        'Pragma'              => 'no-cache',
        'Expires'             => '0',
        'X-Accel-Expires'     => '0',
    ]);
});

Route::get('/downloads/netpulse.apk', function () {
    $path = public_path('downloads/netpulse.apk');
    if (!file_exists($path)) {
        abort(404, 'File APK belum tersedia.');
    }
    return response()->download($path, 'netpulse.apk', [
        'Content-Type'        => 'application/vnd.android.package-archive',
        'Cache-Control'       => 'no-cache, no-store, must-revalidate, max-age=0',
        'Pragma'              => 'no-cache',
        'Expires'             => '0',
        'X-Accel-Expires'     => '0',
    ]);
});

Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/logout', [AuthController::class, 'logout']);

Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/api/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/monitoring', [MonitoringController::class, 'index']);

    Route::get('/map', [MapController::class, 'index']);

    Route::get('/users', [UsersController::class, 'index']);

    Route::get('/settings', [SettingsController::class, 'index']);

    Route::get('/api/users', [UsersApiController::class, 'index'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::post('/api/users', [UsersApiController::class, 'store'])
        ->middleware('legacy.role:admin');
    Route::delete('/api/users', [UsersApiController::class, 'destroy'])
        ->middleware('legacy.role:admin');

    Route::get('/devices', [DevicesController::class, 'index']);

    Route::get('/api/devices', [DevicesApiController::class, 'index']);
    Route::post('/api/devices', [DevicesApiController::class, 'store'])
        ->middleware('legacy.role:admin');
    Route::delete('/api/devices', [DevicesApiController::class, 'destroy'])
        ->middleware('legacy.role:admin');

    Route::get('/interfaces', [InterfacesController::class, 'index']);

    // SLA / uptime report.
    Route::get('/sla', [SlaController::class, 'index']);
    Route::get('/api/sla', [SlaController::class, 'summary'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::get('/api/sla/events', [SlaController::class, 'events'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::get('/api/sla/export', [SlaController::class, 'export'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::get('/api/sla/export-pdf', [SlaController::class, 'exportPdf'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::get('/api/sla/interface/export', [SlaController::class, 'exportInterface'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::get('/api/sla/interface/export-pdf', [SlaController::class, 'exportInterfacePdf'])
        ->middleware('legacy.role:admin,technician,viewer');

    Route::get('/api/interfaces', [InterfacesApiController::class, 'index']);
    Route::get('/api/interfaces/all', [InterfacesListApiController::class, 'index']);
    Route::get('/api/interfaces/traffic_history', [InterfacesListApiController::class, 'trafficHistory']);

    // Per-interface RX threshold overrides (admin-managed).
    Route::get('/api/interfaces/thresholds', [InterfaceThresholdsController::class, 'show'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::post('/api/interfaces/thresholds', [InterfaceThresholdsController::class, 'save'])
        ->middleware('legacy.role:admin');
    Route::delete('/api/interfaces/thresholds', [InterfaceThresholdsController::class, 'clear'])
        ->middleware('legacy.role:admin');

    // Maintenance-window alert muting (per-device + global).
    Route::get('/api/alert_mutes', [AlertMutesController::class, 'index'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::post('/api/alert_mutes', [AlertMutesController::class, 'save'])
        ->middleware('legacy.role:admin');
    Route::delete('/api/alert_mutes', [AlertMutesController::class, 'clear'])
        ->middleware('legacy.role:admin');

    Route::get('/api/monitoring_devices', [MonitoringApiController::class, 'devices']);
    Route::get('/api/monitoring_interfaces', [MonitoringApiController::class, 'interfaces']);
    Route::get('/api/interface_chart', [MonitoringApiController::class, 'chart']);

    Route::any('/api/map_nodes', [MapApiController::class, 'nodes']);
    Route::any('/api/map_links', [MapApiController::class, 'links']);
    Route::get('/api/map_devices', [MapApiController::class, 'devices']);

    Route::get('/api/discover_interfaces', DiscoverInterfacesController::class);
    Route::get('/api/huawei_discover_optics', DiscoverInterfacesController::class);

    Route::match(['GET', 'POST'], '/api/settings', [SettingsApiController::class, 'settings'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::post('/api/telegram_test', [SettingsApiController::class, 'telegramTest'])
        ->middleware('legacy.role:admin');
    Route::get('/api/mobile_devices', [SettingsApiController::class, 'mobileDevices'])
        ->middleware('legacy.role:admin');
    Route::get('/api/mobile_push_targets', [SettingsApiController::class, 'mobilePushTargets'])
        ->middleware('legacy.role:admin');
    Route::post('/api/mobile_push_send', [SettingsApiController::class, 'sendMobilePush'])
        ->middleware('legacy.role:admin');
    Route::delete('/api/mobile_devices/{id}', [SettingsApiController::class, 'revokeMobileDevice'])
        ->middleware('legacy.role:admin')
        ->whereNumber('id');
    Route::get('/api/logs', [SettingsApiController::class, 'logs'])
        ->middleware('legacy.role:admin,viewer');

    Route::get('/api/alert_logs', [AlertLogsApiController::class, 'index'])
        ->middleware('legacy.role:admin,technician,viewer');
    Route::delete('/api/alert_logs', [AlertLogsApiController::class, 'destroy'])
        ->middleware('legacy.role:admin');

    Route::get('/api/data', [LegacyApiController::class, 'data']);
    Route::get('/api/test_connection', [LegacyApiController::class, 'testConnection']);
    Route::get('/api/export', [LegacyApiController::class, 'export']);

});
