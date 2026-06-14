<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LegacySessionMiddleware
{
    /**
     * Inject a lightweight legacy-compatible $_SESSION identity
     * for requests that will execute legacy PHP inside the Laravel proxy.
     *
     * This middleware is ONLY used for routes that proxy legacy public/* PHP
     * files (via LegacyProxyController). The SuperAdmin auth system does NOT
     * use this middleware — it uses pure Laravel Auth facade.
     *
     * The $_SESSION values set here allow legacy PHP files (e.g., Basketball
     * Admin UI/*, analytics/*) to authenticate users via $_SESSION['user_id']
     * and $_SESSION['SS_ROLE'] when they call requireLogin() / requireRole().
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
                $_SESSION['user_id'] = intval($user->id);
                $_SESSION['role'] = $user->role ?? 'viewer';
                $_SESSION['SS_ROLE'] = $user->role ?? 'viewer';
                $_SESSION['SS_USER_ID'] = (string) intval($user->id);
                $_SESSION['username'] = $user->username ?? $user->name ?? null;
                $_COOKIE['SS_USER_ID'] = (string) intval($user->id);
                $_COOKIE['SS_ROLE'] = $user->role ?? 'viewer';
                
                Log::debug('LegacySessionMiddleware: Populated from Auth::user()', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                ]);
            }
        } catch (\Throwable $e) {
            Log::debug('LegacySessionMiddleware inject failed: ' . $e->getMessage());
        }

        // Let the request be handled
        $response = $next($request);

        return $response;
    }
}
