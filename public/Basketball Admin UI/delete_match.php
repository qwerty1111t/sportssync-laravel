<?php
// delete_match.php
// Accepts JSON POST { match_id: N } or { match_ids: [N, M, ...] }
// Deletes match and match_players rows.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

// Check authentication
try {
    $user = requireRole('admin');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Ensure DB connection available
if (!isset($pdo) || !$pdo) {
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
    $pdo->beginTransaction();
    
    // Build placeholders for SQL IN clause
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));

    // Delete players first (foreign key)
    $st1 = $pdo->prepare("DELETE FROM `match_players` WHERE match_id IN ($placeholders)");
    $st1->execute($matchIds);

    // Delete match rows
    $st2 = $pdo->prepare("DELETE FROM `matches` WHERE match_id IN ($placeholders)");
    $st2->execute($matchIds);

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Matches deleted successfully',
        'deleted_count' => count($matchIds)
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
