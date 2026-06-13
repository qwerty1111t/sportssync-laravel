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

// db_config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sportssync');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");
?>