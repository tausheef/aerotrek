<?php

use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\CMS\CmsController;
use App\Http\Controllers\API\V1\Rate\RateCalculatorController;
use App\Http\Controllers\API\V1\User\ProfileController;
use App\Http\Controllers\API\V1\User\AddressController;
use App\Http\Controllers\Admin\AdminCmsController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── GUEST ROUTES ──────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
    });

    Route::prefix('cms')->group(function () {
        Route::get('pages/{slug}',    [CmsController::class, 'getPage']);
        Route::get('blog',            [CmsController::class, 'getBlogPosts']);
        Route::get('blog/categories', [CmsController::class, 'getBlogCategories']);
        Route::get('blog/{slug}',     [CmsController::class, 'getBlogPost']);
        Route::get('faqs',            [CmsController::class, 'getFaqs']);
        Route::get('settings',        [CmsController::class, 'getSettings']);
    });

    // ── USER ROUTES (jwt.auth) ────────────────────────────────────────
    Route::middleware('jwt.auth')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',  [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me',       [AuthController::class, 'me']);
        });

        // Profile
        Route::prefix('user')->group(function () {
            Route::get('profile',              [ProfileController::class, 'show']);
            Route::put('profile',              [ProfileController::class, 'update']);
            Route::get('addresses',            [AddressController::class, 'index']);
            Route::post('addresses',           [AddressController::class, 'store']);
            Route::put('addresses/{id}',       [AddressController::class, 'update']);
            Route::delete('addresses/{id}',    [AddressController::class, 'destroy']);
            Route::put('addresses/{id}/default', [AddressController::class, 'setDefault']);
        });

        // Rate Calculator
        Route::post('rates/calculate', [RateCalculatorController::class, 'calculate']);

        // ── Future routes ─────────────────────────────────────────────
        // KYC, Wallet, Shipments coming next
    });

    // ── ADMIN ROUTES (jwt.auth + jwt.admin) ───────────────────────────
    Route::middleware(['jwt.auth', 'jwt.admin'])->prefix('admin')->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::prefix('cms')->group(function () {
            Route::get('pages',           [AdminCmsController::class, 'indexPages']);
            Route::post('pages',          [AdminCmsController::class, 'storePage']);
            Route::put('pages/{id}',      [AdminCmsController::class, 'updatePage']);
            Route::delete('pages/{id}',   [AdminCmsController::class, 'destroyPage']);

            Route::get('blog',            [AdminCmsController::class, 'indexBlog']);
            Route::post('blog',           [AdminCmsController::class, 'storeBlog']);
            Route::put('blog/{id}',       [AdminCmsController::class, 'updateBlog']);
            Route::delete('blog/{id}',    [AdminCmsController::class, 'destroyBlog']);

            Route::get('blog/categories',  [AdminCmsController::class, 'indexCategories']);
            Route::post('blog/categories', [AdminCmsController::class, 'storeCategory']);

            Route::get('faqs',            [AdminCmsController::class, 'indexFaqs']);
            Route::post('faqs',           [AdminCmsController::class, 'storeFaq']);
            Route::put('faqs/{id}',       [AdminCmsController::class, 'updateFaq']);
            Route::delete('faqs/{id}',    [AdminCmsController::class, 'destroyFaq']);

            Route::get('settings',        [AdminCmsController::class, 'indexSettings']);
            Route::post('settings',       [AdminCmsController::class, 'updateSettings']);
        });
    });

});