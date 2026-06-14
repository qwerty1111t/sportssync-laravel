@extends('layouts.landing')

@section('title', 'Superadmin Dashboard — SportSync')

@push('styles')
<style>
    .admin-dash {
        padding: 40px 24px;
        max-width: 1400px;
        margin: 0 auto;
    }
    .admin-dash h1 {
        font-size: 2.2rem;
        margin-bottom: 8px;
    }
    .admin-dash .subtitle {
        color: #888;
        margin-bottom: 32px;
    }
    .admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 20px;
        margin-bottom: 48px;
    }
    .admin-card {
        background: #1a1a2e;
        border: 1px solid #2a2a4e;
        border-radius: 12px;
        padding: 24px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .admin-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(255, 215, 0, 0.08);
    }
    .admin-card h3 {
        font-size: 1.2rem;
        margin-bottom: 8px;
        color: #FFD700;
    }
    .admin-card p {
        color: #999;
        font-size: 0.9rem;
        margin-bottom: 16px;
    }
    .admin-card .btn {
        display: inline-block;
        padding: 8px 20px;
        background: #FFD700;
        color: #0a0a0a;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .admin-card .btn:hover {
        background: #ffed4a;
    }
    .section-title {
        font-size: 1.4rem;
        margin: 32px 0 16px;
        color: #FFD700;
        border-bottom: 1px solid #2a2a4e;
        padding-bottom: 8px;
    }
    .user-table {
        width: 100%;
        border-collapse: collapse;
        background: #1a1a2e;
        border-radius: 12px;
        overflow: hidden;
    }
    .user-table th {
        background: #2a2a4e;
        color: #FFD700;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
    }
    .user-table td {
        padding: 10px 16px;
        border-bottom: 1px solid #2a2a4e;
        color: #ccc;
    }
    .user-table tr:last-child td {
        border-bottom: none;
    }
    .user-table .role-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .role-badge.superadmin {
        background: #FFD700;
        color: #0a0a0a;
    }
    .role-badge.admin {
        background: #3b82f6;
        color: #fff;
    }
    .role-badge.viewer {
        background: #6b7280;
        color: #fff;
    }
    .promote-form {
        display: inline;
    }
    .promote-form button {
        background: transparent;
        color: #FFD700;
        border: 1px solid #FFD700;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8rem;
    }
    .promote-form button:hover {
        background: #FFD700;
        color: #0a0a0a;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 500;
    }
    .alert-success {
        background: #065f46;
        color: #6ee7b7;
        border: 1px solid #059669;
    }
    .alert-error {
        background: #7f1d1d;
        color: #fca5a5;
        border: 1px solid #dc2626;
    }
</style>
@endpush

@section('content')
<div class="admin-dash">
    <h1>Superadmin Dashboard</h1>
    <p class="subtitle">Welcome, {{ Auth::user()->username ?? Auth::user()->name ?? 'Admin' }} (role: <strong>{{ Auth::user()->role }}</strong>)</p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="section-title">Sport Management</div>
    <div class="admin-grid">
        <div class="admin-card">
            <h3>🏀 Basketball</h3>
            <p>Manage basketball matches, scores, and live state.</p>
            <a class="btn" href="/basketball-admin">Manage</a>
        </div>
        <div class="admin-card">
            <h3>🏐 Volleyball</h3>
            <p>Manage volleyball matches, scores, and live state.</p>
            <a class="btn" href="/volleyball-admin">Manage</a>
        </div>
        <div class="admin-card">
            <h3>🏸 Badminton</h3>
            <p>Manage badminton matches, sets, and live state.</p>
            <a class="btn" href="/badminton-admin">Manage</a>
        </div>
        <div class="admin-card">
            <h3>🏓 Table Tennis</h3>
            <p>Manage table tennis matches, legs, and live state.</p>
            <a class="btn" href="/tabletennis-admin">Manage</a>
        </div>
        <div class="admin-card">
            <h3>🎯 Darts</h3>
            <p>Manage darts matches, legs, throws, and live state.</p>
            <a class="btn" href="/darts-admin">Manage</a>
        </div>
    </div>

    <div class="section-title">Analytics</div>
    <div class="admin-grid">
        <div class="admin-card">
            <h3>📊 Analytics</h3>
            <p>View cross-sport analytics and statistics.</p>
            <a class="btn" href="/analytics/analytics.php">View</a>
        </div>
        <div class="admin-card">
            <h3>👥 Players</h3>
            <p>View player analytics and performance data.</p>
            <a class="btn" href="/analytics/players.php">View</a>
        </div>
    </div>

    <div class="section-title">User Management</div>
    @if(isset($users) && count($users) > 0)
    <table class="user-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
            <tr>
                <td>{{ $u->id }}</td>
                <td>{{ $u->username ?? $u->name ?? '-' }}</td>
                <td>{{ $u->email ?? '-' }}</td>
                <td>
                    <span class="role-badge {{ strtolower((string)($u->role ?? 'viewer')) }}">
                        {{ $u->role ?? 'viewer' }}
                    </span>
                </td>
                <td>{{ $u->status ?? 'active' }}</td>
                <td>
                    @if(strtolower((string)($u->role ?? '')) !== 'superadmin')
                    <form class="promote-form" method="POST" action="{{ route('superadmin.users.promote') }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                        <button type="submit" onclick="return confirm('Promote {{ $u->username ?? $u->name }} to superadmin?')">Promote</button>
                    </form>
                    @else
                    <span style="color:#666;font-size:0.8rem;">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="color:#888;">Unable to load users list.</p>
    @endif

    <div style="margin-top: 48px; text-align: center;">
        <form method="POST" action="{{ route('superadmin.logout') }}" style="display:inline;">
            @csrf
            <button type="submit" style="background: transparent; color: #ff3b30; border: 1px solid #ff3b30; padding: 10px 28px; border-radius: 6px; cursor: pointer; font-size: 0.95rem;">
                Sign Out
            </button>
        </form>
    </div>
</div>
@endsection
