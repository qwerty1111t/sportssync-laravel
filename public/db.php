<?php
// Compatibility shim for moved DB connection.
// Uses Laravel's DB connection if available (when included via Laravel route).
// Otherwise creates standalone PDO connection using env vars or config.

if (php_sapi_name() !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

// If Laravel's DB connection is available in $GLOBALS, use it
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
    $pdo = $GLOBALS['pdo'];
    return;
}

// If we're in Laravel context (config() function exists), use Laravel's connection
if (function_exists('config') && function_exists('app') && app()->bound('db')) {
    try {
        $pdo = \DB::connection()->getPdo();
        $GLOBALS['pdo'] = $pdo;
        return;
    } catch (\Throwable $e) {
        // Fall through to legacy connection
    }
}

// Fall back to legacy standalone connection
require_once __DIR__ . '/../app/Legacy/db.php';