@extends('layouts.landing')

@section('title', 'Admin Landing — SportSync')

@push('styles')
<style>
/* ═══════════════════════════════════════
   ADMIN LAYOUT
   ═══════════════════════════════════════ */
.admin-wrap {
  display: flex;
  min-height: 100vh;
  padding-top: var(--nav-h);
}

/* ── Sidebar ── */
.admin-sidebar {
  position: fixed;
  top: var(--nav-h);
  left: 0;
  bottom: 0;
  width: 240px;
  background: var(--black-mid);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 200;
  transition: transform 0.3s ease;
}
.sidebar-section { padding: 28px 20px 12px; }
.sidebar-label {
  font-family: var(--font-head);
  font-size: 0.65rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--gray);
  margin-bottom: 10px;
}
.sidebar-nav { display: flex; flex-direction: column; gap: 2px; }
.sidebar-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border-radius: 6px;
  font-family: var(--font-head);
  font-size: 0.88rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--gray-light);
  cursor: pointer;
  transition: all 0.22s ease;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  text-decoration: none;
}
.sidebar-link .s-icon { font-size: 1.05rem; width: 20px; text-align: center; flex-shrink: 0; }
.sidebar-link:hover { background: rgba(255,215,0,0.07); color: var(--white); }
.sidebar-link.active { background: rgba(255,215,0,0.12); color: var(--yellow); border-left: 2px solid var(--yellow); }
.sidebar-divider { height: 1px; background: var(--border); margin: 12px 20px; }
.sidebar-bottom { margin-top: auto; padding: 20px; border-top: 1px solid var(--border); }
.sidebar-user { display: flex; align-items: center; gap: 12px; }
.sidebar-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--yellow);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-head);
  font-weight: 700; font-size: 0.9rem;
  color: var(--black); flex-shrink: 0;
}
.sidebar-user-info { flex: 1; min-width: 0; }
.sidebar-user-name {
  font-family: var(--font-head);
  font-size: 0.85rem; font-weight: 600;
  color: var(--white); letter-spacing: 0.04em;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sidebar-user-role { font-size: 0.72rem; color: var(--yellow); letter-spacing: 0.08em; text-transform: uppercase; }

/* ── Main Content ── */
.admin-main {
  margin-left: 240px;
  flex: 1;
  padding: 40px 40px 60px;
  background: var(--black);
  min-height: calc(100vh - var(--nav-h));
}
.admin-page { display: none; animation: fadeIn 0.3s ease; }
.admin-page.active { display: block; }
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Page Header ── */
.admin-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 36px;
  flex-wrap: wrap;
  gap: 16px;
}
.admin-page-title {
  font-family: var(--font-head);
  font-size: clamp(1.8rem, 3vw, 2.6rem);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  line-height: 1.1;
}
.admin-page-title span { color: var(--yellow); }
.admin-breadcrumb { font-size: 0.78rem; color: var(--gray); margin-top: 6px; letter-spacing: 0.04em; }
.admin-breadcrumb strong { color: var(--yellow); }

/* ── Stat Cards ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 36px;
}
.stat-card {
  background: var(--black-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 28px 24px;
  position: relative;
  overflow: hidden;
  transition: var(--transition);
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--yellow);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.35s ease;
}
.stat-card:hover { box-shadow: var(--shadow-hover); border-color: rgba(255,215,0,0.2); }
.stat-card:hover::before { transform: scaleX(1); }
.stat-icon { font-size: 1.8rem; margin-bottom: 14px; display: block; }
.stat-value {
  font-family: var(--font-head);
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1;
  color: var(--white);
  margin-bottom: 6px;
  letter-spacing: 0.02em;
}
.stat-label { font-size: 0.8rem; color: var(--gray); letter-spacing: 0.08em; text-transform: uppercase; font-family: var(--font-head); }
.stat-sub { font-size: 0.72rem; color: var(--gray); margin-top: 4px; }

/* ── Content Grid ── */
.admin-content-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  align-items: start;
}
.admin-content-grid.full { grid-template-columns: 1fr; }

/* ── Panel / Table ── */
.admin-panel {
  background: var(--black-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 24px;
}
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}
.panel-title {
  font-family: var(--font-head);
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--white);
  display: flex;
  align-items: center;
  gap: 10px;
}
.panel-title .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--yellow); display: inline-block; }
.panel-action {
  font-family: var(--font-head);
  font-size: 0.72rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--yellow);
  cursor: pointer;
  background: none;
  border: 1px solid rgba(255,215,0,0.25);
  padding: 5px 12px;
  border-radius: 4px;
  transition: all 0.22s ease;
}
.panel-action:hover { background: rgba(255,215,0,0.1); }

/* Table */
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th {
  font-family: var(--font-head);
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--gray);
  padding: 14px 24px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  background: rgba(255,255,255,0.02);
}
.admin-table td {
  padding: 14px 24px;
  font-size: 0.88rem;
  color: var(--gray-light);
  border-bottom: 1px solid rgba(255,215,0,0.05);
  vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover td { background: rgba(255,215,0,0.03); }
.dt-empty { text-align: center; color: var(--gray); padding: 28px !important; font-style: italic; }

.user-cell { display: flex; align-items: center; gap: 12px; }
.user-avatar-sm {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-head);
  font-size: 0.8rem; font-weight: 700;
  flex-shrink: 0;
}
.user-name { color: var(--white); font-weight: 500; }
.user-meta { font-size: 0.75rem; color: var(--gray); }

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-family: var(--font-head);
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 600;
}
.badge-admin      { background: rgba(255,215,0,0.15);  color: var(--yellow); border: 1px solid rgba(255,215,0,0.3); }
.badge-superadmin { background: rgba(255,140,0,0.15);  color: #ffb347;       border: 1px solid rgba(255,140,0,0.3); }
.badge-viewer     { background: rgba(21,101,192,0.15); color: #64b5f6;       border: 1px solid rgba(21,101,192,0.3); }
.badge-scorer     { background: rgba(0,200,83,0.12);   color: #69f0ae;       border: 1px solid rgba(0,200,83,0.25); }
.badge-active     { background: rgba(0,200,83,0.12);   color: #00c853;       border: 1px solid rgba(0,200,83,0.25); }
.badge-deactivated{ background: rgba(255,82,82,0.1);   color: #ff7675;       border: 1px solid rgba(255,82,82,0.2); }
.badge-inactive   { background: rgba(255,82,82,0.1);   color: #ff7675;       border: 1px solid rgba(255,82,82,0.2); }

/* Action buttons */
.table-action-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--gray);
  font-size: 0.95rem;
  padding: 5px 7px;
  border-radius: 4px;
  transition: all 0.2s ease;
}
.table-action-btn:hover:not(:disabled) { color: var(--yellow); background: rgba(255,215,0,0.1); }
.table-action-btn.danger:hover { color: #ff5252; background: rgba(255,82,82,0.1); }
.table-action-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* Inline edit */
.uname-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.uname-display { color: var(--white); font-weight: 500; }
.uname-field {
  display: none;
  padding: 5px 9px;
  border-radius: 5px;
  border: 1px solid var(--yellow);
  background: rgba(255,255,255,0.06);
  color: var(--white);
  font-size: 0.84rem;
  width: 140px;
  outline: none;
}
.uname-msg { font-size: 0.7rem; }
.btn-save-u, .btn-cancel-u {
  display: none;
  padding: 4px 10px;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  font-size: 0.72rem;
  font-weight: 700;
  font-family: var(--font-head);
  letter-spacing: 0.06em;
}
.btn-save-u   { background: #1565C0; color: #fff; }
.btn-cancel-u { background: rgba(255,255,255,0.08); color: var(--gray); }

/* Activity feed */
.activity-feed { display: flex; flex-direction: column; }
.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255,215,0,0.05);
  transition: background 0.2s;
}
.activity-item:last-child { border-bottom: none; }
.activity-item:hover { background: rgba(255,215,0,0.02); }
.activity-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; }
.activity-body { flex: 1; }
.activity-text { font-size: 0.84rem; color: var(--gray-light); line-height: 1.5; }
.activity-text strong { color: var(--white); }
.activity-time { font-size: 0.72rem; color: var(--gray); margin-top: 3px; }

/* Sport status list */
.sport-status-list { display: flex; flex-direction: column; }
.sport-status-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  border-bottom: 1px solid rgba(255,215,0,0.05);
}
.sport-status-item:last-child { border-bottom: none; }
.sport-name { display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: var(--gray-light); }

/* Quick actions */
.quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 20px 24px; }
.quick-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 18px 12px;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.25s ease;
  text-decoration: none;
  color: var(--gray-light);
}
.quick-btn .q-icon { font-size: 1.5rem; }
.quick-btn .q-label { font-family: var(--font-head); font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; text-align: center; }
.quick-btn:hover { border-color: rgba(255,215,0,0.3); background: rgba(255,215,0,0.06); color: var(--yellow); transform: translateY(-2px); }

