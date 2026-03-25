<?php

use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\CMS\CmsController;
use App\Http\Controllers\Admin\AdminCmsController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  —  /api/v1/...
|--------------------------------------------------------------------------
|
| 3 access levels:
|   1. Guest        → public routes, no token
|   2. Normal User  → jwt.auth middleware
|   3. Admin        → jwt.auth + jwt.admin middleware
|
*/

Route::prefix('v1')->group(function () {

    // ══════════════════════════════════════════════════════════════════
    // GUEST ROUTES — no token required
    // ══════════════════════════════════════════════════════════════════

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
    });

    // CMS - Public read
    Route::prefix('cms')->group(function () {
        Route::get('pages/{slug}',       [CmsController::class, 'getPage']);
        Route::get('blog',               [CmsController::class, 'getBlogPosts']);
        Route::get('blog/categories',    [CmsController::class, 'getBlogCategories']);
        Route::get('blog/{slug}',        [CmsController::class, 'getBlogPost']);
        Route::get('faqs',               [CmsController::class, 'getFaqs']);
        Route::get('settings',           [CmsController::class, 'getSettings']);
    });

    // ══════════════════════════════════════════════════════════════════
    // NORMAL USER ROUTES — jwt.auth required
    // ══════════════════════════════════════════════════════════════════

    Route::middleware('jwt.auth')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',  [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me',       [AuthController::class, 'me']);
        });

        // ── Future user routes ────────────────────────────────────────
        // Route::prefix('shipments')->group(...);
        // Route::prefix('wallet')->group(...);
        // Route::prefix('kyc')->group(...);
    });

    // ══════════════════════════════════════════════════════════════════
    // ADMIN ROUTES — jwt.auth + jwt.admin required
    // ══════════════════════════════════════════════════════════════════

    Route::middleware(['jwt.auth', 'jwt.admin'])->prefix('admin')->group(function () {

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // CMS - Admin CRUD
        Route::prefix('cms')->group(function () {

            // Pages
            Route::get('pages',          [AdminCmsController::class, 'indexPages']);
            Route::post('pages',         [AdminCmsController::class, 'storePage']);
            Route::put('pages/{id}',     [AdminCmsController::class, 'updatePage']);
            Route::delete('pages/{id}',  [AdminCmsController::class, 'destroyPage']);

            // Blog Posts
            Route::get('blog',           [AdminCmsController::class, 'indexBlog']);
            Route::post('blog',          [AdminCmsController::class, 'storeBlog']);
            Route::put('blog/{id}',      [AdminCmsController::class, 'updateBlog']);
            Route::delete('blog/{id}',   [AdminCmsController::class, 'destroyBlog']);

            // Blog Categories
            Route::get('blog/categories',  [AdminCmsController::class, 'indexCategories']);
            Route::post('blog/categories', [AdminCmsController::class, 'storeCategory']);

            // FAQs
            Route::get('faqs',           [AdminCmsController::class, 'indexFaqs']);
            Route::post('faqs',          [AdminCmsController::class, 'storeFaq']);
            Route::put('faqs/{id}',      [AdminCmsController::class, 'updateFaq']);
            Route::delete('faqs/{id}',   [AdminCmsController::class, 'destroyFaq']);

            // Site Settings
            Route::get('settings',       [AdminCmsController::class, 'indexSettings']);
            Route::post('settings',      [AdminCmsController::class, 'updateSettings']);
        });

        // ── Future admin routes ───────────────────────────────────────
        // Route::apiResource('users',     UserManagementController::class);
        // Route::apiResource('shipments', ShipmentManagementController::class);
    });

});