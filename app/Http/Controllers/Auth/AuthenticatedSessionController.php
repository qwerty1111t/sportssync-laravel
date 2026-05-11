<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Prevent superadmin accounts from signing in via the regular login page.
        try {
            $user = Auth::guard('web')->user();
            if ($user && (($user->role ?? '') === 'superadmin')) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors(['identifier' => 'Use the Superadmin login page to sign in.']);
            }
        } catch (\Throwable $_) {}

        // Regenerate session FIRST before any redirect — prevents 419 on next request.
        $request->session()->regenerate();

        // Set legacy compatibility cookies so /public legacy PHP sport files
        // can read the authenticated user's role without going through Laravel middleware.
        try {
            $authUser = Auth::guard('web')->user();
            if ($authUser) {
                $minutes = 60 * 8; // 8 hours — matches superadmin controller
                \Illuminate\Support\Facades\Cookie::queue('SS_USER_ID', (string) intval($authUser->id), $minutes);
                \Illuminate\Support\Facades\Cookie::queue('SS_ROLE', $authUser->role ?? 'viewer', $minutes);
                try {
                    $expire = time() + ($minutes * 60);
                    setcookie('SS_USER_ID', (string) intval($authUser->id), $expire, '/');
                    setcookie('SS_ROLE', $authUser->role ?? 'viewer', $expire, '/');
                } catch (\Throwable $_) { /* non-fatal */ }
            }
        } catch (\Throwable $_) { /* non-fatal */ }

        // Resolve the intended destination safely.
        $next = trim((string) $request->input('next', ''));

        // Resolve sport: keys — determine the correct file based on authenticated role.
        if (str_starts_with($next, 'sport:')) {
            $sportKey = strtolower(substr($next, 6)); // strip 'sport:' prefix

            $authUser = Auth::guard('web')->user();
            $role = strtolower((string)($authUser->role ?? 'viewer'));
            // Superadmin always goes to adminlanding regardless of sport key.
            if ($role === 'superadmin') {
                return redirect('/adminlanding');
            }
            $isAdmin = in_array($role, ['admin', 'scorekeeper'], true);
            $tier = $isAdmin ? 'admin' : 'viewer';

            $sportMap = [
                'basketball'  => ['admin' => 'Basketball%20Admin%20UI/index.php',                  'viewer' => 'Basketball%20Admin%20UI/basketball_viewer.php'],
                'volleyball'  => ['admin' => 'Volleyball%20Admin%20UI/volleyball_admin.php',        'viewer' => 'Volleyball%20Admin%20UI/volleyball_viewer.php'],
                'badminton'   => ['admin' => 'Badminton%20Admin%20UI/badminton_admin.php',          'viewer' => 'Badminton%20Admin%20UI/badminton_viewer.php'],
                'tabletennis' => ['admin' => 'TABLE%20TENNIS%20ADMIN%20UI/tabletennis_admin.php',   'viewer' => 'TABLE%20TENNIS%20ADMIN%20UI/tabletennis_viewer.php'],
                'darts'       => ['admin' => 'DARTS%20ADMIN%20UI/index.php',                        'viewer' => 'DARTS%20ADMIN%20UI/viewer.php'],
                'analytics'   => ['admin' => 'analytics/analytics.php',                             'viewer' => 'analytics/analytics.php'],
                'players'     => ['admin' => 'analytics/players.php',                               'viewer' => 'analytics/players.php'],
            ];

            if (isset($sportMap[$sportKey])) {
                return redirect('/' . $sportMap[$sportKey][$tier]);
            }

            // Unknown sport key — fall through to dashboard
            return redirect()->intended(route('dashboard', [], false));
        }

        // Legacy raw path fallback (handles any existing bookmarked ?next= URLs).
        if ($next !== '') {
            $decoded  = urldecode($next);
            $isOffsite = preg_match('#^(https?:)?//#i', $decoded) &&
                         !str_starts_with(ltrim($decoded, '/'), parse_url(config('app.url'), PHP_URL_HOST) ?? '');
            if (!$isOffsite) {
                $segments = explode('/', ltrim($decoded, '/'));
                $encoded  = implode('/', array_map('rawurlencode', $segments));
                return redirect('/' . $encoded);
            }
        }

        return redirect()->intended(route('dashboard', [], false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Capture current user before logging out
        $user = Auth::guard('web')->user();
        /** @var User|null $user */
        // Determine any remember/recaller cookies to forget later (robust across Laravel versions)
        $recallerCandidates = [];
        foreach ($_COOKIE as $ck => $cv) {
            if (stripos($ck, 'remember') !== false || stripos($ck, 'recaller') !== false) {
                $recallerCandidates[] = $ck;
            }
        }

        // Clear the remember token in the database so the user won't be
        // re-authenticated automatically after logout. Prefer the
        // Authenticatable-compatible `setRememberToken` when available.
        if ($user) {
            try {
                if (method_exists($user, 'setRememberToken')) {
                    $user->setRememberToken(null);
                    if (method_exists($user, 'save')) $user->save();
                } elseif (method_exists($user, 'forceFill')) {
                    $user->forceFill(['remember_token' => null])->save();
                } else {
                    $user->remember_token = null;
                    if (method_exists($user, 'save')) $user->save();
                }
            } catch (\Throwable $e) {
                // Non-fatal: continue with logout even if DB write fails.
            }
        }

        // Perform the standard logout
        Auth::guard('web')->logout();

        // Invalidate session and regenerate CSRF token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Remove the remember-me recaller cookie so users are not immediately
        // re-authenticated after logging out if they had checked "remember me".
        try {
            foreach ($recallerCandidates as $name) {
                try { Cookie::queue(Cookie::forget($name)); } catch (\Throwable $_) {}
            }
        } catch (\Throwable $e) {
            // Non-fatal: if cookie forget fails for any reason, continue logout.
        }

        // Also clear legacy compatibility cookies
        try {
            Cookie::queue(Cookie::forget('SS_USER_ID'));
            Cookie::queue(Cookie::forget('SS_ROLE'));
        } catch (\Throwable $e) { /* non-fatal */ }
        // Avoid direct setcookie() calls; rely on queued cookie forget for any
        // previously-set legacy cookies.

        // Do not force-forget the session cookie here; let Laravel manage the
        // session cookie lifecycle. Explicitly removing the session cookie in
        // the same response can interfere with a fresh session being issued
        // and lead to CSRF token mismatches (419 errors) on subsequent login.

        // Add no-cache headers on the redirect response to discourage browsers
        // from serving cached authenticated pages when navigating back.
        $noCacheHeaders = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 1990 00:00:00 GMT',
        ];

        return redirect('/')->withHeaders($noCacheHeaders);
    }
}
