<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SuperadminController extends Controller
{
    /**
     * Display the superadmin dashboard / admin landing.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // ── Stats ────────────────────────────────────────────────────
        $totalUsers = User::count();

        $eventsThisMonth = 0;
        try {
            $ms = date('Y-m-01 00:00:00');
            $me = date('Y-m-t 23:59:59');
            $eventsThisMonth = (int) DB::selectOne('
                SELECT (
                    (SELECT COUNT(*) FROM matches              WHERE saved_at   BETWEEN ? AND ?)
                  + (SELECT COUNT(*) FROM volleyball_matches   WHERE created_at BETWEEN ? AND ?)
                  + (SELECT COUNT(*) FROM badminton_matches    WHERE created_at BETWEEN ? AND ?)
                  + (SELECT COUNT(*) FROM table_tennis_matches WHERE created_at BETWEEN ? AND ?)
                  + (SELECT COUNT(*) FROM darts_matches        WHERE created_at BETWEEN ? AND ?)
                ) AS total', [$ms, $me, $ms, $me, $ms, $me, $ms, $me, $ms, $me])->total;
        } catch (\Throwable $e) {
            Log::warning('[SuperadminDashboard] events count failed: ' . $e->getMessage());
        }

        // ── Users ────────────────────────────────────────────────────
        $users = User::orderBy('id', 'desc')->limit(200)->get();

        // ── Sports ───────────────────────────────────────────────────
        $sports = [];
        try {
            $sports = DB::select('SELECT id, name, status FROM sports ORDER BY name ASC');
        } catch (\Throwable $e) {
            Log::warning('[SuperadminDashboard] sports query failed: ' . $e->getMessage());
        }
        $activeSportsCount = count(array_filter($sports, fn($s) => ($s->status ?? '') === 'active'));

        // ── Pending admin applicants ─────────────────────────────────
        $pendingApplicants = [];
        try {
            $pendingApplicants = DB::select(
                "SELECT id, username, email, role, status, created_at FROM users WHERE role = 'admin' AND status = 'pending' ORDER BY created_at ASC"
            );
        } catch (\Throwable $e) {}
        $pendingCount = count($pendingApplicants);

        // ── Activity log ─────────────────────────────────────────────
        $activityLog = [];
        try {
            $activityLog = DB::select(
                'SELECT id, user_id, username, action, timestamp FROM activity_log ORDER BY timestamp DESC LIMIT 50'
            );
        } catch (\Throwable $e) {}

        // ── All matches (latest 200) ─────────────────────────────────
        $allMatches = [];
        try {
            $allMatches = DB::select('
                SELECT \'Basketball\' AS sport,
                       CONCAT(team_a_name, \' vs \', team_b_name) AS teams,
                       CONCAT(team_a_score, \' - \', team_b_score, \' (\', match_result, \')\') AS score_result,
                       saved_at AS match_date
                  FROM matches
                UNION ALL
                SELECT \'Volleyball\',
                       CONCAT(team_a_name, \' vs \', team_b_name),
                       CONCAT(team_a_score, \' - \', team_b_score, \' (\', match_result, \')\'),
                       created_at FROM volleyball_matches
                UNION ALL
                SELECT \'Badminton\',
                       CONCAT(team_a_name, \' vs \', team_b_name),
                       CONCAT(\'Winner: \', COALESCE(winner_name,\'\'-\'\'), \' | \', status),
                       created_at FROM badminton_matches
                UNION ALL
                SELECT \'Table Tennis\',
                       CONCAT(team_a_name, \' vs \', team_b_name),
                       CONCAT(\'Winner: \', COALESCE(winner_name,\'\'-\'\'), \' | \', status),
                       created_at FROM table_tennis_matches
                UNION ALL
                SELECT \'Darts\',
                       CONCAT(game_type, \' \'—\' \', COALESCE(legs_to_win,\'\'?\'\'), \' legs\'),
                       CONCAT(\'Winner: \', COALESCE(winner_name,\'\'-\'\')),
                       created_at FROM darts_matches
                ORDER BY match_date DESC LIMIT 200'
            );
        } catch (\Throwable $e) {
            Log::warning('[SuperadminDashboard] matches query failed: ' . $e->getMessage());
        }

        // ── Maintenance mode ─────────────────────────────────────────
        $maintenanceMode = '0';
        try {
            $row = DB::select('SELECT `value` FROM system_settings WHERE `key` = ? LIMIT 1', ['maintenance_mode']);
            if (!empty($row)) $maintenanceMode = $row[0]->value;
        } catch (\Throwable $e) {}

        // ── Feedback count ───────────────────────────────────────────
        $feedbackCount = 0;
        try {
            $feedbackCount = DB::table('feedbacks')->count();
        } catch (\Throwable $e) {}

        return view('superadmin.admin-landing', [
            'user'               => $user,
            'totalUsers'         => $totalUsers,
            'eventsThisMonth'    => $eventsThisMonth,
            'activeSportsCount'  => $activeSportsCount,
            'sports'             => $sports,
            'users'              => $users,
            'pendingApplicants'  => $pendingApplicants,
            'pendingCount'       => $pendingCount,
            'activityLog'        => $activityLog,
            'allMatches'         => $allMatches,
            'maintenanceMode'    => $maintenanceMode,
            'feedbackCount'      => $feedbackCount,
        ]);
    }

    /**
     * List all users (JSON endpoint for AJAX).
     */
    public function users(Request $request)
    {
        $users = User::orderBy('id', 'desc')->limit(200)->get();
        return view('superadmin.users', ['users' => $users]);
    }

    /**
     * Promote a user to superadmin role.
     */
    public function promote(Request $request)
    {
        $id = (int) $request->input('user_id');
        if (!$id) {
            return redirect()->back()->with('error', 'Missing user id');
        }

        $u = User::find($id);
        if (!$u) {
            return redirect()->back()->with('error', 'User not found');
        }

        $u->role = 'superadmin';
        $u->save();

        $this->logActivity($u->id, $u->username ?? $u->name ?? 'system', "Promoted to superadmin: {$u->username}");

        return redirect()->back()->with('success', 'User promoted to superadmin');
    }

    // ═══════════════════════════════════════════════════════════════
    // AJAX ENDPOINTS (return JSON)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Approve or reject an admin applicant.
     */
    public function approveRejectAdmin(Request $request)
    {
        $action = $request->input('action'); // 'approve' or 'reject'
        $targetId = (int) $request->input('user_id');

        if (!$targetId || !in_array($action, ['approve', 'reject'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid parameters'], 400);
        }

        $user = User::find($targetId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Target is not an admin applicant'], 400);
        }

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $user->status = $newStatus;
        if ($action === 'approve') {
            $user->is_active = 1;
        }
        $user->save();

        $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin',
            ucfirst($newStatus) . ' admin applicant: ' . ($user->username ?? 'unknown'));

        return response()->json(['success' => true, 'new_status' => $newStatus, 'user_id' => $targetId]);
    }

    /**
     * Toggle user active/deactivated status.
     */
    public function toggleUserStatus(Request $request)
    {
        $targetId = (int) $request->input('user_id');
        if (!$targetId) {
            return response()->json(['success' => false, 'message' => 'Invalid user ID'], 400);
        }

        $user = User::find($targetId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $newStatus = ($user->status === 'active') ? 'deactivated' : 'active';
        $user->status = $newStatus;
        $user->save();

        $label = $newStatus === 'active' ? 'Account activated' : 'Account deactivated';
        $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin',
            $label . ': ' . ($user->username ?? 'unknown'));

        return response()->json(['success' => true, 'new_status' => $newStatus]);
    }

    /**
     * Delete a user (only if deactivated).
     */
    public function deleteUser(Request $request)
    {
        $targetId = (int) $request->input('user_id');
        if (!$targetId) {
            return response()->json(['success' => false, 'message' => 'Invalid user ID'], 400);
        }

        $user = User::find($targetId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        if ($user->status !== 'deactivated') {
            return response()->json(['success' => false, 'message' => 'Deactivate the user first before deleting'], 400);
        }

        $username = $user->username ?? 'unknown';
        $user->delete();

        $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin',
            'Account deleted: ' . $username);

        return response()->json(['success' => true]);
    }

    /**
     * Change a user's username.
     */
    public function changeUsername(Request $request)
    {
        $targetId = (int) $request->input('user_id');
        $newUsername = trim($request->input('new_username', ''));

        if (!$targetId || $newUsername === '') {
            return response()->json(['success' => false, 'message' => 'User ID and new username required'], 400);
        }
        if (strlen($newUsername) < 3 || strlen($newUsername) > 60) {
            return response()->json(['success' => false, 'message' => 'Username must be 3–60 characters'], 400);
        }

        $existing = User::where('username', $newUsername)->where('id', '!=', $targetId)->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Username already taken'], 409);
        }

        $user = User::find($targetId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $oldUsername = $user->username ?? 'unknown';
        $user->username = $newUsername;
        $user->save();

        $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin',
            "Username changed: {$oldUsername} → {$newUsername}");

        return response()->json(['success' => true, 'new_username' => $newUsername]);
    }

    /**
     * Add a new user manually.
     */
    public function addUser(Request $request)
    {
        $newUsername = trim($request->input('username', ''));
        $newPassword = $request->input('password', '');
        $newRole = trim($request->input('role', 'viewer'));

        if ($newUsername === '' || $newPassword === '') {
            return response()->json(['success' => false, 'message' => 'Username and password required'], 400);
        }
        if (strlen($newUsername) < 3 || strlen($newUsername) > 60) {
            return response()->json(['success' => false, 'message' => 'Username must be 3–60 characters'], 400);
        }
        if (strlen($newPassword) < 6) {
            return response()->json(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
        }
        if (!in_array($newRole, ['admin', 'viewer', 'scorer'], true)) {
            $newRole = 'viewer';
        }

        $existing = User::where('username', $newUsername)->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Username already exists'], 409);
        }

        $user = User::create([
            'username' => $newUsername,
            'password' => Hash::make($newPassword),
            'role' => $newRole,
            'status' => 'active',
        ]);

        $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin',
            "Account created: {$newUsername} (role: {$newRole})");

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'username' => $newUsername,
            'role' => $newRole,
        ]);
    }

    /**
     * Toggle a sport's active/inactive status.
     */
    public function toggleSportStatus(Request $request)
    {
        $sportId = (int) $request->input('sport_id');
        if (!$sportId) {
            return response()->json(['success' => false, 'message' => 'Invalid sport ID'], 400);
        }

        try {
            $sport = DB::table('sports')->where('id', $sportId)->first();
            if (!$sport) {
                return response()->json(['success' => false, 'message' => 'Sport not found'], 404);
            }

            $newStatus = ($sport->status === 'active') ? 'inactive' : 'active';
            DB::table('sports')->where('id', $sportId)->update(['status' => $newStatus]);

            $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin',
                'Sport ' . ucfirst($newStatus) . ': ' . ($sport->name ?? 'unknown'));

            return response()->json(['success' => true, 'new_status' => $newStatus]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save a system setting (e.g. maintenance_mode).
     */
    public function saveSystemSetting(Request $request)
    {
        $key = trim($request->input('key', ''));
        $value = trim($request->input('value', ''));
        $allowed = ['maintenance_mode'];

        if (!in_array($key, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Unknown setting key'], 400);
        }

        try {
            DB::statement('
                INSERT INTO system_settings (`key`, `value`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()
            ', [$key, $value]);

            $label = $key === 'maintenance_mode'
                ? ($value === '1' ? 'Maintenance Mode: ON' : 'Maintenance Mode: OFF')
                : "Setting changed: {$key} = {$value}";

            $this->logActivity(Auth::id(), Auth::user()->username ?? 'admin', $label);

            return response()->json(['success' => true, 'key' => $key, 'value' => $value]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export activity log as CSV.
     */
    public function exportActivityLog(Request $request)
    {
        try {
            $rows = DB::select('SELECT id, user_id, username, action, timestamp FROM activity_log ORDER BY timestamp DESC');
        } catch (\Throwable $e) {
            $rows = [];
        }

        $csv = "ID,User ID,Username,Action,Timestamp\n";
        foreach ($rows as $row) {
            $csv .= '"' . implode('","', [
                $row->id ?? '', $row->user_id ?? '', $row->username ?? '',
                str_replace('"', '""', $row->action ?? ''), $row->timestamp ?? ''
            ]) . "\"\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="activity_log_' . date('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * Log an activity entry.
     */
    private function logActivity(?int $userId, string $username, string $action): void
    {
        try {
            DB::insert(
                'INSERT INTO activity_log (user_id, username, action, timestamp) VALUES (?, ?, ?, NOW())',
                [$userId, $username, $action]
            );
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }
}
