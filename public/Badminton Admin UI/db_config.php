<?php
// Legacy DB config. When included from Laravel wrapper we skip creating globals
if (defined('LARAVEL_WRAPPER')) {
    // Laravel controller provides $mysqli when needed — do nothing here.
    return;
}

// Database configuration - load from environment (Railway) or use local defaults
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USERNAME') ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_DATABASE') ?: 'sportssync';

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    @file_put_contents(__DIR__ . '/badminton_debug.log', date('[Y-m-d H:i:s] ') . "DB connect error: " . $mysqli->connect_error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');
