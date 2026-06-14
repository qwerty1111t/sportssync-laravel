<?php
namespace App\Http\Controllers\Superadmin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            // Authenticate first — throws ValidationException on failure.
            $request->authenticate();

            // Immediately capture the user before any session operation.
            $user = Auth::guard('web')->user();

            // Role gate — must be superadmin.
            if (! $user || strtolower((string)($user->role ?? '')) !== 'superadmin') {
                Auth::guard('web')->logout();
                // Invalidate without regenerateToken so the form CSRF stays valid
                // for the redirect back — prevents 419 on the error page.
                try {
                    if ($request->session()) {
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[SuperadminLogin] Session invalidation failed', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
                return back()->withErrors(['identifier' => 'Invalid credentials or not authorized as superadmin.']);
            }

            // Regenerate session AFTER role is confirmed — prevents fixation.
            try {
                if ($request->session()) {
                    $request->session()->regenerate();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[SuperadminLogin] Session regeneration failed', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            // Store user info in session for both Laravel and legacy PHP access
            try {
                if ($request->session()) {
                    $request->session()->put('user_id', $user->id);
                    $request->session()->put('user_role', $user->role ?? 'viewer');
                    $request->session()->put('username', $user->username ?? 'admin');
                    $request->session()->put('SS_ROLE', $user->role ?? 'viewer'); // Legacy compat
                    $request->session()->put('SS_USER_ID', (string) intval($user->id)); // Legacy compat
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[SuperadminLogin] Session put failed', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            // Set legacy compatibility cookies for /public legacy PHP files.
            // Use ONLY Cookie::queue() — raw setcookie() calls cause duplicate
            // Set-Cookie headers that confuse browsers and can cause header
            // overflow with cookie session driver.
            try {
                $minutes = 60 * 8;
                \Illuminate\Support\Facades\Cookie::queue('SS_USER_ID', (string) intval($user->id), $minutes);
                \Illuminate\Support\Facades\Cookie::queue('SS_ROLE', $user->role ?? 'viewer', $minutes);
            } catch (\Throwable $_) {}

            // Resolve ?next= — support sport: keys and legacy raw paths.
            $next = trim((string)($request->input('next') ?? $request->query('next') ?? ''));
            
            \Illuminate\Support\Facades\Log::info('[SuperadminLoginController] Login successful, processing redirect', [
                'user_id' => $user->id,
                'role' => $user->role,
                'next_param' => $next,
            ]);

            if (str_starts_with($next, 'sport:')) {
                // Superadmin always gets admin-tier pages.
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
                    \Illuminate\Support\Facades\Log::info('[SuperadminLoginController] Redirecting to sport page', [
                        'sport' => $sportKey,
                        'redirect_path' => $redirectPath,
                    ]);
                    return redirect($redirectPath);
                }
            }

            if ($next !== '') {
                $n = strtolower($next);
                if (str_contains($n, 'adminlanding')) {
                    \Illuminate\Support\Facades\Log::info('[SuperadminLoginController] Redirecting to superadmin dashboard (adminlanding requested)');
                    return redirect('/superadmin/dashboard');
                }
                if (preg_match('#admin ui|admin\.php|viewer\.php#i', $n)) {
                    $decoded  = urldecode($next);
                    $segments = explode('/', ltrim($decoded, '/'));
                    $encoded  = implode('/', array_map('rawurlencode', $segments));
                    $redirectPath = '/' . $encoded;
                    \Illuminate\Support\Facades\Log::info('[SuperadminLoginController] Redirecting to requested path', ['redirect_path' => $redirectPath]);
                    return redirect($redirectPath);
                }
            }

            // Default for superadmin — land on superadmin dashboard.
            \Illuminate\Support\Facades\Log::info('[SuperadminLoginController] Redirecting to superadmin dashboard (default)');
            return redirect('/superadmin/dashboard');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Let validation exceptions propagate normally (Laravel handles them)
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SUPERADMIN LOGIN ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            // Re-throw so Laravel's error handler returns 500.
            // This ensures Railway sees the 502/500 and logs the trace.
            throw $e;
        }
    }
}
