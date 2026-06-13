<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BadmintonAdminController extends Controller
{
    use LegacyWrapperTrait;

    public function index(Request $request)
    {
        try {
            $legacyPath = public_path('Badminton Admin UI/badminton_admin.php');
            Log::info('[BADMINTON] Starting request', ['path' => $legacyPath, 'user' => auth()->id()]);
            
            if (!file_exists($legacyPath)) {
                Log::error('[BADMINTON] File not found', ['path' => $legacyPath]);
                return response('Legacy admin file missing at: ' . $legacyPath, 500);
            }
            
            if (!defined('LARAVEL_WRAPPER')) {
                define('LARAVEL_WRAPPER', true);
            }
            
            $cfg = config('db_badminton');
            Log::info('[BADMINTON] Config loaded', ['host' => $cfg['host'] ?? 'null', 'database' => $cfg['database'] ?? 'null']);
            
            // Check if mysqli extension is loaded
            if (!class_exists('mysqli')) {
                Log::error('[BADMINTON] mysqli extension not loaded');
                return response('PHP mysqli extension is not installed. Please ensure php8.2-mysql is installed in the container.', 500);
            }
            
            $mysqli = @new \mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
            if ($mysqli->connect_errno) {
                Log::error('[BADMINTON] DB connect failed', ['errno' => $mysqli->connect_errno, 'error' => $mysqli->connect_error]);
                return response('Database connection failed: ' . $mysqli->connect_error, 500);
            }
            
            $mysqli->set_charset($cfg['charset'] ?? 'utf8mb4');
            
            ob_start();
            include $legacyPath;
            $html = ob_get_clean();
            Log::info('[BADMINTON] Legacy HTML loaded', ['length' => strlen($html)]);
            
            // Strip only the outer HTML structure - Blade view provides wrapper and CSS/JS loading
            $html = preg_replace('/(<!DOCTYPE.*?>)/i', '', $html); // Remove DOCTYPE
            $html = preg_replace('/<html[^>]*>/i', '', $html); // Remove html opening
            $html = preg_replace('/<\/html>/i', '', $html); // Remove html closing
            $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html); // Remove entire head
            $html = preg_replace('/<body[^>]*>/i', '', $html); // Remove body opening
            $html = preg_replace('/<\/body>/i', '', $html); // Remove body closing
            $this->injectLegacyBasePath('badminton-admin', $html);
            
            Log::info('[BADMINTON] Rendering view', ['legacy_html_length' => strlen($html)]);
            return view('badminton.admin', ['legacy_html' => $html]);
        } catch (\Throwable $e) {
            Log::error('[BADMINTON] Exception: ' . $e->getMessage(), ['exception' => $e]);
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
}