/* Toolbar */
.toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.search-input {
  flex: 1; min-width: 200px;
  background: var(--black-card);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px 16px;
  font-family: var(--font-body);
  font-size: 0.88rem;
  color: var(--white);
  outline: none;
  transition: border-color 0.25s ease;
}
.search-input::placeholder { color: var(--gray); }
.search-input:focus { border-color: rgba(255,215,0,0.4); }
.filter-select {
  background: var(--black-card);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px 14px;
  font-family: var(--font-head);
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  color: var(--gray-light);
  cursor: pointer;
  outline: none;
}
.filter-select:focus { border-color: rgba(255,215,0,0.4); }

/* Modal */
.modal-bg {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.8);
  z-index: 600;
  align-items: center;
  justify-content: center;
}
.modal-bg.open { display: flex; }
.modal-box {
  background: #1a1a2e;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 32px 36px;
  min-width: 380px;
  max-width: 480px;
  width: 95%;
}
.modal-title {
  font-family: var(--font-head);
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--yellow);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 22px;
}
.fg { margin-bottom: 16px; }
.fg label {
  display: block;
  font-size: 0.72rem;
  color: var(--gray);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 6px;
  font-family: var(--font-head);
}
.fg input, .fg select {
  width: 100%;
  padding: 10px 14px;
  background: rgba(255,255,255,0.06);
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--white);
  font-size: 0.9rem;
  outline: none;
}
.fg input:focus, .fg select:focus { border-color: rgba(255,215,0,0.5); }
.modal-err { color: #ff5252; font-size: 0.78rem; min-height: 18px; margin: 6px 0 10px; }
.modal-foot { display: flex; gap: 10px; margin-top: 20px; }
.btn-primary {
  flex: 1; padding: 11px;
  border: none; border-radius: 7px;
  background: var(--yellow); color: #000;
  font-family: var(--font-head);
  font-weight: 700; cursor: pointer;
  font-size: 0.88rem; letter-spacing: 0.06em;
  text-transform: uppercase;
}
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary {
  padding: 11px 20px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: transparent;
  color: var(--gray-light);
  cursor: pointer;
  font-size: 0.88rem;
  font-family: var(--font-body);
}

/* Settings */
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.settings-group { display: flex; flex-direction: column; gap: 16px; padding: 24px; }
.settings-row {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.05);
}
.settings-row:last-child { border-bottom: none; padding-bottom: 0; }
.settings-key { font-size: 0.88rem; color: var(--white); }
.settings-hint { font-size: 0.75rem; color: var(--gray); margin-top: 3px; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; inset: 0; background: rgba(255,255,255,0.12); border-radius: 24px; cursor: pointer; transition: 0.25s; }
.toggle-slider::before { content: ''; position: absolute; width: 18px; height: 18px; top: 3px; left: 3px; border-radius: 50%; background: var(--white); transition: 0.25s; }
.toggle-switch input:checked + .toggle-slider { background: var(--yellow); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); background: var(--black); }

/* DB error banner */
.db-err {
  background: rgba(255,82,82,0.1);
  border: 1px solid rgba(255,82,82,0.3);
  border-radius: 8px;
  padding: 10px 16px;
  margin-bottom: 16px;
  color: #ff7675;
  font-size: 0.82rem;
}

/* Match history filter buttons */
.match-filter-btn {
  background: rgba(255,255,255,0.05);
  border: 1px solid var(--border);
  color: var(--gray);
  padding: 4px 11px;
  border-radius: 4px;
  font-family: var(--font-head);
  font-size: 0.68rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.match-filter-btn:hover { border-color: rgba(255,215,0,0.35); color: var(--white); background: rgba(255,215,0,0.06); }
.match-filter-btn.active { background: rgba(255,215,0,0.12); border-color: rgba(255,215,0,0.45); color: var(--yellow); }

/* Maintenance mode admin banner */
#ss-maint-banner {
  position: fixed; top: var(--nav-h, 60px); left: 0; right: 0; z-index: 9999;
  background: #7c2d12; color: #fed7aa;
  padding: 10px 48px 10px 20px;
  font-family: Arial, sans-serif; font-size: 13px; font-weight: 700;
  border-bottom: 2px solid #f97316;
  display: flex; align-items: center; gap: 10px;
}

/* Mobile */
.sidebar-toggle {
  display: none;
  position: fixed;
  bottom: 24px; right: 24px;
  z-index: 500;
  background: var(--yellow);
  color: var(--black);
  border: none;
  border-radius: 50%;
  width: 52px; height: 52px;
  font-size: 1.4rem;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(255,215,0,0.4);
}

@media (max-width: 1100px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .admin-content-grid { grid-template-columns: 1fr; }
  .settings-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .admin-sidebar { transform: translateX(-100%); }
  .admin-sidebar.open { transform: translateX(0); box-shadow: 8px 0 40px rgba(0,0,0,0.6); }
  .admin-main { margin-left: 0; padding: 28px 20px 60px; }
  .sidebar-toggle { display: flex; align-items: center; justify-content: center; }
}
@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr; }
  .quick-actions { grid-template-columns: 1fr 1fr; }
}

