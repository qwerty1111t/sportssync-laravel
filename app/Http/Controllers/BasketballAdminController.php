<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BasketballAdminController extends Controller
{
    use LegacyWrapperTrait;

    public function index(Request $request)
    {
        $legacyPath = public_path('Basketball Admin UI/index.php');
        Log::debug('Basketball Admin path check', ['path' => $legacyPath, 'exists' => file_exists($legacyPath), 'base' => public_path('')]);
        
        if (!file_exists($legacyPath)) {
            Log::error('Basketball Admin file not found', ['path' => $legacyPath]);
            return response('Legacy basketball admin file missing at: ' . $legacyPath, 500);
        }
        if (!defined('LARAVEL_WRAPPER')) {
            define('LARAVEL_WRAPPER', true);
        }
        $cfg = config('db_basketball');
        try {
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
            $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Basketball DB connect: ' . $e->getMessage());
            return response('Database connection failed', 500);
        }

        ob_start();
        include $legacyPath;
        $html = ob_get_clean();
        // Strip only the outer HTML structure - Blade view provides wrapper and CSS/JS loading
        $html = preg_replace('/(<!DOCTYPE.*?>)/i', '', $html); // Remove DOCTYPE
        $html = preg_replace('/<html[^>]*>/i', '', $html); // Remove html opening
        $html = preg_replace('/<\/html>/i', '', $html); // Replace html closing
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html); // Remove entire head
        $html = preg_replace('/<body[^>]*>/i', '', $html); // Remove body opening
        $html = preg_replace('/<\/body>/i', '', $html); // Remove body closing
        $this->injectLegacyBasePath('basketball-admin', $html);

        // Rewrite relative match history button URLs so they go through the proxy (no .php extension)
        $html = str_replace("'basketball_matches_admin.php'", "'/basketball-admin/basketball_matches_admin'", $html);

        // Legacy session/cookie injection is handled by middleware `legacy.session`.

        return view('basketball.admin', ['legacy_html' => $html]);
    }
}
