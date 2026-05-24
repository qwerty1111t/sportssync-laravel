// ============================================================
// basketball_viewer.js — Live read-only viewer
// REWRITE: zero-delay join, real-time timers, no manual reload
//
// SYNC ARCHITECTURE
// ─────────────────
// Every device that joins gets the live state immediately via
// a three-layer fallback, in priority order:
//
//   1. WebSocket (ws-server on :3000) — primary real-time path
//      • On open: joins room, requests last_state, fetches
//        timers from timer.php immediately.
//      • Handles: timer_update, basketball_state, last_state,
//        applied_action, room_state, reset_match, new_match.
//
//   2. No BroadcastChannel/localStorage timer fallback.
//      • The WebSocket server is the only real-time sync layer.
//
// TIMER ENGINE
// ────────────
// Timers run as a single requestAnimationFrame loop (_raf).
// The loop only renders the latest value received from WebSocket.
// It does not locally count down or recalculate authoritative time.
//
// DB POLLING
// ──────────
// Disabled. Timer state must not be polled or broadcast through fallback state layers.
//
// ON JOIN / REJOIN
// ────────────────
// 1. localStorage cache renders immediately (scores + roster).
// 2. state.php fetches the authoritative roster + scores.
// 3. timer.php fetches the authoritative timer state.
// 4. WS send {type:'join'} + {type:'get_state'} — server
//    pushes back last_state which has timers embedded.
// ============================================================

'use strict';

// ── Constants ────────────────────────────────────────────────
const STORAGE_KEY      = 'basketballLiveState';
const TIMER_KEY        = 'basketballTimerState';
const CHANNEL_NAME     = 'basketball_live';
const SC_CIRCUMFERENCE = 2 * Math.PI * 52;
const POLL_INTERVAL_MS = 500;   // timer.php poll when running
const IDLE_POLL_MS     = 5000;  // timer.php poll when stopped (keepalive)
const WS_MAX_BACKOFF   = 30000;

// ── Timer anchor state ────────────────────────────────────────
// All timer values live here. The RAF loop reads these every frame.
const _gt = {
  remaining: 600, // seconds at anchor
  anchorTs:  null, // Date.now() when remaining was recorded
  running:   false,
  total:     600,
  loopId:    null
};
const _sc = {
  remaining: 24,
  anchorTs:  null,
  running:   false,
  total:     24,
  loopId:    null
};

// Monotone display clamp — prevents upward jumps while running
const _prevDisplay = { gtSec: null, scSec: null };

// ── RAF loop ─────────────────────────────────────────────────
// Single persistent loop — started once and never stopped.
// Reads _gt / _sc every frame.
let _rafId = null;

function _liveGT() {
  if (_gt.running && _gt.anchorTs !== null) {
    return Math.max(0, _gt.remaining - (Date.now() - _gt.anchorTs) / 1000);
  }
  return Math.max(0, _gt.remaining);
}

function _liveSC() {
  if (_sc.running && _sc.anchorTs !== null) {
    return Math.max(0, _sc.remaining - (Date.now() - _sc.anchorTs) / 1000);
  }
  return Math.max(0, _sc.remaining);
}

function _rafLoop() {
  _rafId = requestAnimationFrame(_rafLoop);
  const gtRem = _liveGT();
  const scRem = _liveSC();
  _renderGameTimer(gtRem, _gt.running);
  _renderShotClock(scRem, _sc.running, _sc.total);

  // Auto-stop when expired
  if (_gt.running && gtRem <= 0) {
    _gt.running  = false;
    _gt.anchorTs = null;
    _prevDisplay.gtSec = null;
  }
  if (_sc.running && scRem <= 0) {
    _sc.running  = false;
    _sc.anchorTs = null;
    _prevDisplay.scSec = null;
  }
}

function _startRAF() {
  if (_rafId) return;
  _rafId = requestAnimationFrame(_rafLoop);
}
_startRAF(); // start immediately on load

// ── Safe anchor-ts clamp ─────────────────────────────────────
// Ensures we never compute negative elapsed time from a future ts.
function _safeTs(ts) {
  if (typeof ts !== 'number' || !isFinite(ts) || ts <= 0) return null;
  return Math.min(ts, Date.now());
}

