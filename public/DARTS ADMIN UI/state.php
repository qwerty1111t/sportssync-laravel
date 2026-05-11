<?php
/**
 * state.php
 * ============================================================
 * Darts Iskorsit — Live State Endpoint
 * ============================================================
 * GET  state.php?match_id=N   → returns { state: {...} }
 *      Returns the latest live_state stored on the match row,
 *      or the pending state file for match_id=0 (pre-match).
 *
 * POST state.php (body: { match_id: N, state: {...} })
 *      Saves the live_state JSON onto the match row (if match exists)
 *      AND writes to the darts_pending_state.json fallback file.
 * ============================================================
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

function getEnvValue(string $key, $default = null) {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

function sendJsonError(string $message, int $code = 400, string $error = null) {
    http_response_code($code);
    $payload = ['success' => false, 'message' => $message];
    if ($error) $payload['error'] = $error;
    echo json_encode($payload);
    exit;
}

function parseMatchId($value) {
    if ($value === null || $value === '') {
        return 0;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $match_id = intval($value);
    if ($match_id < 0) {
        return null;
    }
    return $match_id;
}

$pendingFile = __DIR__ . '/darts_pending_state.json';
$envFile = realpath(__DIR__ . '/../../.env');
if ($envFile && file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        $val = trim($val, "'\"");
        if (getenv($key) === false) {
            putenv("$key=$val");
        }
    }
}

try {
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/../auth.php';
} catch (Throwable $e) {
    error_log('[state.php] Init error: ' . $e->getMessage());
    sendJsonError('Initialization error', 500, $e->getMessage());
}

if (!isset($conn) || !$conn) {
    error_log('[state.php] Database connection unavailable');
    sendJsonError('Database connection unavailable', 500);
}

$prefix = '';
try {
    $r = $conn->query("SHOW TABLES LIKE 'darts_matches'");
    if ($r && $r->num_rows) $prefix = 'darts_';
} catch (Throwable $e) {
    error_log('[state.php] Error detecting table prefix: ' . $e->getMessage());
}
$matchesTable = $prefix . 'matches';

/* ============================================================
   GET — return current state
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $matchIdInput = $_GET['match_id'] ?? null;
        $match_id = parseMatchId($matchIdInput);
        if ($matchIdInput !== null && $match_id === null) {
            error_log('[state.php GET] Invalid match_id: ' . json_encode($matchIdInput));
            sendJsonError('Invalid match_id', 400);
        }

        $state = null;
        $currentMatchFile = __DIR__ . '/current_match_id.json';
        if ($match_id === 0 && file_exists($currentMatchFile)) {
            $cm = json_decode(@file_get_contents($currentMatchFile), true);
            if ($cm && isset($cm['match_id']) && $cm['match_id'] > 0) {
                $match_id = intval($cm['match_id']);
            }
        }

        if ($match_id > 0) {
            $stmt = $conn->prepare("SELECT live_state FROM `{$matchesTable}` WHERE id=? LIMIT 1");
            if (!$stmt) {
                throw new Exception('DB prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $match_id);
            if (!$stmt->execute()) {
                throw new Exception('DB execute failed: ' . $stmt->error);
            }
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && !empty($row['live_state'])) {
                $decoded = json_decode($row['live_state'], true);
                if ($decoded) $state = $decoded;
            }
        }

        if (!$state && $match_id === 0) {
            $res = $conn->query(
                "SELECT live_state FROM `{$matchesTable}` 
                 WHERE live_state IS NOT NULL AND live_state != '' 
                 ORDER BY updated_at DESC LIMIT 1"
            );
            if ($res === false) {
                throw new Exception('DB query failed: ' . $conn->error);
            }
            if ($row = $res->fetch_assoc()) {
                $decoded = json_decode($row['live_state'], true);
                if ($decoded) $state = $decoded;
            }
        }

        if (!$state && file_exists($pendingFile)) {
            $raw = @file_get_contents($pendingFile);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if ($decoded) $state = $decoded;
            }
        }

        echo json_encode(['success' => true, 'state' => $state]);
        exit;
    } catch (Throwable $e) {
        error_log('[state.php GET] Error: ' . $e->getMessage());
        sendJsonError('Error retrieving state', 500, $e->getMessage());
    }
}

/* ============================================================
   POST — save state
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            error_log('[state.php POST] Invalid JSON payload: ' . substr($raw, 0, 200));
            sendJsonError('Invalid JSON', 400);
        }

        $matchIdInput = $data['match_id'] ?? null;
        $match_id = parseMatchId($matchIdInput);
        if ($matchIdInput !== null && $match_id === null) {
            error_log('[state.php POST] Invalid match_id: ' . json_encode($matchIdInput));
            sendJsonError('Invalid match_id', 400);
        }

        $state = $data['state'] ?? null;
        if (!is_array($state)) {
            error_log('[state.php POST] Missing or invalid state payload');
            sendJsonError('state required', 400);
        }

        $poster = null;
        try { $poster = currentUser(); } catch (Throwable $_) { $poster = null; }
        error_log('[state.php POST] Received update: match_id=' . ($match_id ?? 'null') . ' poster=' . ($poster['username'] ?? 'NOT_LOGGED_IN'));
        if ($poster && isset($poster['role'])) {
            error_log('[state.php POST] State update from user: ' . ($poster['username'] ?? 'unknown') . ' role=' . $poster['role']);
        }

        $stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($stateJson === false) {
            throw new Exception('State encoding failed');
        }

        if ($match_id > 0) {
            $_lww_stmt = $conn->prepare("SELECT live_state FROM `{$matchesTable}` WHERE id=? LIMIT 1");
            if (!$_lww_stmt) {
                throw new Exception('DB prepare failed: ' . $conn->error);
            }
            $_lww_stmt->bind_param('i', $match_id);
            if (!$_lww_stmt->execute()) {
                throw new Exception('DB execute failed: ' . $_lww_stmt->error);
            }
            $_lww_row = $_lww_stmt->get_result()->fetch_assoc();
            $_lww_stmt->close();
            if ($_lww_row && !empty($_lww_row['live_state'])) {
                $_existing_st = json_decode($_lww_row['live_state'], true);
                $_existing_ts  = isset($_existing_st['updated_at']) ? strtotime($_existing_st['updated_at']) : 0;
                $_incoming_ts  = isset($state['updated_at']) ? strtotime($state['updated_at']) : 0;
                if ($_existing_ts > 0 && $_incoming_ts > 0 && $_incoming_ts < $_existing_ts) {
                    echo json_encode(['success' => true, 'stale' => true]);
                    exit;
                }
            }
        }

        $fileWritten = @file_put_contents($pendingFile, $stateJson, LOCK_EX);
        error_log('[state.php POST] Pending file written: ' . ($fileWritten ? 'YES' : 'NO') . ' to ' . $pendingFile);

        if ($match_id > 0) {
            $stmt = $conn->prepare("UPDATE `{$matchesTable}` SET live_state=?, updated_at=NOW() WHERE id=?");
            if (!$stmt) {
                error_log('[state.php POST] DB prepare error: ' . $conn->error);
            } else {
                $stmt->bind_param('si', $stateJson, $match_id);
                if (!$stmt->execute()) {
                    error_log('[state.php POST] DB execute error: ' . $stmt->error);
                } else {
                    error_log('[state.php POST] DB update success for match_id=' . $match_id);
                }
                $stmt->close();
            }
        } else {
            error_log('[state.php POST] Skipped DB update for match_id=' . ($match_id ?? 'null'));
        }

        try {
            $wsBase = getEnvValue('BASKETBALL_WS_URL') ?: getEnvValue('WS_URL');
            if ($wsBase) {
                $wsUrl = rtrim($wsBase, '/') . '/emit';
            } else {
                $wsHost = getEnvValue('WS_HOST', '127.0.0.1');
                $wsPort = getEnvValue('WS_PORT', '3000');
                $wsUrl = 'http://' . $wsHost . ':' . $wsPort . '/emit';
            }
            $clientId = $data['client_id'] ?? null;
            $broadcastPayload = [
                'type' => 'state',
                'match_id' => $match_id,
                'payload' => $state,
                'client_id' => $clientId,
            ];
            if (function_exists('curl_init')) {
                $ch = curl_init($wsUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 2,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($broadcastPayload),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                ]);
                $response = @curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                @curl_close($ch);
                if ($curlError) {
                    error_log('[state.php POST] Broadcast failed: ' . $curlError . ' wsUrl=' . $wsUrl);
                } else {
                    error_log('[state.php POST] Broadcast sent: match_id=' . $match_id . ' http=' . $httpCode . ' wsUrl=' . $wsUrl . ' response=' . substr($response ?? '', 0, 100));
                }
            } else {
                error_log('[state.php POST] Skipping broadcast: cURL unavailable');
            }
        } catch (Throwable $e) {
            error_log('[state.php POST] Broadcast exception: ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'state' => $state]);
        exit;
    } catch (Throwable $e) {
        error_log('[state.php POST] Error: ' . $e->getMessage());
        sendJsonError('Error saving state', 500, $e->getMessage());
    }
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);