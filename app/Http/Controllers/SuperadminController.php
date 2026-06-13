<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperadminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:superadmin']);
    }

    public function index(Request $request)
    {
        // Serve the legacy admin landing page content for superadmin dashboard
        $legacyFile = public_path('adminlanding_page.php');
        if (! file_exists($legacyFile) || ! is_file($legacyFile)) {
            abort(404, 'Admin dashboard not found');
        }

        if (! defined('LARAVEL_WRAPPER')) define('LARAVEL_WRAPPER', true);
        
        if (Auth::check()) {
            $user = Auth::user();
            // Set Laravel session variables (accessible via session() helper)
            $request->session()->put('user_id', Auth::id());
            $request->session()->put('user_role', strtolower((string)($user->role ?? 'viewer')));
            $request->session()->put('role', strtolower((string)($user->role ?? 'viewer')));
            $request->session()->put('username', $user->username ?? 'admin');
            $request->session()->put('SS_ROLE', strtolower((string)($user->role ?? 'viewer'))); // Legacy compat
            $request->session()->put('SS_USER_ID', (string)Auth::id()); // Legacy compat
            
            // Also populate $_SESSION for legacy PHP compatibility
            $_SESSION['user_id'] = Auth::id();
            $_SESSION['user_role'] = strtolower((string)($user->role ?? 'viewer'));
            $_SESSION['role'] = strtolower((string)($user->role ?? 'viewer'));
            $_SESSION['username'] = $user->username ?? 'admin';
            $_SESSION['SS_ROLE'] = strtolower((string)($user->role ?? 'viewer'));
            $_SESSION['SS_USER_ID'] = (string)Auth::id();
        }
        
        chdir(public_path());
        ob_start();
        try {
            include $legacyFile;
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level()) ob_end_clean();
            abort(500, 'Admin dashboard error: ' . $e->getMessage());
        }
        
        return response($content)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function users(Request $request)
    {
        $users = \App\Models\User::orderBy('id', 'desc')->limit(200)->get();
        return view('superadmin.users', ['users' => $users]);
    }

    public function promote(Request $request)
    {
        $id = (int) $request->input('user_id');
        if (!$id) return redirect()->back()->with('error', 'Missing user id');
        $u = \App\Models\User::find($id);
        if (!$u) return redirect()->back()->with('error', 'User not found');
        $u->role = 'superadmin';
        $u->save();
        return redirect()->back()->with('success', 'User promoted to superadmin');
    }
}
