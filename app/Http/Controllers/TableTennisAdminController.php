<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TableTennisAdminController extends Controller
{
    use LegacyWrapperTrait;

    public function index(Request $request)
    {
        $legacyPath = public_path('TABLE TENNIS ADMIN UI/tabletennis_admin.php');
        Log::debug('TableTennis Admin path check', ['path' => $legacyPath, 'exists' => file_exists($legacyPath), 'base' => public_path('')]);
        
        if (!file_exists($legacyPath)) {
            Log::error('TableTennis Admin file not found', ['path' => $legacyPath]);
            return response('Legacy TT admin missing at: ' . $legacyPath, 500);
        }
        if (!defined('LARAVEL_WRAPPER')) {
            define('LARAVEL_WRAPPER', true);
        }
        $cfg = config('db_tabletennis');
        $mysqli = @new \mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
        if ($mysqli->connect_errno) {
            Log::error('TableTennis DB connect: ' . $mysqli->connect_error);
            return response('Database connection failed', 500);
        }
        $mysqli->set_charset($cfg['charset'] ?? 'utf8mb4');
        ob_start();
        include $legacyPath;
        $html = ob_get_clean();
        // Remove CSS/JS links from legacy HTML (Blade view loads them in proper <head> location)
        ob_start();
        include $legacyPath;
        $html = ob_get_clean();
        // Strip only the outer HTML structure - Blade view provides wrapper and CSS/JS loading
        $html = preg_replace('/(<!DOCTYPE.*?>)/i', '', $html); // Remove DOCTYPE
        $html = preg_replace('/<html[^>]*>/i', '', $html); // Remove html opening
        $html = preg_replace('/<\/html>/i', '', $html); // Remove html closing
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html); // Remove entire head
        $html = preg_replace('/<body[^>]*>/i', '', $html); // Remove body opening
        $html = preg_replace('/<\/body>/i', '', $html); // Remove body closing
        $this->injectLegacyBasePath('tabletennis-admin', $html);

        // Legacy session/cookie injection is handled by middleware `legacy.session`.

        return view('tabletennis.admin', ['legacy_html' => $html]);
    }
}
