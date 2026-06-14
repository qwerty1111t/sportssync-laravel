<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegacyProxyController;
use App\Http\Controllers\BadmintonAdminController;
use App\Http\Controllers\BadmintonViewerController;
use App\Http\Controllers\BasketballAdminController;
use App\Http\Controllers\BasketballViewerController;
use App\Http\Controllers\TableTennisAdminController;
use App\Http\Controllers\TableTennisViewerController;
use App\Http\Controllers\DartsAdminController;
use App\Http\Controllers\VolleyballAdminController;
use App\Http\Controllers\VolleyballViewerController;
use Illuminate\Support\Facades\Auth;

// All legacy admin UI access must go through Laravel and require auth
// 'legacy.session' middleware injects $_SESSION values for legacy PHP files
// that are proxied through LegacyProxyController (e.g., public/Basketball Admin UI/*)
// NOTE: The SuperAdmin auth system does NOT use legacy.session - it uses
// pure Laravel Auth facade with 'auth' and 'superadmin' middleware.
Route::middleware(['auth', 'legacy.session'])->group(function () {
    // Admin routes - require both auth + admin role
    Route::middleware('ensure.role:admin')->group(function () {
        Route::get('/badminton-admin', [BadmintonAdminController::class, 'index'])->name('badminton.admin');
        Route::get('/basketball-admin', [BasketballAdminController::class, 'index'])->name('basketball.admin');
        Route::get('/tabletennis-admin', [TableTennisAdminController::class, 'index'])->name('tabletennis.admin');
        Route::get('/darts-admin', [DartsAdminController::class, 'index'])->name('darts.admin');
        Route::get('/volleyball-admin', [VolleyballAdminController::class, 'index'])->name('volleyball.admin');
        
        // LEGACY REDIRECT: Support old space-based URLs
        Route::redirect('/Badminton Admin UI', '/badminton-admin', 301);
        Route::redirect('/Basketball Admin UI', '/basketball-admin', 301);
        Route::redirect('/TABLE TENNIS ADMIN UI', '/tabletennis-admin', 301);
        Route::redirect('/DARTS ADMIN UI', '/darts-admin', 301);
        Route::redirect('/Volleyball Admin UI', '/volleyball-admin', 301);
    });

    // Viewer routes
    Route::middleware('ensure.role:viewer')->group(function () {
        Route::get('/badminton-admin/viewer', [BadmintonViewerController::class, 'show'])->name('badminton.viewer');
        Route::get('/basketball-admin/viewer', [BasketballViewerController::class, 'show'])->name('basketball.viewer');
        Route::get('/tabletennis-admin/viewer', [TableTennisViewerController::class, 'show'])->name('tabletennis.viewer');
        Route::get('/volleyball-admin/viewer', [VolleyballViewerController::class, 'show'])->name('volleyball.viewer');
        
        Route::redirect('/Badminton Admin UI/viewer', '/badminton-admin/viewer', 301);
        Route::redirect('/Basketball Admin UI/viewer', '/basketball-admin/viewer', 301);
        Route::redirect('/TABLE TENNIS ADMIN UI/viewer', '/tabletennis-admin/viewer', 301);
        Route::redirect('/Volleyball Admin UI/viewer', '/volleyball-admin/viewer', 301);
    });
});

// Proxy route for static assets and legacy PHP files
Route::any('/{sport}/{path?}', [LegacyProxyController::class, 'handle'])
    ->where('sport', 'badminton-admin|basketball-admin|tabletennis-admin|darts-admin|volleyball-admin|analytics')
    ->where('path', '.*')
    ->middleware(['auth', 'legacy.session']);
