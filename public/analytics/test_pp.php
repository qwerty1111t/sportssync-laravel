<?php
require_once '../db.php';
$uid = 656;
try {
    $thS = $pdo->prepare("SELECT sport, side, actual_team_name AS team_name, games_played, first_game, last_game, is_current FROM player_team_history WHERE player_universal_id=:uid ORDER BY sport, last_game ASC");
    $thS->execute([':uid'=>$uid]);
    $teamHistory = $thS->fetchAll();
    echo "Team history rows: " . count($teamHistory) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>