/* Mobile Responsiveness */
.mobile-header {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: var(--nav-bg);
  z-index: 1001;
  padding: 10px 20px;
  border-bottom: 1px solid var(--border);
}
.mobile-menu-btn {
  background: var(--primary);
  color: var(--primary-fg);
  border: none;
  padding: 10px 15px;
  border-radius: var(--radius);
  font-size: 1rem;
  cursor: pointer;
  font-family: var(--font-head);
  font-weight: 600;
}
.sidebar-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  z-index: 999;
}
@media (max-width: 768px) {
  .mobile-header { display: block; }
  .admin-sidebar {
    position: fixed;
    left: -280px;
    top: 50px;
    height: calc(100vh - 50px);
    z-index: 1000;
    transition: left 0.3s ease;
    overflow-y: auto;
  }
  .admin-sidebar.open {
    left: 0;
  }
  .admin-main {
    margin-left: 0;
  }
}
</style>
@endpush

{{-- ═══════════════════════════════════════════════════════════ --}}
{{--  CURRENT USER ID  --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
@section('main')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>window.__SS_CURRENT_USER_ID = {{ Auth::id() }};</script>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{--  NAVBAR  --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a href="{{ url('/') }}" class="nav-logo">
      <img src="/logo.png" alt="SportSync" class="logo-img">
    </a>
    <ul class="nav-links" id="nav-links">
      <li><a href="{{ url('/superadmin/landing') }}" class="nav-link">Home</a></li>
      <li><a href="{{ url('/') }}" class="nav-link active">Dashboard</a></li>
    </ul>
    <div class="nav-auth">
      @if (!empty($pendingCount) && $pendingCount > 0)
        <a href="#" id="pendingApplicantsBtn" style="margin-right:14px;color:var(--yellow);font-weight:700;text-decoration:none;">
          🔔 Applications <span style="background:var(--yellow);color:#000;border-radius:12px;padding:2px 8px;margin-left:8px;font-weight:700;">{{ $pendingCount }}</span>
        </a>
      @endif
      <span class="nav-user">👤 {{ Auth::user()->username }}</span>
      <form id="logout-form" method="POST" action="{{ route('superadmin.logout') }}" style="display:inline">
        @csrf
        <button type="submit" class="nav-auth-btn nav-logout">Sign Out</button>
      </form>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{--  ADMIN LAYOUT  --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<div class="admin-wrap">

  {{-- ── Sidebar ── --}}
  <aside class="admin-sidebar" id="adminSidebar">

    <div class="sidebar-section">
      <div class="sidebar-label">Main</div>
      <nav class="sidebar-nav">
        <button class="sidebar-link active" data-page="overview">
          <span class="s-icon">📊</span> Overview
        </button>
        <button class="sidebar-link" data-page="users">
          <span class="s-icon">👥</span> Users
        </button>
        <button class="sidebar-link" data-page="sports">
          <span class="s-icon">🏆</span> Sports
        </button>
        <button class="sidebar-link" data-page="matches">
          <span class="s-icon">🎮</span> Pending Users & Match Logs
        </button>
        <button class="sidebar-link" data-page="feedback">
          <span class="s-icon">💬</span> Feedback
        </button>
        <button class="sidebar-link" data-page="activity">
          <span class="s-icon">📋</span> Activity Log
        </button>
      </nav>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-label">Matches</div>
      <nav class="sidebar-nav">
        <a href="/badminton-admin" class="sidebar-link">
          <span class="s-icon">🏸</span> Badminton Matches
        </a>
        <a href="/basketball-admin" class="sidebar-link">
          <span class="s-icon">🏀</span> Basketball Matches
        </a>
        <a href="/darts-admin" class="sidebar-link">
          <span class="s-icon">🎯</span> Darts Matches
        </a>
        <a href="/tabletennis-admin" class="sidebar-link">
          <span class="s-icon">🏓</span> Table Tennis Matches
        </a>
        <a href="/volleyball-admin" class="sidebar-link">
          <span class="s-icon">🏐</span> Volleyball Matches
        </a>
      </nav>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-section">
      <div class="sidebar-label">System</div>
      <nav class="sidebar-nav">
        <button class="sidebar-link" data-page="settings">
          <span class="s-icon">⚙️</span> Settings
        </button>
        <a href="{{ url('/') }}" class="sidebar-link">
          <span class="s-icon">🏠</span> Landing Page
        </a>
      </nav>
    </div>

    <div class="sidebar-bottom">
      <div class="sidebar-user">
        <div class="sidebar-avatar">{{ strtoupper(substr(Auth::user()->username, 0, 1)) }}</div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name">{{ Auth::user()->username }}</div>
          <div class="sidebar-user-role">{{ Auth::user()->role ?? 'superadmin' }}</div>
        </div>
      </div>
    </div>

  </aside>

  {{-- ── Main Content ── --}}
  <main class="admin-main">

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: OVERVIEW  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page active" id="page-overview">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">Admin <span>Overview</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Dashboard</strong> &mdash; {{ date('l, F j, Y') }}</p>
        </div>
        <span style="font-size:0.78rem;color:var(--gray);font-family:var(--font-head);letter-spacing:0.06em;" id="liveTime"></span>
      </div>

      @if (!empty($dbError))
        <div class="db-err">⚠️ DB Warning: {{ is_array($dbError) ? implode(' | ', $dbError) : $dbError }}</div>
      @endif

      {{-- Stat Cards --}}
      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-icon">👥</span>
          <div class="stat-value">{{ $totalUsers }}</div>
          <div class="stat-label">Total Users</div>
          <div class="stat-sub">All registered accounts</div>
        </div>
        <div class="stat-card">
          <span class="stat-icon">📅</span>
          <div class="stat-value">{{ $eventsThisMonth }}</div>
          <div class="stat-label">Events This Month</div>
          <div class="stat-sub">{{ date('F Y') }}</div>
        </div>
        <div class="stat-card">
          <span class="stat-icon">🏅</span>
          <div class="stat-value">{{ $activeSportsCount }}<span style="font-size:1rem;color:var(--gray);font-weight:400;">/{{ count($sports) }}</span></div>
          <div class="stat-label">Sports Active</div>
          <div class="stat-sub">{{ count($allMatches) }}+ total matches on record</div>
        </div>
      </div>

      <div class="admin-content-grid">
        {{-- Recent Users Table --}}
        <div class="admin-panel">
          <div class="panel-header">
            <span class="panel-title"><span class="dot"></span>Recent Users</span>
            <button class="panel-action" onclick="navigate('users')">View All</button>
          </div>
          <table class="admin-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              @if (empty($users))
                <tr><td colspan="4" class="dt-empty">No users found.</td></tr>
              @else
                @foreach ($users->take(10) as $ru)
                  @php
                    $initial = strtoupper(substr($ru['username'] ?? $ru->username, 0, 1));
                    $role = $ru['role'] ?? $ru->role ?? 'viewer';
                    $status = $ru['status'] ?? $ru->status ?? 'active';
                    $username = $ru['username'] ?? $ru->username ?? '';
                    $createdAt = $ru['created_at'] ?? $ru->created_at ?? '—';
                    $bgColor = ($role === 'admin' || $role === 'superadmin') ? '#FFD700' : '#1565C0';
                    $fgColor = ($role === 'admin' || $role === 'superadmin') ? '#000' : '#fff';
                  @endphp
                  <tr>
                    <td>
                      <div class="user-cell">
                        <div class="user-avatar-sm" style="background:{{ $bgColor }};color:{{ $fgColor }};">{{ $initial }}</div>
                        <div><div class="user-name">{{ $username }}</div></div>
                      </div>
                    </td>
                    <td><span class="badge badge-{{ $role }}">{{ ucfirst($role) }}</span></td>
                    <td><span class="badge badge-{{ $status }}">{{ ucfirst($status) }}</span></td>
                    <td style="color:var(--gray);font-size:0.8rem;">{{ is_string($createdAt) ? substr($createdAt, 0, 10) : '—' }}</td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>

        {{-- Right column --}}
        <div style="display:flex;flex-direction:column;gap:24px;">
          {{-- Quick Actions --}}
          <div class="admin-panel">
            <div class="panel-header">
              <span class="panel-title"><span class="dot"></span>Quick Actions</span>
            </div>
            <div class="quick-actions">
              <button class="quick-btn" onclick="navigate('users')">
                <span class="q-icon">👥</span>
                <span class="q-label">Manage Users</span>
              </button>
              <button class="quick-btn" onclick="navigate('matches')">
                <span class="q-icon">🎮</span>
                <span class="q-label">Match History</span>
              </button>
              <button class="quick-btn" onclick="navigate('activity')">
                <span class="q-icon">📋</span>
                <span class="q-label">Activity Log</span>
              </button>
              <button class="quick-btn" onclick="navigate('settings')">
                <span class="q-icon">⚙️</span>
                <span class="q-label">Settings</span>
              </button>
            </div>
          </div>

          {{-- Sports Status --}}
          @if (!empty($sports))
          <div class="admin-panel">
            <div class="panel-header">
              <span class="panel-title"><span class="dot"></span>Sports Status</span>
              <button class="panel-action" onclick="navigate('sports')">Manage</button>
            </div>
            <div class="sport-status-list">
              @foreach ($sports as $sp)
                @php
                  $spName = $sp['name'] ?? $sp->name ?? '';
                  $spStatus = $sp['status'] ?? $sp->status ?? 'inactive';
                  $isAct = ($spStatus === 'active');
                  $sportEmojis = ['badminton' => '🏸', 'basketball' => '🏀', 'darts' => '🎯', 'table tennis' => '🏓', 'volleyball' => '🏐'];
                  $em = $sportEmojis[strtolower($spName)] ?? '🏅';
                @endphp
                <div class="sport-status-item">
                  <div class="sport-name">
                    <span>{{ $em }}</span>
                    {{ $spName }}
                  </div>
                  <span class="badge badge-{{ $isAct ? 'active' : 'inactive' }}">{{ $isAct ? 'Active' : 'Inactive' }}</span>
                </div>
              @endforeach
            </div>
          </div>
          @endif
        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: USERS  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page" id="page-users">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">User <span>Management</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Users</strong></p>
        </div>
        <button class="panel-action" id="addUserBtn" onclick="document.getElementById('addUserModal').classList.add('open')">+ Add User</button>
      </div>

      @if (!empty($dbError))
        <div class="db-err">⚠️ DB Warning: {{ is_array($dbError) ? implode(' | ', $dbError) : $dbError }}</div>
      @endif

      {{-- Toolbar --}}
      <div class="toolbar">
        <input type="text" class="search-input" id="userSearch" placeholder="Search users…" onkeyup="filterUsers()">
        <select class="filter-select" id="roleFilter" onchange="filterUsers()">
          <option value="">All Roles</option>
          <option value="superadmin">Superadmin</option>
          <option value="admin">Admin</option>
          <option value="scorer">Scorer</option>
          <option value="viewer">Viewer</option>
        </select>
        <select class="filter-select" id="statusFilter" onchange="filterUsers()">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="deactivated">Deactivated</option>
        </select>
      </div>

      {{-- Users Table --}}
      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>All Users</span>
        </div>
        <table class="admin-table" id="usersTable">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($users as $u)
              @php
                $uid = $u['id'] ?? $u->id ?? 0;
                $uname = $u['username'] ?? $u->username ?? '';
                $urole = $u['role'] ?? $u->role ?? 'viewer';
                $ustatus = $u['status'] ?? $u->status ?? 'active';
                $ucreated = $u['created_at'] ?? $u->created_at ?? '—';
                $uinitial = strtoupper(substr($uname, 0, 1));
                $ubgColor = ($urole === 'admin' || $urole === 'superadmin') ? '#FFD700' : '#1565C0';
                $ufgColor = ($urole === 'admin' || $urole === 'superadmin') ? '#000' : '#fff';
              @endphp
              <tr id="urow-{{ $uid }}" data-role="{{ $urole }}" data-status="{{ $ustatus }}">
                <td>
                  <div class="user-cell">
                    <div class="user-avatar-sm" style="background:{{ $ubgColor }};color:{{ $ufgColor }};">{{ $uinitial }}</div>
                    <div>
                      <div class="uname-wrap">
                        <span class="uname-display" id="udisplay-{{ $uid }}">{{ $uname }}</span>
                        <input type="text" class="uname-field" id="ufield-{{ $uid }}" value="{{ $uname }}">
                        <button class="btn-save-u" id="ubtn-save-{{ $uid }}" onclick="saveUsername({{ $uid }})">Save</button>
                        <button class="btn-cancel-u" id="ubtn-cancel-{{ $uid }}" onclick="cancelEdit({{ $uid }})">Cancel</button>
                        <span class="uname-msg" id="umsg-{{ $uid }}"></span>
                      </div>
                      <div class="user-meta">ID: {{ $uid }}</div>
                    </div>
                  </div>
                </td>
                <td><span class="badge badge-{{ $urole }}">{{ ucfirst($urole) }}</span></td>
                <td><span class="badge badge-{{ $ustatus }}" id="ustatus-{{ $uid }}">{{ ucfirst($ustatus) }}</span></td>
                <td style="color:var(--gray);font-size:0.8rem;">{{ is_string($ucreated) ? substr($ucreated, 0, 10) : '—' }}</td>
                <td>
                  <button class="table-action-btn" id="ubtn-edit-{{ $uid }}" onclick="editUsername({{ $uid }})" title="Edit username">✏️</button>
                  <button class="table-action-btn" id="utoggle-{{ $uid }}" onclick="toggleUserStatus({{ $uid }})" title="Activate / Deactivate">
                    @if ($ustatus === 'active')
                      🔴
                    @else
                      🟢
                    @endif
                  </button>
                  <button class="table-action-btn danger" id="udel-{{ $uid }}" onclick="deleteUser({{ $uid }})" title="Delete user">🗑️</button>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="dt-empty">No users found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Pending Admin Applicants --}}
      @if (!empty($pendingApplicants))
      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>Pending Admin Applicants</span>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Applied</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($pendingApplicants as $app)
              @php
                $appId = $app['id'] ?? $app->id ?? 0;
                $appName = $app['username'] ?? $app->username ?? '';
                $appRole = $app['role'] ?? $app->role ?? 'viewer';
                $appCreated = $app['created_at'] ?? $app->created_at ?? '—';
                $appInitial = strtoupper(substr($appName, 0, 1));
              @endphp
              <tr id="app-{{ $appId }}">
                <td>
                  <div class="user-cell">
                    <div class="user-avatar-sm" style="background:#FFD700;color:#000;">{{ $appInitial }}</div>
                    <div class="user-name">{{ $appName }}</div>
                  </div>
                </td>
                <td><span class="badge badge-{{ $appRole }}">{{ ucfirst($appRole) }}</span></td>
                <td style="color:var(--gray);font-size:0.8rem;">{{ is_string($appCreated) ? substr($appCreated, 0, 10) : '—' }}</td>
                <td>
                  <button class="panel-action" onclick="approveApplicant({{ $appId }})" style="margin-right:6px;">✅ Approve</button>
                  <button class="panel-action" onclick="rejectApplicant({{ $appId }})" style="color:#ff7675;border-color:rgba(255,82,82,0.3);">❌ Reject</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: SPORTS  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page" id="page-sports">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">Sports <span>Management</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Sports</strong></p>
        </div>
      </div>

      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>All Sports</span>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Sport</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @php
              $sportEmojiMap = [
                'Basketball' => '🏀',
                'Volleyball' => '🏐',
                'Badminton'  => '🏸',
                'Table Tennis' => '🏓',
                'Darts'      => '🎯',
              ];
            @endphp
            @forelse ($sports as $sp)
              @php
                $spId = $sp['id'] ?? $sp->id ?? 0;
                $spName = $sp['name'] ?? $sp->name ?? '';
                $spStatus = $sp['status'] ?? $sp->status ?? 'inactive';
                $spActive = ($spStatus === 'active');
                $spEmoji = $sportEmojiMap[$spName] ?? '🏅';
              @endphp
              <tr id="sprow-{{ $spId }}">
                <td>
                  <div class="sport-name">
                    <span style="font-size:1.4rem;">{{ $spEmoji }}</span>
                    <span style="color:var(--white);font-weight:500;">{{ $spName }}</span>
                  </div>
                </td>
                <td><span class="badge badge-{{ $spStatus }}" id="spstatus-{{ $spId }}">{{ ucfirst($spStatus) }}</span></td>
                <td>
                  <button class="table-action-btn" id="sptoggle-{{ $spId }}" onclick="toggleSport({{ $spId }})" title="Toggle active status">
                    @if ($spActive)
                      🔴 Deactivate
                    @else
                      🟢 Activate
                    @endif
                  </button>
                  <span class="uname-msg" id="spmsg-{{ $spId }}"></span>
                </td>
              </tr>
            @empty
              <tr><td colspan="3" class="dt-empty">No sports found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: MATCH HISTORY  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page" id="page-matches">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">Match <span>History</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Matches</strong></p>
        </div>
      </div>

      {{-- Filter Buttons --}}
      <div class="toolbar" style="margin-bottom:24px;">
        <button class="match-filter-btn active" data-sport="all" onclick="filterMatches('all')">All</button>
        <button class="match-filter-btn" data-sport="Basketball" onclick="filterMatches('Basketball')">🏀 Basketball</button>
        <button class="match-filter-btn" data-sport="Volleyball" onclick="filterMatches('Volleyball')">🏐 Volleyball</button>
        <button class="match-filter-btn" data-sport="Badminton" onclick="filterMatches('Badminton')">🏸 Badminton</button>
        <button class="match-filter-btn" data-sport="Table Tennis" onclick="filterMatches('Table Tennis')">🏓 Table Tennis</button>
        <button class="match-filter-btn" data-sport="Darts" onclick="filterMatches('Darts')">🎯 Darts</button>
      </div>

      {{-- Pending Applicants (shown on Matches page as well) --}}
      @if (!empty($pendingApplicants))
      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>Committee Applications</span>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Applicant</th>
              <th>Role</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($pendingApplicants as $app)
              @php
                $appId = $app['id'] ?? $app->id ?? 0;
                $appName = $app['username'] ?? $app->username ?? '';
                $appRole = $app['role'] ?? $app->role ?? 'viewer';
                $appCreated = $app['created_at'] ?? $app->created_at ?? '—';
                $appInitial = strtoupper(substr($appName, 0, 1));
              @endphp
              <tr id="app-{{ $appId }}">
                <td>
                  <div class="user-cell">
                    <div class="user-avatar-sm" style="background:#FFD700;color:#000;">{{ $appInitial }}</div>
                    <div class="user-name">{{ $appName }}</div>
                  </div>
                </td>
                <td><span class="badge badge-{{ $appRole }}">{{ ucfirst($appRole) }}</span></td>
                <td style="color:var(--gray);font-size:0.8rem;">{{ is_string($appCreated) ? substr($appCreated, 0, 10) : '—' }}</td>
                <td>
                  <button class="panel-action" onclick="approveApplicant({{ $appId }})" style="margin-right:6px;">✅ Approve</button>
                  <button class="panel-action" onclick="rejectApplicant({{ $appId }})" style="color:#ff7675;border-color:rgba(255,82,82,0.3);">❌ Reject</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif

      {{-- All Matches Table --}}
      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>All Matches</span>
        </div>
        <table class="admin-table" id="matchesTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Sport</th>
              <th>Teams / Players</th>
              <th>Score / Result</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            @php $matchIndex = 0; @endphp
            @forelse ($allMatches as $m)
              @php
                $matchIndex++;
                $mSport = $m['sport'] ?? $m->sport ?? '';
                $mTeams = $m['teams'] ?? $m->teams ?? ($m['team1'] ?? $m->team1 ?? '') . ' vs ' . ($m['team2'] ?? $m->team2 ?? '');
                $mScore = $m['score'] ?? $m->score ?? ($m['result'] ?? $m->result ?? '—');
                $mDate  = $m['date'] ?? $m->date ?? ($m['created_at'] ?? $m->created_at ?? '—');
                $mEmoji = $sportEmojiMap[$mSport] ?? '🏅';
              @endphp
              <tr data-sport="{{ $mSport }}">
                <td style="color:var(--gray);">{{ $matchIndex }}</td>
                <td>{{ $mEmoji }} {{ $mSport }}</td>
                <td>{{ $mTeams }}</td>
                <td>{{ $mScore }}</td>
                <td style="color:var(--gray);font-size:0.8rem;">{{ is_string($mDate) ? substr($mDate, 0, 10) : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="dt-empty">No matches found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: ACTIVITY LOG  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page" id="page-activity">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">Activity <span>Log</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Activity</strong></p>
        </div>
        <button class="panel-action" onclick="exportActivityLog()">⬇ Export CSV</button>
      </div>

      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>Recent Activity</span>
          <span style="font-family:var(--font-head);font-size:0.72rem;color:var(--gray);">Last 50 entries</span>
        </div>
        <div class="activity-feed">
          @forelse ($activityLog as $log)
            @php
              $ac = strtolower($log->action ?? $log['action'] ?? '');
              $dotColor = '#00c853';
              if (str_contains($ac, 'delet') || str_contains($ac, 'deactivat')) $dotColor = '#ff5252';
              elseif (str_contains($ac, 'creat') || str_contains($ac, 'activat') || str_contains($ac, 'login')) $dotColor = '#00c853';
              elseif (str_contains($ac, 'chang') || str_contains($ac, 'updat') || str_contains($ac, 'sport')) $dotColor = '#FFD700';
              elseif (str_contains($ac, 'logout')) $dotColor = '#888';
              $username = $log->username ?? $log['username'] ?? '';
              $action = $log->action ?? $log['action'] ?? '';
              $timestamp = $log->timestamp ?? $log['timestamp'] ?? '';
            @endphp
            <div class="activity-item">
              <div class="activity-dot" style="background:{{ $dotColor }};"></div>
              <div class="activity-body">
                <div class="activity-text"><strong>{{ $username }}</strong> — {{ $action }}</div>
                <div class="activity-time">{{ $timestamp }}</div>
              </div>
            </div>
          @empty
            <div style="padding:28px;text-align:center;color:var(--gray);font-style:italic;">No activity logged yet.</div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: FEEDBACK  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page" id="page-feedback">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">User <span>Feedback</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Feedback</strong></p>
        </div>
        <span style="font-family:var(--font-head);font-size:0.82rem;color:var(--gray);">All submissions</span>
      </div>

      <div class="toolbar">
        <input type="text" class="search-input" id="feedbackSearch" placeholder="Search by name or email…" onkeyup="filterFeedback()">
        <select class="filter-select" id="feedbackStatusFilter" onchange="filterFeedback()">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="resolved">Resolved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <div class="admin-panel">
        <div class="panel-header">
          <span class="panel-title"><span class="dot"></span>All Feedback</span>
          <span id="feedbackCountLabel" style="font-family:var(--font-head);font-size:0.72rem;color:var(--gray);letter-spacing:0.08em;">0 Total</span>
        </div>
        <table class="admin-table" id="feedbackTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Message</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="feedbackTbody">
            <tr><td colspan="6" class="dt-empty">Loading feedback...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  PAGE: SETTINGS  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="admin-page" id="page-settings">
      <div class="admin-page-header">
        <div>
          <h1 class="admin-page-title">System <span>Settings</span></h1>
          <p class="admin-breadcrumb">SportSync / <strong>Settings</strong></p>
        </div>
      </div>

      <div class="settings-grid">
        <div class="admin-panel">
          <div class="panel-header"><span class="panel-title"><span class="dot"></span>General</span></div>
          <div class="settings-group">
            <div class="settings-row" id="maintenanceRow">
              <div>
                <div class="settings-key">Maintenance Mode</div>
                <div class="settings-hint" id="maintenanceHint">
                  @if ($maintenanceMode === '1')
                    <span style="color:#ff7675;font-weight:700;">⚠️ ACTIVE — All viewer & admin pages are blocked for non-admins</span>
                  @else
                    Take the app offline for maintenance
                  @endif
                </div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" id="maintenanceModeToggle"
                       {{ $maintenanceMode === '1' ? 'checked' : '' }}
                       onchange="toggleMaintenanceMode(this.checked)">
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="admin-panel">
          <div class="panel-header"><span class="panel-title"><span class="dot"></span>Security</span></div>
          <div class="settings-group">
            <div class="settings-row">
              <div>
                <div class="settings-key">Session Timeout</div>
                <div class="settings-hint">Auto-logout after 30 minutes idle (coming soon)</div>
              </div>
              <label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label>
            </div>
            <div class="settings-row">
              <div>
                <div class="settings-key">Login Attempt Limit</div>
                <div class="settings-hint">Lock account after 5 failed attempts (coming soon)</div>
              </div>
              <label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label>
            </div>
            <div class="settings-row">
              <div>
                <div class="settings-key">Activity Logging</div>
                <div class="settings-hint">Log all admin and user actions (coming soon)</div>
              </div>
              <label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label>
            </div>
          </div>
        </div>

        <div class="admin-panel" style="border-color:rgba(255,82,82,0.2);">
          <div class="panel-header" style="border-color:rgba(255,82,82,0.12);">
            <span class="panel-title" style="color:#ff5252;"><span class="dot" style="background:#ff5252;"></span>Danger Zone</span>
          </div>
          <div class="settings-group">
            <div class="settings-row">
              <div>
                <div class="settings-key">Clear Activity Logs</div>
                <div class="settings-hint">Permanently delete all logs (coming soon)</div>
              </div>
              <button class="btn-secondary" style="opacity:0.5;cursor:not-allowed;" disabled>Clear Logs</button>
            </div>
            <div class="settings-row" style="border-bottom:none;padding-bottom:0;">
              <div>
                <div class="settings-key">Reset All Scores</div>
                <div class="settings-hint">Wipe all game scores from the system (coming soon)</div>
              </div>
              <button class="btn-secondary" style="opacity:0.5;cursor:not-allowed;" disabled>Reset</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  ADD USER MODAL  --}}
    {{-- ══════════════════════════════════════ --}}
    <div class="modal-bg" id="addUserModal">
      <div class="modal-box">
        <div class="modal-title">➕ Add New User</div>
        <div class="fg">
          <label>Username</label>
          <input type="text" id="newUserName" placeholder="Enter username">
        </div>
        <div class="fg">
          <label>Email</label>
          <input type="email" id="newUserEmail" placeholder="Enter email address">
        </div>
        <div class="fg">
          <label>Password</label>
          <input type="password" id="newUserPassword" placeholder="Enter password">
        </div>
        <div class="fg">
          <label>Role</label>
          <select id="newUserRole">
            <option value="viewer">Viewer</option>
            <option value="scorer">Scorer</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="modal-err" id="addUserErr"></div>
        <div class="modal-foot">
          <button class="btn-secondary" onclick="document.getElementById('addUserModal').classList.remove('open')">Cancel</button>
          <button class="btn-primary" onclick="addUser()">Create User</button>
        </div>
      </div>
    </div>

    {{-- ══════════════════════════════════════ --}}
    {{--  MOBILE SIDEBAR TOGGLE  --}}
    {{-- ══════════════════════════════════════ --}}
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">☰</button>

  </main>
</div>

@push('scripts')
<script>
'use strict';

// ── CSRF token for fetch API ──
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ── AJAX helper: POST JSON to a route ──
async function apiPost(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': CSRF_TOKEN,
    },
    body: JSON.stringify(data),
    credentials: 'same-origin',
  });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

