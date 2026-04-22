<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DemoSearchController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\SyncController;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\TrackConnectionMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - eCommerce AI SaaS Platform
|--------------------------------------------------------------------------
*/

// ── Public demo search (no API key required) ──────────────────────────────
Route::get('/demo/search', [DemoSearchController::class, 'search']);

// ── Public auth routes (no API key required) ──────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register',     [AuthController::class, 'register']);
    Route::post('/login',        [AuthController::class, 'login']);
    Route::post('/setup-domain', [AuthController::class, 'setupDomain']);
});

// ── Protected routes (API key required) ───────────────────────────────────
Route::middleware([ApiKeyMiddleware::class, TrackConnectionMiddleware::class])->group(function () {

    // Auth: current user profile + domain management
    Route::get('/auth/me',       [AuthController::class, 'me']);
    Route::get('/validate-key',  [AuthController::class, 'validateKey']);
    Route::put('/auth/domain',   [AuthController::class, 'updateDomain']);

    // Product sync
    Route::post('/sync-products', [SyncController::class, 'sync']);

    // Chat (RAG, client-isolated)
    Route::prefix('chat')->group(function () {
        Route::post('/',        [ChatController::class, 'chat']);
        Route::post('/stream',  [ChatController::class, 'streamChat']);
        Route::get('/history',  [ChatController::class, 'history']);
        Route::delete('/history', [ChatController::class, 'clearHistory']);
    });

    // Priority rules — attribute-level search boosting
    Route::prefix('priorities')->group(function () {
        Route::get('/',        [PriorityController::class, 'index']);
        Route::post('/',       [PriorityController::class, 'store']);
        Route::put('/{id}',    [PriorityController::class, 'update']);
        Route::delete('/all',  [PriorityController::class, 'destroyAll']);
        Route::delete('/{id}', [PriorityController::class, 'destroy']);
    });
});
