# Phase 1 — FULL PROJECT AUDIT REPORT

## 1. Executive Summary

The Superadmin authentication system is **split between two incompatible systems**:
1. **Laravel-native auth** (new, clean — `routes/superadmin_auth.php`, `SuperAdminMiddleware`, `SuperadminController`)
2. **Legacy PHP session auth** (old, broken — `public/adminlanding_page.php`, `app/Legacy/auth.php`, `$_SESSION`/`$_COOKIE` based)

The two systems conflict because `public/adminlanding_page.php` still uses legacy auth and is **wrapped** by `AdminLandingController` which injects `$_SESSION` values. This file is 2539 lines of spaghetti with inline CSS/JS, raw PDO queries, and 10 AJAX handlers — all needing replacement.

## 2. Files Using Legacy PHP Sessions ($_SESSION / $_COOKIE / SS_ROLE / SS_USER_ID)

| File | Pattern | Severity |
|---|---|---|
| `public/adminlanding_page.php:17-56` | `$_SESSION['user_id']`, `$_SESSION['SS_USER_ID']`, `$_SESSION['SS_ROLE']`, `$_SESSION['csrf_token']` | **CRITICAL** — entire auth relies on these |
| `public/adminlanding_page.php:515-518` | `$_SESSION['csrf_token']` in HTML output | Must remove |
| `app/Http/Controllers/AdminLandingController.php:30-40` | Sets `$_SESSION['user_id']`, `$_SESSION['SS_USER_ID']`, `$_SESSION['SS_ROLE']` | Must remove whole controller |
| `app/Legacy/auth.php:46-99` | `_legacyCurrentUser()` reads `$_SESSION['user_id']`, `$_SESSION['SS_USER_ID']`, `$_COOKIE['SS_USER_ID']` | Must remove entire file |
| `app/Legacy/auth.php:102-129` | `requireLogin()`, `requireRole()` with `LARAVEL_WRAPPER` fallback | Must remove |
| `app/Http/Middleware/LegacySessionMiddleware.php:42-49` | Injects `$_SESSION['SS_ROLE']`, `$_SESSION['SS_USER_ID']`, `$_COOKIE['SS_ROLE']` | Keep only for sport admin proxy |
| `app/Http/Middleware/EnsureRole.php:43-51` | Session/COOKIE fallback | Refactor to use only Auth facade |
| `app/Http/Middleware/AdminMatchScope.php:14-29` | `$_SESSION['admin_match_id']` | Keep for sport-specific feature |
| `app/Http/Middleware/EncryptCookies.php:13-16` | `SS_USER_ID`, `SS_ROLE` in except list | Remove after no longer needed |
| `app/Providers/AppServiceProvider.php:37-41` | Excepts `SS_USER_ID`, `SS_ROLE` from encryption | Remove after cleanup |
| `routes/web.php:180-186` | `/legacy-logout` destroys `$_SESSION` | Keep for backward compat |
| `routes/web.php:190-192` | Clears `SS_USER_ID`, `SS_ROLE` cookies | Remove after cleanup |
| `public/logout.php:1-6` | Calls `logoutUser()`, `header('Location: /legacy-logout')` | Keep |
| `public/DARTS ADMIN UI/save_match.php:5` | `session_start()` | Keep for sport files |
| `public/DARTS ADMIN UI/delete_match.php:16` | `session_start()` | Keep for sport files |
| `public/DARTS ADMIN UI/save_leg.php:9` | `session_start()` | Keep for sport files |

## 3. Files That Require `public/auth.php` or `app/Legacy/auth.php`

These are **legacy sport admin/viewer** PHP files that use `requireLogin()`/`requireRole()`. They are NOT part of Superadmin auth but run through `LegacyProxyController`:

- `public/Badminton Admin UI/*` (11 files)
- `public/Basketball Admin UI/*` (9 files)
- `public/Volleyball Admin UI/*` (11 files)
- `public/TABLE TENNIS ADMIN UI/*` (10 files)
- `public/DARTS ADMIN UI/*` (10 files)
- `public/analytics/*` (4 files)
- `public/save_system_setting.php`
- `public/auth.php` (thin wrapper)

