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
     * Show the superadmin dashboard / admin landing page.
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
            $allMatches = DB::select("SELECT 'Basketball' AS sport,
                   CONCAT(team_a_name, ' vs ', team_b_name) AS teams,
                   CONCAT(team_a_score, ' - ', team_b_score, ' (', match_result, ')') AS result,
                   saved_at AS match_date
              FROM matches
            UNION ALL
            SELECT 'Volleyball',
                   CONCAT(team_a_name, ' vs ', team_b_name),
                   CONCAT(team_a_score, ' - ', team_b_score, ' (', match_result, ')'),
                   created_at FROM volleyball_matches
            UNION ALL
            SELECT 'Badminton',
                   CONCAT(team_a_name, ' vs ', team_b_name),
                   CONCAT('Winner: ', COALESCE(winner_name, '?'), ' | ', status),
                   created_at FROM badminton_matches
            UNION ALL
            SELECT 'Table Tennis',
                   CONCAT(team_a_name, ' vs ', team_b_name),
                   CONCAT('Winner: ', COALESCE(winner_name, '?'), ' | ', status),
                   created_at FROM table_tennis_matches
            UNION ALL
            SELECT 'Darts',
                   CONCAT(game_type, ' ', COALESCE(legs_to_win, '?'), ' legs'),
                   CONCAT('Winner: ', COALESCE(winner_name, '?')),
                   created_at FROM darts_matches
            ORDER BY match_date DESC LIMIT 200");
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
        return response()->json(['users' => $users]);
    }

    /**
     * Promote a viewer/scorekeeper to admin (or set pending).
     */
    public function promote(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($request->input('user_id'));
        // Only allow promoting viewers or scorers
        if (!in_array($target->role, ['viewer', 'scorer'], true)) {
            return back()->with('error', 'User is already admin or superadmin.');
        }
        $target->role = 'admin';
        $target->status = 'pending'; // requires superadmin approval
        $target->save();

        $this->logActivity(Auth::id(), Auth::user()->username, "Promoted {$target->username} (ID:{$target->id}) to admin (pending)");

        return back()->with('success', "{$target->username} has been promoted to admin (pending approval).");
    }

    /**
     * Approve or reject a pending admin applicant.
     * POST /superadmin/admin-landing/approve-reject
     * Expects JSON: { user_id: int, action: 'approve'|'reject' }
     */
    public function approveRejectAdmin(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'action'  => 'required|in:approve,reject',
        ]);

        $target = User::findOrFail($request->input('user_id'));
        $action = $request->input('action');

        if ($action === 'approve') {
            $target->status = 'active';
            $target->role = 'admin';
            $target->save();
            $this->logActivity(Auth::id(), Auth::user()->username, "Approved admin applicant: {$target->username} (ID:{$target->id})");
            return response()->json(['success' => true, 'message' => 'Applicant approved.']);
        } else {
            // reject: revert to viewer
            $target->role = 'viewer';
            $target->status = 'active';
            $target->save();
            $this->logActivity(Auth::id(), Auth::user()->username, "Rejected admin applicant: {$target->username} (ID:{$target->id})");
            return response()->json(['success' => true, 'message' => 'Applicant rejected.']);
        }
    }

    /**
     * Toggle user active/deactivated status.
     * POST /superadmin/admin-landing/toggle-user-status
     */
    public function toggleUserStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($request->input('user_id'));
        $newStatus = ($target->status === 'active') ? 'deactivated' : 'active';
        $target->status = $newStatus;
        $target->save();

        $this->logActivity(Auth::id(), Auth::user()->username, "Changed {$target->username} (ID:{$target->id}) status to {$newStatus}");

        return response()->json(['success' => true, 'new_status' => $newStatus]);
    }

    /**
     * Delete a user.
     * POST /superadmin/admin-landing/delete-user
     */
    public function deleteUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($request->input('user_id'));
        if ($target->role === 'superadmin') {
            return response()->json(['success' => false, 'error' => 'Cannot delete a superadmin.'], 403);
        }

        $username = $target->username;
        $target->delete();

        $this->logActivity(Auth::id(), Auth::user()->username, "Deleted user {$username} (ID:{$request->input('user_id')})");

        return response()->json(['success' => true, 'message' => 'User deleted.']);
    }

    /**
     * Change a user's username.
     * POST /superadmin/admin-landing/change-username
     */
    public function changeUsername(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'new_username' => 'required|string|max:255|unique:users,username',
        ]);

        $target = User::findOrFail($request->input('user_id'));
        $old = $target->username;
        $target->username = $request->input('new_username');
        $target->save();

        $this->logActivity(Auth::id(), Auth::user()->username, "Changed username from {$old} to {$target->username} (ID:{$target->id})");

        return response()->json(['success' => true]);
    }

    /**
     * Add a new user manually.
     * POST /superadmin/admin-landing/add-user
     */
    public function addUser(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'nullable|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:viewer,scorer,admin',
        ]);

        $user = User::create([
            'name'          => $validated['username'],
            'username'      => $validated['username'],
            'email'         => $validated['email'] ?? $validated['username'] . '@sportssync.local',
            'password'      => Hash::make($validated['password']),
            'password_hash' => Hash::make($validated['password']),
            'role'          => $validated['role'],
            'status'        => 'active',
        ]);

        $this->logActivity(Auth::id(), Auth::user()->username, "Created user {$user->username} (ID:{$user->id}) as {$validated['role']}");

        return response()->json(['success' => true, 'user' => $user]);
    }

    /**
     * Toggle sport active/inactive status.
     * POST /superadmin/admin-landing/toggle-sport-status
     */
    public function toggleSportStatus(Request $request)
    {
        $request->validate([
            'sport_id' => 'required|integer',
        ]);

        $sportId = $request->input('sport_id');
        $sport = DB::table('sports')->where('id', $sportId)->first();
        if (!$sport) {
            return response()->json(['success' => false, 'error' => 'Sport not found.'], 404);
        }
        $newStatus = ($sport->status === 'active') ? 'inactive' : 'active';
        DB::table('sports')->where('id', $sportId)->update(['status' => $newStatus]);

        $this->logActivity(Auth::id(), Auth::user()->username, "Toggled sport {$sport->name} (ID:{$sportId}) to {$newStatus}");

        return response()->json(['success' => true, 'new_status' => $newStatus, 'message' => "{$sport->name} is now {$newStatus}."]);
    }

    /**
     * Save a system setting (key-value).
     * POST /superadmin/admin-landing/save-setting
     */
    public function saveSystemSetting(Request $request)
    {
        $request->validate([
            'key'   => 'required|string',
            'value' => 'required|string',
        ]);

        $key = $request->input('key');
        $value = $request->input('value');

        try {
            $exists = DB::table('system_settings')->where('key', $key)->exists();
            if ($exists) {
                DB::table('system_settings')->where('key', $key)->update(['value' => $value]);
            } else {
                DB::table('system_settings')->insert(['key' => $key, 'value' => $value]);
            }
            $this->logActivity(Auth::id(), Auth::user()->username, "Changed setting '{$key}' to '{$value}'");
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export activity log as CSV.
     * GET /superadmin/admin-landing/export-activity-log
     */
    public function exportActivityLog(Request $request)
    {
        try {
            $logs = DB::select('SELECT * FROM activity_log ORDER BY timestamp DESC');
        } catch (\Throwable $e) {
            $logs = [];
        }

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="activity-log-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($logs) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['ID', 'User ID', 'Username', 'Action', 'Timestamp']);
            foreach ($logs as $row) {
                fputcsv($fh, [
                    $row->id ?? '',
                    $row->user_id ?? '',
                    $row->username ?? '',
                    $row->action ?? '',
                    $row->timestamp ?? '',
                ]);
            }
            fclose($fh);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Log an activity entry.
     */
    private function logActivity(?int $userId, string $username, string $action): void
    {
        try {
            DB::table('activity_log')->insert([
                'user_id'   => $userId,
                'username'  => $username,
                'action'    => $action,
                'timestamp' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SuperadminDashboard] Failed to log activity: ' . $e->getMessage());
        }
    }

    /**
     * Serve the legacy adminlanding_page.php (superadmin_adminlanding_page.php).
     * This action sets up globals and includes the legacy PHP file.
     */
    public function legacyAdminLanding()
    {
        try {
            // Get the Laravel DB connection as $pdo
            $pdo = DB::connection()->getPdo();
            
            // Set $pdo as a GLOBAL variable so the included PHP file can access it
            // When PHP includes a file, local vars from the calling function are NOT accessible
            // The included file must use 'global $pdo;' or $GLOBALS['pdo'] to access this
            $GLOBALS['pdo'] = $pdo;
            
            // Get current user for the legacy file
            $user = Auth::user();
            
            // Include the legacy PHP file
            // The file is in public/ directory
            include public_path('superadmin_adminlanding_page.php');
            
            return response('', 200);
        } catch (\Throwable $e) {
            Log::error('[SuperadminController@legacyAdminLanding] ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            abort(500, 'Admin landing error: ' . $e->getMessage());
        }
    }
}
