<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BasketballViewerController extends Controller
{
    use LegacyWrapperTrait;

    public function show(Request $request)
    {
        $legacyPath = public_path('Basketball Admin UI/basketball_viewer.php');
        if (!file_exists($legacyPath)) return response('Legacy viewer missing', 500);
        if (!defined('LARAVEL_WRAPPER')) define('LARAVEL_WRAPPER', true);
        $cfg = config('db_basketball');
        try {
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
            $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Basketball viewer DB connect: ' . $e->getMessage());
            return response('Database connection failed', 500);
        }
        ob_start(); include $legacyPath; $html = ob_get_clean();
        $html = str_replace('basketball_viewer.css', '/basketball-admin/basketball_viewer.css', $html);
        $html = str_replace('basketball_viewer.js', '/basketball-admin/basketball_viewer.js', $html);
        $this->injectLegacyBasePath('basketball-admin', $html);
        return view('basketball.viewer', ['legacy_html' => $html]);
    }
}
