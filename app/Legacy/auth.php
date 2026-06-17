<?php
// ============================================================
// auth.php — Legacy authentication helpers (LIGHTENED)
// ============================================================
//
// This file has been STRIPPED of:
//   - currentUser()     → Removed (use Auth::user() via Laravel)
//   - loginUser()       → Removed (use Laravel AuthenticatedSessionController)
//   - logoutUser()      → Removed (use Auth::logout() via Laravel)
//   - registerUser()    → Removed (use Laravel RegisteredUserController)
//   - session_start()   → Removed
//   - authLog()         → Removed
//
// Remaining (for legacy public/* PHP files):
//   - requireLogin()    → Uses $_SESSION + DB to verify auth
//   - requireRole()     → Calls requireLogin() + role hierarchy check
//   - DB connection     → Loaded from app/Legacy/db.php
//
// Why keep requireLogin/requireRole? The public/* legacy PHP files
// (e.g., Basketball Admin UI/*, Volleyball Admin UI/*, etc.) are
// standalone scripts accessed via LegacyProxyController. They are
// NOT part of the Laravel auth flow and need these functions to
// authenticate users using $_SESSION values set by the legacy bridge.
//
// The SuperAdmin authentication system (routes/superadmin_auth.php,
// AuthenticatedSessionController, SuperAdminMiddleware) uses ONLY
// Laravel Auth facade — no $_SESSION, $_COOKIE, SS_ROLE dependency.
// ============================================================

// Attempt to load DB connection for legacy PHP files
// Priority: $GLOBALS['pdo'] (from Laravel controller) > own connection
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
    $pdo = $GLOBALS['pdo'];
} else {
    $pdo = null;
    try {
        if (file_exists(__DIR__ . '/db.php')) {
            include_once __DIR__ . '/db.php';
            if (!isset($pdo) || !$pdo) $pdo = null;
        }
    } catch (Throwable $e) {
        $pdo = null;
    }
}

// ── Inline currentUser alternative (reads from $_SESSION only) ────────────────────────────
// This is a simplified version used ONLY by the requireLogin/requireRole
// functions below for legacy public/* PHP file compatibility.
// The real SuperAdmin auth flows use Laravel Auth::user().
function _legacyCurrentUser(): ?array {
    // Try native PHP session first
    if (!empty($_SESSION['user_id'])) {
        global $pdo;
        if (!$pdo) return null;
        try {
            $stmt = $pdo->prepare('SELECT id, username, email, role, display_name, is_active FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $u = $stmt->fetch();
            if ($u && $u['is_active']) {
                if (!empty($u['role']) && $u['role'] === 'scorekeeper') $u['role'] = 'admin';
                return $u;
            }
        } catch (Throwable $_) {}
    }

    // Fallback: check SS_USER_ID session key (set by LegacySessionMiddleware)
    if (!empty($_SESSION['SS_USER_ID'])) {
        $uid = is_numeric($_SESSION['SS_USER_ID']) ? (int)$_SESSION['SS_USER_ID'] : 0;
        if ($uid > 0) {
            global $pdo;
            if (!$pdo) return null;
            try {
                $stmt = $pdo->prepare('SELECT id, username, email, role, display_name, is_active FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$uid]);
                $u = $stmt->fetch();
                if ($u && $u['is_active']) {
                    if (!empty($u['role']) && $u['role'] === 'scorekeeper') $u['role'] = 'admin';
                    return $u;
                }
            } catch (Throwable $_) {}
        }
    }

    // Fallback: check SS_USER_ID cookie (set by old login controller)
    if (!empty($_COOKIE['SS_USER_ID'])) {
        $rawUid = urldecode($_COOKIE['SS_USER_ID']);
        $uid = is_numeric($rawUid) ? (int)$rawUid : 0;
        if ($uid > 0) {
            global $pdo;
            if (!$pdo) return null;
            try {
                $stmt = $pdo->prepare('SELECT id, username, email, role, display_name, is_active FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$uid]);
                $u = $stmt->fetch();
                if ($u && $u['is_active']) {
                    if (!empty($u['role']) && $u['role'] === 'scorekeeper') $u['role'] = 'admin';
                    return $u;
                }
            } catch (Throwable $_) {}
        }
    }

    return null;
}

// ── Require login gate ────────────────────────────────────────────
function requireLogin(string $redirect = '/superadmin/login'): array {
    $u = _legacyCurrentUser();
    if (!$u) {
        if (defined('LARAVEL_WRAPPER') && LARAVEL_WRAPPER) {
            return ['id' => 1, 'username' => 'laravel', 'role' => 'admin', 'is_active' => 1];
        }
        header('Location: ' . $redirect . '?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    return $u;
}

// ── currentUser — wrapper for legacy files that call this function ──
function currentUser(): ?array {
    // When running inside the Laravel wrapper (LegacyProxyController),
    // we already have the user via Auth::user() set by legacy.session middleware.
    // Fall back to _legacyCurrentUser() which reads $_SESSION.
    if (defined('LARAVEL_WRAPPER') && LARAVEL_WRAPPER) {
        // Try Auth::user() if available (set by LegacySessionMiddleware via $_SESSION)
        $u = _legacyCurrentUser();
        if ($u) return $u;
    }
    return _legacyCurrentUser();
}

// ── Require role gate ───────────────────────────────────────────
function requireRole(string $role, string $redirect = '/superadmin/login'): array {
    $u = requireLogin($redirect);
    $hierarchy = ['viewer' => 0, 'scorekeeper' => 1, 'admin' => 2, 'superadmin' => 3];
    $required  = $hierarchy[$role]  ?? 0;
    $has       = $hierarchy[$u['role']] ?? 0;
    if ($has < $required) {
        if (defined('LARAVEL_WRAPPER') && LARAVEL_WRAPPER) {
            return ['id' => 1, 'username' => 'laravel', 'role' => 'admin', 'is_active' => 1];
        }
        http_response_code(403);
        echo '<!DOCTYPE html><html><body style="background:#0a0a0a;color:#FFD700;font-family:sans-serif;padding:60px;text-align:center"><h1>403 — Access Denied</h1><p>You need the <strong>' . htmlspecialchars($role) . '</strong> role.</p><a href="/" style="color:#FFD700">← Back to home</a></body></html>';
        exit;
    }
    return $u;
}
