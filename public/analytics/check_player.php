<?php
require_once '../db.php';
try {
    $stmt = $pdo->prepare("SELECT * FROM universal_players WHERE id = ?");
    $stmt->execute([656]);
    $player = $stmt->fetch();
    if ($player) {
        echo "Player found: " . json_encode($player) . "\n";
    } else {
        echo "Player not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>