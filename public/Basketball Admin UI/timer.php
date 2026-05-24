<?php
// ================================================================
// timer.php — Basketball authoritative timer state + direct WS emit
// ================================================================
// GET  ?match_id=N  → return latest authoritative timer state
// POST JSON body     → persist final timer state and emit directly to WS
//
// This is the ONLY backend endpoint that owns timer state.
// It never emits intermediate reset values. Reset always emits 600/24.
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
  CREATE TABLE IF NOT EXISTS basketball_timer_state (
    match_id   VARCHAR(64) NOT NULL PRIMARY KEY,
    state_json MEDIUMTEXT  NOT NULL,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

function now_ms() {
    return (int) round(microtime(true) * 1000);
}

function default_timer_payload($now = null) {
    $now = $now ?: now_ms();
    return [
        'game_time'      => 600,
        'shot_clock'     => 24,
        'is_running'     => false,
        'last_update_at' => $now,
        'gameTimer'      => ['total' => 600, 'remaining' => 600, 'running' => false, 'ts' => null],
        'shotClock'      => ['total' => 24,  'remaining' => 24,  'running' => false, 'ts' => null],
    ];
}

function live_timer_payload($payload) {
    $now = now_ms();
    $payload = is_array($payload) ? array_replace_recursive(default_timer_payload($now), $payload) : default_timer_payload($now);

    foreach ([['gameTimer', 600, 'game_time'], ['shotClock', 24, 'shot_clock']] as $def) {
        [$key, $fallbackTotal, $flatKey] = $def;
        $timer = isset($payload[$key]) && is_array($payload[$key]) ? $payload[$key] : [];
        $total = isset($timer['total']) ? (float)$timer['total'] : (float)$fallbackTotal;
        $remaining = isset($timer['remaining']) ? (float)$timer['remaining'] : (float)$fallbackTotal;
        $running = !empty($timer['running']);
        $ts = isset($timer['ts']) && is_numeric($timer['ts']) ? (int)$timer['ts'] : null;

        if ($running && $ts !== null && $ts > 0) {
            $remaining = max(0, $remaining - (($now - $ts) / 1000));
            // Re-anchor at now so reconnecting clients start from an exact current value.
            $ts = $now;
        }

        $payload[$key] = [
            'total'     => $total,
            'remaining' => max(0, min($remaining, $total > 0 ? $total : $fallbackTotal)),
            'running'   => $running && $remaining > 0,
            'ts'        => ($running && $remaining > 0) ? $ts : null,
        ];
        $payload[$flatKey] = (int) round($payload[$key]['remaining']);
    }

    $payload['is_running'] = !empty($payload['gameTimer']['running']) || !empty($payload['shotClock']['running']);
    $payload['last_update_at'] = $now;
    return $payload;
}

function normalize_timer_post($data) {
    $now = isset($data['last_update_at']) && is_numeric($data['last_update_at']) ? (int)$data['last_update_at'] : now_ms();
    $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
    $control = isset($meta['control']) ? (string)$meta['control'] : '';
    $timerTarget = isset($meta['timer']) ? (string)$meta['timer'] : '';

    $payload = default_timer_payload($now);

    $gIn = isset($data['gameTimer']) && is_array($data['gameTimer']) ? $data['gameTimer'] : [];
    $sIn = isset($data['shotClock']) && is_array($data['shotClock']) ? $data['shotClock'] : [];

    $gTotal = isset($gIn['total']) ? (float)$gIn['total'] : 600;
    $sTotal = isset($sIn['total']) ? (float)$sIn['total'] : 24;
    $gRem = isset($gIn['remaining']) ? (float)$gIn['remaining'] : (isset($data['game_time']) ? (float)$data['game_time'] : 600);
    $sRem = isset($sIn['remaining']) ? (float)$sIn['remaining'] : (isset($data['shot_clock']) ? (float)$data['shot_clock'] : 24);
    $gRunning = isset($gIn['running']) ? (bool)$gIn['running'] : false;
    $sRunning = isset($sIn['running']) ? (bool)$sIn['running'] : false;

    // Critical reset fix: never persist or emit 00:00 / 0 during reset.
    if ($control === 'reset') {
        if ($timerTarget === 'game' || $timerTarget === '') {
            $gTotal = 600; $gRem = 600; $gRunning = false;
            // In this app, game reset also resets the shot clock.
            $sTotal = 24; $sRem = 24; $sRunning = false;
        }
        if ($timerTarget === 'shot') {
            // Respect selected shot-clock preset, either 24 or 14.
            // Do not force shot reset back to 24.
            $sTotal = $sTotal > 0 ? $sTotal : 24;
            $sRem = $sTotal;
            $sRunning = false;
        }
    }

    $payload['gameTimer'] = [
        'total'     => $gTotal,
        'remaining' => max(0, min($gRem, $gTotal > 0 ? $gTotal : 600)),
        'running'   => $gRunning,
        'ts'        => $gRunning ? $now : null,
    ];
    $payload['shotClock'] = [
        'total'     => $sTotal,
        'remaining' => max(0, min($sRem, $sTotal > 0 ? $sTotal : 24)),
        'running'   => $sRunning,
        'ts'        => $sRunning ? $now : null,
    ];
    $payload['game_time'] = (int) round($payload['gameTimer']['remaining']);
    $payload['shot_clock'] = (int) round($payload['shotClock']['remaining']);
    $payload['is_running'] = $payload['gameTimer']['running'] || $payload['shotClock']['running'];
    $payload['last_update_at'] = $now;
    $payload['meta'] = $meta;

    return $payload;
}

function emit_ws_timer($match_id, $payload) {
    try {
        $wsRelay = getenv('WS_RELAY_URL') ?: 'http://127.0.0.1:3000/emit';
        $wsToken = getenv('WS_TOKEN') ?: null;
        $emitObj = [
            'type'           => 'timer_update',
            'sport'          => 'basketball',
            'match_id'       => (string)$match_id,
            'game_time'      => $payload['game_time'],
            'shot_clock'     => $payload['shot_clock'],
            'is_running'     => $payload['is_running'],
            'last_update_at' => $payload['last_update_at'],
            'gameTimer'      => $payload['gameTimer'],
            'shotClock'      => $payload['shotClock'],
            'payload'        => $payload,
            'ts'             => $payload['last_update_at'],
            'meta'           => isset($payload['meta']) ? $payload['meta'] : ['source' => 'timer.php']
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
    if ($match_id === '') {
        echo json_encode(['success' => true, 'payload' => default_timer_payload()]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT state_json FROM basketball_timer_state WHERE match_id = ?");
    $stmt->bind_param('s', $match_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $payload = $row ? json_decode($row['state_json'], true) : default_timer_payload();
    $payload = live_timer_payload($payload);
    echo json_encode(['success' => true, 'match_id' => $match_id, 'payload' => $payload]);
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

    $match_id = isset($data['match_id']) && $data['match_id'] !== '' ? (string)$data['match_id'] : 'live';
    $payload = normalize_timer_post($data);
    $json = json_encode($payload);

    $stmt = $mysqli->prepare("
        INSERT INTO basketball_timer_state (match_id, state_json)
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

    emit_ws_timer($match_id, $payload);
    echo json_encode(['success' => true, 'match_id' => $match_id, 'payload' => $payload]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