// ── Apply timer payload from any source ──────────────────────
// Called only from WebSocket messages.
// Updates the anchor objects. RAF loop picks up the new values next frame.
function applyTimerPayload(payload) {
  if (!payload) return;

  const gt = payload.gameTimer  || payload.game_timer  || null;
  const sc = payload.shotClock  || payload.shot_clock  || null;

  if (gt && typeof gt.remaining === 'number') {
    const total      = typeof gt.total   === 'number' ? gt.total   : _gt.total;
    const running    = typeof gt.running === 'boolean' ? gt.running : _gt.running;
    const safeTs     = _safeTs(gt.ts);
    const wasRunning = _gt.running;

    // Clamp remaining to [0, total]
    let rem = Math.max(0, Math.min(gt.remaining, total > 0 ? total : 600));
    if (running && safeTs !== null) {
      rem = Math.max(0, rem - (Date.now() - safeTs) / 1000);
    }

    _gt.total     = total;
    _gt.remaining = rem;
    _gt.anchorTs  = running ? (safeTs !== null ? safeTs : Date.now()) : null;
    _gt.running   = running && rem > 0;

    // Reset display clamp on any stop→start or reset
    if (running && !wasRunning) _prevDisplay.gtSec = null;
    if (!running && wasRunning) _prevDisplay.gtSec = null;
  }

  if (sc && typeof sc.remaining === 'number') {
    const total      = typeof sc.total   === 'number' ? sc.total   : _sc.total;
    const running    = typeof sc.running === 'boolean' ? sc.running : _sc.running;
    const safeTs     = _safeTs(sc.ts);
    const wasRunning = _sc.running;

    let rem = Math.max(0, Math.min(sc.remaining, total > 0 ? total : 24));

    if (running && safeTs !== null) {
      rem = Math.max(0, rem - (Date.now() - safeTs) / 1000);
    }

    _sc.total     = total;
    _sc.remaining = rem;
    _sc.anchorTs  = running ? (safeTs !== null ? safeTs : Date.now()) : null;
    _sc.running   = running && rem > 0;

    if (running && !wasRunning) _prevDisplay.scSec = null;
    if (!running && wasRunning) _prevDisplay.scSec = null;
  }
}

// ── Render: game timer ────────────────────────────────────────
function _renderGameTimer(remaining, running) {
  const el    = _el('gtTime');
  const block = _el('gtBlock');
  if (!el) return;

  remaining = Math.max(0, Math.min(remaining, _gt.total > 0 ? _gt.total : 600));
  const dispSec = Math.floor(remaining);

  // Monotone clamp: suppress upward jumps while running
  if (running && _prevDisplay.gtSec !== null && dispSec > _prevDisplay.gtSec + 1) {
    // network artifact — hold last displayed value
  } else {
    _prevDisplay.gtSec = dispSec;
  }

  const sec = _prevDisplay.gtSec !== null ? _prevDisplay.gtSec : dispSec;
  const txt = String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
  if (el.textContent !== txt) el.textContent = txt;

  const expired = remaining <= 0;
  const cls = 'gt-time' + (expired ? ' expired' : remaining <= 60 ? ' danger' : '');
  if (el.className !== cls) el.className = cls;

  if (block) {
    const bcls = 'game-timer-block' + (expired ? ' gt-expired' : running && remaining <= 60 ? ' gt-danger' : running ? ' gt-running' : '');
    if (block.className !== bcls) block.className = bcls;
  }
}

// ── Render: shot clock ────────────────────────────────────────
function _renderShotClock(remaining, running, total) {
  const timeEl  = _el('scTime');
  const tenthEl = _el('scTenth');
  const ringEl  = _el('scRing');
  const block   = _el('scBlock');
  if (!timeEl) return;

  total     = total > 0 ? total : 24;
  remaining = Math.max(0, Math.min(remaining, total));

  let candidate = Math.ceil(remaining - 1e-6);
  if (running && _prevDisplay.scSec !== null && candidate > _prevDisplay.scSec + 1) {
    // suppress upward jump
  } else {
    _prevDisplay.scSec = candidate;
  }
  const disp    = _prevDisplay.scSec !== null ? _prevDisplay.scSec : candidate;
  const tenths  = (remaining % 1).toFixed(1).slice(1);
  const expired = remaining <= 0;

  const timeTxt = expired ? '0' : String(disp);
  if (timeEl.textContent !== timeTxt) timeEl.textContent = timeTxt;

  const tcls = 'sc-time' + (expired ? ' expired' : remaining <= 5 ? ' danger' : '');
  if (timeEl.className !== tcls) timeEl.className = tcls;

  if (tenthEl) {
    const tt = (!expired && remaining < 10) ? tenths : '';
    if (tenthEl.textContent !== tt) tenthEl.textContent = tt;
  }

  if (ringEl) {
    const pct    = Math.max(0, remaining / total);
    const offset = SC_CIRCUMFERENCE * (1 - pct);
    ringEl.style.strokeDashoffset = offset;
    ringEl.style.stroke = expired ? '#e74c3c' : remaining <= 5 ? '#e74c3c' : remaining <= total * 0.5 ? '#e67e22' : '#F5C518';
  }

  if (block) {
    const bcls = 'shot-clock-block' + (expired ? ' sc-expired' : running && remaining <= 5 ? ' sc-danger' : running ? ' sc-running' : '');
    if (block.className !== bcls) block.className = bcls;
  }

  const lbl = document.getElementById('presetLabel');
  if (lbl && lbl.textContent !== total + 's') lbl.textContent = total + 's';
}

