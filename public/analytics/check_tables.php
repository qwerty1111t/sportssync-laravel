<?php
require_once '../db.php';
try {
    $tables = ['universal_players', 'badminton_matches', 'table_tennis_matches', 'darts_matches', 'matches', 'volleyball_matches', 'player_team_history', 'player_profiles'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "$table exists\n";
        } else {
            echo "$table does not exist\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>