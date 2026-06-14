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
                
                Log::debug('LegacySessionMiddleware: Populated from Auth::user()', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                ]);
            } else {
                // Auth::check() returned false. Try to populate $_SESSION from
                // the Laravel database session store (session() helper) and
                // from the incoming request cookies.
                // This handles the case where the browser redirects after login
                // and the Laravel session cookie is present with SS_ROLE data
                // but Auth::check() hasn't been fully established yet.
                try {
                    $sessionRole = session('SS_ROLE') ?? session('user_role') ?? session('role') ?? null;
                    $sessionUserId = session('SS_USER_ID') ?? session('user_id') ?? null;
                    
                    if ($sessionRole) {
                        $_SESSION['SS_ROLE'] = $sessionRole;
                        $_SESSION['role'] = $sessionRole;
                        $_SESSION['user_role'] = $sessionRole;
                        $_COOKIE['SS_ROLE'] = $sessionRole;
                        
                        if ($sessionUserId) {
                            $_SESSION['user_id'] = (int)$sessionUserId;
                            $_SESSION['SS_USER_ID'] = (string)$sessionUserId;
                            $_COOKIE['SS_USER_ID'] = (string)$sessionUserId;
                        }
                        
                        Log::debug('LegacySessionMiddleware: Populated from Laravel session', [
                            'role' => $sessionRole,
                            'user_id' => $sessionUserId,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::debug('LegacySessionMiddleware: Laravel session fallback failed: ' . $e->getMessage());
                }
                
                // Also try to populate from cookies if not already populated
                if (empty($_SESSION['SS_ROLE'])) {
                    try {
                        $cookieRole = $_COOKIE['SS_ROLE'] ?? $request->cookie('SS_ROLE') ?? null;
                        $cookieUserId = $_COOKIE['SS_USER_ID'] ?? $request->cookie('SS_USER_ID') ?? null;
                        
                        if ($cookieRole) {
                            $_SESSION['SS_ROLE'] = urldecode($cookieRole);
                            $_SESSION['role'] = urldecode($cookieRole);
                            $_SESSION['user_role'] = urldecode($cookieRole);
                            
                            if ($cookieUserId) {
                                $_SESSION['user_id'] = (int)$cookieUserId;
                                $_SESSION['SS_USER_ID'] = (string)$cookieUserId;
                            }
                            
                            Log::debug('LegacySessionMiddleware: Populated from cookies', [
                                'role' => $cookieRole,
                                'user_id' => $cookieUserId,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::debug('LegacySessionMiddleware: Cookie fallback failed: ' . $e->getMessage());
                    }
                }
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
                // NOTE: Removed raw setcookie() calls to avoid duplicate Set-Cookie
                // headers. Cookie::queue() handles this properly via Laravel's cookie
                // jar, respecting the encrypted cookie middleware lifecycle.
                // Raw setcookie() bypasses the middleware and causes duplicate cookies
                // with conflicting domain/path/httponly attributes.
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
