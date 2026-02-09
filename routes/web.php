<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicesApiController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\DiscoverInterfacesController;
use App\Http\Controllers\InterfacesApiController;
use App\Http\Controllers\MapApiController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MonitoringApiController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\LegacyApiController;
use App\Http\Controllers\SettingsApiController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersApiController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\AlertLogsApiController;
use App\Http\Controllers\OltApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->to('/login');
});

Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/logout', [AuthController::class, 'logout']);

Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/monitoring', [MonitoringController::class, 'index']);

    Route::get('/map', [MapController::class, 'index']);

    Route::get('/users', [UsersController::class, 'index'])
        ->middleware('legacy.role:admin,technician');

    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('legacy.role:admin,technician');

    Route::get('/olt', [OltController::class, 'index']);

    Route::get('/api/users', [UsersApiController::class, 'index'])
        ->middleware('legacy.role:admin,technician');
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

    Route::get('/api/interfaces', [InterfacesApiController::class, 'index']);

    Route::get('/api/monitoring_devices', [MonitoringApiController::class, 'devices']);
    Route::get('/api/monitoring_interfaces', [MonitoringApiController::class, 'interfaces']);
    Route::get('/api/interface_chart', [MonitoringApiController::class, 'chart']);

    Route::any('/api/map_nodes', [MapApiController::class, 'nodes']);
    Route::any('/api/map_links', [MapApiController::class, 'links']);
    Route::get('/api/map_devices', [MapApiController::class, 'devices']);

    Route::get('/api/discover_interfaces', DiscoverInterfacesController::class);
    Route::get('/api/huawei_discover_optics', DiscoverInterfacesController::class);

    Route::match(['GET', 'POST'], '/api/settings', [SettingsApiController::class, 'settings'])
        ->middleware('legacy.role:admin,technician');
    Route::post('/api/telegram_test', [SettingsApiController::class, 'telegramTest'])
        ->middleware('legacy.role:admin');
    Route::get('/api/logs', [SettingsApiController::class, 'logs'])
        ->middleware('legacy.role:admin');

    Route::get('/api/olt', [OltApiController::class, 'list'])
        ->middleware('legacy.role:admin,technician');
    Route::get('/api/olt_data', [OltApiController::class, 'data'])
        ->middleware('legacy.role:admin,technician');

    Route::get('/api/alert_logs', [AlertLogsApiController::class, 'index'])
        ->middleware('legacy.role:admin,technician');
    Route::delete('/api/alert_logs', [AlertLogsApiController::class, 'destroy'])
        ->middleware('legacy.role:admin');

    Route::get('/api/data', [LegacyApiController::class, 'data']);
    Route::get('/api/test_connection', [LegacyApiController::class, 'testConnection']);
    Route::get('/api/export', [LegacyApiController::class, 'export']);

});
