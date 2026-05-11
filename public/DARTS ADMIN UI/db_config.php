<?php
// Legacy db_config — skip when wrapped by Laravel
if (defined('LARAVEL_WRAPPER')) {
    return;
}

// db_config.php — support both local dev and Railway production
// Railway passes DB credentials via environment variables

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
// Try multiple sources: $_ENV, $_SERVER, getenv()
$dbHost     = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbUsername = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
$dbPassword = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$dbName     = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'sportssync';

// Log the connection attempt (for debugging)
error_log('[db_config.php] Attempting connection to ' . $dbHost . ':3306 user=' . $dbUsername . ' db=' . $dbName);

$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    error_log('[db_config.php] FAILED: ' . $conn->connect_error);
    http_response_code(500);
    die(json_encode([
        "success" => false, 
        "message" => "Database connection failed",
        "error" => substr($conn->connect_error, 0, 100)
    ]));
}

$conn->set_charset("utf8mb4");
error_log('[db_config.php] Connected successfully to ' . $dbHost . ':' . $dbName);
?>