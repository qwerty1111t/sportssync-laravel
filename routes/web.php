<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperadminController;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('dashboard');
});

// Legacy routes are defined in `routes/legacy.php` and are proxied through
// Laravel so that authentication middleware (auth, ensure.role) runs.
// The 'legacy.session' middleware has been REMOVED. Legacy PHP files that need
// SS_USER_ID/SS_ROLE compatibility cookies receive them via the dedicated
// LegacySessionMiddleware (if still needed for public/analytics/ endpoints).

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::post('/contact', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'message' => 'required|string|max:2000',
    ]);

    DB::table('feedbacks')->insert([
        'name' => $data['name'],
        'email' => $data['email'],
        'message' => $data['message'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect()->route('contact')->with('status', 'Thank you! Your feedback has been received.');
})->name('contact.submit');

Route::get('/dashboard', function (Request $request) {
    $user = Auth::user();
    
    // If superadmin, they should use /superadmin/dashboard instead
    if ($user && strtolower((string)($user->role ?? '')) === 'superadmin') {
        return redirect('/superadmin/dashboard');
    }
    
    if ($user) {
        $status = strtolower((string)($user->status ?? ''));
        if (in_array($status, ['pending', 'rejected'], true) || (isset($user->is_active) && !(int)$user->is_active)) {
            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            $message = $status === 'pending'
                ? 'Your account is pending approval by a superadmin. Please wait for approval before signing in.'
                : 'Account not approved.';

            return redirect()->route('login')->with('status', $message);
        }
    }
    
    return view('dashboard');
})->middleware(['auth', 'verified', PreventBackHistory::class])->name('dashboard');

Route::middleware(['auth', PreventBackHistory::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Feedback API endpoints for admin - CSRF skipped via route-level exclusion
Route::middleware(['auth', 'ensure.role:superadmin'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->group(function () {
    Route::get('/api/feedbacks', function (Request $request) {
        try {
            $feedbacks = DB::table('feedbacks')->orderBy('created_at', 'desc')->get();
            return response()->json($feedbacks);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to load feedbacks', 'message' => $e->getMessage()], 500);
        }
    });

    Route::post('/api/feedbacks/{id}/status', function (Request $request, $id) {
        try {
            $data = $request->validate([
                'status' => 'required|in:pending,resolved,rejected',
            ]);
            
            $id = (int)$id;
            if ($id <= 0) {
                return response()->json(['error' => 'Invalid feedback ID'], 400);
            }
            
            $updated = DB::table('feedbacks')->where('id', $id)->update(['status' => $data['status']]);
            
            if ($updated) {
                return response()->json(['success' => true, 'message' => 'Feedback status updated successfully']);
            }
            
            return response()->json(['error' => 'Feedback not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to update feedback status', 'message' => $e->getMessage()], 500);
        }
    });
});

// Superadmin routes: protected by 'auth' + 'superadmin' middleware
// 'auth' middleware ensures Laravel Auth::check() passes
// 'superadmin' middleware ensures role = 'superadmin' (returns 403 for wrong roles)
// The legacy.session middleware has been REMOVED - no legacy auth fallback needed.
Route::middleware(['auth', 'superadmin', PreventBackHistory::class])->group(function () {
    // Main superadmin dashboard (Laravel Blade view)
    Route::get('/superadmin/dashboard', [SuperadminController::class, 'index'])->name('superadmin.dashboard');
    
    // Legacy redirect for backwards compatibility
    Route::get('/superadmin', [SuperadminController::class, 'index'])->name('superadmin.home');
    
    Route::get('/superadmin/users', [SuperadminController::class, 'users'])->name('superadmin.users');
    Route::post('/superadmin/users/promote', [SuperadminController::class, 'promote'])->name('superadmin.users.promote');

    // Admin landing (Blade SPA replacing adminlanding_page.php)
    Route::get('/superadmin/admin-landing', [SuperadminController::class, 'index'])->name('superadmin.admin-landing');

    // API endpoints for admin-landing CRUD operations
    Route::post('/superadmin/admin-landing/toggle-user-status', [SuperadminController::class, 'toggleUserStatus']);
    Route::post('/superadmin/admin-landing/approve-reject', [SuperadminController::class, 'approveRejectAdmin']);
    Route::post('/superadmin/admin-landing/delete-user', [SuperadminController::class, 'deleteUser']);
    Route::post('/superadmin/admin-landing/change-username', [SuperadminController::class, 'changeUsername']);
    Route::post('/superadmin/admin-landing/add-user', [SuperadminController::class, 'addUser']);
    Route::post('/superadmin/admin-landing/toggle-sport-status', [SuperadminController::class, 'toggleSportStatus']);
    Route::post('/superadmin/admin-landing/save-setting', [SuperadminController::class, 'saveSystemSetting']);
    Route::get('/superadmin/admin-landing/export-activity-log', [SuperadminController::class, 'exportActivityLog']);
});

require __DIR__.'/legacy.php';
require __DIR__.'/auth.php';
require __DIR__.'/superadmin_auth.php';

// Server-side session check endpoint used by client-side pages to validate
// whether the user session is still valid. Returns JSON and sets no-cache
// headers so responses are never served from browser cache.
Route::get('/auth/check', function (Request $request) {
    $isAuth = Auth::check();
    $uid = $isAuth ? (int) Auth::id() : null;
    $status = null;
    try {
        if ($isAuth && Auth::user()) {
            $status = Auth::user()->status ?? null;
        }
    } catch (Throwable $_) { $status = null; }
    $resp = response()->json(['authenticated' => $isAuth, 'user_id' => $uid, 'status' => $status]);
    $resp->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
    $resp->headers->set('Pragma', 'no-cache');
    $resp->headers->set('Expires', '0');
    return $resp;
});

// Logout endpoint that clears both Laravel and legacy sessions/cookies
Route::get('/legacy-logout', function (Request $request) {
    // Laravel logout
    try {
        if (Auth::check()) {
            Auth::logout();
        }
    } catch (\Throwable $_) { }

    try {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    } catch (\Throwable $_) { }

    // Destroy native PHP session (legacy) if present
    try {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? false);
        }
        @session_destroy();
    } catch (\Throwable $_) { }

    // Remove legacy compatibility cookies
    try {
        setcookie('SS_USER_ID', '', time() - 3600, '/');
        setcookie('SS_ROLE', '', time() - 3600, '/');
    } catch (\Throwable $_) { }

    return redirect()->route('superadmin.login');
})->name('legacy.logout');
