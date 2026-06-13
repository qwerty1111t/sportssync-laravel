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
        // Authenticate first — throws ValidationException on failure.
        $request->authenticate();

        // Immediately capture the user before any session operation.
        $user = Auth::guard('web')->user();

        // Role gate — must be superadmin.
        if (! $user || strtolower((string)($user->role ?? '')) !== 'superadmin') {
            Auth::guard('web')->logout();
            // Invalidate without regenerateToken so the form CSRF stays valid
            // for the redirect back — prevents 419 on the error page.
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['identifier' => 'Invalid credentials or not authorized as superadmin.']);
        }

        // Regenerate session AFTER role is confirmed — prevents fixation.
        $request->session()->regenerate();

        // Store user info in session for both Laravel and legacy PHP access
        $request->session()->put('user_id', $user->id);
        $request->session()->put('user_role', $user->role ?? 'viewer');
        $request->session()->put('username', $user->username ?? 'admin');
        $request->session()->put('SS_ROLE', $user->role ?? 'viewer'); // Legacy compat
        $request->session()->put('SS_USER_ID', (string) intval($user->id)); // Legacy compat

        // Set legacy compatibility cookies for /public legacy PHP files.
        try {
            $minutes = 60 * 8;
            \Illuminate\Support\Facades\Cookie::queue('SS_USER_ID', (string) intval($user->id), $minutes);
            \Illuminate\Support\Facades\Cookie::queue('SS_ROLE', $user->role ?? 'viewer', $minutes);
            try {
                $expire = time() + ($minutes * 60);
                setcookie('SS_USER_ID', (string) intval($user->id), $expire, '/');
                setcookie('SS_ROLE', $user->role ?? 'viewer', $expire, '/');
            } catch (\Throwable $_) {}
        } catch (\Throwable $_) {}

        // Resolve ?next= — support sport: keys and legacy raw paths.
        $next = trim((string)($request->input('next') ?? $request->query('next') ?? ''));

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
                return redirect('/' . $sportMap[$sportKey]);
            }
        }

        if ($next !== '') {
            $n = strtolower($next);
            if (str_contains($n, 'adminlanding')) {
                return redirect('/adminlanding_page.php');
            }
            if (preg_match('#admin ui|admin\.php|viewer\.php#i', $n)) {
                $decoded  = urldecode($next);
                $segments = explode('/', ltrim($decoded, '/'));
                $encoded  = implode('/', array_map('rawurlencode', $segments));
                return redirect('/' . $encoded);
            }
        }

        // Default for superadmin — always land on the legacy admin landing page.
        return redirect('/adminlanding_page.php');
    }
}
