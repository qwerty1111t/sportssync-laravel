<?php
// Legacy DB config for Badminton Admin UI
// This file creates a $mysqli global MySQLi connection for all legacy PHP files.
// When included from LegacyProxyController (LARAVEL_WRAPPER mode), the controller
// does NOT provide its own $mysqli — so we must always connect here.

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

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if ($mysqli->connect_errno) {
    @file_put_contents(__DIR__ . '/badminton_debug.log', date('[Y-m-d H:i:s] ') . "DB connect error: " . $mysqli->connect_error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');
