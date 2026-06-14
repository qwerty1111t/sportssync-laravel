<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * This middleware validates that the user has the 'superadmin' role.
     * 
     * It checks role from multiple sources (in priority order):
     * 1. Laravel Auth::user()->role (if user is authenticated in Laravel Auth system)
     * 2. Session variables: user_role, SS_ROLE
     * 3. Database lookup via user_id
     * 4. Cookies: SS_ROLE
     * 
     * This supports both:
     * - Laravel Auth-based authentication (database-backed users table)
     * - Session/cookie-based authentication (legacy PHP compatibility)
     * 
     * If valid superadmin role is found, allows the request through.
     * If no role is found, redirects unauthenticated users to /superadmin/login.
     * If role is found but not superadmin, returns 403 Forbidden.
     */
    public function handle(Request $request, Closure $next)
    {
        $role = null;
        $userId = null;

        // 1) Try Laravel authenticated user (prefer DB-stored role when possible)
        try {
            if (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
                if ($user) {
                    $userId = $user->id ?? null;
                    if ($userId) {
                        try {
                            $dbRole = DB::table('users')->where('id', $userId)->value('role');
                            if ($dbRole) {
                                $role = $dbRole;
                                Log::debug('[SuperAdminMiddleware] Got role from DB', ['role' => $role, 'user_id' => $userId]);
                            } elseif (isset($user->role)) {
                                $role = $user->role;
                                Log::debug('[SuperAdminMiddleware] Got role from user model', ['role' => $role, 'user_id' => $userId]);
                            }
                        } catch (\Throwable $e) {
                            $role = $user->role ?? null;
                            Log::debug('[SuperAdminMiddleware] DB query failed, using model role', ['role' => $role, 'error' => $e->getMessage()]);
                        }
                    } else {
                        $role = $user->role ?? null;
                        Log::debug('[SuperAdminMiddleware] No user ID, using model role', ['role' => $role]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[SuperAdminMiddleware] Auth check failed', ['error' => $e->getMessage()]);
        }

        // 2) Try request user (alternate access point)
        if (!$role && $request->user()) {
            $ruser = $request->user();
            $role = $ruser->role ?? $role;
            $userId = $ruser->id ?? $userId;
            Log::debug('[SuperAdminMiddleware] Got role from request->user()', ['role' => $role]);
        }

        // 3) Check Laravel session before native PHP session (database session driver)
        // With SESSION_DRIVER=database, session() reads from MySQL, not $_SESSION
        if (!$role) {
            $laravelSessionRole = session('SS_ROLE') ?? session('user_role') ?? session('role') ?? null;
            $laravelSessionUserId = session('user_id') ?? session('SS_USER_ID') ?? null;
            if ($laravelSessionRole) {
                $role = $laravelSessionRole;
                if ($laravelSessionUserId) {
                    $userId = (int)$laravelSessionUserId;
                }
                Log::debug('[SuperAdminMiddleware] Got role from Laravel session', ['role' => $role, 'user_id' => $userId]);
            } else {
                Log::debug('[SuperAdminMiddleware] Laravel session has no role data', [
                    'session_keys' => array_keys(session()->all() ?? []),
                ]);
            }
        }

        // 4) Legacy PHP native session fallback (for native PHP session compat)
        if (!$role) {
            $sessionUserId = $_SESSION['user_id'] ?? $_SESSION['SS_USER_ID'] ?? null;
            if ($sessionUserId) {
                $userId = (int)$sessionUserId;
                // Get role from session - prefer SS_ROLE (set by login controller / SuperadminController)
                if (!empty($_SESSION['SS_ROLE'])) {
                    $role = $_SESSION['SS_ROLE'];
                    Log::debug('[SuperAdminMiddleware] Got role from native session SS_ROLE', ['role' => $role, 'user_id' => $userId]);
                } elseif (!empty($_SESSION['user_role'])) {
                    $role = $_SESSION['user_role'];
                    Log::debug('[SuperAdminMiddleware] Got role from native session user_role', ['role' => $role, 'user_id' => $userId]);
                } elseif (!empty($_SESSION['role'])) {
                    $role = $_SESSION['role'];
                    Log::debug('[SuperAdminMiddleware] Got role from native session role', ['role' => $role, 'user_id' => $userId]);
                } else {
                    // Try to fetch from database
                    try {
                        $dbRole = DB::table('users')->where('id', $userId)->value('role');
                        if ($dbRole) {
                            $role = $dbRole;
                            Log::debug('[SuperAdminMiddleware] Got role from DB via native session user_id', ['role' => $role, 'user_id' => $userId]);
                        }
                    } catch (\Throwable $_) {
                        // Database unavailable
                    }
                }
            }
        }

        // 5) Cookie fallback (no session found — browser still has SS cookies)
        // Check both $_COOKIE (native PHP) and $request->cookie() (Laravel)
        // $request->cookie() reads from the Laravel cookie jar (decrypted if EncryptCookies ran)
        if (!$role) {
            $cookieRole = $_COOKIE['SS_ROLE'] ?? null;
            try {
                $cookieRole = $cookieRole ?: $request->cookie('SS_ROLE');
            } catch (\Throwable $_) {}
            if (!$cookieRole) {
                // Last resort: check raw cookies from the request header
                try {
                    $rawCookies = $request->server('HTTP_COOKIE', '');
                    if (preg_match('/(?:^|;)\s*SS_ROLE\s*=\s*([^;]+)/i', $rawCookies, $m)) {
                        $cookieRole = trim($m[1]);
                        Log::debug('[SuperAdminMiddleware] Got role from raw cookie header', ['role' => $cookieRole]);
                    }
                } catch (\Throwable $_) {}
            }
            if ($cookieRole) {
                $role = urldecode($cookieRole);
                $cookieUid = $_COOKIE['SS_USER_ID'] ?? null;
                try {
                    $cookieUid = $cookieUid ?: $request->cookie('SS_USER_ID');
                } catch (\Throwable $_) {}
                if (!$cookieUid) {
                    try {
                        $rawCookies = $request->server('HTTP_COOKIE', '');
                        if (preg_match('/(?:^|;)\s*SS_USER_ID\s*=\s*([^;]+)/i', $rawCookies, $m)) {
                            $cookieUid = (int)trim($m[1]);
                        }
                    } catch (\Throwable $_) {}
                }
                if ($cookieUid) {
                    $userId = (int)$cookieUid;
                }
                Log::debug('[SuperAdminMiddleware] Got role from cookie', ['role' => $role, 'user_id' => $userId]);
            }
        }

        // Normalize role (handle array / JSON strings and case-insensitivity)
        $normalized = '';
        if (is_array($role)) {
            $normalized = strtolower(trim((string)($role[0] ?? $role['role'] ?? '')));
        } else {
            if (is_string($role)) {
                $decoded = json_decode($role, true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded) {
                    if (is_array($decoded)) {
                        $normalized = strtolower(trim((string)($decoded[0] ?? $decoded['role'] ?? '')));
                    } elseif (is_string($decoded)) {
                        $normalized = strtolower(trim($decoded));
                    }
                } else {
                    $normalized = strtolower(trim($role));
                }
            } else {
                $normalized = strtolower(trim((string)$role));
            }
        }

        if ($normalized === 'scorekeeper') {
            $normalized = 'admin';
        }

        // GUARD: Detect redirect loops by checking if we're already in the superadmin namespace
        $currentPath = $request->path();
        $isSuperadminPath = str_starts_with($currentPath, 'superadmin');
        
        Log::debug('[SuperAdminMiddleware] Final role check', [
            'raw_role' => $role,
            'normalized_role' => $normalized,
            'auth_check' => Auth::check(),
            'user_id' => $userId,
            'path' => $currentPath,
            'is_superadmin_path' => $isSuperadminPath,
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'session_ss_role' => $_SESSION['SS_ROLE'] ?? null,
            'cookie_ss_role' => $_COOKIE['SS_ROLE'] ?? null,
        ]);

        // SUCCESS: Allow superadmin users through
        if ($normalized === 'superadmin') {
            Log::info('[SuperAdminMiddleware] SUPERADMIN ACCESS GRANTED', [
                'user_id' => $userId,
                'path' => $currentPath,
            ]);
            return $next($request);
        }

        // FAIL: No authentication found from any source
        if (empty($role)) {
            Log::warning('[SuperAdminMiddleware] NO AUTHENTICATION - redirecting to login', [
                'path' => $currentPath,
                'auth_check' => Auth::check(),
                'session_user_id' => $_SESSION['user_id'] ?? null,
            ]);
            
            // Don't redirect if already at login page (prevent loops)
            if (!str_contains($currentPath, 'login') && !str_contains($currentPath, 'password')) {
                return redirect('/superadmin/login');
            }
            return $next($request);
        }

        // FAIL: Authenticated but wrong role
        Log::warning('[SuperAdminMiddleware] WRONG ROLE - denying access', [
            'user_id' => $userId,
            'role_found' => $normalized,
            'path' => $currentPath,
        ]);
        abort(403, 'Superadmin role required');
    }
}
