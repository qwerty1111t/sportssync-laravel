<?php
// Legacy DB config. When included from Laravel wrapper we skip creating globals
if (defined('LARAVEL_WRAPPER')) {
    // Laravel controller provides $mysqli when needed — do nothing here.
    return;
}

// Try to load Laravel's .env if available (for local dev)
$envFile = realpath(__DIR__ . '/../../.env');
if ($envFile && file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim(trim($val), '\'"');
            if (!getenv($key)) putenv("$key=$val");
        }
    }
}

// Railway provides DB credentials via environment variables
$dbHost     = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbUsername = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
$dbPassword = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$dbName     = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'sportssync';

error_log('[badminton db_config] Attempting connection to ' . $dbHost . ' user=' . $dbUsername);

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
if ($mysqli->connect_errno) {
    @file_put_contents(__DIR__ . '/badminton_debug.log', date('[Y-m-d H:i:s] ') . "DB connect error to " . $dbHost . ": " . $mysqli->connect_error . "\n", FILE_APPEND);
    error_log('[badminton db_config] Connection failed: ' . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed', 'error' => substr($mysqli->connect_error, 0, 100)]);
    exit;
}
$mysqli->set_charset('utf8mb4');
