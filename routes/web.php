<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperadminController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('dashboard');
});

// Legacy routes are defined in `routes/legacy.php` and are proxied through
// Laravel so that authentication and the `legacy.session` middleware run.
// This ensures legacy pages execute within the Laravel request lifecycle
// and receive server-side session/cookie injection for compatibility.

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

Route::get('/dashboard', function () {
    $user = Auth::user();
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
})->middleware(['auth', 'verified', \App\Http\Middleware\PreventBackHistory::class])->name('dashboard');

Route::middleware(['auth', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
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

// Superadmin routes: full access, protected by auth + ensure.role:superadmin
Route::middleware(['auth', 'ensure.role:superadmin', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
    Route::get('/superadmin', [SuperadminController::class, 'index'])->name('superadmin.dashboard');
    Route::get('/superadmin/users', [SuperadminController::class, 'users'])->name('superadmin.users');
    Route::post('/superadmin/users/promote', [SuperadminController::class, 'promote'])->name('superadmin.users.promote');
});

// Legacy admin landing proxied through Laravel so middleware can enforce auth + role
Route::get('/adminlanding_page.php', function (Request $request) {
    $legacyFile = public_path('adminlanding_page.php');
    if (! file_exists($legacyFile) || ! is_file($legacyFile)) {
        abort(404);
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
        abort(500, 'Legacy admin page error');
    }
    
    return response($content)->header('Content-Type', 'text/html; charset=utf-8');
})->middleware(['auth', 'superadmin'])->name('legacy.adminlanding');

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
        Cookie::queue(Cookie::forget('SS_USER_ID'));
        Cookie::queue(Cookie::forget('SS_ROLE'));
        setcookie('SS_USER_ID', '', time() - 3600, '/');
        setcookie('SS_ROLE', '', time() - 3600, '/');
    } catch (\Throwable $_) { }

    return redirect()->route('superadmin.login');
})->name('legacy.logout');
