<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Superadmin authentication routes (separate from normal auth)
Route::prefix('superadmin')->name('superadmin.')->group(function () {
    // Superadmin login - allow access if already authenticated (as superadmin)
    Route::middleware(['legacy.session', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::get('login', function (\Illuminate\Http\Request $request) {
            // Check if already authenticated as superadmin (check both Laravel Auth AND session/cookie)
            $isAuthenticatedSuperadmin = false;
            
            // Check Laravel Auth first
            if (Auth::check()) {
                $user = Auth::user();
                if ($user && strtolower((string)($user->role ?? '')) === 'superadmin') {
                    $isAuthenticatedSuperadmin = true;
                    \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Already authenticated as superadmin (Laravel Auth)', [
                        'user_id' => $user->id,
                        'role' => $user->role,
                    ]);
                }
            }
            
            // Check session/cookie values if Laravel Auth didn't confirm
            if (!$isAuthenticatedSuperadmin) {
                $sessionRole = session('SS_ROLE') ?? $_SESSION['SS_ROLE'] ?? null;
                $cookieRole = $_COOKIE['SS_ROLE'] ?? $request->cookie('SS_ROLE') ?? null;
                
                if ($sessionRole && strtolower((string)$sessionRole) === 'superadmin') {
                    $isAuthenticatedSuperadmin = true;
                    \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Already authenticated as superadmin (session)', [
                        'user_id' => session('user_id') ?? $_SESSION['user_id'] ?? null,
                        'ss_role' => $sessionRole,
                    ]);
                } elseif ($cookieRole && strtolower((string)$cookieRole) === 'superadmin') {
                    $isAuthenticatedSuperadmin = true;
                    // Also check that there's a matching user_id cookie to prevent cookie-only bypass
                    $cookieUid = $_COOKIE['SS_USER_ID'] ?? $request->cookie('SS_USER_ID') ?? null;
                    \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Already authenticated as superadmin (cookie)', [
                        'user_id' => $cookieUid,
                        'ss_role' => $cookieRole,
                    ]);
                }
            }
            
            // If superadmin is already authenticated, redirect to dashboard
            if ($isAuthenticatedSuperadmin) {
                \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Redirecting to dashboard');
                return redirect('/superadmin/dashboard');
            }
            
            // If authenticated with Laravel Auth but NOT superadmin, logout
            if (Auth::check()) {
                $user = Auth::user();
                \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Authenticated but not superadmin, logging out', [
                    'user_id' => $user?->id,
                    'role' => $user?->role,
                ]);
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
            
            \Illuminate\Support\Facades\Log::debug('[SuperadminLogin-GET] Showing login form');
            return view('superadmin.auth.login');
        })->name('login');
        
        Route::post('login', [\App\Http\Controllers\Superadmin\Auth\AuthenticatedSessionController::class, 'store']);

        Route::get('forgot-password', [\App\Http\Controllers\Superadmin\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [\App\Http\Controllers\Superadmin\Auth\PasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('reset-password/{token}', [\App\Http\Controllers\Superadmin\Auth\NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [\App\Http\Controllers\Superadmin\Auth\NewPasswordController::class, 'store'])->name('password.store');
    });

    // Use shared logout (POST) from standard Auth controller, but require superadmin role to call it
    // Use 'superadmin' middleware instead of 'auth' to support session/cookie-based auth
    Route::middleware(['legacy.session', 'superadmin', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::post('logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });

    // Superadmin dashboard routes
    // Note: Main /superadmin/dashboard route is defined in routes/web.php
    // These are just convenience redirects for shortcuts
    Route::middleware(['legacy.session', 'superadmin', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::get('adminlanding', function () {
            // Redirect to the superadmin dashboard
            return redirect('/superadmin/dashboard');
        })->name('adminlanding');
    });
});
