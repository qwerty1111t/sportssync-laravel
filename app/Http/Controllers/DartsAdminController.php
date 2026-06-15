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
        try {
            $legacyPath = public_path('DARTS ADMIN UI/index.php');
            Log::info('[DARTS] Starting request', ['path' => $legacyPath, 'user' => auth()->id()]);
            
            if (!file_exists($legacyPath)) {
                Log::error('[DARTS] File not found', ['path' => $legacyPath]);
                return response('Legacy darts admin missing at: ' . $legacyPath, 500);
            }
            
            if (!defined('LARAVEL_WRAPPER')) {
                define('LARAVEL_WRAPPER', true);
            }
            
            $cfg = config('db_darts');
            Log::info('[DARTS] Config loaded', ['host' => $cfg['host'] ?? 'null', 'database' => $cfg['database'] ?? 'null']);
            
            // Check if mysqli extension is loaded
            if (!class_exists('mysqli')) {
                Log::error('[DARTS] mysqli extension not loaded');
                return response('PHP mysqli extension is not installed. Please ensure php8.2-mysql is installed in the container.', 500);
            }
            
            $mysqli = @new \mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
            if ($mysqli->connect_errno) {
                Log::error('[DARTS] DB connect failed', ['errno' => $mysqli->connect_errno, 'error' => $mysqli->connect_error]);
                return response('Database connection failed: ' . $mysqli->connect_error, 500);
            }
            
            $mysqli->set_charset($cfg['charset'] ?? 'utf8mb4');
            
            ob_start();
            include $legacyPath;
            $html = ob_get_clean();
            Log::info('[DARTS] Legacy HTML loaded', ['length' => strlen($html)]);
            
            // Expose a JS global so embedded legacy pages can resolve sibling URLs
            $legacyDir = '/darts-admin/';
            // Strip only the outer HTML structure - Blade view provides wrapper and CSS/JS loading
            $html = preg_replace('/(<!DOCTYPE.*?>)/i', '', $html); // Remove DOCTYPE
            $html = preg_replace('/<html[^>]*>/i', '', $html); // Remove html opening
            $html = preg_replace('/<\/html>/i', '', $html); // Remove html closing
            $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html); // Remove entire head
            $html = preg_replace('/<body[^>]*>/i', '', $html); // Remove body opening
            $html = preg_replace('/<\/body>/i', '', $html); // Remove body closing
            $html = str_replace('darts.sql', $legacyDir . 'darts.sql', $html);

            // Rewrite relative history button URL so it goes through the proxy (no .php extension)
            $html = str_replace("'history.html'", "'/darts-admin/history'", $html);

            $this->injectLegacyBasePath('darts-admin', $html);
            
            Log::info('[DARTS] Rendering view', ['legacy_html_length' => strlen($html)]);
            return view('darts.admin', ['legacy_html' => $html]);
        } catch (\Throwable $e) {
            Log::error('[DARTS] Exception: ' . $e->getMessage(), ['exception' => $e]);
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
}
