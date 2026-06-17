<?php

namespace App\Http\Controllers\Superadmin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the superadmin login view.
     */
    public function create(): View
    {
        return view('superadmin.auth.login');
    }

    /**
     * Handle an incoming authentication request for superadmin.
     *
     * This controller ONLY uses Laravel's Auth facade and session() helper.
     * No $_SESSION, $_COOKIE, SS_ROLE, SS_USER_ID, session_start(), or
     * native PHP session functions are used.
     *
     * Legacy compatibility cookies (SS_USER_ID, SS_ROLE) are NOT set here.
     * They are handled exclusively by LegacySessionMiddleware for requests
     * that need to execute legacy PHP files outside the Laravel lifecycle.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var \App\Models\User $user */
        $user = Auth::guard('web')->user();

        // Role gate — only superadmin users may log in here.
        if (! $user || strtolower((string)($user->role ?? '')) !== 'superadmin') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'identifier' => 'Invalid credentials or not authorized as superadmin.',
            ]);
        }

        // Regenerate session to prevent fixation attacks
        $request->session()->regenerate();

        Log::info('[SuperadminLogin] Login successful', [
            'user_id' => $user->id,
            'role' => $user->role,
        ]);

        // Resolve ?next= parameter for post-login redirect
        $next = trim((string)($request->input('next') ?? $request->query('next') ?? ''));

        if (str_starts_with($next, 'sport:')) {
            $sportKey = strtolower(substr($next, 6));
            $sportMap = [
                'basketball'  => 'Basketball%20Admin%20UI/index.php',
                'volleyball'  => 'Volleyball%20Admin%20UI/volleyball_admin.php',
                'badminton'   => 'Badminton%20Admin%20UI/badminton_admin.php',
                'tabletennis' => 'TABLE%20TENNIS%20ADMIN%20UI/tabletennis_admin.php',
                'darts'       => 'DARTS%20ADMIN%20UI/index.php',
                'analytics'   => 'analytics/analytics.php',
                'players'     => 'analytics/players.php',
            ];
            if (isset($sportMap[$sportKey])) {
                $redirectPath = '/' . $sportMap[$sportKey];
                Log::info('[SuperadminLogin] Redirecting to sport page', [
                    'sport' => $sportKey,
                ]);
                return redirect($redirectPath);
            }
        }

        if ($next !== '') {
            $n = strtolower($next);
            if (str_contains($n, 'adminlanding')) {
                return redirect()->route('superadmin.dashboard');
            }
            if (preg_match('#admin ui|admin\.php|viewer\.php#i', $n)) {
                $decoded  = urldecode($next);
                $segments = explode('/', ltrim($decoded, '/'));
                $encoded  = implode('/', array_map('rawurlencode', $segments));
                return redirect('/' . $encoded);
            }
        }

        // Default: land on the legacy admin landing page (public/superadmin_adminlanding_page.php)
        return redirect('/superadmin_adminlanding_page.php');
    }

    /**
     * Destroy the superadmin session (logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }
}
