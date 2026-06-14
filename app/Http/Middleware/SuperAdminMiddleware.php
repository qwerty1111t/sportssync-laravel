<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware ensures the user is authenticated AND has the 'superadmin' role.
     * Unauthenticated users are redirected to the superadmin login page.
     * Authenticated users without the superadmin role receive a 403 Forbidden response.
     *
     * This middleware ONLY uses Laravel's Auth facade — no $_SESSION, no $_COOKIE,
     * no legacy SS_ROLE/SS_USER_ID keys, and no native PHP session dependency.
     */
    public function handle(Request $request, Closure $next)
    {
        // If not authenticated via Laravel Auth, redirect to superadmin login
        if (!Auth::check()) {
            return redirect()->route('superadmin.login');
        }

        $user = Auth::user();
        $role = strtolower((string)($user->role ?? ''));

        // Superadmin role grants access
        if ($role === 'superadmin') {
            return $next($request);
        }

        // Authenticated but wrong role — 403 Forbidden (never redirect, never loop)
        abort(403, 'Superadmin access required.');
    }
}
