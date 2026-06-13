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

// All legacy admin UI access must go through Laravel and require auth + legacy.session
Route::middleware(['auth', 'legacy.session'])->group(function () {
    // Admin routes - require both auth + admin role
    Route::middleware('ensure.role:admin')->group(function () {
        Route::get('/Badminton Admin UI', [BadmintonAdminController::class, 'index'])->name('badminton.admin');
        Route::get('/Basketball Admin UI', [BasketballAdminController::class, 'index'])->name('basketball.admin');
        Route::get('/TABLE TENNIS ADMIN UI', [TableTennisAdminController::class, 'index'])->name('tabletennis.admin');
        Route::get('/DARTS ADMIN UI', [DartsAdminController::class, 'index'])->name('darts.admin');
        Route::get('/Volleyball Admin UI', [VolleyballAdminController::class, 'index'])->name('volleyball.admin');
    });

    // Viewer routes - require auth only (allow both viewer and admin)
    Route::middleware('ensure.role:viewer')->group(function () {
        Route::get('/Badminton Admin UI/viewer', [BadmintonViewerController::class, 'show'])->name('badminton.viewer');
        Route::get('/Basketball Admin UI/viewer', [BasketballViewerController::class, 'show'])->name('basketball.viewer');
        Route::get('/TABLE TENNIS ADMIN UI/viewer', [TableTennisViewerController::class, 'show'])->name('tabletennis.viewer');
        Route::get('/Volleyball Admin UI/viewer', [VolleyballViewerController::class, 'show'])->name('volleyball.viewer');
    });

    // Proxy any other legacy files (AJAX endpoints, PHP helpers, etc.) through the controller.
    Route::any('/{sport}/{path?}', [LegacyProxyController::class, 'handle'])
        ->where('sport', 'TABLE TENNIS ADMIN UI|Badminton Admin UI|Basketball Admin UI|DARTS ADMIN UI|Volleyball Admin UI|analytics')
        ->where('path', '.*');

    // Admin landing page (legacy) proxied for superadmins
    // NOTE: Moved to web.php with CSRF token support - see legacy.adminlanding route in web.php
});
