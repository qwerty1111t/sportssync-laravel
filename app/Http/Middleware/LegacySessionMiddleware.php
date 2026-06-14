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
                $_SESSION[config('legacy.session_keys.user_id', 'user_id')] = intval($user->id);
                $_SESSION[config('legacy.session_keys.role', 'role')] = $user->role ?? 'viewer';
                $_SESSION[config('legacy.session_keys.username', 'username')] = $user->username ?? $user->name ?? null;
                // Populate $_COOKIE so legacy scripts that read $_COOKIE during
                // the same request will see the values.
                $_COOKIE['SS_USER_ID'] = (string) intval($user->id);
                $_COOKIE['SS_ROLE'] = $user->role ?? 'viewer';
            }
            // IMPORTANT: Do NOT unset $_COOKIE values when user is not authenticated.
            // The browser still sends SS_ROLE/SS_USER_ID cookies from a previous login.
            // Other middlewares (SuperAdminMiddleware) rely on reading these cookies
            // to authenticate the request via the legacy session/cookie system.
            // Unsetting them here breaks the cookie-based auth fallback.
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
            if ($user) {
                Cookie::queue('SS_USER_ID', (string) intval($user->id), $minutes);
                Cookie::queue('SS_ROLE', $user->role ?? 'viewer', $minutes);
                // Also set raw (unencrypted) cookies via native PHP so legacy
                // public PHP files (served outside Laravel) can read them.
                try {
                    $expire = time() + ($minutes * 60);
                    $secure = $request->isSecure();
                    setcookie('SS_USER_ID', (string) intval($user->id), $expire, '/');
                    setcookie('SS_ROLE', $user->role ?? 'viewer', $expire, '/');
                } catch (\Throwable $_) {
                    // non-fatal if setcookie fails
                }
            }
            // IMPORTANT: Do NOT clear SS_ROLE/SS_USER_ID cookies when user is not
            // authenticated via Laravel Auth. The browser may still have valid
            // legacy SS cookies from a previous login. Clearing them here would
            // prevent the SuperAdminMiddleware from using cookie-based auth
            // fallback on the NEXT request. Only clear these cookies explicitly
            // through the /legacy-logout endpoint.
        } catch (\Throwable $e) {
            Log::debug('LegacySessionMiddleware cookie queue failed: ' . $e->getMessage());
        }

        return $response;
    }
}
