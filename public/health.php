<?php
// Simple health check endpoint for diagnostics
header('Content-Type: application/json');

$data = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'laravel_path' => __DIR__ . '/..',
    'cwd' => getcwd(),
    'laravel_exists' => file_exists(__DIR__ . '/../bootstrap/app.php'),
    'config_cache' => file_exists(__DIR__ . '/../bootstrap/cache/config.php'),
    'env_file' => file_exists(__DIR__ . '/../.env'),
    'storage_writable' => is_writable(__DIR__ . '/../storage'),
    'bootstrap_cache_writable' => is_writable(__DIR__ . '/../bootstrap/cache'),
];

try {
    // Try to bootstrap Laravel
    if (file_exists(__DIR__ . '/../bootstrap/app.php')) {
        require_once __DIR__ . '/../bootstrap/app.php';
        $data['laravel_bootstrap'] = 'success';
    } else {
        $data['laravel_bootstrap'] = 'bootstrap file not found';
    }
} catch (Exception $e) {
    $data['laravel_bootstrap'] = 'error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($data, JSON_PRETTY_PRINT);
?>
