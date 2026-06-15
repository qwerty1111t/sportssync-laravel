<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class AdminLandingController extends Controller
{
    /**
     * Serve the legacy adminlanding_page.php through Laravel's auth middleware.
     *
     * This controller wraps the legacy PHP file so that:
     *   - Nginx does NOT intercept the .php request directly
     *   - Laravel 'auth' + 'superadmin' middleware runs first
     *   - Legacy auth functions (_legacyCurrentUser) find the user via $_SESSION + $pdo
     *   - No redirect loops occur
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('superadmin.login');
        }

        // Ensure PHP session is started so $_SESSION is available
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ── Set $_SESSION for _legacyCurrentUser() ────────────────────
        // _legacyCurrentUser() in app/Legacy/auth.php checks these keys.
        $_SESSION['user_id'] = $user->id;
        $_SESSION['SS_USER_ID'] = (string) $user->id;
        $_SESSION['SS_ROLE'] = $user->role ?? 'superadmin';
        // Set CSRF token so legacy file can use it in meta tags / JS
        $_SESSION['csrf_token'] = csrf_token();

        // ── Set global $pdo for _legacyCurrentUser() ──────────────────
        // _legacyCurrentUser() does: global $pdo; if (!$pdo) return null;
        // app/Legacy/db.php EXITS early when LARAVEL_WRAPPER is defined,
        // so $pdo is never created automatically. We must create it here.
        $GLOBALS['pdo'] = $this->createPdoFromLaravelConfig();

        // ── Define LARAVEL_WRAPPER ────────────────────────────────────
        // This tells app/Legacy/db.php to skip DB creation (we handle it
        // above), and tells requireLogin() to return a fallback user.
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
        unset($_SESSION['user_id'], $_SESSION['SS_USER_ID'], $_SESSION['SS_ROLE']);

        return response($content)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Create a PDO instance from Laravel's database configuration.
     * This ensures _legacyCurrentUser() can query the users table.
     */
    private function createPdoFromLaravelConfig(): ?\PDO
    {
        try {
            $host   = Config::get('database.connections.mysql.host', 'localhost');
            $port   = Config::get('database.connections.mysql.port', 3306);
            $dbname = Config::get('database.connections.mysql.database', 'sportssync');
            $user   = Config::get('database.connections.mysql.username', 'root');
            $pass   = Config::get('database.connections.mysql.password', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            return new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            error_log('[AdminLandingController] PDO connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