// ── DB polling ────────────────────────────────────────────────
// Polls timer.php at POLL_INTERVAL_MS when running,
// IDLE_POLL_MS when both stopped (safety net for missed WS msgs).
let _pollTimer = null;

function _scheduleNextPoll() {
  // Disabled: WebSocket server is the only timer sync engine.
  return;
}

function _doPoll() {
  // Disabled: clients must not poll timer.php for real-time timer state.
  return;
}

// ── DOM element cache ─────────────────────────────────────────
const _elCache = {};
function _el(id) {
  if (!id) return null;
  if (_elCache[id] === undefined) _elCache[id] = document.getElementById(id) || null;
  return _elCache[id];
}

function _setText(id, val) {
  const el = _el(id);
  if (!el) return;
  const s = val == null ? '' : String(val);
  if (el.textContent !== s) el.textContent = s;
}

// ── Roster row cache ─────────────────────────────────────────
// Incremental DOM updates — never rebuilds the whole table.
const _rowMap     = { A: {}, B: {} };
const _lastRoster = { A: null, B: null };

function _createRow(p) {
  const tr = document.createElement('tr');
  tr.className = 'player-main-row';
  tr.dataset.playerId = String(p.id);

  const tdNo = document.createElement('td'); tdNo.className = 'td-no'; tdNo.textContent = p.no || '';
  const tdNm = document.createElement('td'); tdNm.className = 'td-name'; tdNm.textContent = p.name || '—';
  tr.appendChild(tdNo); tr.appendChild(tdNm);

  const stats = {};
  ['pts','foul','reb','ast','blk','stl'].forEach(function(stat) {
    const td = document.createElement('td');
    if (stat === 'pts') td.className = 'pts-cell';
    const sp = document.createElement('span');
    sp.className = 'stat-val';
    sp.textContent = p[stat] != null ? p[stat] : 0;
    td.appendChild(sp); tr.appendChild(td);
    stats[stat] = sp;
  });

  const tdTF  = document.createElement('td');
  const tfSp  = document.createElement('span'); tfSp.className = 'stat-val tf-val'; tfSp.textContent = p.techFoul || 0;
  tdTF.appendChild(tfSp); tr.appendChild(tdTF);

  let techTr = null;
  if (p.techFoul > 0 || p.techReason) {
    techTr = _buildTechRow(p);
  }

  return { id: String(p.id), main: tr, tech: techTr, elems: { no: tdNo, name: tdNm, stats, tf: tfSp,
    techCountEl: techTr ? techTr.querySelector('.tech-count-val') : null,
    techReasonEl: techTr ? techTr.querySelector('.tech-reason-display') : null
  }};
}

function _buildTechRow(p) {
  const techTr = document.createElement('tr'); techTr.className = 'player-tech-row';
  const techTd = document.createElement('td'); techTd.colSpan = 9;
  const inner  = document.createElement('div'); inner.className = 'tech-inner';
  const lbl    = document.createElement('span'); lbl.className = 'tech-label'; lbl.textContent = 'Tech Foul:';
  const val    = document.createElement('span'); val.className = 'tech-count-val'; val.textContent = p.techFoul || 0;
  const reason = document.createElement('span'); reason.className = 'tech-reason-display'; reason.textContent = p.techReason || '';
  inner.appendChild(lbl); inner.appendChild(val); inner.appendChild(reason);
  techTd.appendChild(inner); techTr.appendChild(techTd);
  return techTr;
}

