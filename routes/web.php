<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ThresholdController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/kolam/{pond:code}', [DashboardController::class, 'index'])->name('pond.show');
    Route::get('/kolam/{pond:code}/live', [DashboardController::class, 'live'])->name('pond.live');
    Route::get('/kolam/{pond:code}/history', [DashboardController::class, 'history'])->name('pond.history');

    Route::post('/kolam/{pond:code}/actuator', [ControlController::class, 'actuator'])->name('pond.actuator');
    Route::post('/kolam/{pond:code}/mode', [ControlController::class, 'mode'])->name('pond.mode');
    Route::post('/kolam/{pond:code}/action', [ControlController::class, 'action'])->name('pond.action');

    Route::get('/kolam/{pond:code}/ambang', [ThresholdController::class, 'edit'])->name('pond.thresholds');
    Route::put('/kolam/{pond:code}/ambang', [ThresholdController::class, 'update']);
    Route::post('/kolam/{pond:code}/ambang/reset', [ThresholdController::class, 'reset'])->name('pond.thresholds.reset');

    Route::get('/kolam/{pond:code}/riwayat', [LogController::class, 'index'])->name('pond.logs');
    Route::get('/kolam/{pond:code}/riwayat/{type}.csv', [LogController::class, 'export'])->name('pond.export');
});
