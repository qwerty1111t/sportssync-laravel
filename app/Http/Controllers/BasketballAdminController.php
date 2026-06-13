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
        // Rewrite ALL asset paths to use new hyphenated routes (no spaces - avoids Railway security blocks)
        $html = str_replace('style.css', '/basketball-admin/style.css', $html);
        $html = str_replace('app.js', '/basketball-admin/app.js', $html);
        $html = str_replace('basketball_viewer.css', '/basketball-admin/basketball_viewer.css', $html);
        $html = str_replace('basketball_viewer.js', '/basketball-admin/basketball_viewer.js', $html);
        $this->injectLegacyBasePath('basketball-admin', $html);

        // Legacy session/cookie injection is handled by middleware `legacy.session`.

        return view('basketball.admin', ['legacy_html' => $html]);
    }
}