function _updateRow(row, p) {
  if (!row || !p) return;
  const set = function(el, v) { const s = v == null ? '' : String(v); if (el && el.textContent !== s) el.textContent = s; };
  set(row.elems.no,   p.no || '');
  set(row.elems.name, p.name || '—');
  ['pts','foul','reb','ast','blk','stl'].forEach(function(stat) { set(row.elems.stats[stat], p[stat] != null ? p[stat] : 0); });
  set(row.elems.tf, p.techFoul || 0);

  const hasTech = p.techFoul > 0 || !!p.techReason;
  if (hasTech && !row.tech) {
    row.tech = _buildTechRow(p);
    row.elems.techCountEl  = row.tech.querySelector('.tech-count-val');
    row.elems.techReasonEl = row.tech.querySelector('.tech-reason-display');
    if (row.main && row.main.parentNode) row.main.parentNode.insertBefore(row.tech, row.main.nextSibling);
  } else if (!hasTech && row.tech) {
    if (row.tech.parentNode) row.tech.parentNode.removeChild(row.tech);
    row.tech = null; row.elems.techCountEl = null; row.elems.techReasonEl = null;
  } else if (row.tech) {
    set(row.elems.techCountEl,  p.techFoul || 0);
    set(row.elems.techReasonEl, p.techReason || '');
  }
}

function _renderRoster(team, players) {
  const tbody = _el('tbody' + team);
  if (!tbody) return;

  try {
    const raw = JSON.stringify(players || []);
    if (_lastRoster[team] === raw) return;
    _lastRoster[team] = raw;
  } catch(e) { _lastRoster[team] = null; }

  const map = _rowMap[team];
  const ids = [];
  (players || []).forEach(function(p) {
    const id = String(p.id);
    ids.push(id);
    if (!map[id]) map[id] = _createRow(p);
    _updateRow(map[id], p);
  });

  // Remove stale rows
  Object.keys(Object.assign({}, map)).forEach(function(eid) {
    if (ids.indexOf(eid) === -1) {
      const r = map[eid];
      if (r.main && r.main.parentNode) r.main.parentNode.removeChild(r.main);
      if (r.tech && r.tech.parentNode) r.tech.parentNode.removeChild(r.tech);
      delete map[eid];
    }
  });

  // Reorder to match incoming order (moves existing nodes, no recreation)
  const frag = document.createDocumentFragment();
  ids.forEach(function(id) {
    const r = map[id];
    if (!r) return;
    frag.appendChild(r.main);
    if (r.tech) frag.appendChild(r.tech);
  });
  tbody.appendChild(frag);
}

// ── Score flash ───────────────────────────────────────────────
const _prev = { scoreA: null, scoreB: null };

function _flash(el) {
  if (!el) return;
  el.classList.remove('flash');
  void el.offsetWidth;
  el.classList.add('flash');
  setTimeout(function() { el.classList.remove('flash'); }, 400);
}

// ── Main state render ─────────────────────────────────────────
// Renders everything EXCEPT timers (those run in RAF loop).
function _render(s) {
  if (!s) return;
  const tA = s.teamA || {};
  const tB = s.teamB || {};
  const sh = s.shared || {};

  const nameA = tA.name || 'TEAM A';
  const nameB = tB.name || 'TEAM B';

  _setText('labelA', nameA);
  _setText('labelB', nameB);
  _setText('teamANameDisplay', nameA);
  _setText('teamBNameDisplay', nameB);

  const sA = tA.score != null ? tA.score : 0;
  const sB = tB.score != null ? tB.score : 0;
  if (_prev.scoreA !== null && sA !== _prev.scoreA) _flash(_el('scoreA'));
  if (_prev.scoreB !== null && sB !== _prev.scoreB) _flash(_el('scoreB'));
  _prev.scoreA = sA; _prev.scoreB = sB;
  _setText('scoreA', sA);
  _setText('scoreB', sB);

  // Compact nav (small screens)
  if (_el('compactScoreA')) {
    _setText('compactScoreA', sA);
    _setText('compactScoreB', sB);
    _setText('compactLabelA', (nameA || 'A').split(/\s+/)[0].slice(0,6));
    _setText('compactLabelB', (nameB || 'B').split(/\s+/)[0].slice(0,6));
  }

  // Stats bars
  ['foul','timeout','quarter'].forEach(function(k) {
    _setText('tsbA_' + k, tA[k] != null ? tA[k] : 0);
    _setText('tsbB_' + k, tB[k] != null ? tB[k] : 0);
  });

  // Shared counters
  _setText('foulVal',      sh.foul    != null ? sh.foul    : 0);
  _setText('timeoutVal',   sh.timeout != null ? sh.timeout : 0);
  _setText('bbQuarterVal', sh.quarter != null ? sh.quarter : 0);

  // Committee
  _setText('bbCommitteeValue', (s.committee || '').trim() || '—');

  // Rosters
  _renderRoster('A', tA.players || []);
  _renderRoster('B', tB.players || []);
}