// ── Page navigation ──
function navigate(pageId) {
  document.querySelectorAll('.admin-page').forEach(function(p) { p.classList.remove('active'); });
  var target = document.getElementById('page-' + pageId);
  if (target) target.classList.add('active');
  document.querySelectorAll('.sidebar-link').forEach(function(l) {
    l.classList.toggle('active', l.getAttribute('data-page') === pageId);
  });
}

// ── Mobile sidebar ──
function toggleSidebar() {
  document.getElementById('adminSidebar').classList.toggle('open');
  document.querySelector('.sidebar-overlay').classList.toggle('open');
}

// ── Live clock ──
function updateClock() {
  var el = document.getElementById('liveTime');
  if (!el) return;
  var now = new Date();
  el.textContent = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateClock, 1000);
updateClock();

// ── XSS escape ──
function esc(s) {
  if (typeof s !== 'string') return s;
  var div = document.createElement('div');
  div.appendChild(document.createTextNode(s));
  return div.innerHTML;
}

// ── Users: filter ──
function filterUsers() {
  var q = (document.getElementById('userSearch')?.value || '').toLowerCase();
  var roleF = document.getElementById('roleFilter')?.value || '';
  var statusF = document.getElementById('statusFilter')?.value || '';
  document.querySelectorAll('#usersTable tbody tr').forEach(function(tr) {
    if (tr.classList.contains('dt-empty')) return;
    var text = tr.textContent.toLowerCase();
    var role = (tr.getAttribute('data-role') || '').toLowerCase();
    var status = (tr.getAttribute('data-status') || '').toLowerCase();
    var match = (!q || text.includes(q)) && (!roleF || role === roleF) && (!statusF || status === statusF);
    tr.style.display = match ? '' : 'none';
  });
}

