<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperadminController extends Controller
{
    /**
     * Display the superadmin dashboard.
     *
     * This controller ONLY uses Laravel's Auth facade. No $_SESSION,
     * $_COOKIE, SS_ROLE, SS_USER_ID, or native PHP session functions.
     * The 'superadmin' middleware (not 'legacy.session') ensures role access.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Fetch recent users for the management table
        $users = User::orderBy('id', 'desc')->limit(200)->get();

        return view('superadmin.dashboard', [
            'users' => $users,
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

        return redirect()->back()->with('success', 'User promoted to superadmin');
    }
}
