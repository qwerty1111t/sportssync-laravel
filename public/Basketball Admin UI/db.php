<?php
// ============================================================
// db.php — PDO database connection
// When included from Laravel wrappers, controllers provide $pdo.
//
// Credentials priority:
//   1. Laravel config('database.connections.mysql.*') if available
//   2. Environment variables (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
//   3. Hardcoded localhost fallback for local XAMPP development
// ============================================================
if (defined('LARAVEL_WRAPPER')) {
    return;
}

// Try to read from Laravel config first (when included within Laravel request lifecycle)
if (function_exists('config')) {
    $host   = config('database.connections.mysql.host', 'localhost');
    $port   = config('database.connections.mysql.port', 3306);
    $dbname = config('database.connections.mysql.database', 'sportssync');
    $user   = config('database.connections.mysql.username', 'root');
    $pass   = config('database.connections.mysql.password', '');
} else {
    // Fallback to environment variables
    $host   = getenv('DB_HOST') ?: 'localhost';
    $port   = getenv('DB_PORT') ?: 3306;
    $dbname = getenv('DB_DATABASE') ?: 'sportssync';
    $user   = getenv('DB_USERNAME') ?: 'root';
    $pass   = getenv('DB_PASSWORD') ?: '';
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('[Basketball UI DB] PDO connection failed: ' . $e->getMessage()
        . ' | DSN: ' . preg_replace('/password=[^;]+/', 'password=***', $dsn));
    $pdo = null;
}