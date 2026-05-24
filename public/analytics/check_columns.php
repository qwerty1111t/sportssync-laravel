<?php
require_once '../db.php';
try {
    $stmt = $pdo->query("DESCRIBE badminton_matches");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "badminton_matches columns: " . implode(', ', $columns) . "\n";

    $stmt = $pdo->query("DESCRIBE table_tennis_matches");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "table_tennis_matches columns: " . implode(', ', $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>