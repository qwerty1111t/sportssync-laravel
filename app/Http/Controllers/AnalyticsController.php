<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function analytics()
    {
        // Include the legacy analytics.php file
        $legacyFile = public_path('analytics/analytics.php');
        if (!file_exists($legacyFile) || !is_file($legacyFile)) {
            abort(404);
        }

        if (!defined('LARAVEL_WRAPPER')) {
            define('LARAVEL_WRAPPER', true);
        }

        chdir(public_path());
        ob_start();
        include $legacyFile;
        $content = ob_get_clean();

        return response($content, 200)->header('Content-Type', 'text/html');
    }

    public function players()
    {
        // Include the legacy players.php file
        $legacyFile = public_path('analytics/players.php');
        if (!file_exists($legacyFile) || !is_file($legacyFile)) {
            abort(404);
        }

        if (!defined('LARAVEL_WRAPPER')) {
            define('LARAVEL_WRAPPER', true);
        }

        chdir(public_path());
        ob_start();
        include $legacyFile;
        $content = ob_get_clean();

        return response($content, 200)->header('Content-Type', 'text/html');
    }
}