// ── Users: toggle status ──
async function toggleUserStatus(uid) {
  try {
    var res = await apiPost('/superadmin/admin-landing/toggle-user-status', { user_id: uid });
    if (res.success) {
      var badge = document.getElementById('ustatus-' + uid);
      var btn = document.getElementById('utoggle-' + uid);
      var row = document.getElementById('urow-' + uid);
      if (res.new_status === 'deactivated') {
        if (badge) { badge.textContent = 'Deactivated'; badge.className = 'badge badge-deactivated'; }
        if (btn) btn.textContent = '🟢';
        if (row) row.setAttribute('data-status', 'deactivated');
      } else {
        if (badge) { badge.textContent = 'Active'; badge.className = 'badge badge-active'; }
        if (btn) btn.textContent = '🔴';
        if (row) row.setAttribute('data-status', 'active');
      }
    } else {
      alert(res.error || 'Failed to toggle status.');
    }
  } catch (e) {
    alert('Error toggling status: ' + e.message);
  }
}

// ── Users: approve applicant ──
async function approveApplicant(uid) {
  if (!confirm('Approve this applicant?')) return;
  try {
    var res = await apiPost('/superadmin/admin-landing/approve-reject', { user_id: uid, action: 'approve' });
    if (res.success) {
      var el = document.getElementById('app-' + uid);
      if (el) el.remove();
      // update pending count if present
      var badge = document.querySelector('#pendingApplicantsBtn span');
      if (badge) {
        var c = parseInt(badge.textContent) - 1;
        badge.textContent = c;
        if (c <= 0) document.getElementById('pendingApplicantsBtn')?.remove();
      }
    } else {
      alert(res.error || 'Failed to approve.');
    }
  } catch (e) {
    alert('Error approving: ' + e.message);
  }
}

