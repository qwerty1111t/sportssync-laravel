<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                            } elseif (isset($user->role)) {
                                $role = $user->role;
                            }
                        } catch (\Throwable $_) {
                            $role = $user->role ?? null;
                        }
                    } else {
                        $role = $user->role ?? null;
                    }
                }
            }
        } catch (\Throwable $_) {
            // ignore and fall through to legacy/session checks
        }

        // 2) Try request user (alternate access point)
        if (!$role && $request->user()) {
            $ruser = $request->user();
            $role = $ruser->role ?? $role;
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

        // Allow only superadmin
        if ($normalized !== 'superadmin') {
            // If no valid auth present, redirect to login; otherwise deny
            if (!Auth::check()) {
                return redirect('/login');
            }
            abort(403, 'Unauthorized access');
        }

        return $next($request);
    }
}
