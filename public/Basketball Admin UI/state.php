<?php
// ================================================================
// state.php — Basketball roster/stat/committee authoritative cache
// ================================================================
// GET  ?match_id=N   → return last known roster/stat state for match
// POST JSON body      → persist roster/stat state and emit to WS relay
//
// IMPORTANT TIMER RULE:
// This file does NOT own timers. Timer state belongs to timer.php.
// This file only syncs teams, scores, player stats, fouls, timeouts,
// quarter, committee, and reset roster payloads.
// ================================================================

ob_start();
require_once 'db_config.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

function json_output(array $data, int $code = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $code = 500, string $details = ''): void {
    $payload = ['success' => false, 'message' => $message];
    if ($details !== '') {
        $payload['details'] = $details;
    }
    json_output($payload, $code);
}

register_shutdown_function(function() {
    $buffer = ob_get_clean();
    if ($buffer === null) return;
    $trimmed = trim($buffer);
    if ($trimmed === '') return;
    if (json_decode($trimmed, true) !== null && json_last_error() === JSON_ERROR_NONE) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $trimmed;
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    $clean = trim(strip_tags($trimmed));
    if ($clean === '') {
        $clean = 'Internal server error';
    }
    echo json_encode(['success' => false, 'message' => 'Server error', 'details' => substr($clean, 0, 1000)]);
});

/** @var mysqli $mysqli */

$mysqli->query("
  CREATE TABLE IF NOT EXISTS basketball_match_state (
    match_id   VARCHAR(64) NOT NULL PRIMARY KEY,
    state_json MEDIUMTEXT  NOT NULL,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

function normalize_basketball_state_payload($data) {
    // Admin usually sends: { match_id, payload: {...}, meta: {...} }
    // Store and emit only the actual state payload so viewers can ingest it directly.
    $payload = (isset($data['payload']) && is_array($data['payload'])) ? $data['payload'] : $data;

    // Never let this endpoint overwrite timer authority.
    unset($payload['gameTimer'], $payload['shotClock'], $payload['game_timer'], $payload['shot_clock']);
    unset($payload['game_time'], $payload['shot_clock'], $payload['is_running'], $payload['last_update_at']);

    return $payload;
}

function emit_ws_state($match_id, $payload, $meta = null) {
    try {
        $wsRelay = getenv('WS_RELAY_URL') ?: 'http://127.0.0.1:3000/emit';
        $wsToken = getenv('WS_TOKEN') ?: null;
        $emitObj = [
            'type'     => 'basketball_state',
            'sport'    => 'basketball',
            'match_id' => (string)$match_id,
            'payload'  => $payload,
            'ts'       => (int) round(microtime(true) * 1000),
            'meta'     => is_array($meta) ? $meta : ['source' => 'state.php']
        ];
        $json = json_encode($emitObj);
        $ch = curl_init($wsRelay);
        $headers = ['Content-Type: application/json'];
        if ($wsToken) $headers[] = 'X-WS-Token: ' . $wsToken;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 200);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 700);
        @curl_exec($ch);
        @curl_close($ch);
    } catch (Throwable $_) {}
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $match_id = isset($_GET['match_id']) ? trim($_GET['match_id']) : '';
    $latest   = isset($_GET['latest']) && $_GET['latest'] === '1';

    if ($match_id === '' || $latest) {
        $result = $mysqli->query("SELECT match_id, state_json, updated_at FROM basketball_match_state WHERE state_json NOT LIKE '%\"_reset\":true%' ORDER BY updated_at DESC LIMIT 1");
        $row = $result ? $result->fetch_assoc() : null;
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No state found', 'payload' => null, 'state' => null]);
            exit;
        }
        $payload = json_decode($row['state_json'], true);
        echo json_encode(['success' => true, 'match_id' => $row['match_id'], 'payload' => $payload, 'state' => $payload, 'updated_at' => $row['updated_at']]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT state_json, updated_at FROM basketball_match_state WHERE match_id = ?");
    $stmt->bind_param('s', $match_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No state found', 'payload' => null, 'state' => null]);
        exit;
    }

    $payload = json_decode($row['state_json'], true);
    echo json_encode(['success' => true, 'match_id' => $match_id, 'payload' => $payload, 'state' => $payload, 'updated_at' => $row['updated_at']]);
    exit;
}

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $poster = null;
    try { $poster = currentUser(); } catch (Throwable $_) { $poster = null; }
    $allowed = ['admin', 'scorekeeper', 'superadmin'];
    if (!$poster || !in_array($poster['role'] ?? '', $allowed, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $match_id = isset($data['match_id']) && $data['match_id'] !== '' && $data['match_id'] !== null
        ? (string)$data['match_id']
        : 'live';

    $payload = normalize_basketball_state_payload($data);
    $json = json_encode($payload);

    $stmt = $mysqli->prepare("
        INSERT INTO basketball_match_state (match_id, state_json)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('ss', $match_id, $json);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    emit_ws_state($match_id, $payload, $data['meta'] ?? ['source' => 'state.php']);

    echo json_encode(['success' => true, 'match_id' => $match_id, 'payload' => $payload]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
