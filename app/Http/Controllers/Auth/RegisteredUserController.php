<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $request->session()->regenerateToken();

        return response()->view('auth.register')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $username = strtolower(trim((string)$request->input('name')));
        $email = strtolower(trim((string)$request->input('email')));

        $request->merge(['name' => $username, 'email' => $email]);

        $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique(User::class, 'username')],
            'email' => ['required', 'string', 'email', 'max:120', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['sometimes', 'string', 'in:admin,viewer'],
        ], [
            'name.unique' => 'Username already exists.',
            'email.unique' => 'Email already exists.',
        ]);

        // IMPORTANT: Do NOT pre-hash the password. The User model's 'password' => 'hashed' cast
        // automatically hashes it. Pre-hashing causes double-hashing and login failures.
        $userData = [
            'name' => $username,
            'username' => $username,
            'email' => $email,
            'password' => $request->password, // Model cast 'hashed' handles hashing
            'password_hash' => $request->password, // Plaintext for legacy compatibility
        ];
        // Default to conservative 'viewer' role when none provided.
        $userData['role'] = $request->input('role', 'viewer');

        // If users table has a `status` column, set admin applicants to 'pending'
        $roleLower = strtolower((string)$userData['role']);
        if ($roleLower === 'admin' && Schema::hasColumn('users', 'status')) {
            $userData['status'] = 'pending';
        }

        try {
            $user = User::create($userData);
        } catch (\Illuminate\Database\QueryException $e) {
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'username') || str_contains($message, 'uq_username')) {
                throw ValidationException::withMessages(['name' => 'Username already exists.']);
            }
            if (str_contains($message, 'email') || str_contains($message, 'users_email_unique')) {
                throw ValidationException::withMessages(['email' => 'Email already exists.']);
            }

            // Fallback to a friendly duplicate user message rather than exposing SQL errors.
            if (($e->errorInfo[0] ?? '') === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages(['name' => 'Username already exists.']);
            }

            throw $e;
        }

        try {
            event(new Registered($user));
            session()->flash('status', 'verification-link-sent');
        } catch (\Throwable $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            session()->flash('status', 'verification-send-failed');
        }

        Auth::login($user);

        // Legacy compatibility cookies are intentionally NOT set here.
        // Legacy pages will be provided compatibility via server-side middleware.

        return redirect(route('verification.notice', [], false));
    }
}
