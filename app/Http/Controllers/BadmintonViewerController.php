<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BadmintonViewerController extends Controller
{
    use LegacyWrapperTrait;

    public function show(Request $request)
    {
        $legacyPath = public_path('Badminton Admin UI/badminton_viewer.php');
        if (!file_exists($legacyPath)) {
            return response('Legacy viewer file missing', 500);
        }

        if (!defined('LARAVEL_WRAPPER')) define('LARAVEL_WRAPPER', true);
        $cfg = config('db_badminton');
        $mysqli = @new \mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
        if ($mysqli->connect_errno) {
            \Illuminate\Support\Facades\Log::error('Badminton viewer DB connect: ' . $mysqli->connect_error);
            return response('Database connection failed', 500);
        }
        $mysqli->set_charset($cfg['charset'] ?? 'utf8mb4');
        ob_start();
        include $legacyPath; // outputs the viewer HTML
        $html = ob_get_clean();

        // Remove CSS/JS links from legacy HTML (Blade view loads them in proper <head> location)
        $html = str_replace('<link rel="stylesheet" href="badminton_viewer.css">', '', $html);
        $html = str_replace('<script src="badminton_viewer.js"></script>', '', $html);
        $this->injectLegacyBasePath('badminton-admin', $html);

        return view('badminton.viewer', ['legacy_html' => $html]);
    }
}