// ── State merge ───────────────────────────────────────────────
// Merges incoming partial payload into the running state snapshot.
// Timer fields are separated and routed to applyTimerPayload.
let _state = {};


function _normalizeIncomingPayload(incoming) {
  if (!incoming || typeof incoming !== 'object') return incoming;
  // WS server may wrap state by type name or by { payload: ... }.
  let p = incoming.payload && typeof incoming.payload === 'object' ? incoming.payload : incoming;
  if (p.basketball_state && typeof p.basketball_state === 'object') p = p.basketball_state;
  if (p.basketball && typeof p.basketball === 'object') p = p.basketball;
  if (p.state && typeof p.state === 'object' && (p.state.teamA || p.state.teamB || p.state.shared)) p = p.state;
  return p;
}

function _legacyStateSyncToPayload(p) {
  if (!p || typeof p !== 'object') return null;
  if (p.teamA || p.teamB || p.shared || p.committee !== undefined) return p;
  const out = {};
  if (p.players) {
    out.teamA = Object.assign({}, out.teamA, { players: p.players.teamA || [] });
    out.teamB = Object.assign({}, out.teamB, { players: p.players.teamB || [] });
  }
  if (p.foul) {
    out.teamA = Object.assign({}, out.teamA, { foul: p.foul.teamA != null ? p.foul.teamA : 0 });
    out.teamB = Object.assign({}, out.teamB, { foul: p.foul.teamB != null ? p.foul.teamB : 0 });
  }
  if (p.timeout) {
    out.teamA = Object.assign({}, out.teamA, { timeout: p.timeout.teamA != null ? p.timeout.teamA : 0 });
    out.teamB = Object.assign({}, out.teamB, { timeout: p.timeout.teamB != null ? p.timeout.teamB : 0 });
  }
  if (p.quarter !== undefined) out.shared = Object.assign({}, out.shared, { quarter: p.quarter });
  if (p.committee !== undefined) out.committee = p.committee;
  return Object.keys(out).length ? out : null;
}

function _ingestPayload(incoming) {
  incoming = _normalizeIncomingPayload(incoming);
  if (!incoming || typeof incoming !== 'object') return;

  // Merge roster/score/shared fields
  ['teamA','teamB','shared','committee','resetAt'].forEach(function(k) {
    if (incoming[k] !== undefined && incoming[k] !== null) {
      if (typeof incoming[k] === 'object' && !Array.isArray(incoming[k])) {
        _state[k] = Object.assign({}, _state[k] || {}, incoming[k]);
      } else {
        _state[k] = incoming[k];
      }
    }
  });

  // Apply timer fields directly to anchor (bypasses RAF, safe)
  if (incoming.gameTimer || incoming.game_timer || incoming.shotClock || incoming.shot_clock) {
    applyTimerPayload(incoming);
  }

  _render(_state);
}

