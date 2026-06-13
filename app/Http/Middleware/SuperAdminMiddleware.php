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
     */
    public function handle(Request $request, Closure $next)
    {
        $role = null;

        // 1) Try Laravel authenticated user (prefer DB-stored role when possible)
        try {
            if (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
                if ($user) {
                    if (isset($user->id)) {
                        try {
                            $dbRole = DB::table('users')->where('id', $user->id)->value('role');
                            if ($dbRole) {
                                $role = $dbRole;
                                Log::debug('[SuperAdminMiddleware] Got role from DB', ['role' => $role, 'user_id' => $user->id]);
                            } elseif (isset($user->role)) {
                                $role = $user->role;
                                Log::debug('[SuperAdminMiddleware] Got role from user model', ['role' => $role, 'user_id' => $user->id]);
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
            Log::debug('[SuperAdminMiddleware] Got role from request->user()', ['role' => $role]);
        }

        // 3) Legacy PHP session / lightweight cookie fallback (SS_ROLE)
        // First check for session user_id to ensure user is authenticated via session
        if (!$role) {
            // Check if user is authenticated via session
            if (!empty($_SESSION['user_id'])) {
                // User has a session user_id - get role from session or database
                if (!empty($_SESSION['user_role'])) {
                    $role = $_SESSION['user_role'];
                } elseif (!empty($_SESSION['SS_ROLE'])) {
                    $role = $_SESSION['SS_ROLE'];
                } else {
                    // Try to fetch from database
                    try {
                        $userId = (int)$_SESSION['user_id'];
                        $dbRole = DB::table('users')->where('id', $userId)->value('role');
                        if ($dbRole) {
                            $role = $dbRole;
                        }
                    } catch (\Throwable $_) {
                        // Database unavailable
                    }
                }
            }
            
            // Fallback to cookies if no session user
            if (!$role && !empty($_COOKIE['SS_ROLE'])) {
                $role = urldecode($_COOKIE['SS_ROLE']);
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
        // If middleware keeps redirecting, we'll end up in a loop
        $currentPath = $request->path();
        $isSuperadminPath = str_starts_with($currentPath, 'superadmin');
        
        Log::debug('[SuperAdminMiddleware] Final role check', [
            'raw_role' => $role,
            'normalized_role' => $normalized,
            'auth_check' => Auth::check(),
            'user_id' => Auth::id(),
            'path' => $currentPath,
            'is_superadmin_path' => $isSuperadminPath,
        ]);

        // Allow only superadmin
        if ($normalized === 'superadmin') {
            // User is authenticated AND has superadmin role - allow
            Log::info('[SuperAdminMiddleware] Superadmin access granted', [
                'user_id' => Auth::id(),
                'path' => $currentPath,
                'normalized_role' => $normalized,
            ]);
            return $next($request);
        }

        // User does not have superadmin role
        if (!Auth::check()) {
            // Not authenticated at all - redirect to login
            // GUARD: only redirect if we're not already at login
            if (!str_contains($currentPath, 'login')) {
                Log::info('[SuperAdminMiddleware] Not authenticated, redirecting to login', ['path' => $currentPath]);
                return redirect('/superadmin/login');
            }
            Log::warning('[SuperAdminMiddleware] Not authenticated but already at login, allowing through', ['path' => $currentPath]);
            return $next($request);
        }

        // Authenticated but not superadmin - deny
        Log::warning('[SuperAdminMiddleware] Authenticated but not superadmin, denying access', [
            'user_id' => Auth::id(),
            'role' => $normalized,
            'path' => $currentPath,
        ]);
        abort(403, 'Superadmin role required');
    }
}
