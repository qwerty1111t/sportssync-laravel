<?php
// Legacy db_config — skip when wrapped by Laravel
if (defined('LARAVEL_WRAPPER')) {
    return;
}

// db_config.php — support both local dev and Railway production
// Try to load .env file if available
$envFile = realpath(__DIR__ . '/../../.env');
if ($envFile && file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

// Read from environment variables; fallback to localhost defaults for local dev
define('DB_HOST', $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_USER', $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'sportssync');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('[db_config.php] Connection failed to ' . DB_HOST . ': ' . $conn->connect_error);
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");
?>