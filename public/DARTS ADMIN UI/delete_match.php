<?php
// delete_match.php — Delete darts match(es) with cascade delete
// Expects POST JSON with either:
// { "match_id": N } or { "match_ids": [N, M, ...] }

header('Content-Type: application/json');
require_once 'db_config.php';
require_once __DIR__ . '/../auth.php';

// Suppress accidental output
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
ob_start();

// Auth check
session_start();
try {
    $user = currentUser();
    $allowed = ['admin', 'superadmin'];
    if (!$user || !in_array($user['role'] ?? '', $allowed, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
} catch (Throwable $_) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication failed']);
    exit;
}

// Ensure DB connection
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request']);
    exit;
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Handle both single and multiple match IDs
$matchIds = [];

if (isset($body['match_id'])) {
    $matchIds[] = (int)$body['match_id'];
}

if (isset($body['match_ids']) && is_array($body['match_ids'])) {
    foreach ($body['match_ids'] as $id) {
        $matchIds[] = (int)$id;
    }
}

// Remove duplicates and filter invalid IDs
$matchIds = array_unique(array_filter($matchIds, fn($id) => $id > 0));

if (empty($matchIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid match IDs provided']);
    exit;
}

try {
    // Detect darts_ prefix
    $prefix = '';
    $r = $conn->query("SHOW TABLES LIKE 'darts_matches'");
    if ($r && $r->num_rows) {
        $prefix = 'darts_';
    }
    
    $matchesTable = $prefix . 'matches';
    $playersTable = $prefix . 'players';
    $legsTable = $prefix . 'legs';
    $throwsTable = $prefix . 'throws';
    $summaryTable = $prefix . 'match_summary';

    // Delete in order (respect FK constraints)
    // 1. Delete throws
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    
    // Get all leg IDs first
    $legSql = "SELECT id FROM `{$legsTable}` WHERE match_id IN ($placeholders)";
    $legStmt = $conn->prepare($legSql);
    $legStmt->bind_param(str_repeat('i', count($matchIds)), ...$matchIds);
    $legStmt->execute();
    $legRes = $legStmt->get_result();
    $legIds = [];
    while ($row = $legRes->fetch_assoc()) {
        $legIds[] = $row['id'];
    }
    $legStmt->close();
    
    // Delete throws for these legs
    if (!empty($legIds)) {
        $legPlaceholders = implode(',', array_fill(0, count($legIds), '?'));
        $throwSql = "DELETE FROM `{$throwsTable}` WHERE leg_id IN ($legPlaceholders)";
        $throwStmt = $conn->prepare($throwSql);
        $throwStmt->bind_param(str_repeat('i', count($legIds)), ...$legIds);
        $throwStmt->execute();
        $throwStmt->close();
    }
    
    // 2. Delete legs
    $delLegSql = "DELETE FROM `{$legsTable}` WHERE match_id IN ($placeholders)";
    $delLegStmt = $conn->prepare($delLegSql);
    $delLegStmt->bind_param(str_repeat('i', count($matchIds)), ...$matchIds);
    $delLegStmt->execute();
    $delLegStmt->close();
    
    // 3. Delete players
    $delPlayerSql = "DELETE FROM `{$playersTable}` WHERE match_id IN ($placeholders)";
    $delPlayerStmt = $conn->prepare($delPlayerSql);
    $delPlayerStmt->bind_param(str_repeat('i', count($matchIds)), ...$matchIds);
    $delPlayerStmt->execute();
    $delPlayerStmt->close();
    
    // 4. Delete summary
    $delSummarySql = "DELETE FROM `{$summaryTable}` WHERE match_id IN ($placeholders)";
    $delSummaryStmt = $conn->prepare($delSummarySql);
    $delSummaryStmt->bind_param(str_repeat('i', count($matchIds)), ...$matchIds);
    $delSummaryStmt->execute();
    $delSummaryStmt->close();
    
    // 5. Delete matches
    $delMatchSql = "DELETE FROM `{$matchesTable}` WHERE id IN ($placeholders)";
    $delMatchStmt = $conn->prepare($delMatchSql);
    $delMatchStmt->bind_param(str_repeat('i', count($matchIds)), ...$matchIds);
    $delMatchStmt->execute();
    $delCount = $delMatchStmt->affected_rows;
    $delMatchStmt->close();
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Matches deleted successfully',
        'deleted_count' => $delCount
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>

