<?php

use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  —  /api/v1/...
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public auth routes (no token required) ────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
    });

    // ── Protected routes (JWT required) ──────────────────────────────
    Route::middleware('jwt.auth')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',  [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me',       [AuthController::class, 'me']);
        });

        // ── Future protected routes go here ──────────────────────────
        // Route::prefix('shipments')->group(...);
        // Route::prefix('wallet')->group(...);
    });

    // ── Admin routes (JWT + is_admin check) ──────────────────────────
    Route::middleware(['jwt.auth', 'jwt.admin'])->prefix('admin')->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index']);

        // ── Future admin routes go here ───────────────────────────────
        // Route::apiResource('users',     UserManagementController::class);
        // Route::apiResource('shipments', ShipmentManagementController::class);
    });

});