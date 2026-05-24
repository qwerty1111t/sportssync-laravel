<?php
// ================================================================
// state.php — Badminton live match state cache
// ================================================================
// GET  ?match_id=N   → return last known state for that match
// POST (JSON body)   → upsert state for a match_id
//
// This sits between localStorage (instant, same-device) and the
// full save_set.php (end-of-set). The admin debounce-posts here
// on every score change; viewers fetch on page load so they can
// restore state even on a different device or after a refresh.
// ================================================================

require_once __DIR__ . '/db.php';
// Auth helpers: only required for POST operations
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

/** @var PDO $pdo */

// ── Ensure the state table exists (auto-create once) ────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS badminton_match_state (
            match_id   VARCHAR(64) NOT NULL PRIMARY KEY,
            state_json MEDIUMTEXT  NOT NULL,
            updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $_) { /* table may already exist */ }

// ── Route by HTTP method ─────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $match_id = isset($_GET['match_id']) ? trim($_GET['match_id']) : '';
    $latest   = isset($_GET['latest'])   && $_GET['latest'] === '1';

    if ($match_id === '' || $latest) {
        // No match_id or ?latest=1: return the most recently updated non-reset state row
        try {
            $stmt = $pdo->prepare("SELECT state_json, updated_at FROM badminton_match_state WHERE state_json NOT LIKE ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute(['%"_reset":true%']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'No state found', 'state' => null]);
                exit;
            }
            echo json_encode(['success' => true, 'state' => json_decode($row['state_json'], true), 'updated_at' => $row['updated_at']]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error', 'state' => null]);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT state_json, updated_at FROM badminton_match_state WHERE match_id = :match_id");
        $stmt->execute([':match_id' => $match_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No state found', 'state' => null]);
            exit;
        }

        echo json_encode([
            'success'    => true,
            'state'      => json_decode($row['state_json'], true),
            'updated_at' => $row['updated_at']
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    // Only authenticated admins may publish live state (legacy 'scorekeeper' is mapped to 'admin')
    $poster = null;
    try { $poster = currentUser(); } catch (Throwable $_) { $poster = null; }
    $allowed = ['admin', 'scorekeeper', 'superadmin'];
    if (!$poster || !in_array($poster['role'] ?? '', $allowed, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    // Use match_id if present, otherwise use 'live' as sentinel key
    $match_id = isset($data['match_id']) && $data['match_id'] !== '' && $data['match_id'] !== null
        ? (string)$data['match_id']
        : 'live';

    $json = json_encode($data);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO badminton_match_state (match_id, state_json)
            VALUES (:match_id, :state_json)
            ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([':match_id' => $match_id, ':state_json' => $json]);

        // Best-effort: notify WS relay so connected clients receive this update immediately
        try {
            $wsRelay = getenv('WS_RELAY_URL') ?: 'http://127.0.0.1:3000/emit';
            $wsToken = getenv('WS_TOKEN') ?: null;
            $emitObj = ['type' => 'badminton_state', 'match_id' => $match_id, 'payload' => $data, 'sport' => 'badminton'];
            $payload = json_encode($emitObj);
            $ch = curl_init($wsRelay);
            $headers = ['Content-Type: application/json'];
            if ($wsToken) $headers[] = 'X-WS-Token: ' . $wsToken;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 200);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
            @curl_exec($ch);
            @curl_close($ch);
        } catch (Throwable $_) { /* non-fatal */ }

        echo json_encode(['success' => true, 'match_id' => $match_id]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
