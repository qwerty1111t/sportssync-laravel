<?php
// state.php - Table Tennis Match State Endpoint
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

/** @var PDO $pdo */

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS table_tennis_match_state (
            match_id   VARCHAR(64) NOT NULL PRIMARY KEY,
            state_json MEDIUMTEXT  NOT NULL,
            updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $_) { /* table may already exist */ }

$method = $_SERVER['REQUEST_METHOD'];
$match_id = $_GET['match_id'] ?? null;
$data = null;

// Try to get JSON body
$raw = $GLOBALS['__LEGACY_INPUT_JSON'] ?? file_get_contents('php://input');
if ($raw) {
    $data = json_decode($raw, true);
}

if ($method === 'GET' || !$data) {
    // Viewer read: no auth required
    try {
        if ($match_id) {
            $stmt = $pdo->prepare("SELECT state_json FROM table_tennis_match_state WHERE match_id = :match_id LIMIT 1");
            $stmt->execute([':match_id' => $match_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo $row ? $row['state_json'] : '{}';
        } else {
            // Latest state
            $stmt = $pdo->prepare("SELECT state_json FROM table_tennis_match_state ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo $row ? $row['state_json'] : '{}';
        }
    } catch (Exception $e) {
        echo '{}';
    }
    exit;
}

// POST - admin write: require auth
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

try {
    $poster = currentUser();
} catch (Throwable $_) {
    $poster = null;
}
$allowed = ['admin', 'scorekeeper', 'superadmin'];
if (!$poster || !in_array($poster['role'] ?? '', $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$state_json = json_encode($data);
try {
    $stmt = $pdo->prepare("
        INSERT INTO table_tennis_match_state (match_id, state_json)
        VALUES (:match_id, :state_json)
        ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':match_id' => $match_id, ':state_json' => $state_json]);
    
    // Notify WS relay so connected clients receive this update immediately
    try {
        $wsRelay = getenv('WS_RELAY_URL') ?: 'http://127.0.0.1:3000/emit';
        $wsToken = getenv('WS_TOKEN') ?: null;
        $emit = json_encode([
            'type' => 'tabletennis_state',
            'match_id' => $match_id,
            'payload' => $data,
            'sport' => 'tabletennis'
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($wsRelay);
        $headers = ['Content-Type: application/json'];
        if ($wsToken) $headers[] = 'X-WS-Token: ' . $wsToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $emit);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 200);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        @curl_exec($ch);
        @curl_close($ch);
    } catch (Throwable $_) { /* non-fatal */ }
    
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>