// ── Match ID resolution ───────────────────────────────────────
function _resolveMatchId() {
  try {
    const def = (typeof window.__defaultRoomId !== 'undefined' && String(window.__defaultRoomId) !== '0') ? String(window.__defaultRoomId) : 'live';
    let mid = '';

    // 1) URL is the strongest source for viewer pages.
    try {
      const qs = new URLSearchParams(location.search);
      mid = qs.get('match_id') || qs.get('match') || qs.get('id') || '';
      if (mid && String(mid).trim() !== '' && String(mid).trim() !== '0') {
        mid = String(mid).trim();
        window.__matchId = mid;
        try { sessionStorage.setItem('basketball_match_id', mid); } catch(_) {}
        try { localStorage.setItem('basketball_match_id', mid); } catch(_) {}
        return mid;
      }
    } catch(_) {}

    // 2) Adopted runtime match id from WS/admin_present/new_match.
    if (window.__matchId && String(window.__matchId) !== '0') return String(window.__matchId);

    // 3) Persisted browser storage.
    try {
      const s = sessionStorage.getItem('basketball_match_id');
      if (s && String(s) !== '0') { window.__matchId = String(s); return String(s); }
      const l = localStorage.getItem('basketball_match_id');
      if (l && String(l) !== '0') { window.__matchId = String(l); return String(l); }
    } catch(_) {}

    // 4) Server-injected data, if present.
    if (window.MATCH_DATA && window.MATCH_DATA.match_id && String(window.MATCH_DATA.match_id) !== '0') {
      mid = String(window.MATCH_DATA.match_id);
      window.__matchId = mid;
      try { sessionStorage.setItem('basketball_match_id', mid); localStorage.setItem('basketball_match_id', mid); } catch(_) {}
      return mid;
    }

    return def;
  } catch(_) { return 'live'; }
}

console.log('[Basketball Viewer] resolved match_id on load:', _resolveMatchId());


// ── Initial state fetch ───────────────────────────────────────
// Fetches roster/scores from state.php and timers from timer.php
// in parallel on join.
async function _fetchInitialState(mid) {
  // Initial roster/stat hydrate only. Timer hydrate must come from WS server get_state/last_state.
  if (!mid) return;
  try {
    const r = await fetch('state.php?match_id=' + encodeURIComponent(mid) + '&t=' + Date.now(), { cache: 'no-store', credentials: 'include' });
    const j = await r.json();
    const payload = j && (j.payload || j.state);
    if (j && j.success && payload) _ingestPayload(payload);
  } catch (_) {}
}

// BroadcastChannel/localStorage fallback removed for timer sync.
// ── New-match adoption ────────────────────────────────────────
function _adoptNewMatch(msg) {
  try {
    const newId = msg && (msg.match_id || (msg.payload && msg.payload.match_id));
    if (!newId) return;
    const sid = String(newId);
    try { sessionStorage.setItem('basketball_match_id', sid); } catch(_) {}
    try { localStorage.setItem('basketball_match_id', sid); } catch(_) {}
    try { window.__matchId = sid; } catch(_) {}
    console.log('[Basketball Viewer] adopted match_id from server/admin:', sid);

    // Rejoin WS rooms
    const joinMsg = JSON.stringify({ type: 'join', match_id: sid });
    if (_ws    && _ws.readyState    === WebSocket.OPEN) try { _ws.send(joinMsg); }    catch(_) {}
    if (_wsAlt && _wsAlt.readyState === WebSocket.OPEN) try { _wsAlt.send(joinMsg); } catch(_) {}

    // Apply payload or fetch fresh
    if (msg.payload) {
      _ingestPayload(msg.payload);
    } else {
      const getMsg = JSON.stringify({ type: 'get_state', match_id: sid });
      if (_ws    && _ws.readyState    === WebSocket.OPEN) try { _ws.send(getMsg); }    catch(_) {}
      if (_wsAlt && _wsAlt.readyState === WebSocket.OPEN) try { _wsAlt.send(getMsg); } catch(_) {}
    }

    // Brief UI toast
    try {
      let toast = document.getElementById('_viewerToast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = '_viewerToast';
        Object.assign(toast.style, { position:'fixed', left:'12px', top:'12px', zIndex:99999,
          background:'rgba(0,0,0,0.75)', color:'#fff', padding:'8px 12px', borderRadius:'6px',
          fontSize:'13px', pointerEvents:'none' });
        document.body.appendChild(toast);
      }
      toast.textContent = 'Switched to match ' + sid;
      toast.style.display = '';
      setTimeout(function() { if (toast) toast.style.display = 'none'; }, 3000);
    } catch(_) {}
  } catch(_) {}
}

