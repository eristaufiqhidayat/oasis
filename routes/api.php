<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\FieldController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// API aplikasi HP motoris. Dokumentasi: docs/API.md
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:mobile', 'salesman', 'throttle:mobile'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/password', [AuthController::class, 'changePassword']);
        Route::get('/bootstrap', BootstrapController::class);

        Route::post('/attendance/check-in', [FieldController::class, 'checkIn']);
        Route::post('/attendance/check-out', [FieldController::class, 'checkOut']);
        Route::post('/trip/depart', [FieldController::class, 'depart']);
        Route::post('/trip/finish', [FieldController::class, 'finish']);

        Route::get('/outlets', [FieldController::class, 'outlets']);
        Route::post('/outlets', [FieldController::class, 'registerOutlet']);
        Route::post('/visits', [FieldController::class, 'startVisit']);
        Route::post('/visits/{uuid}/end', [FieldController::class, 'endVisit']);
        Route::post('/locations', [FieldController::class, 'pings']);

        Route::get('/sales', [TransactionController::class, 'sales']);
        Route::post('/sales', [TransactionController::class, 'storeSale']);
        Route::post('/returns', [TransactionController::class, 'storeReturn']);
        Route::post('/containers', [TransactionController::class, 'storeContainers']);
    });
});
