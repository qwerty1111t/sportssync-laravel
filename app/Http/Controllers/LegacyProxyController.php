<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LegacyProxyController extends Controller
{
    /**
     * Handle proxied requests to legacy PHP files under /public.
     * Middleware should ensure auth and legacy session injection when needed.
     */
    public function handle(Request $request, $sport, $path = '')
    {
        // Map hyphenated route names to actual file system directory names
        $sportMap = [
            'badminton-admin' => 'Badminton Admin UI',
            'basketball-admin' => 'Basketball Admin UI',
            'tabletennis-admin' => 'TABLE TENNIS ADMIN UI',
            'darts-admin' => 'DARTS ADMIN UI',
            'volleyball-admin' => 'Volleyball Admin UI',
            'analytics' => 'analytics',
        ];
        
        if (!isset($sportMap[$sport])) {
            abort(404);
        }
        
        $actualSport = $sportMap[$sport];
        $allowed = config('legacy.allowed_folders', []);
        if (! in_array($actualSport, $allowed, true)) {
            abort(404);
        }

        // Determine default file for this sport
        $defaultFiles = [
            'Badminton Admin UI' => 'badminton_admin.php',
            'Basketball Admin UI' => 'index.php',
            'TABLE TENNIS ADMIN UI' => 'tabletennis_admin.php',
            'DARTS ADMIN UI' => 'index.php',
            'Volleyball Admin UI' => 'volleyball_admin.php',
            'analytics' => 'index.php',
        ];
        
        if ($path === '' || $path === null) {
            $path = $defaultFiles[$actualSport] ?? 'index.php';
        }
        
        // Map viewer routes to actual PHP files
        if ($path === 'viewer' && $actualSport === 'DARTS ADMIN UI') {
            $path = 'viewer.php';
        }
        
        // Generic path mapping: if the URL path matches a known legacy file (without .php),
        // map it back to the actual .php filename so it works through the proxy without
        // Nginx intercepting the .php extension.
        $knownLegacyFiles = [
            'Basketball Admin UI' => [
                'basketball_matches_admin' => 'basketball_matches_admin.php',
                'timer' => 'timer.php',
                'state' => 'state.php',
                'new_match' => 'new_match.php',
                'save_game' => 'save_game.php',
                'delete_match' => 'delete_match.php',
                'edit_match' => 'edit_match.php',
                'report' => 'report.php',
            ],
            'Volleyball Admin UI' => [
                'volleyball_matches_admin' => 'volleyball_matches_admin.php',
            ],
            'Badminton Admin UI' => [
                'badminton_matches_admin' => 'badminton_matches_admin.php',
            ],
            'TABLE TENNIS ADMIN UI' => [
                'tabletennis_matches_admin' => 'tabletennis_matches_admin.php',
            ],
            'DARTS ADMIN UI' => [
                'history'                      => 'history.html',
                'get_history'                  => 'get_history.php',
                'get_match'                    => 'get_match.php',
                'delete_match'                 => 'delete_match.php',
                'update_match'                 => 'update_match.php',
                'darts_report'                 => 'darts_report.php',
                'report_export'                => 'report_export.php',
                'save_match'                   => 'save_match.php',
                'save_leg'                     => 'save_leg.php',
                'state'                        => 'state.php',
            ],
        ];
        
        if (isset($knownLegacyFiles[$actualSport][$path])) {
            $path = $knownLegacyFiles[$actualSport][$path];
        }

        // Basic sanitization
        $path = str_replace("\0", '', $path);
        if (strpos($path, '..') !== false || preg_match('#(^/|\\\\)#', $path)) {
            abort(400);
        }

        $legacyFile = public_path($actualSport . '/' . $path);
        if (! file_exists($legacyFile) || ! is_file($legacyFile)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($legacyFile, PATHINFO_EXTENSION));
        
        // For static files, just read the content directly without executing PHP
        $staticExtensions = ['css', 'js', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'html'];
        if (in_array($ext, $staticExtensions)) {
            $content = file_get_contents($legacyFile);
        } else {
            // For PHP files, execute them
            if (! defined('LARAVEL_WRAPPER')) define('LARAVEL_WRAPPER', true);
            chdir(dirname($legacyFile));
            ob_start();
            include $legacyFile;
            $content = ob_get_clean();
        }

        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            default => 'text/html',
        };

        return response($content, 200)->header('Content-Type', $mime);
    }
}
