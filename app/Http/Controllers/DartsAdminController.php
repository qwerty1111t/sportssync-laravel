<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DartsAdminController extends Controller
{
    use LegacyWrapperTrait;

    public function index(Request $request)
    {
        $legacyPath = public_path('DARTS ADMIN UI/index.php');
        Log::debug('Darts Admin path check', ['path' => $legacyPath, 'exists' => file_exists($legacyPath), 'base' => public_path('')]);
        
        if (!file_exists($legacyPath)) {
            Log::error('Darts Admin file not found', ['path' => $legacyPath]);
            return response('Legacy darts admin missing at: ' . $legacyPath, 500);
        }
        if (!defined('LARAVEL_WRAPPER')) {
            define('LARAVEL_WRAPPER', true);
        }
        $cfg = config('db_darts');
        $mysqli = @new \mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
        if ($mysqli->connect_errno) {
            Log::error('Darts DB connect: ' . $mysqli->connect_error);
            return response('Database connection failed', 500);
        }
        $mysqli->set_charset($cfg['charset'] ?? 'utf8mb4');
        ob_start();
        include $legacyPath;
        $html = ob_get_clean();
        // Expose a JS global so embedded legacy pages can resolve sibling URLs
        $legacyDir = '/darts-admin/';
        $script = '<script>window.LEGACY_BASE_PATH = ' . json_encode($legacyDir) . ';</script>';
        $html = $script . $html;
        // Fix a couple of resource paths that expect the legacy folder root
        $html = str_replace('darts.sql', $legacyDir . 'darts.sql', $html);
        $html = str_replace('darts_admin.js', $legacyDir . 'darts_admin.js', $html);
        $this->injectLegacyBasePath('darts-admin', $html);

        // Legacy session/cookie injection is handled by middleware `legacy.session`.

        return view('darts.admin', ['legacy_html' => $html]);
    }
}
