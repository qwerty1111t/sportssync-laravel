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
        // Strip the outer HTML structure and doctypes from legacy file - Blade view provides wrapper
        $html = preg_replace('/<\?php.*?\?>/s', '', $html); // Remove PHP tags
        $html = preg_replace('/(<!DOCTYPE.*?>)/i', '', $html); // Remove DOCTYPE
        $html = preg_replace('/<html[^>]*>/i', '', $html); // Remove html opening tag
        $html = preg_replace('/<\/html>/i', '', $html); // Remove html closing tag  
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html); // Remove entire head section
        $html = preg_replace('/<body[^>]*>/i', '', $html); // Remove body opening tag
        $html = preg_replace('/<\/body>/i', '', $html); // Remove body closing tag
        $html = str_replace('darts.sql', $legacyDir . 'darts.sql', $html);
        $this->injectLegacyBasePath('darts-admin', $html);

        // Legacy session/cookie injection is handled by middleware `legacy.session`.

        return view('darts.admin', ['legacy_html' => $html]);
    }
}