// ── Users: reject applicant ──
async function rejectApplicant(uid) {
  if (!confirm('Reject this applicant?')) return;
  try {
    var res = await apiPost('/superadmin/admin-landing/approve-reject', { user_id: uid, action: 'reject' });
    if (res.success) {
      var el = document.getElementById('app-' + uid);
      if (el) el.remove();
      var badge = document.querySelector('#pendingApplicantsBtn span');
      if (badge) {
        var c = parseInt(badge.textContent) - 1;
        badge.textContent = c;
        if (c <= 0) document.getElementById('pendingApplicantsBtn')?.remove();
      }
    } else {
      alert(res.error || 'Failed to reject.');
    }
  } catch (e) {
    alert('Error rejecting: ' + e.message);
  }
}

// ── Users: delete ──
async function deleteUser(uid) {
  if (!confirm('Permanently delete this user? This action cannot be undone.')) return;
  try {
    var res = await apiPost('/superadmin/admin-landing/delete-user', { user_id: uid });
    if (res.success) {
      var el = document.getElementById('urow-' + uid);
      if (el) el.remove();
    } else {
      alert(res.error || 'Failed to delete user.');
    }
  } catch (e) {
    alert('Error deleting: ' + e.message);
  }
}

// ── Users: inline edit username ──
function startEdit(uid) {
  document.getElementById('udisplay-' + uid).style.display = 'none';
  document.getElementById('ufield-' + uid).style.display = 'inline-block';
  document.getElementById('ubtn-save-' + uid).style.display = 'inline-block';
  document.getElementById('ubtn-cancel-' + uid).style.display = 'inline-block';
  document.getElementById('umsg-' + uid).textContent = '';
  document.getElementById('ubtn-edit-' + uid).style.display = 'none';
}

