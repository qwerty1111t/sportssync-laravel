<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class LegacySessionMiddleware
{
    /**
     * Inject a lightweight legacy-compatible $_SESSION/$_COOKIE identity
     * for requests that will execute legacy PHP inside the Laravel proxy.
     */
    public function handle(Request $request, Closure $next)
    {
        // Respect config toggle
        if (!config('legacy.inject_session', true)) {
            return $next($request);
        }

        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
        } catch (\Throwable $e) {
            Log::debug('LegacySessionMiddleware: session_start failed: ' . $e->getMessage());
        }

        $user = null;
        try {
            $user = Auth::guard('web')->user();
            if ($user) {
                // Populate $_SESSION from Laravel Auth user
                $_SESSION[config('legacy.session_keys.user_id', 'user_id')] = intval($user->id);
                $_SESSION[config('legacy.session_keys.role', 'role')] = $user->role ?? 'viewer';
                $_SESSION[config('legacy.session_keys.username', 'username')] = $user->username ?? $user->name ?? null;
                // Populate $_COOKIE so legacy scripts that read $_COOKIE during
                // the same request will see the values.
                $_COOKIE['SS_USER_ID'] = (string) intval($user->id);
                $_COOKIE['SS_ROLE'] = $user->role ?? 'viewer';
            } else {
                // If Auth::user() is null but session variables exist, preserve them
                // Don't unset - they might be from a valid session that's just not synced with Auth yet
                // Instead, populate $_COOKIE from session values if they exist
                $sessionRole = session('SS_ROLE') ?? session('role') ?? $_SESSION['SS_ROLE'] ?? $_SESSION['role'] ?? null;
                $sessionUserId = session('user_id') ?? $_SESSION['user_id'] ?? null;
                
                if ($sessionRole) {
                    $_COOKIE['SS_ROLE'] = (string)$sessionRole;
                }
                if ($sessionUserId) {
                    $_COOKIE['SS_USER_ID'] = (string)$sessionUserId;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('LegacySessionMiddleware inject failed: ' . $e->getMessage());
        }

        // Let the request be handled; then attach legacy compatibility cookies
        // to the outgoing response so subsequent direct AJAX calls to public
        // legacy endpoints will carry the SS_* identity cookies.
        $response = $next($request);

        try {
            // Use a reasonable lifetime (minutes) for the compatibility cookies
            $minutes = 60 * 8; // 8 hours
            
            // Determine what values to use for cookies
            $cookieUserId = null;
            $cookieRole = null;
            
            if ($user) {
                // Use Auth user values
                $cookieUserId = (string) intval($user->id);
                $cookieRole = $user->role ?? 'viewer';
            } else {
                // Use session values if Auth user doesn't exist
                $cookieRole = session('SS_ROLE') ?? session('role') ?? $_SESSION['SS_ROLE'] ?? $_SESSION['role'] ?? null;
                $cookieUserId = session('SS_USER_ID') ?? session('user_id') ?? $_SESSION['SS_USER_ID'] ?? $_SESSION['user_id'] ?? null;
            }
            
            // Queue cookies if values exist
            if ($cookieUserId || $cookieRole) {
                if ($cookieUserId) {
                    Cookie::queue('SS_USER_ID', $cookieUserId, $minutes);
                }
                if ($cookieRole) {
                    Cookie::queue('SS_ROLE', $cookieRole, $minutes);
                }
                
                // Also set raw (unencrypted) cookies via native PHP so legacy
                // public PHP files (served outside Laravel) can read them.
                try {
                    $expire = time() + ($minutes * 60);
                    if ($cookieUserId) {
                        setcookie('SS_USER_ID', $cookieUserId, $expire, '/');
                    }
                    if ($cookieRole) {
                        setcookie('SS_ROLE', $cookieRole, $expire, '/');
                    }
                } catch (\Throwable $_) {
                    // non-fatal if setcookie fails
                }
            } else {
                // No user/role - remove cookies
                Cookie::queue(Cookie::forget('SS_USER_ID'));
                Cookie::queue(Cookie::forget('SS_ROLE'));
                try {
                    setcookie('SS_USER_ID', '', time() - 3600, '/');
                    setcookie('SS_ROLE', '', time() - 3600, '/');
                } catch (\Throwable $_) { }
            }
        } catch (\Throwable $e) {
            Log::debug('LegacySessionMiddleware cookie queue failed: ' . $e->getMessage());
        }

        return $response;
    }
}
