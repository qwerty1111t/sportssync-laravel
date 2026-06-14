<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has at least the specified role.
     * Usage: ensure.role:admin or ensure.role:viewer
     *
     * Priority: Auth::user() role from database.
     * Fallback: $_SESSION / $_COOKIE (for legacy public/* PHP files proxied
     *           through LegacyProxyController outside the Laravel lifecycle).
     *
     * This middleware allows 'superadmin' through any role check (bypass).
     */
    public function handle($request, $next, $requiredRole)
    {
        // ---- 1) Try Laravel Auth (primary source) ----
        $role = null;
        $status = null;

        try {
            if (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
                if ($user && isset($user->role)) {
                    $role = $user->role;
                }
            }
        } catch (\Throwable $e) {
            $role = null;
        }

        // ---- 2) Fallback: session/cookie (for legacy PHP files) ----
        if (!$role) {
            try {
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                }
                if (!empty($_SESSION['role'])) {
                    $role = $_SESSION['role'];
                } elseif (!empty($_SESSION['SS_ROLE'])) {
                    $role = $_SESSION['SS_ROLE'];
                } elseif (!empty($_COOKIE['SS_ROLE'])) {
                    $role = urldecode($_COOKIE['SS_ROLE']);
                }
            } catch (\Throwable $_) {
                // Session unavailable
            }
        }

        // ---- Normalize role string ----
        $roleVal = '';
        if (is_array($role)) {
            $roleVal = (string)($role[0] ?? $role['role'] ?? '');
        } elseif (is_string($role)) {
            $decoded = json_decode($role, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded) {
                $roleVal = (string)($decoded[0] ?? $decoded['role'] ?? '');
            } else {
                $roleVal = $role;
            }
        } else {
            $roleVal = (string)$role;
        }
        $role = strtolower(trim($roleVal));

        // Map legacy 'scorekeeper' to 'admin'
        if ($role === 'scorekeeper') {
            $role = 'admin';
        }

        // Superadmin bypasses all checks
        if ($role === 'superadmin') {
            return $next($request);
        }

        // Block users whose account status is pending/rejected
        if (!empty($status) && in_array(strtolower($status), ['pending', 'rejected'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account not approved'], 403);
            }
            abort(403, 'Account not approved');
        }

        // Normalize required role
        if ($requiredRole === 'scorekeeper') {
            $requiredRole = 'admin';
        }

        // Role check: 'viewer' allows viewer+admin, otherwise exact match
        if ($requiredRole === 'viewer') {
            $allowed = ['viewer', 'admin'];
        } else {
            $allowed = [$requiredRole];
        }

        if (!in_array($role, $allowed, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied: insufficient privileges'], 403);
            }
            abort(403, 'Access denied: insufficient privileges');
        }

        return $next($request);
    }
}
