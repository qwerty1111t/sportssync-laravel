# Admin Landing Rewrite — Incremental Build Plan

## Overview

Replace the 2539-line `public/adminlanding_page.php` with a clean Laravel Blade view at `resources/views/superadmin/admin-landing.blade.php`. Rewrite is broken into 4 incremental steps to avoid single-file size limits.

## Step 1: Create admin-landing.blade.php Part 1 — Layout + Overview page

**Files to create/modify:**
- `resources/views/superadmin/admin-landing.blade.php` (extends `layouts.landing`)

**Content:**
- Extends `layouts.landing` layout
- `@section('title', 'Admin Landing — SportSync')`
- `@push('styles')` — ALL CSS from adminlanding_page.php lines 526-1123
- Overview page HTML (stat cards, recent users, quick actions, sports status)
- CSRF meta tag using `@csrf` (NOT `$_SESSION`)
- Uses Blade `{{ route('superadmin.logout') }}` for logout
- Uses `{{ Auth::user()->username }}` instead of `$_SESSION`
- Stat values from `$totalUsers`, `$eventsThisMonth`, `$activeSportsCount`, `$sports`

## Step 2: admin-landing.blade.php Part 2 — Users page + Sports page + Match History

**Modify:**
- `resources/views/superadmin/admin-landing.blade.php` — Add sections after Overview

**Content:**
- Users management page with search/filter, toggle status, delete, change username, add user modal
- Sports management page with activate/deactivate toggles
- Match History page with cross-sport union table and filter buttons
- Pending Applicants (Committee Applications) panel
- All AJAX calls point to `route('superadmin.api.{action}')` or JS `fetch()` to new API routes

## Step 3: admin-landing.blade.php Part 3 — Activity Log + Feedback + Settings + JavaScript

**Modify:**
- `resources/views/superadmin/admin-landing.blade.php` — Add remaining pages

**Content:**
- Activity Log page with export CSV
- Feedback management page (fetches from `/api/feedbacks`)
- Settings page with maintenance mode toggle
- All inline JavaScript (WebSocket, page navigation, all AJAX functions)
- Mobile sidebar toggle

## Step 4: Update routes + middleware + cleanup

**Modify:**
- `routes/web.php` — Replace `/adminlanding_page.php` route with `/superadmin/admin-landing` route, add API routes for all CRUD operations
- Delete `app/Http/Controllers/AdminLandingController.php` (no longer needed)
- `resources/views/dashboard.blade.php` — Update "Open Admin Landing" button to use named route
- `resources/views/superadmin/dashboard.blade.php` — Add link to admin-landing from dashboard

## New Routes to Add (in routes/web.php)

```php
Route::middleware(['auth', 'superadmin', PreventBackHistory::class])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('admin-landing', [SuperadminController::class, 'index'])->name('admin-landing');
    
    // API routes (JSON)
    Route::post('api/approve-reject', [SuperadminController::class, 'approveRejectAdmin'])->name('api.approve-reject');
    Route::post('api/toggle-user-status', [SuperadminController::class, 'toggleUserStatus'])->name('api.toggle-user');
    Route::post('api/delete-user', [SuperadminController::class, 'deleteUser'])->name('api.delete-user');
    Route::post('api/change-username', [SuperadminController::class, 'changeUsername'])->name('api.change-username');
    Route::post('api/add-user', [SuperadminController::class, 'addUser'])->name('api.add-user');
    Route::post('api/toggle-sport-status', [SuperadminController::class, 'toggleSportStatus'])->name('api.toggle-sport');
    Route::post('api/save-setting', [SuperadminController::class, 'saveSystemSetting'])->name('api.save-setting');
    Route::get('api/export-activity-log', [SuperadminController::class, 'exportActivityLog'])->name('api.export-activity');
});
```

## Files to Delete

1. `app/Http/Controllers/AdminLandingController.php` — Replaced by SuperadminController@index
2. `public/adminlanding_page.php` — Replaced by Blade view (keep backup first)
3. `tools/test_legacy_direct.php` — No longer needed
4. `tools/test_legacy_access.php` — No longer needed

## Files to Modify for Cleanup

1. `app/Http/Middleware/EncryptCookies.php` — Remove `SS_USER_ID`, `SS_ROLE` from `$except`
2. `app/Providers/AppServiceProvider.php` — Remove `EncryptCookies::except()` call for legacy cookies

## Files to Keep (unchanged)

- `app/Legacy/auth.php` — Still needed by sport admin PHP files via `public/auth.php`
- `app/Legacy/db.php` — Still needed by sport admin PHP files via `public/db.php`
- `public/auth.php` — Legacy wrapper for sport files
- `app/Http/Middleware/LegacySessionMiddleware.php` — Still used by legacy proxy routes
- `app/Http/Middleware/EnsureRole.php` — Still needed for sport admin routes (but refactor to remove $_SESSION fallback)

## Verification Checklist

- [ ] GET `/superadmin/admin-landing` returns 200 for superadmin
- [ ] GET `/superadmin/admin-landing` returns 403 for non-superadmin
- [ ] GET `/superadmin/admin-landing` returns 302 to login for guests
- [ ] All CRUD operations work via POST API routes
- [ ] Activity log export downloads CSV
- [ ] No references to `$_SESSION['user_id']`, `$_SESSION['SS_USER_ID']`, `$_SESSION['SS_ROLE']` in any superadmin file
- [ ] No references to `session_start()` in any superadmin file
- [ ] Old `/adminlanding_page.php` route removed or redirects to new route
- [ ] Old `AdminLandingController` deleted
