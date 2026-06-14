<?php

use App\Http\Controllers\Superadmin\Auth\AuthenticatedSessionController;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Superadmin Authentication Routes
|--------------------------------------------------------------------------
|
| These routes use ONLY Laravel's Auth facade and session() helper.
| No $_SESSION, $_COOKIE, SS_ROLE, SS_USER_ID, session_start(), or
| native PHP session functions are used anywhere in this flow.
|
| Guest routes:  Only accessible when NOT authenticated.
| Auth routes:  Protected by 'auth' + 'superadmin' middleware.
|                The 'superadmin' middleware returns 403 for wrong roles.
|                NEVER redirect loops.
*/

Route::prefix('superadmin')->name('superadmin.')->group(function () {

    // ── Guest-only routes (login page, password reset) ───────────
    // Uses Laravel's built-in 'guest' middleware which redirects
    // authenticated users away without touching legacy sessions.
    Route::middleware(['guest', PreventBackHistory::class])->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store']);

        Route::get('forgot-password', [\App\Http\Controllers\Superadmin\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [\App\Http\Controllers\Superadmin\Auth\PasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('reset-password/{token}', [\App\Http\Controllers\Superadmin\Auth\NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [\App\Http\Controllers\Superadmin\Auth\NewPasswordController::class, 'store'])->name('password.store');
    });

    // ── Authenticated + Superadmin-only routes ───────────────────
    // 'auth' middleware:      ensures user is logged in via Laravel Auth
    // 'superadmin' middleware: ensures user has role='superadmin' (403 if not)
    // These NEVER redirect-loop because they use abort(403) for wrong roles.
    Route::middleware(['auth', 'superadmin', PreventBackHistory::class])->group(function () {
        // Superadmin logout (POST only)
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        // Convenience redirect: /superadmin/adminlanding → /superadmin/dashboard
        Route::get('adminlanding', function () {
            return redirect()->route('superadmin.dashboard');
        })->name('adminlanding');
    });
});
