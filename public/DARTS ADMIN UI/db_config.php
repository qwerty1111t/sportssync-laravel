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

// Log connection attempt for debugging
error_log('[db_config.php] Attempting connection: host=' . $db_host . ' user=' . $db_user . ' db=' . $db_name . ' (password=' . (strlen($db_pass) > 0 ? 'SET' : 'EMPTY') . ')');

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log('[db_config.php] Connection failed: ' . $conn->connect_error);
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
error_log('[db_config.php] Connection successful to ' . $db_name . '@' . $db_host);
$conn->set_charset("utf8mb4");
?>