// ── WebSocket message handler (shared) ───────────────────────
function _handleWSMessage(ev) {
  try {
    const m = JSON.parse(ev.data);
    if (!m) return;

    // IMPORTANT: adoption messages must be handled before match filtering.
    // Otherwise a viewer stuck in 'live' will drop the admin's real match_id.
    if (m.type === 'new_match') { _adoptNewMatch(m); return; }
    if (m.type === 'admin_present' && m.match_id) { _adoptNewMatch({ match_id: m.match_id, payload: m.payload || null }); return; }
    if (m.type === 'active_match' && m.match_id) { _adoptNewMatch({ match_id: m.match_id, payload: m.payload || null }); return; }

    // Drop messages for a different match room
    if (m.match_id && _resolveMatchId() !== '0') {
      const inMid = String(m.match_id);
      const myMid = String(_resolveMatchId());
      if (myMid !== '0' && inMid !== myMid) return;
    }

    if (m.type === 'new_match') { _adoptNewMatch(m); return; }

    if (m.type === 'reset_match') {
      if (m.payload) {
        _ingestPayload(m.payload);
        if (m.payload.gameTimer || m.payload.shotClock || m.payload.game_timer || m.payload.shot_clock) applyTimerPayload(m.payload);
      }
      return;
    }

    if (m.type === 'admin_present' && m.match_id) {
      _adoptNewMatch({ match_id: m.match_id });
      return;
    }

    // timer_update — the most frequent message type
    if (m.type === 'timer_update') {
      // Build a combined payload for applyTimerPayload
      const tp = {};
      if (m.gameTimer)  tp.gameTimer  = m.gameTimer;
      if (m.shotClock)  tp.shotClock  = m.shotClock;
      if (m.game_timer) tp.gameTimer  = m.game_timer;
      if (m.shot_clock) tp.shotClock  = m.shot_clock;
      if (tp.gameTimer || tp.shotClock) applyTimerPayload(tp);
      return;
    }

    if (m.type === 'last_state' && m.payload) {
      const p = _normalizeIncomingPayload(m.payload);
      _ingestPayload(p);
      // Also apply embedded timer data from WS server state.
      if (p && (p.gameTimer || p.shotClock || p.game_timer || p.shot_clock)) applyTimerPayload(p);
      return;
    }

    if (m.type === 'room_state' && m.payload) {
      const p = _normalizeIncomingPayload(m.payload);
      if (p) _ingestPayload(p);
      if (p && (p.gameTimer || p.shotClock || p.game_timer || p.shot_clock)) applyTimerPayload(p);
      return;
    }

    if (m.type === 'basketball:state-sync' && m.payload) {
      const p = _legacyStateSyncToPayload(m.payload);
      if (p) _ingestPayload(p);
      return;
    }

    if (m.type === 'basketball_state' || m.type === 'state' || m.type === 'applied_action') {
      if (m.payload) {
        const p = _normalizeIncomingPayload(m.payload);
        _ingestPayload(p);
        if (p && (p.gameTimer || p.shotClock || p.game_timer || p.shot_clock)) applyTimerPayload(p);
      }
      return;
    }

    // Untyped message with a payload
    if (m.payload) _ingestPayload(m.payload);
  } catch(_) {}
}

// ── WebSocket setup ───────────────────────────────────────────
let _ws    = null;
let _wsAlt = null; // second connection attempt (some setups need it)
const _wsBackoff = {};

function _nextBackoff(key) {
  _wsBackoff[key] = Math.min(WS_MAX_BACKOFF, (_wsBackoff[key] || 0) ? _wsBackoff[key] * 2 : 500);
  return _wsBackoff[key];
}

function _wsOpen(socket) {
  // Always join fallback live room first so reload/new viewers can adopt
  // the current active admin match_id even when browser storage has an old id.
  const mid = _resolveMatchId() || 'live';

  // Reset backoff on successful open
  _wsBackoff['main'] = 0;

  try { console.log('[Basketball Viewer] WS open, current resolved match_id:', mid); } catch(_) {}

  // 1) Join live fallback to receive active_match/admin_present from server.
  try { socket.send(JSON.stringify({ type: 'join',      match_id: 'live' })); } catch(_) {}
  try { socket.send(JSON.stringify({ type: 'get_state', match_id: 'live' })); } catch(_) {}

  // 2) Also join the resolved room if already known. If server replies with
  // active_match, _adoptNewMatch() will rejoin the correct current room.
  if (mid && String(mid) !== 'live' && String(mid) !== '0') {
    try { socket.send(JSON.stringify({ type: 'join',      match_id: String(mid) })); } catch(_) {}
    try { socket.send(JSON.stringify({ type: 'get_state', match_id: String(mid) })); } catch(_) {}
  }
}