These should remain as-is (they work through LegacyProxyController with `legacy.session` middleware).

## 4. Superadmin-Specific Files (THE ONES TO REWRITE)

### Keep (clean Laravel):
- `app/Http/Controllers/SuperadminController.php` — Clean Laravel controller
- `app/Http/Controllers/Superadmin/Auth/AuthenticatedSessionController.php` — Clean Laravel auth
- `app/Http/Controllers/Superadmin/Auth/PasswordResetLinkController.php` — Clean
- `app/Http/Controllers/Superadmin/Auth/NewPasswordController.php` — Clean
- `app/Http/Middleware/SuperAdminMiddleware.php` — Clean, returns 403 for wrong roles
- `resources/views/superadmin/dashboard.blade.php` — Blade view (252 lines)
- `resources/views/superadmin/users.blade.php` — Blade view (38 lines)
- `resources/views/superadmin/auth/login.blade.php` — Blade view
- `resources/views/superadmin/auth/forgot-password.blade.php` — Blade view
- `resources/views/superadmin/auth/reset-password.blade.php` — Blade view
- `routes/superadmin_auth.php` — Clean routes
- `routes/web.php:119-132` — Clean route group for /superadmin/dashboard

### Remove/Replace (legacy/bridge):
- **REMOVE**: `public/adminlanding_page.php` (2539 lines) — Replace with Blade
- **REMOVE**: `app/Http/Controllers/AdminLandingController.php` (99 lines) — Delete
- **REMOVE**: `app/Legacy/auth.php` (130 lines) — Only needed for sport files now
- **REMOVE**: `app/Legacy/db.php` (48 lines) — Only needed for sport files
- **REMOVE**: `public/auth.php` — Only needed for sport files
- **REMOVE/REFACTOR**: `app/Http/Middleware/LegacySessionMiddleware.php` — Keep for sport proxy only

## 5. Current Route Map (Superadmin)

```
/superadmin/login          GET|POST   → AuthenticatedSessionController  (guest)
/superadmin/forgot-password GET|POST  → PasswordResetLinkController      (guest)
/superadmin/reset-password/{token}   → NewPasswordController            (guest)
/superadmin/logout         POST      → AuthenticatedSessionController.destroy  (auth+superadmin)
/superadmin/adminlanding   GET       → redirect → superadmin.dashboard         (auth+superadmin)
/superadmin/dashboard      GET       → SuperadminController@index       (auth+superadmin)
/superadmin                GET       → SuperadminController@index       (auth+superadmin)
/superadmin/users          GET       → SuperadminController@users       (auth+superadmin)
/superadmin/users/promote  POST      → SuperadminController@promote     (auth+superadmin)
/adminlanding_page.php     GET       → AdminLandingController@index     (auth+superadmin)
/dashboard                 GET       → view('dashboard') with superadmin→/superadmin/dashboard
/adminlanding              GET       → redirect → superadmin.dashboard  (auth+superadmin)  [in superadmin_auth.php]
```

## 6. ALL Redirection Points for Superadmin

