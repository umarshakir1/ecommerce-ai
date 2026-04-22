<?php

use App\Http\Controllers\AdminController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $apiKey = auth()->user()?->api_key ?? env('DEMO_API_KEY', '');
    return view('chat', compact('apiKey'));
});

Route::get('/login',     fn () => view('auth.login'))->name('login');
Route::get('/register',  fn () => view('auth.register'))->name('register');
Route::get('/dashboard', fn () => view('auth.dashboard'))->name('dashboard');

// ── Admin panel ───────────────────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::get('/login',  [AdminController::class, 'loginForm'])->name('admin.login');
    Route::post('/login', [AdminController::class, 'login'])->name('admin.login.post');

    Route::middleware(AdminMiddleware::class)->group(function () {
        Route::get('/',      [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('admin.logout');

        Route::prefix('api')->group(function () {
            Route::get('/stats',                          [AdminController::class, 'stats']);
            Route::get('/clients',                        [AdminController::class, 'clients']);
            Route::get('/clients/{id}',                   [AdminController::class, 'showClient']);
            Route::delete('/clients/{id}',                [AdminController::class, 'deleteClient']);
            Route::post('/clients/{id}/regenerate-key',   [AdminController::class, 'regenerateKey']);
            Route::post('/clients/{id}/toggle-active',    [AdminController::class, 'toggleActive']);
        });
    });
});

Route::get('/download/wordpress-plugin', function () {
    $pluginDir = base_path('wordpress-plugin/shopai-plugin');

    if (! is_dir($pluginDir)) {
        abort(404, 'Plugin files not found on this server.');
    }

    $zipPath = storage_path('app/shopai-plugin.zip');

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        abort(500, 'Could not create plugin archive.');
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (! $file->isDir()) {
            $realPath     = $file->getRealPath();
            $relativePath = 'shopai-plugin/' . str_replace('\\', '/', substr($realPath, strlen($pluginDir) + 1));
            $zip->addFile($realPath, $relativePath);
        }
    }

    $zip->close();

    return response()->download($zipPath, 'shopai-plugin.zip', [
        'Content-Type'        => 'application/zip',
        'Content-Disposition' => 'attachment; filename="shopai-plugin.zip"',
    ]);
})->name('download.plugin');