(function _initWS() {
  try {
    if (!location || !location.hostname) return;
    const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
    let url = proto + '//' + location.hostname + ':3000';
    if (window.__wsToken) url += '?token=' + encodeURIComponent(window.__wsToken);

    _ws = new WebSocket(url);

    _ws.addEventListener('open', function() {
      _wsBackoff['main'] = 0;
      _wsOpen(_ws);
    });

    _ws.addEventListener('message', _handleWSMessage);

    _ws.addEventListener('close', function() {
      const delay = _nextBackoff('main');
      setTimeout(_initWS, delay);
    });

    _ws.addEventListener('error', function() { /* handled via close */ });

  } catch(_) {}
})();

// ── Alternate WS connection (handles some proxy setups) ──────
try {
  if (location && location.hostname) {
    const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
    let url = proto + '//' + location.hostname + ':3000';
    if (window.__wsToken) url += '?token=' + encodeURIComponent(window.__wsToken);

    _wsAlt = new WebSocket(url);
    _wsAlt.addEventListener('open', function() {
      _wsBackoff['alt'] = 0;
      _wsOpen(_wsAlt);
    });
    _wsAlt.addEventListener('message', _handleWSMessage);
    function _reconnectAltWS() {
      try {
        _wsAlt = new WebSocket(url);
        _wsAlt.addEventListener('open', function() {
          _wsBackoff['alt'] = 0;
          _wsOpen(_wsAlt);
        });
        _wsAlt.addEventListener('message', _handleWSMessage);
        _wsAlt.addEventListener('close', function() {
          const d2 = _nextBackoff('alt');
          setTimeout(_reconnectAltWS, d2);
        });
        _wsAlt.addEventListener('error', function() {});
      } catch(_) {}
    }

    _wsAlt.addEventListener('close', function() {
      const delay = _nextBackoff('alt');
      setTimeout(_reconnectAltWS, delay);
    });
    _wsAlt.addEventListener('error', function() {});
  }
} catch(_) {}

// ── Page visibility — rejoin WS room on un-hide ─
document.addEventListener('visibilitychange', function() {
  if (document.visibilityState === 'visible') {
    const mid = _resolveMatchId() || 'live';
    // Re-send live join first so returning/reloaded viewers can adopt current match_id.
    const liveJoinMsg = JSON.stringify({ type: 'join', match_id: 'live' });
    const liveGetMsg  = JSON.stringify({ type: 'get_state', match_id: 'live' });
    if (_ws    && _ws.readyState    === WebSocket.OPEN) { try { _ws.send(liveJoinMsg); _ws.send(liveGetMsg); } catch(_) {} }
    if (_wsAlt && _wsAlt.readyState === WebSocket.OPEN) { try { _wsAlt.send(liveJoinMsg); _wsAlt.send(liveGetMsg); } catch(_) {} }

    // Re-send known match room too, if available.
    const joinMsg = mid && String(mid) !== 'live' ? JSON.stringify({ type: 'join', match_id: mid }) : null;
    if (joinMsg) {
      if (_ws    && _ws.readyState    === WebSocket.OPEN) try { _ws.send(joinMsg);    } catch(_) {}
      if (_wsAlt && _wsAlt.readyState === WebSocket.OPEN) try { _wsAlt.send(joinMsg); } catch(_) {}
    }
  }
});

// ── Init sequence ─────────────────────────────────────────────
(async function _init() {
  try {
    // Step 1: restore match_id from session/URL
    try { window.__matchId = sessionStorage.getItem('basketball_match_id') || window.__matchId; } catch(_) {}

    // Step 2: resolve match_id. WS join/get_state will provide latest timer state.
    // Roster/stat state is also fetched from state.php so viewer is not blank if WS last_state is empty.
    const mid = _resolveMatchId();
    if (mid && mid !== '0') await _fetchInitialState(mid);

  } catch(_) {}
})();

// Viewer reload/rejoin safety probe: after page load, ask the WS server again
// for the active live room. This fixes viewers that reload with a stale match_id.
setTimeout(function () {
  try {
    const liveJoinMsg = JSON.stringify({ type: 'join', match_id: 'live' });
    const liveGetMsg  = JSON.stringify({ type: 'get_state', match_id: 'live' });
    if (_ws && _ws.readyState === WebSocket.OPEN) { _ws.send(liveJoinMsg); _ws.send(liveGetMsg); }
    if (_wsAlt && _wsAlt.readyState === WebSocket.OPEN) { _wsAlt.send(liveJoinMsg); _wsAlt.send(liveGetMsg); }
    console.log('[Basketball Viewer] requested active match from live room after reload');
  } catch (_) {}
}, 800);