| Source | Target | File |
|---|---|---|
| `/dashboard` (superadmin) | `/superadmin/dashboard` | `routes/web.php:52-53` |
| `/adminlanding_page.php` (legacy direct) | `/superadmin/login?next=adminlanding` | `public/adminlanding_page.php:72` |
| `/legacy-logout` | `/superadmin/login` | `routes/web.php:195` |
| `/superadmin/login` (auth'd) | `/superadmin/dashboard` | Laravel `guest` middleware |
| `/superadmin/adminlanding` | `/superadmin/dashboard` | `routes/superadmin_auth.php:48` |
| Login `?next=adminlanding` | `/superadmin/dashboard` | `AuthenticatedSessionController.php:85-86` |
| Login `?next=...` | Redirect to next URL | `AuthenticatedSessionController.php:88-94` |

## 7. Activity Log Table Operations (only in adminlanding_page.php)

The following operations are ONLY in `public/adminlanding_page.php` and need Blade equivalents:
- Approve/reject admin applicants (lines 158-194)
- Export activity log as CSV (lines 196-216)
- Toggle user status active/deactivated (lines 218-242) — NOT in SuperadminController
- Delete user (lines 244-266) — NOT in SuperadminController
- Change username (lines 268-294) — NOT in SuperadminController
- Add user (lines 296-329) — NOT in SuperadminController
- Toggle sport status (lines 331-350) — NOT in SuperadminController
- Save system setting / maintenance mode (lines 352-376) — NOT in SuperadminController
- Activity log display (lines 432-441) — NOT in dashboard
- Sports management display (lines 443-452) — NOT in dashboard
- Pending applicants list (lines 393-403) — NOT in dashboard
- Match history with all sports union (lines 463-496) — NOT in dashboard
- Settings page with maintenance mode toggle (lines 454-461) — NOT in dashboard

## 8. Current SuperadminController Limitations

The existing `SuperadminController@index` only:
- Fetches users list
- Renders `superadmin.dashboard` Blade view

The Blade dashboard only shows:
- 5 sport cards (links to admin pages)
- 2 analytics links
- Users table with promote button
- Logout button

It does NOT include:
- Overview stats (total users, events this month, active sports)
- Recent users table
- Sports status management (activate/deactivate)
- Match history with cross-sport union
- Activity log display
- Feedback management
- System settings (maintenance mode)
- User CRUD (add/edit/delete/activate/toggle)
- Pending admin applicants approval

## 9. Railway Deployment Checks

- `config/session.php` — SESSION_DRIVER=database (correct)
- `bootstrap/app.php` — Trusts all proxies (correct for Railway HTTPS)
- `railway.json` — Exists and configured

## 10. Summary of Changes Needed

**NEW FILES TO CREATE:**

| File | Purpose |
|---|---|
| `resources/views/superadmin/admin-landing.blade.php` | Main Blade admin landing (replaces 2539-line PHP file) |
| `app/Http/Controllers/Superadmin/AdminLandingController.php` | New clean controller (replaces old AdminLandingController wrapper) |

**EXISTING FILES TO MODIFY:**

| File | Changes |
|---|---|
| `app/Http/Controllers/SuperadminController.php` | Add all CRUD methods (add, delete, toggle user, etc.) |
| `resources/views/superadmin/dashboard.blade.php` | Complete rewrite with full admin landing features |
| `routes/web.php` | Remove `adminlanding_page.php` route, add new routes |
| `routes/superadmin_auth.php` | Remove `adminlanding` redirect |
| `app/Http/Middleware/EnsureRole.php` | Remove $_SESSION/$_COOKIE fallbacks |
| `app/Http/Middleware/EncryptCookies.php` | Remove SS_USER_ID, SS_ROLE |
| `app/Providers/AppServiceProvider.php` | Remove SS_USER_ID, SS_ROLE except call |
| `resources/views/dashboard.blade.php` | Update 'Open Admin Landing' button route |

**FILES TO DELETE:**

| File | Reason |
|---|---|
| `public/adminlanding_page.php` | Replaced by Blade view |
| `app/Http/Controllers/AdminLandingController.php` | No longer needed |
| `app/Legacy/auth.php` | No longer needed by superadmin (keep backup) |
| `app/Legacy/db.php` | No longer needed by superadmin (keep backup) |
| `public/auth.php` | No longer needed by superadmin (keep backup) |

**FILES TO KEEP (for sport admin proxy only):**
- `app/Legacy/auth.php` — Still needed by sport files via `public/auth.php`
- `app/Legacy/db.php` — Still needed by sport files via `public/db.php`
- `public/auth.php` — Thin wrapper for sport files
- `app/Http/Middleware/LegacySessionMiddleware.php` — Still used by legacy proxy routes
- `app/Http/Middleware/EnsureRole.php` — Still used by sport admin routes
- `app/Http/Middleware/AdminMatchScope.php` — Still used by sport feature