function cancelEdit(uid) {
  document.getElementById('udisplay-' + uid).style.display = '';
  document.getElementById('ufield-' + uid).style.display = 'none';
  document.getElementById('ubtn-save-' + uid).style.display = 'none';
  document.getElementById('ubtn-cancel-' + uid).style.display = 'none';
  document.getElementById('umsg-' + uid).textContent = '';
  document.getElementById('ubtn-edit-' + uid).style.display = '';
}

function editUsername(uid) { startEdit(uid); }

async function saveUsername(uid) {
  var field = document.getElementById('ufield-' + uid);
  var newName = field.value.trim();
  if (!newName) { document.getElementById('umsg-' + uid).textContent = 'Username required.'; return; }
  try {
    var res = await apiPost('/superadmin/admin-landing/change-username', { user_id: uid, new_username: newName });
    if (res.success) {
      document.getElementById('udisplay-' + uid).textContent = newName;
      document.getElementById('umsg-' + uid).textContent = '✅ Saved';
      document.getElementById('umsg-' + uid).style.color = '#00c853';
      cancelEdit(uid);
    } else {
      document.getElementById('umsg-' + uid).textContent = res.error || 'Failed';
      document.getElementById('umsg-' + uid).style.color = '#ff5252';
    }
  } catch (e) {
    document.getElementById('umsg-' + uid).textContent = 'Error: ' + e.message;
    document.getElementById('umsg-' + uid).style.color = '#ff5252';
  }
}

// ── Add User modal ──
function openAddUser() {
  document.getElementById('addUserModal').classList.add('open');
}
function closeAddUser() {
  document.getElementById('addUserModal').classList.remove('open');
}

async function submitAddUser() {
  var username = document.getElementById('newUserName').value.trim();
  var password = document.getElementById('newUserPassword').value;
  var role = document.getElementById('newUserRole').value;
  var errEl = document.getElementById('addUserErr');
  if (!username || !password) { errEl.textContent = 'Username and password required.'; return; }
  try {
    var res = await apiPost('/superadmin/admin-landing/add-user', { username: username, password: password, role: role });
    if (res.success) {
      closeAddUser();
      location.reload();
    } else {
      errEl.textContent = res.error || 'Failed to create user.';
    }
  } catch (e) {
    errEl.textContent = 'Error: ' + e.message;
  }
}

// alias for onclick compatibility
function addUser() { submitAddUser(); }

// ── Sports: toggle sport status ──
async function toggleSport(spId) {
  try {
    var res = await apiPost('/superadmin/admin-landing/toggle-sport-status', { sport_id: spId });
    if (res.success) {
      var badge = document.getElementById('spstatus-' + spId);
      var btn = document.getElementById('sptoggle-' + spId);
      var msg = document.getElementById('spmsg-' + spId);
      if (res.new_status === 'active') {
        if (badge) { badge.textContent = 'Active'; badge.className = 'badge badge-active'; }
        if (btn) btn.innerHTML = '🔴 Deactivate';
      } else {
        if (badge) { badge.textContent = 'Inactive'; badge.className = 'badge badge-inactive'; }
        if (btn) btn.innerHTML = '🟢 Activate';
      }
      if (msg) { msg.textContent = res.message || '✅ Updated'; msg.style.color = '#00c853'; setTimeout(function() { msg.textContent = ''; }, 3000); }
    } else {
      alert(res.error || 'Failed to toggle sport.');
    }
  } catch (e) {
    alert('Error toggling sport: ' + e.message);
  }
}

