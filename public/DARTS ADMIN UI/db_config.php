<?php
// Legacy db_config — skip when wrapped by Laravel
if (defined('LARAVEL_WRAPPER')) {
    return;
}

// Check if mysqli extension is loaded
if (!class_exists('mysqli')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PHP mysqli extension is not installed']);
    exit;
}

// Database configuration - reads from environment (Railway) or Laravel config
// Priority: Laravel config → env vars → localhost fallback
if (function_exists('config')) {
    $dbHost = config('database.connections.mysql.host', 'localhost');
    $dbPort = config('database.connections.mysql.port', 3306);
    $dbName = config('database.connections.mysql.database', 'sportssync');
    $dbUser = config('database.connections.mysql.username', 'root');
    $dbPass = config('database.connections.mysql.password', '');
} else {
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbPort = getenv('DB_PORT') ?: 3306;
    $dbName = getenv('DB_DATABASE') ?: 'sportssync';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
}

define('DB_HOST', $dbHost);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);
define('DB_PORT', $dbPort);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");
?>