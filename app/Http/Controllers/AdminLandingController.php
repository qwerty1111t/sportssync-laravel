<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLandingController extends Controller
{
    /**
     * Serve the legacy adminlanding_page.php through Laravel's auth middleware.
     *
     * This controller wraps the legacy PHP file so that:
     *   - Nginx does NOT intercept the .php request directly
     *   - Laravel 'auth' + 'superadmin' middleware runs first
     *   - Legacy auth functions (_legacyCurrentUser) find the user via $_SESSION
     *   - No redirect loops occur
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('superadmin.login');
        }

        // Set legacy session variables so _legacyCurrentUser() in
        // app/Legacy/auth.php can find the authenticated user via
        // the $_SESSION['SS_USER_ID'] fallback (line 62-76).
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['SS_USER_ID'] = (string) $user->id;
        $_SESSION['SS_ROLE'] = $user->role ?? 'superadmin';
        $_SESSION['user_id'] = $user->id;

        // Define LARAVEL_WRAPPER so requireLogin() returns a fallback user
        // if _legacyCurrentUser() fails for any reason.
        if (!defined('LARAVEL_WRAPPER')) {
            define('LARAVEL_WRAPPER', true);
        }

        $legacyPath = public_path('adminlanding_page.php');

        if (!file_exists($legacyPath)) {
            return response('Legacy admin landing page not found.', 500);
        }

        ob_start();
        include $legacyPath;
        $content = ob_get_clean();

        // Clean up session variables we injected
        unset($_SESSION['SS_USER_ID'], $_SESSION['SS_ROLE'], $_SESSION['user_id']);

        return response($content)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
