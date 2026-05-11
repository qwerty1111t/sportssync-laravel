<?php
// Legacy db_config — skip when wrapped by Laravel
if (defined('LARAVEL_WRAPPER')) {
    return;
}

// db_config.php — Load from environment variables (Railway) or use local defaults
$db_host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
$db_user = getenv('DB_USERNAME') ?: getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('DB_DATABASE') ?: getenv('MYSQLDATABASE') ?: 'sportssync';

error_log('[tabletennis db_config] Attempting connection: host=' . $db_host . ' user=' . $db_user);

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    @file_put_contents(__DIR__ . '/tabletennis_debug.log', date('[Y-m-d H:i:s] ') . "DB connect error: " . $mysqli->connect_error . "\n", FILE_APPEND);
    error_log('[tabletennis db_config] Connection failed: ' . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
error_log('[tabletennis db_config] Connection successful');
$mysqli->set_charset('utf8mb4');
