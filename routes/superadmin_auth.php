<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Superadmin authentication routes (separate from normal auth)
Route::prefix('superadmin')->name('superadmin.')->group(function () {
    // Superadmin login - allow access if already authenticated (as superadmin)
    Route::middleware([\App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::get('login', function () {
            // If already authenticated as superadmin, redirect to dashboard
            if (Auth::check()) {
                $user = Auth::user();
                if ($user && strtolower((string)($user->role ?? '')) === 'superadmin') {
                    \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Already authenticated as superadmin', [
                        'user_id' => $user->id,
                        'role' => $user->role,
                        'redirect_target' => '/superadmin/dashboard',
                    ]);
                    return redirect('/superadmin/dashboard');
                }
                // If authenticated but not superadmin, logout and show login page
                \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Authenticated but not superadmin, logging out', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                ]);
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
            \Illuminate\Support\Facades\Log::debug('[SuperadminLogin-GET] Showing login form', [
                'authenticated' => Auth::check(),
                'path' => request()->path(),
            ]);
            return view('superadmin.auth.login');
        })->name('login');
        
        Route::post('login', [\App\Http\Controllers\Superadmin\Auth\AuthenticatedSessionController::class, 'store']);

        Route::get('forgot-password', [\App\Http\Controllers\Superadmin\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [\App\Http\Controllers\Superadmin\Auth\PasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('reset-password/{token}', [\App\Http\Controllers\Superadmin\Auth\NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [\App\Http\Controllers\Superadmin\Auth\NewPasswordController::class, 'store'])->name('password.store');
    });

    // Use shared logout (POST) from standard Auth controller, but require superadmin role to call it
    Route::middleware(['auth', 'ensure.role:superadmin', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::post('logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });

    // Superadmin dashboard routes
    // Note: Main /superadmin/dashboard route is defined in routes/web.php
    // These are just convenience redirects for shortcuts
    Route::middleware(['auth', 'superadmin', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::get('adminlanding', function () {
            // Redirect to the superadmin dashboard
            return redirect('/superadmin/dashboard');
        })->name('adminlanding');
    });
});
