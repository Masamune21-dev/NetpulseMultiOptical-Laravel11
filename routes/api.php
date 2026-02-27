<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AlertLogsController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\LogsController;
use App\Http\Controllers\Api\V1\MapController;
use App\Http\Controllers\Api\V1\MonitoringController;
use App\Http\Controllers\Api\V1\OltController;
use App\Http\Controllers\Api\V1\PushTestController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json(['ok' => true, 'ts' => now()->toIso8601String()]);
    });

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('api.auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::post('/location', [LocationController::class, 'store']);
        Route::post('/push/test', [PushTestController::class, 'send']);

        Route::get('/olt', [OltController::class, 'list']);
        Route::get('/olt-data', [OltController::class, 'data']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::get('/monitoring/devices', [MonitoringController::class, 'devices']);
        Route::get('/monitoring/interfaces', [MonitoringController::class, 'interfaces']);
        Route::get('/monitoring/chart', [MonitoringController::class, 'chart']);

        Route::get('/map/nodes', [MapController::class, 'nodes']);
        Route::get('/map/links', [MapController::class, 'links']);

        Route::get('/alert-logs', [AlertLogsController::class, 'index']);
        Route::delete('/alert-logs', [AlertLogsController::class, 'destroy']);

        Route::get('/logs', [LogsController::class, 'index']);

        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/settings', [SettingsController::class, 'upsert']);
        Route::match(['GET', 'POST'], '/alert-preferences', [SettingsController::class, 'alertPreferences']);
    });
});
