<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Superadmin authentication routes (separate from normal auth)
Route::prefix('superadmin')->name('superadmin.')->group(function () {
    // Superadmin login - allow access if already authenticated (as superadmin)
    Route::middleware(['legacy.session', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
        Route::get('login', function (\Illuminate\Http\Request $request) {
            // ===== DIAGNOSTIC LOGGING =====
            \Illuminate\Support\Facades\Log::debug('[SuperadminLogin-GET] === FULL DIAGNOSTIC ===', [
                'auth_check' => Auth::check(),
                'auth_user_id' => Auth::id(),
                'session_all' => session()->all(),
                'session_id' => session()->getId(),
                'cookie_ss_role_raw' => $_COOKIE['SS_ROLE'] ?? 'NOT_SET',
                'cookie_ss_userid_raw' => $_COOKIE['SS_USER_ID'] ?? 'NOT_SET',
                'cookie_ss_role_request' => $request->cookie('SS_ROLE'),
                'cookie_ss_userid_request' => $request->cookie('SS_USER_ID'),
                'session_ss_role_laravel' => session('SS_ROLE'),
                'session_ss_role_native' => $_SESSION['SS_ROLE'] ?? 'NOT_SET',
                'session_user_id_native' => $_SESSION['user_id'] ?? 'NOT_SET',
                'has_ss_cookies' => isset($_COOKIE['SS_ROLE']) || isset($_COOKIE['SS_USER_ID']) ? 'YES' : 'NO',
                'has_ss_request_cookies' => ($request->cookie('SS_ROLE') || $request->cookie('SS_USER_ID')) ? 'YES' : 'NO',
                'has_ss_session' => (session('SS_ROLE') || session('SS_USER_ID') || session('user_id')) ? 'YES' : 'NO',
                'raw_cookie_header' => $request->server('HTTP_COOKIE', 'NOT_SET'),
            ]);
            
            // Check if already authenticated as superadmin (check both Laravel Auth AND session/cookie)
            $isAuthenticatedSuperadmin = false;
            $authenticatedUserId = null;
            
            // Check Laravel Auth first
            if (Auth::check()) {
                $user = Auth::user();
                if ($user && strtolower((string)($user->role ?? '')) === 'superadmin') {
                    $isAuthenticatedSuperadmin = true;
                    $authenticatedUserId = $user->id;
                    \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Already authenticated as superadmin (Laravel Auth)', [
                        'user_id' => $user->id,
                        'role' => $user->role,
                    ]);
                }
            }
            
            // Check session/cookie values if Laravel Auth didn't confirm
            if (!$isAuthenticatedSuperadmin) {
                $sessionRole = session('SS_ROLE') ?? null;
                $sessionUserId = session('user_id') ?? session('SS_USER_ID') ?? null;
                $cookieRole = $_COOKIE['SS_ROLE'] ?? $request->cookie('SS_ROLE') ?? null;
                $cookieUserId = $_COOKIE['SS_USER_ID'] ?? $request->cookie('SS_USER_ID') ?? null;
                
                // ALSO check native PHP session
                $nativeSessionRole = $_SESSION['SS_ROLE'] ?? null;
                $nativeSessionUserId = $_SESSION['user_id'] ?? null;
                
                $effectiveRole = $sessionRole ?: $nativeSessionRole ?: $cookieRole;
                $effectiveUserId = $sessionUserId ?: $nativeSessionUserId ?: $cookieUserId;
                
                if ($effectiveRole && strtolower((string)$effectiveRole) === 'superadmin') {
                    // VALIDATE: check the user actually exists in the database
                    $isValidUser = false;
                    if ($effectiveUserId) {
                        try {
                            $dbUser = \Illuminate\Support\Facades\DB::table('users')
                                ->where('id', (int)$effectiveUserId)
                                ->where('role', 'superadmin')
                                ->first();
                            if ($dbUser) {
                                $isValidUser = true;
                                \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Session/cookie validated against DB', [
                                    'user_id' => $effectiveUserId,
                                    'db_role' => $dbUser->role,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('[SuperadminLogin-GET] DB validation failed', [
                                'error' => $e->getMessage(),
                            ]);
                            // If DB fails, trust the session/cookie (may be temporary outage)
                            $isValidUser = true;
                        }
                    }
                    
                    if ($isValidUser) {
                        $isAuthenticatedSuperadmin = true;
                        $authenticatedUserId = $effectiveUserId;
                        \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Already authenticated as superadmin (validated)', [
                            'user_id' => $effectiveUserId,
                            'role_source' => $sessionRole ? 'laravel_session' : ($nativeSessionRole ? 'native_session' : 'cookie'),
                            'ss_role' => $effectiveRole,
                        ]);
                    } else {
                        // Stale session data — user doesn't exist or isn't superadmin
                        \Illuminate\Support\Facades\Log::warning('[SuperadminLogin-GET] Stale session data detected — clearing', [
                            'ss_role' => $effectiveRole,
                            'user_id' => $effectiveUserId,
                        ]);
                        // Clear the stale data
                        try {
                            session()->forget(['SS_ROLE', 'user_role', 'role', 'user_id', 'SS_USER_ID', 'username']);
                            $_SESSION['SS_ROLE'] = null;
                            $_SESSION['user_id'] = null;
                            $_SESSION['user_role'] = null;
                            $_SESSION['role'] = null;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('[SuperadminLogin-GET] Failed to clear stale session', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
            
            // If superadmin is already authenticated, redirect to dashboard
            if ($isAuthenticatedSuperadmin) {
                \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Redirecting to dashboard', [
                    'user_id' => $authenticatedUserId,
                ]);
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
                try {
                    request()->session()->invalidate();
                    request()->session()->regenerateToken();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[SuperadminLogin-GET] Session invalidation failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            \Illuminate\Support\Facades\Log::info('[SuperadminLogin-GET] Showing login form (unauthenticated)');
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