// ── Match History filter ──
function filterMatches(sport, btn) {
  document.querySelectorAll('.match-filter-btn').forEach(function(b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  document.querySelectorAll('#matchesTable tbody tr').forEach(function(tr) {
    if (tr.classList.contains('dt-empty')) return;
    tr.style.display = (sport === 'all' || tr.getAttribute('data-sport') === sport) ? '' : 'none';
  });
}

// ── Feedback ──
let allFeedback = [];

async function loadFeedback() {
  var tbody = document.getElementById('feedbackTbody');
  if (!tbody) return;
  try {
    var r = await fetch('/api/feedbacks', { headers: { 'Accept': 'application/json' } });
    if (!r.ok) { tbody.innerHTML = '<tr><td colspan="6" class="dt-empty">Failed to load feedback.</td></tr>'; return; }
    var data = await r.json();
    allFeedback = data.feedbacks || data || [];
    renderFeedback();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="dt-empty">Error loading feedback: ' + esc(e.message) + '</td></tr>';
  }
}

function renderFeedback() {
  var tbody = document.getElementById('feedbackTbody');
  if (!tbody) return;
  var q = (document.getElementById('feedbackSearch')?.value || '').toLowerCase();
  var statusF = document.getElementById('feedbackStatusFilter')?.value || '';
  var filtered = allFeedback.filter(function(f) {
    var name = (f.name || f.Name || '').toLowerCase();
    var email = (f.email || f.Email || '').toLowerCase();
    var s = (f.status || f.Status || '').toLowerCase();
    return (!q || name.includes(q) || email.includes(q)) && (!statusF || s === statusF);
  });
  document.getElementById('feedbackCountLabel').textContent = filtered.length + ' Total';
  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="dt-empty">No feedback found.</td></tr>';
    return;
  }
  tbody.innerHTML = filtered.map(function(f) {
    var id = f.id || f.ID || 0;
    var name = esc(f.name || f.Name || '');
    var email = esc(f.email || f.Email || '');
    var message = esc(f.message || f.Message || f.msg || '');
    var status = (f.status || f.Status || 'pending').toLowerCase();
    var date = esc(f.created_at || f.CreatedAt || f.date || '');
    if (date && date.length > 10) date = date.substring(0, 10);
    return '<tr id="fbrow-' + id + '">'
      + '<td>' + name + '</td>'
      + '<td>' + email + '</td>'
      + '<td style="max-width:280px;white-space:normal;word-break:break-word;">' + message + '</td>'
      + '<td><span class="badge badge-' + status + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span></td>'
      + '<td style="font-size:0.8rem;color:var(--gray);">' + date + '</td>'
      + '<td>' + (status === 'pending'
          ? '<button class="panel-action" onclick="updateFeedbackStatus(' + id + ', \'resolved\')" style="margin-right:4px;">✅ Resolve</button><button class="panel-action" onclick="updateFeedbackStatus(' + id + ', \'rejected\')" style="color:#ff7675;">❌ Reject</button>'
          : '<span style="font-size:0.75rem;color:var(--gray);">' + (status === 'resolved' ? '✅' : '❌') + ' ' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>') + '</td>'
      + '</tr>';
  }).join('');
}

function filterFeedback() { renderFeedback(); }

async function updateFeedbackStatus(id, status) {
  try {
    var res = await apiPost('/api/feedbacks/' + id + '/status', { status: status });
    if (res.success) {
      var f = allFeedback.find(function(x) { return (x.id || x.ID) === id; });
      if (f) { f.status = status; f.Status = status; }
      renderFeedback();
    } else {
      alert(res.error || 'Failed to update feedback status.');
    }
  } catch (e) {
    alert('Error updating feedback: ' + e.message);
  }
}

// ── Settings: Maintenance Mode ──
async function toggleMaintenanceMode(checked) {
  try {
    var val = checked ? '1' : '0';
    var res = await apiPost('/superadmin/admin-landing/save-setting', { key: 'maintenance_mode', value: val });
    if (res.success) {
      var hint = document.getElementById('maintenanceHint');
      if (hint) {
        if (val === '1') {
          hint.innerHTML = '<span style="color:#ff7675;font-weight:700;">⚠️ ACTIVE — All viewer & admin pages are blocked for non-admins</span>';
        } else {
          hint.textContent = 'Take the app offline for maintenance';
        }
      }
      _syncMaintenanceBanner(val === '1');
    } else {
      alert(res.error || 'Failed to save setting.');
      // revert toggle
      document.getElementById('maintenanceModeToggle').checked = !checked;
    }
  } catch (e) {
    alert('Error saving setting: ' + e.message);
    document.getElementById('maintenanceModeToggle').checked = !checked;
  }
}

function _syncMaintenanceBanner(on) {
  var existing = document.getElementById('ss-maint-banner');
  if (on) {
    if (!existing) {
      var b = document.createElement('div');
      b.id = 'ss-maint-banner';
      b.innerHTML = '🔧 Maintenance Mode is ON — Only superadmins can access the dashboard.';
      document.body.prepend(b);
    }
  } else {
    if (existing) existing.remove();
  }
}

// ── Export Activity Log ──
function exportActivityLog() {
  window.open('/superadmin/admin-landing/export-activity-log', '_blank');
}

// ── Pending applicants button handler ──
document.addEventListener('DOMContentLoaded', function() {
  var pendingBtn = document.getElementById('pendingApplicantsBtn');
  if (pendingBtn) {
    pendingBtn.addEventListener('click', function(e) {
      e.preventDefault();
      navigate('matches');
      // scroll to the committee applications panel
      var appPanel = document.querySelector('#page-matches .admin-panel');
      if (appPanel) appPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  // Load feedback on Feedback page navigation
  loadFeedback();
});

// ── WebSocket Listener ──
(function() {
  try {
    var wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    var wsHost = window.location.hostname;
    var wsPort = '6080';
    var wsUrl = wsProto + '//' + wsHost + ':' + wsPort;
    var ws = new WebSocket(wsUrl);

    ws.onopen = function() {
      console.log('[SS Admin] WebSocket connected');
    };

    ws.onmessage = function(evt) {
      try {
        var msg = JSON.parse(evt.data);
        // Handle score updates, match changes, etc.
        if (msg.type === 'score_update' || msg.type === 'match_update') {
          // Could auto-refresh or show a toast
          console.log('[SS Admin] Live update:', msg);
        }
      } catch (e) {
        // ignore non-JSON messages
      }
    };

    ws.onerror = function() {
      console.warn('[SS Admin] WebSocket connection error');
    };

    ws.onclose = function() {
      console.log('[SS Admin] WebSocket disconnected');
      // Optionally reconnect after a delay
      setTimeout(function() {
        // could attempt reconnect
      }, 5000);
    };
  } catch (e) {
    console.warn('[SS Admin] WebSocket not available:', e.message);
  }
})();

// ── BroadcastChannel fallback ──
(function() {
  try {
    if (typeof BroadcastChannel !== 'undefined') {
      var bc = new BroadcastChannel('sportssync_updates');
      bc.onmessage = function(evt) {
        console.log('[SS Admin] BroadcastChannel update:', evt.data);
        if (evt.data && evt.data.type === 'score_update') {
          // Handle cross-tab sync
        }
      };
    }
  } catch (e) {
    // BroadcastChannel not supported
  }
})();
</script>
@endpush

@endsection
