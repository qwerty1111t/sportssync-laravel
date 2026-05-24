<?php
require_once '../db.php';
$n = 'Abiera';
$t = 'Cyan Bball';
try {
    $s = $pdo->prepare("SELECT vp.*, IF(vp.team='A',vm.team_a_name,vm.team_b_name) AS actual_team_name,
                vm.team_a_name, vm.team_b_name, vm.match_result, vm.created_at
                FROM volleyball_players vp JOIN volleyball_matches vm ON vp.match_id=vm.match_id
                WHERE vp.player_name=:n AND IF(vp.team='A',vm.team_a_name,vm.team_b_name)=:t
                ORDER BY vm.created_at ASC");
    $s->execute([':n'=>$n,':t'=>$t]);
    $rows = $s->fetchAll();
    echo "Volleyball Rows: " . count($rows) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>