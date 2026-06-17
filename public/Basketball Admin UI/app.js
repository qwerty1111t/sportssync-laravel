// ═══════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════
const state = {
  teamA: { name:'TEAM A', players:[], foul:0, timeout:0, manualScore: 0 },
  teamB: { name:'TEAM B', players:[], foul:0, timeout:0, manualScore: 0 },
  shared: { foul:0, timeout:0, quarter:1 }
};
const STATS = ['pts','foul','reb','ast','blk','stl'];
// Saved vs Draft roster handling
// `state` is the current draft shown in the UI. `savedState` is the
// last-saved authoritative state loaded from the server. Draft edits
// are temporary until `saveRoster()` is invoked.
let savedState = null;
let draftDirty = false;
const IS_BASKETBALL_PAGE = typeof document !== 'undefined' && document.body && document.body.dataset && document.body.dataset.sport === 'basketball';

function markRosterDirty() {
  try { draftDirty = true; } catch(_){}
  try { const btn = document.getElementById('saveRosterBtn'); if (btn) btn.disabled = false; } catch(_){}
}

function clearRosterDirty() {
  try { draftDirty = false; } catch(_){}
  try { const btn = document.getElementById('saveRosterBtn'); if (btn) btn.disabled = true; } catch(_){}
}

// FIX: decoupled from timer — Save ONLY roster/team/shared data (NO timers) to server and broadcast
// This is the primary roster save path for user interactions like add/delete/edit.
function saveRosterState() {
  // FIX: decoupled from timer
  try {
    const mid = getMatchId() || 'live';
    const payload = buildRosterOnlyPayload(); // FIX: decoupled from timer — new function
    syncRosterState(payload); // FIX: decoupled from timer — new function
    clearRosterDirty(); // FIX: decoupled from timer
    try { showToast('Roster saved'); } catch(_) {}
    return Promise.resolve({ success:true, payload });
  } catch (e) { console.error('saveRosterState error', e); return Promise.resolve({ success:false, error: String(e) }); }
}

// FIX: decoupled from timer — Save ONLY timer state (game_timer, shot_clock) to server
function saveTimerState() {
  // FIX: decoupled from timer — Explicit timer save (delegates to immediatePersistControl)
  try {
    return immediatePersistControl('none'); // FIX: decoupled from timer — 'none' = no-op control, just persist current state
  } catch (e) { console.error('saveTimerState error', e); return Promise.resolve({ success:false, error: String(e) }); }
}

// Save the current draft roster by syncing the current UI state to the server.
// Real-time WebSocket broadcast is used for live updates, and canonical
// state persistence is done via state.php so reloads restore the latest state.
function saveRoster() {
  try {
    const mid = getMatchId() || 'live';
    const payload = buildStatePayload();
    syncBasketballState(payload, { forceServer: true });
    clearRosterDirty();
    try { showToast('Roster sync requested'); } catch(_) {}
    return Promise.resolve({ success:true, payload });
  } catch (e) { console.error('saveRoster error', e); return Promise.resolve({ success:false, error: String(e) }); }
}

// Backwards-compat shim (unused by draft flow).
function immediatePersistRoster() { try { return saveRoster(); } catch(_) { return Promise.resolve({ success:false }); } }
let pCount = { A:0, B:0 };
// Unique client id for this admin page (used in action metadata)
const CLIENT_ID = (window.__clientId = window.__clientId || ('c_' + Math.random().toString(36).slice(2,10)));
let _lastStateResetTs = 0;
// Guard: single delegation attachment for roster event handlers
let _rosterDelegatesAttached = false;

// Internal flags to avoid re-broadcasting incoming remote updates
let _appApplyingRemote = false;
let _lastOutgoingSerialized = null;

// Previously the admin intentionally removed any persisted live state on load.
// Keep persisted state so admin page restores players and scores across reloads.
// (Loading occurs after BroadcastChannel / storage key is declared.)

// ═══════════════════════════════════════════════════════
//  LIVE SCORE
// ═══════════════════════════════════════════════════════
function recalcScore(team) {
  const playersSum = state['team'+team].players.reduce((s, p) => s + (p.pts || 0), 0);
  const manual = typeof state['team'+team].manualScore === 'number' ? state['team'+team].manualScore : 0;
  const total = playersSum + manual;
  const el = document.getElementById('score'+team);
  if (el) el.textContent = total;
  if (el) el.style.transform = 'scale(1.22)';
  setTimeout(() => { if (el) el.style.transform = 'scale(1)'; }, 140);
}

// ═══════════════════════════════════════════════════════
//  SHARED COUNTERS (two-sided mode right panel)
// ═══════════════════════════════════════════════════════
function adjustShared(key, delta) {
  state.shared[key] = Math.max(0, state.shared[key] + delta);
  const el = document.getElementById(key+'Val');
  if (el) {
    el.textContent = state.shared[key];
    el.style.transform = 'scale(1.2)';
    setTimeout(() => { el.style.transform = 'scale(1)'; }, 130);
  }
    // also update per-two-sided quarter display if present
    if (key === 'quarter') {
      const perQ = document.getElementById('per_quarterVal');
      if (perQ) perQ.textContent = state.shared.quarter;
      const qEls = [document.getElementById('bbQuarterVal'), document.getElementById('bbPerQuarterVal')];
      qEls.forEach(q => { if (q && q.style) { q.style.transform = 'scale(1.2)'; setTimeout(() => { if (q) q.style.transform = 'scale(1)'; }, 130); } });
    }
    try { syncRightPanelCounters(); } catch(_) {}
    // FIX: isolated from timer — NEVER call postImmediateTimerUpdate() from roster actions
    try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
    localStorage.setItem('basketball_state', JSON.stringify(state));
}

// ═══════════════════════════════════════════════════════
//  INLINE TEAM STATS BAR COUNTERS (one-sided mode)
// ═══════════════════════════════════════════════════════
function adjustTsb(team, key, delta) {
  // Quarter is a shared value; route quarter adjustments to shared state.
  if (key === 'quarter') {
    state.shared.quarter = Math.max(0, state.shared.quarter + delta);
    const elq = document.getElementById('bbQuarterVal');
    if (elq) {
      elq.textContent = state.shared.quarter;
      if (elq.style) elq.style.transform = 'scale(1.25)';
      setTimeout(() => { if (elq && elq.style) elq.style.transform = 'scale(1)'; }, 130);
    }
    try { syncRightPanelCounters(); } catch(_) {}
    // FIX: isolated from timer — NEVER call postImmediateTimerUpdate() from roster actions
    try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
    localStorage.setItem('basketball_state', JSON.stringify(state));
    return;
  }
  state['team'+team][key] = Math.max(0, state['team'+team][key] + delta);
  const el = document.getElementById('tsb'+team+'_'+key);
  if (el) el.textContent = state['team'+team][key];
  if (el) el.style.transform = 'scale(1.25)';
  setTimeout(() => { if (el) el.style.transform = 'scale(1)'; }, 130);

  // Also update right-panel per-team counters if present
  const rightEl = document.getElementById('right_tsb'+team+'_'+key);
  if (rightEl) {
    rightEl.textContent = state['team'+team][key];
    if (rightEl.style) rightEl.style.transform = 'scale(1.25)';
    setTimeout(() => { if (rightEl && rightEl.style) rightEl.style.transform = 'scale(1)'; }, 130);
  }
  try { syncRightPanelCounters(); } catch(_) {}
  // FIX: isolated from timer — NEVER call postImmediateTimerUpdate() from roster actions
  try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
  localStorage.setItem('basketball_state', JSON.stringify(state));
}

// Team-level manual score adjustments (offset added to players sum)
function adjustTeamScore(team, delta) {
  try {
    state['team'+team].manualScore = Math.max(0, (state['team'+team].manualScore || 0) + delta);
    recalcScore(team);
    // FIX: isolated from timer — NEVER call postImmediateTimerUpdate() from roster actions
    try { saveRosterState(); } catch (e) {} // FIX: decoupled from timer — use roster-only save
    localStorage.setItem('basketball_state', JSON.stringify(state));
  } catch (e) { /* ignore */ }
}

// ═══════════════════════════════════════════════════════
//  TEAM NAME
// ═══════════════════════════════════════════════════════
function onTeamName(team) {
  const v = document.getElementById('team'+team+'Name').value;
  state['team'+team].name = v;
  document.getElementById('label'+team).textContent = v || ('TEAM '+team);
  try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
  localStorage.setItem('basketball_state', JSON.stringify(state));
}

// ═══════════════════════════════════════════════════════
//  ADD PLAYER
// ═══════════════════════════════════════════════════════
function bbAddPlayer(team) {
  const noInput = document.getElementById('addPlayerNo' + team);
  const nameInput = document.getElementById('addPlayerName' + team);
  pCount[team]++;
  const id = 'p'+team+pCount[team];
  const p = { id, no: noInput ? noInput.value.trim() : '', name: nameInput ? nameInput.value.trim() : '', pts:0, foul:0, reb:0, ast:0, blk:0, stl:0, techFoul:0, techReason:'', selected:false };
  state['team'+team].players.push(p);
  bbRenderRosterTable();
  try { markRosterDirty(); } catch(_) {}
  try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
  localStorage.setItem('basketball_state', JSON.stringify(state));
  // Clear inputs after adding
  if (noInput) noInput.value = '';
  if (nameInput) nameInput.value = '';
}

// ═══════════════════════════════════════════════════════
//  ROSTER TABLE (clean renderer + event delegation)
// ═══════════════════════════════════════════════════════
function bbRenderRosterTable() {
  try {
    const esc = (s) => { if (s === null || s === undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); };
    const isAdmin = (typeof window.__role === 'undefined') || (window.__role === 'admin' || window.__role === 'superadmin');

    const buildTeam = function(team) {
      const tbody = document.getElementById('tbody' + team);
      if (!tbody) return;
      let html = '';
      (state['team'+team].players || []).forEach(function(p) {
        html += '<tr class="player-main-row" data-player-id="' + esc(p.id) + '" data-team="' + team + '">';
        html += '<td class="player-cb-cell"><input type="checkbox" class="bbPlayerCb"' + (p.selected ? ' checked' : '') + '></td>';
        html += '<td class="td-no"><input type="text" value="' + esc(p.no) + '" class="player-no" placeholder="#" maxlength="3" ' + (!isAdmin ? 'readonly tabindex="-1"' : '') + '></td>';
        html += '<td class="td-name"><input type="text" value="' + esc(p.name) + '" class="player-name" placeholder="Player name" ' + (!isAdmin ? 'readonly tabindex="-1"' : '') + '></td>';
        // stats
        STATS.forEach(function(stat) {
          html += '<td data-stat="' + stat + '"><div class="stat-cell"><button class="bbSbtn minus" data-action="dec" data-stat="' + stat + '">−</button><span class="stat-val" data-stat="' + stat + '">' + esc(p[stat]) + '</span><button class="bbSbtn plus" data-action="inc" data-stat="' + stat + '">+</button></div></td>';
        });
        html += '<td><span class="stat-val tech-display" data-stat="techFoul">' + esc(p.techFoul) + '</span></td>';
        html += '<td><button class="bbBtnDel" data-action="delete">✕</button></td>';
        html += '</tr>';
        html += '<tr class="player-tech-row" data-player-id="' + esc(p.id) + '" data-team="' + team + '"><td colspan="11"><div class="tech-inner"><span class="tech-label">Tech Foul:</span><div class="tech-counter"><button class="tbtn minus" data-action="dec" data-stat="techFoul">−</button><span class="tech-count-val" data-stat="techFoul">' + esc(p.techFoul) + '</span><button class="tbtn plus" data-action="inc" data-stat="techFoul">+</button></div><input class="tech-reason-input" type="text" value="' + esc(p.techReason) + '" placeholder="Reason / description of technical foul…"></div></td></tr>';
      });
      tbody.innerHTML = html;
    };

    buildTeam('A'); buildTeam('B');

    // Attach event delegation once per tbody
    if (!_rosterDelegatesAttached) {
      const attach = function(tbody) {
        if (!tbody) return;
        tbody.addEventListener('click', function(ev) {
          const t = ev.target;
          const tr = t.closest('tr[data-player-id]');
          if (!tr) return;
          const pid = tr.dataset.playerId;
          const team = tr.dataset.team || (tbody.id === 'tbodyA' ? 'A' : 'B');
          const players = state['team' + team].players || [];
          const p = players.find(function(x){ return x.id === pid; });
          if (!p) return;
          const action = t.dataset.action;
          const stat = t.dataset.stat;
          if (action === 'inc' || action === 'dec') {
            if (!stat) return;
            if (action === 'inc') p[stat] = (p[stat] || 0) + 1; else p[stat] = Math.max(0, (p[stat] || 0) - 1);
            const span = tbody.querySelector('tr[data-player-id="' + pid + '"] .stat-val[data-stat="' + stat + '"]');
            if (span) span.textContent = p[stat];
            if (stat === 'pts') try { recalcScore(team); } catch(_) {}
            try { markRosterDirty(); } catch(_) {}
            try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
            return;
          }
          if (action === 'delete') {
            if (players.length <= 1) {
              alert('At least one player must remain in the roster.');
              return;
            }
            const idx = players.findIndex(function(x){ return x.id === pid; });
            const playerName = p.name ? p.name : ('Player #' + (p.no || p.id));
            const confirmed = confirm('Delete ' + playerName + '? This cannot be undone.');
            if (!confirmed) return;
            if (idx >= 0) players.splice(idx, 1);
            localStorage.setItem('basketball_state', JSON.stringify(state));
            try {
              const immediatePayload = buildRosterOnlyPayload();
              persistRosterStateToServer(immediatePayload);
            } catch(_) {}
            bbRenderRosterTable();
            try { recalcScore(team); } catch(_) {}
            try { markRosterDirty(); } catch(_) {}
            try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
            return;
          }
        });
        tbody.addEventListener('input', function(ev) {
          const inp = ev.target;
          const tr = inp.closest('tr[data-player-id]');
          if (!tr) return;
          const pid = tr.dataset.playerId;
          const team = tr.dataset.team || (tbody.id === 'tbodyA' ? 'A' : 'B');
          const players = state['team' + team].players || [];
          const p = players.find(function(x){ return x.id === pid; });
          if (!p) return;
          if (inp.classList.contains('player-no') || inp.classList.contains('player-name') || inp.classList.contains('tech-reason-input')) {
            if (inp.classList.contains('player-no')) p.no = inp.value;
            if (inp.classList.contains('player-name')) p.name = inp.value;
            if (inp.classList.contains('tech-reason-input')) p.techReason = inp.value;
            try { markRosterDirty(); } catch(_) {}
            localStorage.setItem('basketball_state', JSON.stringify(state));
            if (_rosterTypingDebounce) clearTimeout(_rosterTypingDebounce);
            _rosterTypingDebounce = setTimeout(function () {
              _rosterTypingDebounce = null;
              try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
            }, 600);
            return;
          }
        });
        tbody.addEventListener('change', function(ev) {
          const el = ev.target;
          if (!el.classList.contains('bbPlayerCb')) return;
          const tr = el.closest('tr[data-player-id]');
          if (!tr) return;
          const pid = tr.dataset.playerId;
          const team = tr.dataset.team || (tbody.id === 'tbodyA' ? 'A' : 'B');
          const players = state['team' + team].players || [];
          const p = players.find(function(x){ return x.id === pid; });
          if (!p) return;
          p.selected = !!el.checked;
          tr.classList.toggle('row-checked', !!el.checked);
          try { syncSelectAll(team); } catch(_) {}
          try { markRosterDirty(); } catch(_) {}
          try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
        });
      };
      const ta = document.getElementById('tbodyA'); if (ta) attach(ta);
      const tb = document.getElementById('tbodyB'); if (tb) attach(tb);
      _rosterDelegatesAttached = true;
    }
  } catch (e) { /* ignore render errors */ }
}

// ═══════════════════════════════════════════════════════
//  GAME TIMER
// ═══════════════════════════════════════════════════════
let gtTotalSecs = 10 * 60;
let gtRemaining = 10 * 60;
let gtRunning   = false;
let gtInterval  = null; // legacy var kept for compatibility
let gtLastTick  = null;
let gtAnchorTs = null; // server start_timestamp (ms) when running
let gtRemainingAtAnchor = null; // remaining (secs) corresponding to gtAnchorTs

const gtTimeEl   = document.getElementById('gtTime');
const gtBlock    = document.getElementById('gtBlock');
const gtPlayBtn  = document.getElementById('gtPlayBtn');
const gtPauseBtn = document.getElementById('gtPauseBtn');
const GT_DANGER  = 60;

function gtFmt(secs) {
  const m = Math.floor(secs / 60);
  const s = Math.floor(secs % 60);
  return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
}
function gtRender() {
  const expired = gtRemaining <= 0;
  if (gtTimeEl) {
    gtTimeEl.textContent = gtFmt(gtRemaining);
    gtTimeEl.className   = 'gt-time' + (expired ? ' expired' : gtRemaining <= GT_DANGER ? ' danger' : '');
  }
  if (gtBlock) {
    gtBlock.className    = 'game-timer-block' +
      (expired ? ' gt-expired' : gtRunning && gtRemaining <= GT_DANGER ? ' gt-danger' : gtRunning ? ' gt-running' : '');
  }
}
// Centralized UI toggle for timer Play/Pause buttons
function applyTimerButtonState(timerType, running) {
  try {
    // Show/Hide play vs pause consistently and keep disabled state for
    // accessibility. Play visible when stopped/paused; Pause visible when running.
    if (timerType === 'game') {
      if (gtPlayBtn) { gtPlayBtn.style.display = running ? 'none' : ''; gtPlayBtn.disabled = !!running; }
      if (gtPauseBtn) { gtPauseBtn.style.display = running ? '' : 'none'; gtPauseBtn.disabled = !running; }
    } else if (timerType === 'shot') {
      if (scPlayBtn) { scPlayBtn.style.display = running ? 'none' : ''; scPlayBtn.disabled = !!running; }
      if (scPauseBtn) { scPauseBtn.style.display = running ? '' : 'none'; scPauseBtn.disabled = !running; }
    }
  } catch (_) {}
}
// FIX: mainLoop replaces setInterval — gtTick is now driven by _mainLoop, kept for backwards compatibility
function gtTick() {
  // FIX: mainLoop replaces setInterval — This function is obsolete, logic moved to _mainLoop
  // Kept here for backwards compatibility only
  return;
}
function gtPlay() {
  // FIX: mainLoop replaces setInterval — Removed setInterval start; mainLoop picks up gtRunning flag
  if (gtRunning || gtRemaining <= 0) return;
  gtRunning = true;
  gtLastTick = null; // mainLoop will pick up on next frame
  applyTimerButtonState('game', true);
  gtRender();
}
function gtPause() {
  // FIX: mainLoop replaces setInterval — Removed clearInterval; mainLoop stops ticking when gtRunning=false
  if (!gtRunning) return;
  // Compute live remaining from server anchor (if present) before stopping
  try {
    if (typeof gtAnchorTs === 'number' && typeof gtRemainingAtAnchor === 'number') {
      gtRemaining = Math.max(0, gtRemainingAtAnchor - ((Date.now() - Number(gtAnchorTs)) / 1000));
    }
  } catch (_) {}
  // Clear anchor to prevent a stale anchor overwrite after pause
  try { gtAnchorTs = null; gtRemainingAtAnchor = null; } catch (_) {}

  gtRunning = false;
  gtLastTick = null; // discard timestamp so resume never inherits a stale gap
  applyTimerButtonState('game', false);
  gtRender();
}
function gtReset() {
  // FIX: mainLoop replaces setInterval — Removed clearInterval; mainLoop stops ticking when gtRunning=false
  gtRunning = false;
  gtLastTick = null;
  gtRemaining = gtTotalSecs;

  // Clear server anchor so a stale anchor does not overwrite the just-reset value
  try { gtAnchorTs = null; gtRemainingAtAnchor = null; } catch(_) {}

  // Note: persistence is handled by the wrapped gtReset handler via immediatePersistControl
  applyTimerButtonState('game', false);
  gtRender();
}
function gtSetDuration() {
  const mins  = parseInt(document.getElementById('gtInputMin').value, 10) || 0;
  const secs  = parseInt(document.getElementById('gtInputSec').value, 10) || 0;
  const total = Math.max(1, mins * 60 + secs);
  gtTotalSecs = total;
  gtRemaining = total;
  gtRunning = false;
  gtLastTick = null;
  gtAnchorTs = null;
  gtRemainingAtAnchor = null;
  // stop gt loop when duration is set manually
  try { applyTimerButtonState('game', false); } catch(_){}
  gtRender();
}
gtRender();

// Initialize timers from server state (called after server state is loaded)
// FIX: game timer path only — Initialize game timer and shot clock independently on page load
function initializeTimersFromServerState() {
  try {
    const mid = getMatchId() || 'live';

    // Fetch timer state from server
    fetch('/basketball-admin/timer?match_id=' + encodeURIComponent(mid), { cache: 'no-store', credentials: 'include' })
    .then(r => r.json())
    .then(j => {
        if (j && j.success && j.payload) {
            const payload = j.payload;

            if (payload.gameTimer) payload.gameTimer._timerControl = true;
            if (payload.shotClock) payload.shotClock._timerControl = true;

            // Copilot: Insert a guard before the applyGameTimer and applyShotClock calls below.
            // MUST: add `if (_lastTimerControlTs > 0) return;` as the first statement before both apply calls.
            // Reason: a live timer_update from another admin already set the correct anchor;
            // applying stale server data here would overwrite it and cause desync.
            // MUST NOT: remove or reorder the applyGameTimer / applyShotClock calls below the guard.
            // MUST NOT: change the fetch URL, response parsing, or catch block.
            // FIX: game timer path only — Apply game timer independently
            if (payload.gameTimer && (!(_lastTimerControlTs > 0 && payload.gameTimer.ts && payload.gameTimer.ts < _lastTimerControlTs))) {
                applyGameTimer(payload.gameTimer);
            }

            // FIX: shot clock path only — Apply shot clock independently
            if (payload.shotClock && (!(_lastTimerControlTs > 0 && payload.shotClock.ts && payload.shotClock.ts < _lastTimerControlTs))) {
                applyShotClock(payload.shotClock);
            }
        }
    })
    .catch(err => {
        console.warn('Failed to load timer state from server:', err);
        // Fall back to default timer initialization
        gtRender();
        scRenderFrame();
    });
  } catch (e) {
    console.error('Error initializing timers from server state:', e);
    // Fall back to default timer initialization
    gtRender();
    scRenderFrame();
  }
}
const SC_CIRCUMFERENCE    = 2 * Math.PI * 52;
const SC_DANGER_THRESHOLD = 5;
let scPresetVal = 24, scTotal = 24, scRemaining = 24.0;
let scRunning = false, scInterval = null, scLastTick = null;
let scAnchorTs = null; // server start_timestamp (ms) when running
let scRemainingAtAnchor = null; // remaining (secs) corresponding to scAnchorTs

const scTimeEl   = document.getElementById('scTime');
const scTenthEl  = document.getElementById('scTenth');
const scRingEl   = document.getElementById('scRing');
const scBlock    = document.getElementById('scBlock');
const scPlayBtn  = document.getElementById('scPlayBtn');
const scPauseBtn = document.getElementById('scPauseBtn');

function scRenderFrame() {
  const secs = Math.ceil(scRemaining);
  const tenths = (scRemaining % 1).toFixed(1).slice(1);
  const expired = scRemaining <= 0;
  if (scTimeEl) {
    scTimeEl.textContent = expired ? '0' : secs;
    scTimeEl.className = 'sc-time' + (expired ? ' expired' : scRemaining <= SC_DANGER_THRESHOLD ? ' danger' : '');
  }
  if (scTenthEl) {
    scTenthEl.textContent = (!expired && scRemaining < 10) ? tenths : '';
  }
  if (scRingEl) {
    const pct = Math.max(0, scRemaining / scTotal);
    const offset = SC_CIRCUMFERENCE * (1 - pct);
    scRingEl.style.strokeDashoffset = offset;
    scRingEl.style.stroke = expired ? '#e74c3c'
      : scRemaining <= SC_DANGER_THRESHOLD ? '#e74c3c'
      : scRemaining <= scTotal * 0.5 ? '#e67e22' : '#F5C518';
  }
  if (scBlock) {
    scBlock.className = 'shot-clock-block' +
      (expired ? ' sc-expired' : scRunning && scRemaining <= SC_DANGER_THRESHOLD ? ' sc-danger' : scRunning ? ' sc-running' : '');
  }
}

// ═══════════════════════════════════════════════════════
//  PERSISTENT RAF MAINLOOP (replaces setInterval)
// ═══════════════════════════════════════════════════════
// FIX: mainLoop replaces setInterval — Persistent RAF mainLoop started once on page load, NEVER stopped
let _mainLoopId = null;
let _mainLoopLastFrame = null;

// FIX: mainLoop replaces setInterval — Drives both game timer and shot clock display independently
function _mainLoop(timestamp) {
  _mainLoopId = requestAnimationFrame(_mainLoop); // FIX: mainLoop replaces setInterval — schedule next frame first

  const nowMs = Date.now();
  const dtMs = (_mainLoopLastFrame !== null) ? Math.min(timestamp - _mainLoopLastFrame, 200) : 0;
  _mainLoopLastFrame = timestamp;

  // FIX: mainLoop replaces setInterval — Game Timer tick (independent)
  if (gtRunning && gtRemaining > 0) {
    if (typeof gtAnchorTs === 'number' && typeof gtRemainingAtAnchor === 'number') {
      // server-anchored: compute from wall clock
      gtRemaining = Math.max(0, gtRemainingAtAnchor - ((nowMs - Number(gtAnchorTs)) / 1000));
    } else {
      // FIX: no local tick — hold current gtRemaining until server anchor arrives
    }
    if (gtRemaining <= 0) {
      gtRunning = false;
      gtAnchorTs = null;
      gtRemainingAtAnchor = null;
      try { applyTimerButtonState('game', false); } catch(_) {}
      try { flashTitle('\u23F0 GAME OVER!', 8, 450); } catch(_) {}
    }
  }
  gtRender();

  // FIX: mainLoop replaces setInterval — Shot Clock tick (independent)
  if (scRunning && scRemaining > 0) {
    if (typeof scAnchorTs === 'number' && typeof scRemainingAtAnchor === 'number') {
      // server-anchored: compute from wall clock
      scRemaining = Math.max(0, scRemainingAtAnchor - ((nowMs - Number(scAnchorTs)) / 1000));
    } else {
      // FIX: no local tick — hold current scRemaining until server anchor arrives
    }
    if (scRemaining <= 0) {
      scRunning = false;
      scAnchorTs = null;
      scRemainingAtAnchor = null;
      try { applyTimerButtonState('shot', false); } catch(_) {}
      try { flashTitle('\uD83D\uDD34 SHOT CLOCK!', 6, 400); } catch(_) {}
    }
  }
  scRenderFrame();
}

// FIX: mainLoop replaces setInterval — Start the mainLoop once on page load
_mainLoopId = requestAnimationFrame(_mainLoop);
// FIX: mainLoop replaces setInterval — scTick is now driven by _mainLoop, kept for backwards compatibility
function scTick() {
  // FIX: mainLoop replaces setInterval — This function is obsolete, logic moved to _mainLoop
  // Kept here for backwards compatibility only
  return;
}
function scPlay() {
  // FIX: mainLoop replaces setInterval — Removed setInterval start; mainLoop picks up scRunning flag
  if (scRunning || scRemaining <= 0) return;
  scRunning = true;
  scLastTick = null; // mainLoop will pick up on next frame
  applyTimerButtonState('shot', true);
  scRenderFrame();
}
function scPause() {
  // FIX: mainLoop replaces setInterval — Removed clearInterval; mainLoop stops ticking when scRunning=false
  if (!scRunning) return;
  // Compute live remaining from server anchor (if present) before stopping
  try {
    if (typeof scAnchorTs === 'number' && typeof scRemainingAtAnchor === 'number') {
      scRemaining = Math.max(0, scRemainingAtAnchor - ((Date.now() - Number(scAnchorTs)) / 1000));
    }
  } catch (_) {}
  // Clear anchor to prevent a stale anchor overwrite after pause
  try { scAnchorTs = null; scRemainingAtAnchor = null; } catch (_) {}

  scRunning = false;
  scLastTick = null; // discard timestamp so resume never inherits a stale gap
  applyTimerButtonState('shot', false);
  scRenderFrame();
}
function scReset() {
  // FIX: mainLoop replaces setInterval — Removed clearInterval; mainLoop stops ticking when scRunning=false
  scRunning = false;
  scLastTick = null;
  scRemaining = scTotal;

  // Clear server anchor so a stale anchor does not overwrite the just-reset value
  try { scAnchorTs = null; scRemainingAtAnchor = null; } catch(_) {}

  // Note: persistence is handled by the wrapped scReset handler via immediatePersistControl
  applyTimerButtonState('shot', false);
  scRenderFrame();
}
function refreshScPresetActive() {
  try {
    const btn24 = document.getElementById('preset24');
    const btn14 = document.getElementById('preset14');
    if (btn24) btn24.classList.toggle('active', scPresetVal === 24);
    if (btn14) btn14.classList.toggle('active', scPresetVal === 14);
  } catch (_) {}
}

function scPreset(secs) {
  scPresetVal = secs;
  scTotal = secs;
  refreshScPresetActive();
  scReset();
}
scRenderFrame();

// ═══════════════════════════════════════════════════════
//  CHECKBOXES
// ═══════════════════════════════════════════════════════
function toggleSelectAll(team, masterCb) {
  const players = state['team'+team].players;
  players.forEach(p => { p.selected = masterCb.checked; });
  const tbody = document.getElementById('tbody'+team);
  tbody.querySelectorAll('.bbPlayerCb').forEach(cb => {
    cb.checked = masterCb.checked;
    const row = cb.closest('tr');
    if (row) row.classList.toggle('row-checked', masterCb.checked);
  });
  try { syncSelectAll(team); } catch(_) {}
  try { markRosterDirty(); } catch(_) {}
  try { saveRosterState(); } catch(_) {}
  try { localStorage.setItem('basketball_state', JSON.stringify(state)); } catch(_) {}
}
function syncSelectAll(team) {
  const players = state['team'+team].players;
  const master  = document.getElementById('selectAll'+team);
  if (!master || players.length === 0) {
    if (master) { master.checked = false; master.indeterminate = false; }
    return;
  }
  const allChecked  = players.every(p => p.selected);
  const noneChecked = players.every(p => !p.selected);
  master.indeterminate = !allChecked && !noneChecked;
  master.checked = allChecked;
}
function deleteSelected(team) {
  const arr = state['team'+team].players;
  const toDelete = arr.filter(p => p.selected);
  if (toDelete.length === 0) return;
  const remaining = arr.length - toDelete.length;
  if (remaining < 1) {
    alert('At least one player must remain in the roster. Deselect one player before deleting.');
    return;
  }
  const names = toDelete.map(p => p.name || ('#' + (p.no || p.id))).join(', ');
  const confirmed = confirm('Delete ' + toDelete.length + ' player(s): ' + names + '? This cannot be undone.');
  if (!confirmed) return;
  state['team'+team].players = arr.filter(p => !p.selected);
  localStorage.setItem('basketball_state', JSON.stringify(state));
  try {
    const immediatePayload = buildRosterOnlyPayload();
    persistRosterStateToServer(immediatePayload);
  } catch(_) {}
  bbRenderRosterTable();
  recalcScore(team);
  syncSelectAll(team);
  try { markRosterDirty(); } catch(_) {}
  try { saveRosterState(); } catch(_) {} // FIX: decoupled from timer — use roster-only save
}

// ═══════════════════════════════════════════════════════
//  VIEW MODE (one-sided / two-sided)
// ═══════════════════════════════════════════════════════
// restore persisted view mode if present
let viewMode = 'two';
try {
  const sv = localStorage.getItem('basketball_viewMode');
  if (sv === 'one' || sv === 'two') viewMode = sv;
} catch (e) {}
let activeTab = 'A';

function toggleViewMode() {
  viewMode = viewMode === 'two' ? 'one' : 'two';
  try { localStorage.setItem('basketball_viewMode', viewMode); } catch (e) {}
  applyViewMode();
}

function applyViewMode() {
  const grid    = document.getElementById('mainGrid');
  const btn     = document.getElementById('bbViewToggleBtn');
  const panelA  = document.getElementById('panelA');
  const panelB  = document.getElementById('panelB');
  const sharedC = document.getElementById('sharedCounters');
  const sharedD = document.getElementById('sharedCounterDivider');
  const perTeamC = document.getElementById('perTeamCounters');

  if (viewMode === 'two') {
    grid.classList.remove('one-sided');
    btn.textContent = '⇄ Two-Sided';
    btn.classList.add('two-sided');
    panelA.classList.add('visible');
    panelB.classList.add('visible');
    // show per-team quick controls on the right panel in two-sided mode
    if (perTeamC) perTeamC.style.display = '';
    // quarter control stays visible in both modes
    if (sharedC) sharedC.style.display = 'none';
    if (sharedD) sharedD.style.display = 'none';
  } else {
    grid.classList.add('one-sided');
    btn.textContent = '⇆ One-Sided';
    btn.classList.remove('two-sided');
    panelA.classList.toggle('visible', activeTab === 'A');
    panelB.classList.toggle('visible', activeTab === 'B');
    highlightTab(activeTab);
    // hide per-team controls and show quarter-only area in one-sided
    if (perTeamC) perTeamC.style.display = 'none';
    if (sharedC) sharedC.style.display = ''; // show shared quarter control below shot clock
    if (sharedD) sharedD.style.display = '';
  }

  // keep right-panel counters in sync with state whenever view changes
  syncRightPanelCounters();
}

// Update right-panel elements from current state
function syncRightPanelCounters() {
  try {
    const rA_f = document.getElementById('bbRightTsbAFoul');
    const rA_t = document.getElementById('bbRightTsbATimeout');
    const rB_f = document.getElementById('bbRightTsbBFoul');
    const rB_t = document.getElementById('bbRightTsbBTimeout');
    const qEl  = document.getElementById('bbQuarterVal');
    const perQ = document.getElementById('bbPerQuarterVal');
    const tsbA_f = document.getElementById('bbTsbAFoul');
    const tsbA_t = document.getElementById('bbTsbATimeout');
    const tsbB_f = document.getElementById('bbTsbBFoul');
    const tsbB_t = document.getElementById('bbTsbBTimeout');
    const foulValEl = document.getElementById('foulVal');
    const timeoutValEl = document.getElementById('timeoutVal');

    if (rA_f) rA_f.textContent = state.teamA.foul;
    if (rA_t) rA_t.textContent = state.teamA.timeout;
    if (rB_f) rB_f.textContent = state.teamB.foul;
    if (rB_t) rB_t.textContent = state.teamB.timeout;
    if (qEl) qEl.textContent = state.shared.quarter;
    if (perQ) perQ.textContent = state.shared.quarter;
    if (tsbA_f) tsbA_f.textContent = state.teamA.foul;
    if (tsbA_t) tsbA_t.textContent = state.teamA.timeout;
    if (tsbB_f) tsbB_f.textContent = state.teamB.foul;
    if (tsbB_t) tsbB_t.textContent = state.teamB.timeout;
    // keep legacy shared displays (if any) in sync but they are hidden
    if (foulValEl) foulValEl.textContent = state.shared.foul;
    if (timeoutValEl) timeoutValEl.textContent = state.shared.timeout;
  } catch (e) { /* ignore */ }
}

function switchTab(team) {
  if (viewMode !== 'one') return;
  activeTab = team;
  document.getElementById('panelA').classList.toggle('visible', team === 'A');
  document.getElementById('panelB').classList.toggle('visible', team === 'B');
  highlightTab(team);
}

function highlightTab(team) {
  document.getElementById('tabA').className = 'team-tab' + (team === 'A' ? ' active-a' : '');
  document.getElementById('tabB').className = 'team-tab' + (team === 'B' ? ' active-b' : '');
}

// ═══════════════════════════════════════════════════════
//  SAVE FILE — posts game data to save_game.php
// ═══════════════════════════════════════════════════════
async function bbSaveFile() {
  const scoreA = state.teamA.players.reduce((s,p) => s + (p.pts || 0), 0) + (typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0);
  const scoreB = state.teamB.players.reduce((s,p) => s + (p.pts || 0), 0) + (typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0);
  const committee = document.getElementById('bbCommitteeInput')?.value?.trim() || '';
  const payload = {
    teamA: { ...state.teamA, score: scoreA },
    teamB: { ...state.teamB, score: scoreB },
    shared: state.shared,
    committee
  };

  // Include a live snapshot of the full client state (including timers)
  // so the server can persist canonical match_state for this saved match.
  try {
    const stateSnapshot = buildStatePayload();
    try { if (typeof gtAnchorTs === 'number') stateSnapshot.gameTimer.ts = Number(gtAnchorTs); } catch(_) {}
    try { if (typeof scAnchorTs === 'number') stateSnapshot.shotClock.ts = Number(scAnchorTs); } catch(_) {}
    payload.state = stateSnapshot;
    // Include current match id (when available) so server updates the existing match
    try {
      const curMid = getMatchId();
      if (curMid && String(curMid) !== '0' && !isNaN(parseInt(curMid,10)) && parseInt(curMid,10) > 0) payload.match_id = String(curMid);
    } catch(_) {}
  } catch (_) {}

  try {
    const res = await fetch('save_game.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data && data.success) {
      const reportUrl = 'report.php?match_id=' + data.match_id;
      try { sessionStorage.setItem('basketball_match_id', String(data.match_id)); } catch (e) {}
      try { sessionStorage.setItem('shouldClearPersistedOnBack:basketball', '1'); } catch (e) {}
      // Redirect to the report in the same tab (avoids popup-blocking issues)
      window.location.href = reportUrl;
      return;
    } else {
      showToast('❌ Save failed: ' + (data && data.error ? data.error : 'Unknown error'));
    }
  } catch (err) {
    showToast('❌ Network error: ' + (err && err.message ? err.message : String(err)));
  }
}

// Reset the current match: clear state, DOM, localStorage and broadcast reset.
async function bbResetMatch(force, clearPlayers) {
  try {
    if (!force) force = false;
    if (typeof clearPlayers === 'undefined') clearPlayers = true;
    if (!force) {
      const ok = confirm('Reset match? This will clear all players, scores, fouls, timeouts, and timers for ALL connected admins.');
      if (!ok) return;
    }

    const resetTs = Date.now();
    _lastStateResetTs = resetTs;
    const mid = getMatchId(); // keep existing match_id — do NOT create a new one

    // Stop timers
    gtTotalSecs = 10 * 60; gtRemaining = gtTotalSecs; gtRunning = false;
    gtAnchorTs = null; gtRemainingAtAnchor = null; gtLastTick = null;
    scPresetVal = 24; scTotal = 24; scRemaining = 24; scRunning = false;
    scAnchorTs = null; scRemainingAtAnchor = null; scLastTick = null;
    try { applyTimerButtonState('game', false); } catch(_) {}
    try { applyTimerButtonState('shot', false); } catch(_) {}
    try { gtRender(); } catch(_) {}
    try { scRenderFrame(); } catch(_) {}

    // Clear in-memory state
    state.teamA = { name: 'TEAM A', players: [], foul: 0, timeout: 0, manualScore: 0 };
    state.teamB = { name: 'TEAM B', players: [], foul: 0, timeout: 0, manualScore: 0 };
    state.shared = { foul: 0, timeout: 0, quarter: 1 };
    pCount = { A: 0, B: 0 };
    savedState = null;
    clearRosterDirty();

    // Update DOM
    try { document.getElementById('tbodyA').innerHTML = ''; } catch(_) {}
    try { document.getElementById('tbodyB').innerHTML = ''; } catch(_) {}
    try { document.getElementById('teamAName').value = 'TEAM A'; } catch(_) {}
    try { document.getElementById('teamBName').value = 'TEAM B'; } catch(_) {}
    try { document.getElementById('labelA').textContent = 'TEAM A'; } catch(_) {}
    try { document.getElementById('labelB').textContent = 'TEAM B'; } catch(_) {}
    try { document.getElementById('scoreA').textContent = '0'; } catch(_) {}
    try { document.getElementById('scoreB').textContent = '0'; } catch(_) {}
    try { document.getElementById('bbTsbAFoul').textContent = '0'; } catch(_) {}
    try { document.getElementById('bbTsbATimeout').textContent = '0'; } catch(_) {}
    try { document.getElementById('bbTsbBFoul').textContent = '0'; } catch(_) {}
    try { document.getElementById('bbTsbBTimeout').textContent = '0'; } catch(_) {}
    try { document.getElementById('bbQuarterVal').textContent = '1'; } catch(_) {}
    try { document.getElementById('bbPerQuarterVal').textContent = '1'; } catch(_) {}
    try { const ci = document.getElementById('bbCommitteeInput'); if (ci) ci.value = ''; } catch(_) {}
    try { syncRightPanelCounters(); } catch(_) {}

    // Clear localStorage state cache only — keep match_id intact
    try { localStorage.removeItem('basketball_state'); } catch(_) {}

    if (!mid || String(mid) === '0') {
      try { showToast('Match reset locally (no match_id)'); } catch(_) {}
      return;
    }

    // Build cleared payloads
    const clearedRosterPayload = {
      teamA: { name: 'TEAM A', score: 0, foul: 0, timeout: 0, manualScore: 0, players: [] },
      teamB: { name: 'TEAM B', score: 0, foul: 0, timeout: 0, manualScore: 0, players: [] },
      shared: { foul: 0, timeout: 0, quarter: 1 },
      committee: '',
      resetAt: resetTs
    };
    const clearedTimerPayload = {
      gameTimer: { total: 600, remaining: 600, running: false, ts: null, last_update_at: resetTs },
      shotClock: { total: 24, remaining: 24, running: false, ts: null, last_update_at: resetTs }
    };

    // Persist cleared roster to state.php under existing match_id
    fetch('/basketball-admin/state', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ match_id: mid, payload: clearedRosterPayload, meta: { action: 'reset_match', clientId: CLIENT_ID }, confirmed: true })
    }).catch(() => {});

    // Persist cleared timers to timer.php under existing match_id
    try {
        await fetch('/basketball-admin/timer', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ match_id: mid, game_time: 600, shot_clock: 24, is_running: false, last_update_at: resetTs, gameTimer: clearedTimerPayload.gameTimer, shotClock: clearedTimerPayload.shotClock, meta: { control: 'reset', clientId: CLIENT_ID } })
        });
    } catch (_) {}

    // Broadcast reset_match to all other admin devices via WS
    if (_ws && _ws.readyState === WebSocket.OPEN) {
      try {
        _ws.send(JSON.stringify({
          type: 'reset_match', sport: 'basketball', match_id: mid,
          payload: clearedRosterPayload,
          ts: resetTs,
          meta: { clientId: CLIENT_ID, ts: resetTs, action: 'reset_match' }
        }));
      } catch(_) {}
    }

    // Timer reset is not sent through BroadcastChannel or browser WebSocket.
    // timer.php must emit the final 600/24 state directly to the WS server.

    try { showToast('Match reset — all admins notified'); } catch(_) {}

  } catch (err) {
    console.error('resetMatch error', err);
    try { showToast('Error resetting match'); } catch(_) {}
  }
}

function confirmReset(clearPlayers) {
  try {
    const ok = confirm('Warning: this will clear all match data and reset the game. Do you want to continue?');
    if (!ok) return;
    bbResetMatch(true, clearPlayers);
  } catch (err) {
    console.error('confirmReset error', err);
  }
}

// Delete saved match rows from server DB. Calls delete_match.php.
async function bbDeleteSavedMatch() {
  try {
    const matchId = getMatchId();
    if (!matchId) {
      alert('No saved match_id found for this session. Save the game first.');
      return;
    }
    const ok = confirm('Warning: this will PERMANENTLY delete the saved match and its players from the server database. Continue?');
    if (!ok) return;

    const res = await fetch('delete_match.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ match_id: String(matchId) })
    });
    const data = await res.json();
    if (data && data.success) {
      showToast('Saved match deleted from server.');
      // also clear local live state and DOM to avoid confusion
      bbResetMatch();
    } else {
      showToast('Delete failed: ' + (data && data.error ? data.error : 'Unknown error'));
    }
  } catch (e) {
    console.error('deleteSavedMatch error', e);
    showToast('Network error while deleting match');
  }
}

// ═══════════════════════════════════════════════════════
//  LIVE BROADCAST — feeds basketball_viewer.php instantly
//  Uses BroadcastChannel for same-browser tabs and WebSocket relay for
//  real-time synchronization. State persistence happens through state.php.
//  Called automatically after every state-changing action.
// ═══════════════════════════════════════════════════════
const _BK_TIMER_KEY    = 'basketballTimerState';
const _BK_CHANNEL_NAME = 'basketball_live';
let   _bkBC = null;
let   _lastOutgoingTimerSerialized = null;
let   _lastTimerControlTs = 0;
let _lastStateSyncTs = 0;
// Timer persist debounce
let _timerPersistTimeout = null;
const TIMER_PERSIST_DEBOUNCE_MS = 600;
// Hydration guard: when true, client is fetching canonical server state
// and must not emit local timer/state writes that could overwrite SSOT.
let _hydrationPending = false;
// Initial hydration complete flag: block automatic debounced writes to
// `state.php` until the client has attempted server-first hydration.
let _initialHydrationDone = false;
try { _bkBC = new BroadcastChannel(_BK_CHANNEL_NAME); } catch (_) {}
if (_bkBC) {
  _bkBC.onmessage = function (e) {
    try {
      const msg = e.data && typeof e.data === 'object' ? e.data : JSON.parse(e.data);
      console.log('BC received:', msg.type || 'state', 'match_id:', msg.match_id);
      if (!msg) return;
      if (msg.type === 'new_match') {
        try { adoptBasketballMatch({ match_id: msg.match_id || (msg.payload && msg.payload.match_id), payload: msg.payload }); } catch (_) {}
        return;
      }
      if (msg.type === 'reset_match') { // FIX: reset — handle remote reset
        try { // FIX: reset
          // Ignore echoes from this client
          if (msg.meta && msg.meta.clientId && msg.meta.clientId === CLIENT_ID) return; // FIX: reset
          const incomingMid = msg.match_id ? String(msg.match_id) : null; // FIX: reset
          const newMatchId = msg.meta && msg.meta.new_match_id ? String(msg.meta.new_match_id) : incomingMid; // FIX: reset

          // Adopt new match_id if provided // FIX: reset
          if (newMatchId) { // FIX: reset
            try { sessionStorage.setItem('basketball_match_id', newMatchId); } catch(_) {} // FIX: reset
            try { localStorage.setItem('basketball_match_id', newMatchId); } catch(_) {} // FIX: reset
            try { window.__matchId = newMatchId; } catch(_) {} // FIX: reset
          } // FIX: reset

          // Apply cleared roster // FIX: reset
          const p = msg.payload || {}; // FIX: reset
          applyRosterState({ // FIX: reset
            teamA: p.teamA || { name: 'TEAM A', score: 0, foul: 0, timeout: 0, manualScore: 0, players: [] }, // FIX: reset
            teamB: p.teamB || { name: 'TEAM B', score: 0, foul: 0, timeout: 0, manualScore: 0, players: [] }, // FIX: reset
            shared: p.shared || { foul: 0, timeout: 0, quarter: 1 }, // FIX: reset
            committee: p.committee || '', // FIX: reset
            resetAt: msg.ts || Date.now() // FIX: reset
          }); // FIX: reset

          // Apply cleared timers // FIX: reset
          applyGameTimer(p.gameTimer || { total: 600, remaining: 600, running: false, ts: null }); // FIX: reset
          applyShotClock(p.shotClock || { total: 24, remaining: 24, running: false, ts: null }); // FIX: reset

          // Clear localStorage on receiving client too // FIX: reset
          try { localStorage.removeItem('basketball_state'); } catch(_) {} // FIX: reset

          try { showToast('Match was reset by another admin'); } catch(_) {} // FIX: reset
        } catch(_) {} // FIX: reset
        return; // FIX: reset — must return to prevent fallthrough
      }
      if (msg.type === 'timer') {
        // Timer updates (throttled lightweight updates)
        const payload = msg.payload;
        if (!payload) return;
        if (typeof msg.ts === 'number' && msg.ts <= _lastTimerControlTs) return;
        try {
          const s = JSON.stringify(payload);
          if (s === _lastOutgoingTimerSerialized) return;
        } catch(_){ }
        try { const incomingMid = msg.match_id ? String(msg.match_id) : null; if (incomingMid && String(incomingMid) !== String(getMatchId())) return; } catch(_) {}
        applyIncomingState(payload);
        return;
      }
      if (msg.type === 'state_changed') {
        console.log('BC received state_changed signal for match_id:', msg.match_id);
        try {
          const payload = msg.payload;
          if (payload) {
            try { const s = JSON.stringify(payload); if (s === _lastOutgoingSerialized) return; } catch(_) {}
            try { const incomingMid = msg.match_id ? String(msg.match_id) : null; if (incomingMid && String(incomingMid) !== String(getMatchId())) return; } catch(_) {}
            applyIncomingState(payload);
            return;
          }
          const mid = msg.match_id || getMatchId();
          console.log('Fetching latest state from server for match_id:', mid);
          loadStateFromServerIfMissing(true).then(() => {
            console.log('State fetch completed');
          }).catch((err) => {
            console.error('State fetch failed:', err);
          });
        } catch (_) {
          console.error('state_changed handler error:', _);
        }
        return;
      }
      // TIMERFIX-ROSTER: route basketball_state through applyRosterState (not applyIncomingState)
      // Prevents bbRenderRosterTable DOM work from saturating the main thread and freezing timer UI
      if (msg.type === 'basketball_state') {
        const bsPayload = msg.payload;
        if (bsPayload) {
          try { const s = JSON.stringify(bsPayload); if (s === _lastOutgoingSerialized) return; } catch(_) {}
          try { const incomingMid = msg.match_id ? String(msg.match_id) : null; if (incomingMid && String(incomingMid) !== String(getMatchId())) return; } catch(_) {}
          applyRosterState({
            teamA: bsPayload.teamA ? { name: bsPayload.teamA.name, score: bsPayload.teamA.score, foul: bsPayload.teamA.foul, timeout: bsPayload.teamA.timeout, manualScore: bsPayload.teamA.manualScore, players: bsPayload.teamA.players } : undefined,
            teamB: bsPayload.teamB ? { name: bsPayload.teamB.name, score: bsPayload.teamB.score, foul: bsPayload.teamB.foul, timeout: bsPayload.teamB.timeout, manualScore: bsPayload.teamB.manualScore, players: bsPayload.teamB.players } : undefined,
            shared: bsPayload.shared ? { quarter: bsPayload.shared.quarter, foul: bsPayload.shared.foul, timeout: bsPayload.shared.timeout } : undefined,
            committee: bsPayload.committee
          });
        }
        return;
      }
      // Default: full state update (for compatibility)
      const incomingMid = msg.match_id || (msg.payload && msg.payload.match_id) || null;
      try { if (incomingMid && String(incomingMid) !== String(getMatchId())) return; } catch(_) {}
      const payload = msg.payload || msg.state || msg;
      if (!payload) return;
      if (typeof msg.ts === 'number' && msg.ts <= _lastTimerControlTs) return;
      try {
        const s = JSON.stringify(payload);
        if (s === _lastOutgoingSerialized || s === _lastOutgoingTimerSerialized) return;
      } catch(_){ }
      // GAMETIMERFIX / SHOTCLOCKFIX: promote wrapper-level meta into payload so _isExplicit gates pass
      if (msg.meta && msg.meta.control && typeof payload === 'object' && payload !== null) {
        payload._timerControl = true;
        if (!payload.meta) payload.meta = {};
        payload.meta.control = msg.meta.control;
      }
      applyIncomingState(payload);
    } catch (_) {}
  };
}

// also listen to storage events (cross-tab fallback)
window.addEventListener('storage', function (e) {
  try {
    if (!IS_BASKETBALL_PAGE) return;
    // Special-case: another admin created a NEW MATCH. Use a dedicated
    // storage key so all tabs can adopt the new match_id and reset.
    if (e.key === 'basketball_new_match') {
      if (!e.newValue) return;
      let info = null;
      try { info = JSON.parse(e.newValue); } catch (_) { info = e.newValue; }
      try { adoptBasketballMatch({ match_id: info && info.match_id ? String(info.match_id) : String(info || ''), payload: info && info.payload ? info.payload : null }); } catch (_) {}
      return;
    }
    // Timer-only updates (lightweight) — apply only for same match
    if (e.key === _BK_TIMER_KEY) {
      if (!e.newValue) return;
      let wrapper = null;
      try { wrapper = JSON.parse(e.newValue); } catch(_) { return; }
      if (!wrapper) return;
      if (typeof wrapper.ts === 'number' && wrapper.ts <= _lastTimerControlTs) return;
      const payload = wrapper && wrapper.payload ? wrapper.payload : wrapper;
      if (!payload) return;
      try {
        const s = JSON.stringify(payload);
        if (s === _lastOutgoingTimerSerialized) return;
      } catch(_) {}
      try { const incomingMid = wrapper && wrapper.match_id ? String(wrapper.match_id) : null; if (incomingMid && String(incomingMid) !== String(getMatchId())) return; } catch(_) {}
      // GAMETIMERFIX / SHOTCLOCKFIX: promote wrapper-level meta into payload so _isExplicit gates pass
      if (wrapper && wrapper.meta && wrapper.meta.control && typeof payload === 'object' && payload !== null) {
        payload._timerControl = true;
        if (!payload.meta) payload.meta = {};
        payload.meta.control = wrapper.meta.control;
      }
      applyIncomingState(payload);
      return;
    }
  } catch (_) {}
});

async function loadStateFromServerIfMissing() {
  try {
    // If caller explicitly forces a server refresh, ignore any localStorage.
    const force = (arguments && arguments.length > 0 && arguments[0]) ? true : false;
    console.log('loadStateFromServerIfMissing called, force:', force);
    // Always prefer fetching server canonical state first; do not hydrate roster/counter state from localStorage.
    const mid = getMatchId();
    console.log('Match ID:', mid);
    // skip when no valid numeric match id available (avoid match_id=0)
    if (!mid) { console.warn('Invalid match_id, using live'); }
    console.log('Fetching state from server for match_id:', mid);
    const res = await fetch('/basketball-admin/state?match_id=' + encodeURIComponent(mid) + '&t=' + Date.now(), { cache: 'no-store', credentials: 'include' });
    const j = await res.json();
    console.log('Server response:', j);
    if (j && j.success) {
      const serverPayload = j.payload;
      console.log('Server payload received:', serverPayload);
      if (!serverPayload) {
        console.log('No server payload and no local persisted roster state; skipping hydration');
        return;
      }

      // Detect whether server payload actually contains roster players
      const hasPlayersA = serverPayload.teamA && Array.isArray(serverPayload.teamA.players) && serverPayload.teamA.players.length > 0;
      const hasPlayersB = serverPayload.teamB && Array.isArray(serverPayload.teamB.players) && serverPayload.teamB.players.length > 0;
      console.log('Has players - Team A:', hasPlayersA, 'Team B:', hasPlayersB);

      let appliedPayload = serverPayload;

      // Server payload is authoritative for hydration; do not merge local drafts.

      // Apply server/merged payload. If there's an authoritative timer store, prefer its values
      try {
        let _usedTimerPayload = false;
        try {
          console.log('Fetching timer state for match_id:', mid);
          const tRes = await fetch('/basketball-admin/timer?match_id=' + encodeURIComponent(mid), { cache: 'no-store', credentials: 'include' });
          const tj = await tRes.json();
          console.log('Timer response:', tj);
          if (tj && tj.success && tj.payload) {
            _usedTimerPayload = true;
            console.log('Using timer payload');
            try {
              const now = Date.now();
              const tPayload = tj.payload || {};
              const newApplied = JSON.parse(JSON.stringify(appliedPayload || {}));

              // Helper to read timer data supporting snake_case or camelCase
              const readTimer = (src) => {
                if (!src) return null;
                // prefer explicit nested keys
                const gt = src.game_timer || src.gameTimer || src;
                return gt;
              };

              // GAME
              const gtSrc = readTimer(tPayload);
              if (gtSrc) {
                const hasMs = typeof gtSrc.remaining_ms === 'number' || typeof gtSrc.paused_remaining_ms === 'number' || typeof gtSrc.total_ms === 'number';
                const remainingAtStart = (typeof gtSrc.remaining_ms === 'number') ? (gtSrc.remaining_ms / 1000.0) : (typeof gtSrc.remaining === 'number' ? gtSrc.remaining : (typeof gtSrc.total_ms === 'number' ? (gtSrc.total_ms / 1000.0) : (typeof gtSrc.total === 'number' ? gtSrc.total : 0)));
                const startTs = (typeof gtSrc.start_timestamp === 'number') ? gtSrc.start_timestamp : (typeof gtSrc.ts === 'number' ? gtSrc.ts : null);
                newApplied.gameTimer = newApplied.gameTimer || {};
                newApplied.gameTimer.total = (typeof gtSrc.total_ms === 'number') ? (gtSrc.total_ms / 1000.0) : ((typeof gtSrc.total === 'number') ? gtSrc.total : (newApplied.gameTimer.total || 0));
                if (gtSrc.running && startTs) {
                  // keep remaining referenced to the original start timestamp
                  newApplied.gameTimer.remaining = remainingAtStart;
                  newApplied.gameTimer.running = !!gtSrc.running;
                  newApplied.gameTimer.ts = startTs;
                } else {
                  // paused/stopped — prefer paused_remaining_ms, then remaining
                  const paused = (typeof gtSrc.paused_remaining_ms === 'number') ? (gtSrc.paused_remaining_ms / 1000.0) : (typeof gtSrc.remaining_ms === 'number' ? (gtSrc.remaining_ms / 1000.0) : (typeof gtSrc.remaining === 'number' ? gtSrc.remaining : (newApplied.gameTimer.remaining || 0)));
                  newApplied.gameTimer.remaining = paused;
                  newApplied.gameTimer.running = !!gtSrc.running;
                  newApplied.gameTimer.ts = null;
                }
              }

              // SHOT
              const scSrc = readTimer(tPayload && tPayload.shotClock ? tPayload : (tPayload && tPayload.shot_clock ? tPayload : null));
              // try explicit shotClock / shot_clock
              let shotSrc = null;
              if (tPayload && (tPayload.shotClock || tPayload.shot_clock)) {
                shotSrc = tPayload.shot_clock || tPayload.shotClock;
              }
              if (shotSrc) {
                const remainingAtStart = (typeof shotSrc.remaining_ms === 'number') ? (shotSrc.remaining_ms / 1000.0) : (typeof shotSrc.remaining === 'number' ? shotSrc.remaining : (typeof shotSrc.total_ms === 'number' ? (shotSrc.total_ms / 1000.0) : (typeof shotSrc.total === 'number' ? shotSrc.total : 0)));
                const startTs = (typeof shotSrc.start_timestamp === 'number') ? shotSrc.start_timestamp : (typeof shotSrc.ts === 'number' ? shotSrc.ts : null);
                newApplied.shotClock = newApplied.shotClock || {};
                newApplied.shotClock.total = (typeof shotSrc.total_ms === 'number') ? (shotSrc.total_ms / 1000.0) : ((typeof shotSrc.total === 'number') ? shotSrc.total : (newApplied.shotClock.total || 0));
                if (shotSrc.running && startTs) {
                  newApplied.shotClock.remaining = remainingAtStart;
                  newApplied.shotClock.running = !!shotSrc.running;
                  newApplied.shotClock.ts = startTs;
                } else {
                  const paused = (typeof shotSrc.paused_remaining_ms === 'number') ? (shotSrc.paused_remaining_ms / 1000.0) : (typeof shotSrc.remaining_ms === 'number' ? (shotSrc.remaining_ms / 1000.0) : (typeof shotSrc.remaining === 'number' ? shotSrc.remaining : (newApplied.shotClock.remaining || 0)));
                  newApplied.shotClock.remaining = paused;
                  newApplied.shotClock.running = !!shotSrc.running;
                  newApplied.shotClock.ts = null;
                }
              }

              appliedPayload = newApplied;
            } catch (e) { /* ignore timer merge errors */ }
          }
        } catch (e) { /* ignore timer fetch errors */ }

        // If timer.php did not provide an authoritative payload, try to
        // compute live remaining from the server `match_state` payload
        // (serverPayload) when it contains a timestamped timer snapshot.
        if (!_usedTimerPayload) {
          try {
            const now = Date.now();
            const newApplied = JSON.parse(JSON.stringify(appliedPayload || {}));
            // prefer snake_case game_timer if present, else camelCase gameTimer
            const srvGT = serverPayload && (serverPayload.game_timer || serverPayload.gameTimer) ? (serverPayload.game_timer || serverPayload.gameTimer) : null;
            if (srvGT) {
              const remainingAtStart = (typeof srvGT.remaining_ms === 'number') ? (srvGT.remaining_ms / 1000.0) : (typeof srvGT.remaining === 'number' ? srvGT.remaining : (typeof srvGT.total_ms === 'number' ? (srvGT.total_ms / 1000.0) : (typeof srvGT.total === 'number' ? srvGT.total : 0)));
              const startTs = (typeof srvGT.start_timestamp === 'number') ? srvGT.start_timestamp : (typeof srvGT.ts === 'number' ? srvGT.ts : null);
              newApplied.gameTimer = newApplied.gameTimer || {};
              newApplied.gameTimer.total = (typeof srvGT.total_ms === 'number') ? (srvGT.total_ms / 1000.0) : ((typeof srvGT.total === 'number') ? srvGT.total : (newApplied.gameTimer.total || 0));
              if (srvGT.running && startTs) {
                newApplied.gameTimer.remaining = remainingAtStart;
                newApplied.gameTimer.running = !!srvGT.running;
                newApplied.gameTimer.ts = startTs;
              } else {
                const paused = (typeof srvGT.paused_remaining_ms === 'number') ? (srvGT.paused_remaining_ms / 1000.0) : (typeof srvGT.remaining_ms === 'number' ? (srvGT.remaining_ms / 1000.0) : (typeof srvGT.remaining === 'number' ? srvGT.remaining : (newApplied.gameTimer.remaining || 0)));
                newApplied.gameTimer.remaining = paused;
                newApplied.gameTimer.running = !!srvGT.running;
                newApplied.gameTimer.ts = null;
              }
            }
            const srvSC = serverPayload && (serverPayload.shot_clock || serverPayload.shotClock) ? (serverPayload.shot_clock || serverPayload.shotClock) : null;
            if (srvSC) {
              const remainingAtStart = (typeof srvSC.remaining_ms === 'number') ? (srvSC.remaining_ms / 1000.0) : (typeof srvSC.remaining === 'number' ? srvSC.remaining : (typeof srvSC.total_ms === 'number' ? (srvSC.total_ms / 1000.0) : (typeof srvSC.total === 'number' ? srvSC.total : 0)));
              const startTs = (typeof srvSC.start_timestamp === 'number') ? srvSC.start_timestamp : (typeof srvSC.ts === 'number' ? srvSC.ts : null);
              newApplied.shotClock = newApplied.shotClock || {};
              newApplied.shotClock.total = (typeof srvSC.total_ms === 'number') ? (srvSC.total_ms / 1000.0) : ((typeof srvSC.total === 'number') ? srvSC.total : (newApplied.shotClock.total || 0));
              if (srvSC.running && startTs) {
                newApplied.shotClock.remaining = remainingAtStart;
                newApplied.shotClock.running = !!srvSC.running;
                newApplied.shotClock.ts = startTs;
              } else {
                const paused = (typeof srvSC.paused_remaining_ms === 'number') ? (srvSC.paused_remaining_ms / 1000.0) : (typeof srvSC.remaining_ms === 'number' ? (srvSC.remaining_ms / 1000.0) : (typeof srvSC.remaining === 'number' ? srvSC.remaining : (newApplied.shotClock.remaining || 0)));
                newApplied.shotClock.remaining = paused;
                newApplied.shotClock.running = !!srvSC.running;
                newApplied.shotClock.ts = null;
              }
            }
            appliedPayload = newApplied;
          } catch (e) { /* ignore fallback errors */ }
        }
        if (appliedPayload && (appliedPayload.gameTimer || appliedPayload.shotClock)) {
          appliedPayload._timerControl = true;
        }
      } catch (e) {}

      try {
        // Ensure payload has required structure to prevent timer-only payloads from wiping a live roster
        if (!appliedPayload.teamA) {
          appliedPayload.teamA = { players: state.teamA.players || [] };
        // FIX: reset — do NOT backfill players from local state if server returned empty (could be a legitimate reset)
        } else if (!Array.isArray(appliedPayload.teamA.players) || appliedPayload.teamA.players.length === 0) { // FIX: reset
          const serverResetAt = appliedPayload.resetAt || 0; // FIX: reset
          const isResetPayload = serverResetAt > 0 && serverResetAt >= (_lastStateResetTs || 0); // FIX: reset
          if (!isResetPayload && state.teamA.players && state.teamA.players.length > 0) { // FIX: reset
            appliedPayload.teamA.players = state.teamA.players; // FIX: reset — only backfill if NOT a reset
          } // FIX: reset
        } // FIX: reset
        if (!appliedPayload.teamB) {
          appliedPayload.teamB = { players: state.teamB.players || [] };
        // FIX: reset — do NOT backfill players from local state if server returned empty (could be a legitimate reset)
        } else if (!Array.isArray(appliedPayload.teamB.players) || appliedPayload.teamB.players.length === 0) { // FIX: reset
          const serverResetAtB = appliedPayload.resetAt || 0; // FIX: reset
          const isResetPayloadB = serverResetAtB > 0 && serverResetAtB >= (_lastStateResetTs || 0); // FIX: reset
          if (!isResetPayloadB && state.teamB.players && state.teamB.players.length > 0) { // FIX: reset
            appliedPayload.teamB.players = state.teamB.players; // FIX: reset — only backfill if NOT a reset
          } // FIX: reset
        } // FIX: reset
        if (!appliedPayload.shared) appliedPayload.shared = {};
        
        const tmp = JSON.parse(JSON.stringify(appliedPayload));
        // record canonical saved state so drafts can be reverted
        try { savedState = JSON.parse(JSON.stringify(tmp)); } catch(_) { savedState = tmp; }
        try { clearRosterDirty(); } catch(_) {}
        console.log('Applying incoming state with players:', tmp.teamA ? (tmp.teamA.players ? tmp.teamA.players.length : 0) : 0, tmp.teamB ? (tmp.teamB.players ? tmp.teamB.players.length : 0) : 0);
        applyIncomingState(tmp);
        console.log('State successfully applied');
        // If server reports timers running, start local loops so reloading
        // admins resume the running timers immediately.
      } catch (e) {
        console.error('Error applying state:', e);
      }

      // If server provided a real payload we successfully applied it.
      console.log('loadStateFromServerIfMissing returning true');
      return true;
    } else {
      console.warn('Server response failed:', j);
    }
  } catch (e) {
    console.error('loadStateFromServerIfMissing error:', e);
  }
  console.log('loadStateFromServerIfMissing returning false');
  return false;
}


// Build the exact full non-timer state to send through WS/state.php.
function buildBasketballStatsSyncPayload() {
  try { return buildRosterOnlyPayload(); } catch (_) {}
  try {
    const committee = document.getElementById('bbCommitteeInput')?.value?.trim() || '';
    const scoreA = (state.teamA.players || []).reduce((sum, p) => sum + (p.pts || 0), 0) + (typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0);
    const scoreB = (state.teamB.players || []).reduce((sum, p) => sum + (p.pts || 0), 0) + (typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0);
    const cleanPlayers = function(players) {
      return (players || []).map(function(p) {
        return {
          id: p.id, no: p.no, name: p.name,
          pts: p.pts || 0, foul: p.foul || 0, reb: p.reb || 0, ast: p.ast || 0,
          blk: p.blk || 0, stl: p.stl || 0,
          techFoul: p.techFoul || 0, techReason: p.techReason || '', selected: !!p.selected
        };
      });
    };
    return {
      teamA: {
        name: state.teamA.name || 'TEAM A', score: scoreA,
        foul: state.teamA.foul || 0, timeout: state.teamA.timeout || 0,
        manualScore: typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0,
        quarter: state.shared.quarter || 1, players: cleanPlayers(state.teamA.players)
      },
      teamB: {
        name: state.teamB.name || 'TEAM B', score: scoreB,
        foul: state.teamB.foul || 0, timeout: state.teamB.timeout || 0,
        manualScore: typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0,
        quarter: state.shared.quarter || 1, players: cleanPlayers(state.teamB.players)
      },
      shared: Object.assign({ foul:0, timeout:0, quarter:1 }, state.shared || {}),
      committee: committee,
      resetAt: _lastStateResetTs || 0
    };
  } catch (_) { return null; }
}

function sendBasketballStatsSync(reason) {
  try {
    const mid = getMatchId();
    if (!mid || String(mid) === '0') return;
    const payload = buildBasketballStatsSyncPayload();
    if (!payload) return;
    const msg = {
      type: 'basketball_state',
      sport: 'basketball',
      match_id: mid,
      payload: payload,
      meta: { clientId: CLIENT_ID, ts: Date.now(), source: reason || 'admin' }
    };
    if (_ws && _ws.readyState === WebSocket.OPEN) _ws.send(JSON.stringify(msg));
  } catch (_) {}
}



// ADMIN RETURN/REJOIN FIX: adopt active match room without touching timer/stat control logic.
// Used when admin comes from Home/back navigation/reconnect and must sync to the current live match.
function adminAdoptActiveMatchId(newMatchId, source) {
  try {
    if (!newMatchId) return false;
    const newId = String(newMatchId).trim();
    if (!newId || newId === '0') return false;
    const oldId = String(typeof getMatchId === 'function' ? getMatchId() : '');

    try { sessionStorage.setItem('basketball_match_id', newId); } catch (_) {}
    try { localStorage.setItem('basketball_match_id', newId); } catch (_) {}
    try { window.__matchId = newId; } catch (_) {}

    console.log('[Basketball Admin] adopted active match_id:', newId, 'from:', source || 'server', 'previous:', oldId);

    if (_ws && _ws.readyState === WebSocket.OPEN) {
      const role = (window && window.__role) ? String(window.__role) : 'admin';
      try { _ws.send(JSON.stringify({ type: 'join', match_id: newId, role: role, meta: { source: 'admin_adopt', clientId: CLIENT_ID, ts: Date.now() } })); } catch (_) {}
      try { _ws.send(JSON.stringify({ type: 'get_state', match_id: newId, role: role, meta: { source: 'admin_adopt', clientId: CLIENT_ID, ts: Date.now() } })); } catch (_) {}
      try { _ws.send(JSON.stringify({ type: 'admin_present', match_id: newId, sport: 'basketball', meta: { source: 'admin_adopt', clientId: CLIENT_ID, ts: Date.now() } })); } catch (_) {}
    }

    // Pull canonical saved state/timer after adopting. This is read/apply only.
    try { loadStateFromServerIfMissing(true).catch(function(){}); } catch (_) {}
    try { initializeTimersFromServerState(); } catch (_) {}
    return true;
  } catch (e) {
    console.warn('[Basketball Admin] failed to adopt active match_id:', e);
    return false;
  }
}

function adminProbeActiveMatch(source) {
  try {
    if (!_ws || _ws.readyState !== WebSocket.OPEN) return;
    const role = (window && window.__role) ? String(window.__role) : 'admin';
    // Join live fallback so server can reply with active_match.
    _ws.send(JSON.stringify({ type: 'join', match_id: 'live', role: role, meta: { source: source || 'admin_probe', clientId: CLIENT_ID, ts: Date.now() } }));
    _ws.send(JSON.stringify({ type: 'get_state', match_id: 'live', role: role, meta: { source: source || 'admin_probe', clientId: CLIENT_ID, ts: Date.now() } }));
  } catch (_) {}
}

// WebSocket relay (Option A): try connect to local WS server to broadcast
// admin updates to remote viewers across devices.
let _ws = null;
try {
  if (location && location.hostname) {
    const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
    let url = proto + '//' + location.hostname + ':3000';
    if (window.__wsToken) url += '?token=' + encodeURIComponent(window.__wsToken);
    _ws = new WebSocket(url);
    _ws.addEventListener('open', async () => {
      console.info('Sportssync WS connected');
      _setWSStatus('connected');
      try {
        // Join with the current match_id
        const mid = getMatchId();
        const role = (window && window.__role) ? String(window.__role) : 'unknown';
        console.log('Joining match_id:', mid);
        const waitForHydration = (cb) => { if (_initialHydrationDone) return cb(); const id = setInterval(() => { if (_initialHydrationDone) { clearInterval(id); cb(); } }, 50); };
        waitForHydration(async () => {
          // ADMIN RETURN/REJOIN FIX: first probe live room so server can tell this admin
          // the current active match_id after coming from Home/back navigation/reconnect.
          adminProbeActiveMatch('admin_ws_open');

          // Keep existing room join if admin already has the correct match_id.
          // Do not push local stats/timer on open, because a returning admin may have stale/default UI.
          // Server cached state / PHP state will hydrate this admin instead.
          if (mid && String(mid) !== '0' && String(mid) !== 'live') {
            _ws.send(JSON.stringify({ type: 'join', match_id: mid, role: role, meta: { source: 'admin_ws_open', clientId: CLIENT_ID, ts: Date.now() } }));
            _ws.send(JSON.stringify({ type: 'get_state', match_id: mid, role: role, meta: { source: 'admin_ws_open', clientId: CLIENT_ID, ts: Date.now() } }));
            _ws.send(JSON.stringify({ type: 'admin_present', match_id: mid, sport: 'basketball', meta: { source: 'admin_ws_open', clientId: CLIENT_ID, ts: Date.now() } }));
          }
        });
      } catch (e) {}
    });
    _ws.addEventListener('close', () => { console.info('Sportssync WS closed'); _setWSStatus('disconnected'); });
    _ws.addEventListener('error', () => { _setWSStatus('error'); /* ignore */ });
    // inbound messages from ws-server: apply remote state/actions into admin UI
    _ws.addEventListener('message', function (ev) {
      try {
        const msg = JSON.parse(ev.data);
        console.log('WS received:', msg.type, 'match_id:', msg.match_id);
        if (!msg) return;
        if (msg.sport && msg.sport !== 'basketball') return;
  
        if (msg.meta && msg.meta.clientId && msg.meta.clientId === CLIENT_ID && msg.type === 'action') return;

        if (msg.type === 'active_match') {
          try {
            const newMid = msg.match_id || (msg.payload && msg.payload.match_id);
            if (newMid && String(newMid) !== String(getMatchId())) {
              adminAdoptActiveMatchId(newMid, 'active_match');
            } else if (newMid) {
              // Same id, but coming from Home/back may still need hydration.
              try { loadStateFromServerIfMissing(true).catch(function(){}); } catch (_) {}
              try { initializeTimersFromServerState(); } catch (_) {}
            }
          } catch (_) {}
          return;
        }
            if (msg.type === 'new_match') {
              try { adoptBasketballMatch({ match_id: msg.match_id || (msg.payload && msg.payload.match_id), payload: msg.payload }); } catch (_) {}
                return;
            }
        if (msg.type === 'timer_update') {
          try {
            // Ignore timer updates originating from a client unload/reload.
            // These are ephemeral and should not affect other admins' loops.
            if (msg.meta && msg.meta.unload) return;
            const incomingTs = (typeof msg.ts === 'number') ? msg.ts : ((msg.meta && typeof msg.meta.ts === 'number') ? msg.meta.ts : null);
            const remoteControl = msg.meta && msg.meta.control ? msg.meta.control : null;
            // Ignore passive timer updates older than the last explicit control.
            if (remoteControl === null && incomingTs !== null && incomingTs <= _lastTimerControlTs) return;
            // Record explicit control ordering so stale updates cannot override it.
            if (remoteControl !== null && incomingTs !== null) {
              _lastTimerControlTs = Math.max(_lastTimerControlTs, incomingTs);
            }
            // FIX: game timer path only — Route game_timer updates to game timer apply only
            if (msg.gameTimer) {
              // GAMETIMERFIX: inject meta and _timerControl so applyGameTimer can honour reset/preset signals
              const gtPayload = Object.assign({}, msg.gameTimer);
              if (remoteControl) { gtPayload._timerControl = true; gtPayload.meta = { control: remoteControl }; }
              applyGameTimer(gtPayload);
            }
            // FIX: shot clock path only — Route shot_clock updates to shot clock apply only
            if (msg.shotClock) {
              // SHOTCLOCKFIX: inject meta and _timerControl so applyShotClock can honour reset/preset signals
              const scPayload = Object.assign({}, msg.shotClock);
              if (remoteControl) { scPayload._timerControl = true; scPayload.meta = { control: remoteControl }; }
              applyShotClock(scPayload);
            }
          } catch (_) {}
          return;
        }
        if (msg.type === 'state_changed') {
          console.log('WS received state_changed signal for match_id:', msg.match_id);
          try {
            const payload = msg.payload;
            if (payload) {
              try { const s = JSON.stringify(payload); if (s === _lastOutgoingSerialized) return; } catch(_) {}
              try { const incomingMid = msg.match_id ? String(msg.match_id) : null; if (incomingMid && String(incomingMid) !== String(getMatchId())) return; } catch(_) {}
              // FIX: state_changed uses roster path — Extract roster-only payload and apply it separately
              const rosterOnly = {
                teamA: payload.teamA ? {
                  name: payload.teamA.name,
                  score: payload.teamA.score,
                  foul: payload.teamA.foul,
                  timeout: payload.teamA.timeout,
                  manualScore: payload.teamA.manualScore,
                  players: payload.teamA.players
                } : undefined,
                teamB: payload.teamB ? {
                  name: payload.teamB.name,
                  score: payload.teamB.score,
                  foul: payload.teamB.foul,
                  timeout: payload.teamB.timeout,
                  manualScore: payload.teamB.manualScore,
                  players: payload.teamB.players
                } : undefined,
                shared: payload.shared ? {
                  quarter: payload.shared.quarter,
                  foul: payload.shared.foul,
                  timeout: payload.shared.timeout
                } : undefined,
                committee: payload.committee,
                resetAt: payload.resetAt
              };
              // FIX: state_changed uses roster path — Apply roster state only (never touch timers)
              applyRosterState(rosterOnly);
              if (payload._timerControl === true || (payload.meta && payload.meta.control)) {
                if (payload.gameTimer) applyGameTimer(payload.gameTimer);
                if (payload.shotClock) applyShotClock(payload.shotClock);
              }
              return;
            }
            const mid = msg.match_id || getMatchId();
            console.log('Fetching latest state from server for match_id:', mid);
            loadStateFromServerIfMissing(true).then(() => {
              console.log('State fetch completed from WS trigger');
            }).catch((err) => {
              console.error('State fetch failed from WS trigger:', err);
            });
          } catch (_) {
            console.error('WS state_changed handler error:', _);
          }
          return;
        }
        // Handle basketball state sync requests and updates
        if (msg.type === 'basketball:request-sync') {
          sendBasketballStatsSync('request_sync_reply');
          return;
        }
        if (msg.type === 'basketball:state-sync') {
          // Accept legacy partial sync and full stats sync, but never touch timers here.
          if (msg.meta && msg.meta.clientId === CLIENT_ID) return;
          if (msg.meta && typeof msg.meta.ts === 'number' && msg.meta.ts <= _lastStateSyncTs) return;
          if (msg.meta && typeof msg.meta.ts === 'number') _lastStateSyncTs = msg.meta.ts;
          const p = msg.payload || {};
          const normalized = (p.teamA || p.teamB || p.shared || p.committee !== undefined) ? p : {
            teamA: {
              players: p.players && p.players.teamA ? p.players.teamA : state.teamA.players,
              foul: p.foul && p.foul.teamA != null ? p.foul.teamA : state.teamA.foul,
              timeout: p.timeout && p.timeout.teamA != null ? p.timeout.teamA : state.teamA.timeout,
              quarter: p.quarter != null ? p.quarter : state.shared.quarter
            },
            teamB: {
              players: p.players && p.players.teamB ? p.players.teamB : state.teamB.players,
              foul: p.foul && p.foul.teamB != null ? p.foul.teamB : state.teamB.foul,
              timeout: p.timeout && p.timeout.teamB != null ? p.timeout.teamB : state.teamB.timeout,
              quarter: p.quarter != null ? p.quarter : state.shared.quarter
            },
            shared: { quarter: p.quarter != null ? p.quarter : state.shared.quarter },
            committee: p.committee
          };
          applyRosterState(normalized);
          return;
        }
        // FIX: isolated from timer — Handle basketball_state separately to apply ONLY roster fields
        if (msg.type === 'basketball_state') {
          const payload = msg.payload;
          if (payload) {
            try { const s = JSON.stringify(payload); if (s === _lastOutgoingSerialized) return; } catch(_){}
            const rosterPayload = {
              teamA: payload.teamA ? {
                name: payload.teamA.name,
                score: payload.teamA.score,
                foul: payload.teamA.foul,
                timeout: payload.teamA.timeout,
                manualScore: payload.teamA.manualScore,
                players: payload.teamA.players
              } : undefined,
              teamB: payload.teamB ? {
                name: payload.teamB.name,
                score: payload.teamB.score,
                foul: payload.teamB.foul,
                timeout: payload.teamB.timeout,
                manualScore: payload.teamB.manualScore,
                players: payload.teamB.players
              } : undefined,
              shared: payload.shared ? {
                quarter: payload.shared.quarter,
                foul: payload.shared.foul,
                timeout: payload.shared.timeout
              } : undefined,
              committee: payload.committee
            };
            applyRosterState(rosterPayload);
          }
          return;
        }
        const payload = msg.payload || (msg.type === 'basketball_state' ? msg.payload : null);
        if (payload) {
          try { const s = JSON.stringify(payload); if (s === _lastOutgoingSerialized) return; } catch(_){}
          applyIncomingState(payload);
        }
      } catch (_) {}
    });
  }
} catch (_) { _ws = null; }



// ADMIN RETURN/REJOIN FIX: when returning from Home/back-forward cache or tab focus,
// ask WS server again for the current active basketball match. This is read-only.
try {
  window.addEventListener('pageshow', function () {
    try { setTimeout(function(){ adminProbeActiveMatch('admin_pageshow'); }, 150); } catch (_) {}
  });
  document.addEventListener('visibilitychange', function () {
    try { if (!document.hidden) setTimeout(function(){ adminProbeActiveMatch('admin_visibility'); }, 150); } catch (_) {}
  });
  window.addEventListener('focus', function () {
    try { setTimeout(function(){ adminProbeActiveMatch('admin_focus'); }, 150); } catch (_) {}
  });
} catch (_) {}

function getMatchId() {
  try {
    const DEFAULT_ROOM_ID = (typeof window.__defaultRoomId !== 'undefined' && String(window.__defaultRoomId) !== '0') ? String(window.__defaultRoomId) : 'live';
    // Check URL parameters first
    try {
      const urlParams = new URLSearchParams(window.location.search);
      const urlMid = urlParams.get('match_id');
      if (urlMid && String(urlMid).trim() !== '') {
        const mid = String(urlMid).trim();
        try { sessionStorage.setItem('basketball_match_id', mid); localStorage.setItem('basketball_match_id', mid); } catch (_) {}
        return mid;
      }
    } catch (_) {}
    if (window.MATCH_DATA && MATCH_DATA.match_id) return String(MATCH_DATA.match_id);
    if (window.__matchId) return String(window.__matchId);
    // Check persisted storage
    try {
      const sess = sessionStorage.getItem('basketball_match_id');
      if (sess) return String(sess);
      const loc = localStorage.getItem('basketball_match_id');
      if (loc) return String(loc);
    } catch (_) {}
    const el = document.getElementById('matchId'); if (el) return String(el.value || el.textContent || '').trim() || DEFAULT_ROOM_ID;
    return DEFAULT_ROOM_ID;
  } catch (e) { return 'live'; }
}

function adoptBasketballMatch(event) { // FIX: new match broadcast
  try { // FIX: new match broadcast
    if (!event || !event.match_id) return false; // FIX: new match broadcast
    const newId = String(event.match_id); // FIX: new match broadcast
    const currentId = getMatchId(); // FIX: new match broadcast
    if (newId === currentId) return false; // creating admin already handled locally // FIX: new match broadcast

    // Adopt new match id // FIX: new match broadcast
    try { sessionStorage.setItem('basketball_match_id', newId); localStorage.setItem('basketball_match_id', newId); } catch (_) {} // FIX: new match broadcast
    try { window.__matchId = newId; } catch (_) {} // FIX: new match broadcast

    // Rejoin WS room under new match id // FIX: new match broadcast
    if (_ws && _ws.readyState === WebSocket.OPEN) { // FIX: new match broadcast
      const role = (window.__role) ? String(window.__role) : 'unknown'; // FIX: new match broadcast
      try { _ws.send(JSON.stringify({ type: 'join', match_id: newId, role })); } catch (_) {} // FIX: new match broadcast
    } // FIX: new match broadcast

    // Full local reset — timers + roster + counters // FIX: new match broadcast
    try { bbResetMatch(true, true); } catch (_) {} // FIX: new match broadcast — was resetMatch(true) which is undefined

    // Apply canonical payload from creating admin if provided // FIX: new match broadcast
    if (event.payload && typeof event.payload === 'object') { // FIX: new match broadcast
      // Apply roster fields // FIX: new match broadcast
      try { // FIX: new match broadcast
        const rp = event.payload; // FIX: new match broadcast
        applyRosterState({ // FIX: new match broadcast
          teamA: rp.teamA, teamB: rp.teamB, shared: rp.shared, // FIX: new match broadcast
          committee: rp.committee, resetAt: rp.resetAt // FIX: new match broadcast
        }); // FIX: new match broadcast
      } catch (_) {} // FIX: new match broadcast
      // Apply timer fields explicitly (new match = timers stopped) // FIX: new match broadcast
      try { if (event.payload.gameTimer) applyGameTimer(event.payload.gameTimer); } catch (_) {} // FIX: new match broadcast
      try { if (event.payload.shotClock) applyShotClock(event.payload.shotClock); } catch (_) {} // FIX: new match broadcast
    } else { // FIX: new match broadcast
      try { loadStateFromServerIfMissing(true); } catch (_) {} // FIX: new match broadcast
    } // FIX: new match broadcast

    try { showToast('New match adopted: ' + newId); } catch (_) {} // FIX: new match broadcast
    return true; // FIX: new match broadcast
  } catch (_) { return false; } // FIX: new match broadcast
}

// Generic loop management helpers
function _ensureLoop(loopVar, loopFn, isRunning) {
  if (typeof loopVar !== 'object' || loopVar === null) return null;
  
  if (loopVar.id) {
    cancelAnimationFrame(loopVar.id);
    loopVar.id = null;
  }
  if (isRunning) {
    loopVar.id = requestAnimationFrame(loopFn);
  }
  return loopVar;
}

function syncBasketballState(payload, options) {
  try {
    const mid = getMatchId();
    if (!mid || String(mid) === '0' || isNaN(parseInt(mid,10)) || parseInt(mid,10) <= 0) return null;
    const statePayload = payload || buildStatePayload();

    try {
      _lastOutgoingSerialized = JSON.stringify(statePayload);
    } catch (_) {
      _lastOutgoingSerialized = null;
    }

    const message = {
      type: 'basketball_state',
      match_id: mid,
      payload: statePayload,
      meta: { clientId: CLIENT_ID, ts: Date.now(), source: 'admin' }
    };

    if (_bkBC) {
      try { _bkBC.postMessage(message); } catch (_) { console.warn('BroadcastChannel send failed', _); }
    }
    if (_ws && _ws.readyState === WebSocket.OPEN) {
      try { _ws.send(JSON.stringify(message)); } catch (_) { console.warn('WebSocket send failed', _); }
    }

    if (!options || options.forceServer !== false) {
      persistStateImmediately(statePayload);
    }

    return statePayload;
  } catch (e) {
    console.error('syncBasketballState error:', e);
    return null;
  }
}

function broadcastState(options) {
  return syncBasketballState(null, options);
}

// FIX: decoupled from timer — Broadcast and persist ONLY roster state (NO timers)
function syncRosterState(payload) {
  // FIX: decoupled from timer
  try {
    const mid = getMatchId();
    if (!mid || String(mid) === '0' || isNaN(parseInt(mid,10)) || parseInt(mid,10) <= 0) return null;
    const rosterPayload = payload || buildRosterOnlyPayload(); // FIX: decoupled from timer

    try {
      _lastOutgoingSerialized = JSON.stringify(rosterPayload); // FIX: decoupled from timer
    } catch (_) {
      _lastOutgoingSerialized = null;
    }

    const message = {
      type: 'basketball_state',
      match_id: mid,
      payload: rosterPayload,
      meta: { clientId: CLIENT_ID, ts: Date.now(), source: 'admin' }
    };

    // FIX: decoupled from timer — Broadcast roster updates only
    if (_bkBC) {
      try { _bkBC.postMessage(message); } catch (_) { console.warn('BroadcastChannel send failed', _); }
    }
    if (_ws && _ws.readyState === WebSocket.OPEN) {
      try { _ws.send(JSON.stringify(message)); } catch (_) { console.warn('WebSocket send failed', _); }
    }

    // FIX: decoupled from timer — Persist roster-only payload to server (timer.php never touched)
    persistRosterStateToServer(rosterPayload); // FIX: decoupled from timer

    return rosterPayload;
  } catch (e) {
    console.error('syncRosterState error:', e); // FIX: decoupled from timer
    return null;
  }
}

// FIX: decoupled from timer — Persist ONLY roster data to server (no timers)
function persistRosterStateToServer(payload) {
  // FIX: decoupled from timer
  try {
    const mid = getMatchId();
    const extraHeaders = { 'Content-Type': 'application/json' };
    try {
      if (window && window.__userId) extraHeaders['X-SS-UID'] = String(window.__userId);
      if (window && window.__role)   extraHeaders['X-SS-ROLE'] = String(window.__role);
    } catch (_) {}

    // FIX: decoupled from timer — Send ONLY roster payload (guaranteed no timers)
    try {
      const outgoing = payload || buildRosterOnlyPayload(); // FIX: decoupled from timer
      fetch('/basketball-admin/state', {
      method: 'POST',
      headers: extraHeaders,
      credentials: 'include',
      body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload: outgoing }),
      keepalive: true
      }).then(r => r.json()).catch(()=>{});
    } catch (_) {
      // Fallback: send payload as-is if serialization fails (very unlikely)
      fetch('state.php', { method: 'POST', headers: extraHeaders, credentials: 'include', body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload }), keepalive: true }).catch(()=>{});
    }
  } catch (_) {}
}

// Apply an incoming remote payload into the admin UI without re-broadcasting.
function applyIncomingState(payload) {
  if (!payload || _appApplyingRemote) return;
  if (typeof payload.resetAt === 'number' && payload.resetAt < _lastStateResetTs) return;
  _appApplyingRemote = true;
  try {
    // Preserve active input to restore focus after render
    let activeInputInfo = null;
    const activeEl = document.activeElement;
    if (activeEl && activeEl.tagName === 'INPUT' && activeEl.closest('#tbodyA, #tbodyB')) {
      const tr = activeEl.closest('tr[data-player-id]');
      if (tr) {
        activeInputInfo = {
          playerId: tr.dataset.playerId,
          className: activeEl.className,
          value: activeEl.value
        };
      }
    }
    if (typeof payload.resetAt === 'number' && payload.resetAt > _lastStateResetTs) {
      _lastStateResetTs = payload.resetAt;
    }
    // Teams and names
    if (payload.teamA && payload.teamA.name !== undefined) {
      state.teamA.name = payload.teamA.name || state.teamA.name;
      const el = document.getElementById('teamAName'); if (el) el.value = state.teamA.name;
      const label = document.getElementById('labelA'); if (label) label.textContent = state.teamA.name || 'TEAM A';
    }
    if (payload.teamB && payload.teamB.name !== undefined) {
      state.teamB.name = payload.teamB.name || state.teamB.name;
      const el = document.getElementById('teamBName'); if (el) el.value = state.teamB.name;
      const label = document.getElementById('labelB'); if (label) label.textContent = state.teamB.name || 'TEAM B';
    }

    // Shared
    if (payload.shared && typeof payload.shared === 'object') {
      state.shared = Object.assign({}, state.shared, payload.shared);
      const q = document.getElementById('bbQuarterVal'); if (q) q.textContent = state.shared.quarter;
      const perQ = document.getElementById('bbPerQuarterVal'); if (perQ) perQ.textContent = state.shared.quarter;
      const f = document.getElementById('foulVal'); if (f) f.textContent = state.shared.foul;
      const t = document.getElementById('timeoutVal'); if (t) t.textContent = state.shared.timeout;
    }

    // Team-level counters
    if (payload.teamA) {
      state.teamA.foul = typeof payload.teamA.foul === 'number' ? payload.teamA.foul : state.teamA.foul;
      state.teamA.timeout = typeof payload.teamA.timeout === 'number' ? payload.teamA.timeout : state.teamA.timeout;
      state.teamA.manualScore = typeof payload.teamA.manualScore === 'number' ? payload.teamA.manualScore : state.teamA.manualScore;
      const elF = document.getElementById('bbTsbAFoul'); if (elF) elF.textContent = state.teamA.foul;
      const elT = document.getElementById('bbTsbATimeout'); if (elT) elT.textContent = state.teamA.timeout;
      const elRF = document.getElementById('bbRightTsbAFoul'); if (elRF) elRF.textContent = state.teamA.foul;
      const elRT = document.getElementById('bbRightTsbATimeout'); if (elRT) elRT.textContent = state.teamA.timeout;
    }
    if (payload.teamB) {
      state.teamB.foul = typeof payload.teamB.foul === 'number' ? payload.teamB.foul : state.teamB.foul;
      state.teamB.timeout = typeof payload.teamB.timeout === 'number' ? payload.teamB.timeout : state.teamB.timeout;
      state.teamB.manualScore = typeof payload.teamB.manualScore === 'number' ? payload.teamB.manualScore : state.teamB.manualScore;
      const elF = document.getElementById('bbTsbBFoul'); if (elF) elF.textContent = state.teamB.foul;
      const elT = document.getElementById('bbTsbBTimeout'); if (elT) elT.textContent = state.teamB.timeout;
      const elRF = document.getElementById('bbRightTsbBFoul'); if (elRF) elRF.textContent = state.teamB.foul;
      const elRT = document.getElementById('bbRightTsbBTimeout'); if (elRT) elRT.textContent = state.teamB.timeout;
    }

    // Rosters — replace and render without triggering user-input handlers
    try {
      const isTimerOnly = payload &&
        (payload.gameTimer || payload.shotClock || payload.game_timer || payload.shot_clock) &&
        !payload.teamA && !payload.teamB;
      if (!isTimerOnly) {
        // Team A
        if (payload.teamA && Array.isArray(payload.teamA.players)) {
          if (payload.teamA.players.length === 0) {
            state.teamA.players = [];
            pCount.A = 0;
            try { const tA = document.getElementById('tbodyA'); if (tA) tA.innerHTML = ''; } catch(_) {}
          } else {
            state.teamA.players = payload.teamA.players.map(function(p){
              return Object.assign({ id: p.id || null, no: p.no || '', name: p.name || '', pts: p.pts || 0, foul: p.foul || 0, reb: p.reb || 0, ast: p.ast || 0, blk: p.blk || 0, stl: p.stl || 0, techFoul: p.techFoul || 0, techReason: p.techReason || '', selected: false}, p);
            });
            // ensure pCount roughly matches
            pCount.A = Math.max(0, state.teamA.players.length || 0);
            const tA = document.getElementById('tbodyA');
            try {
              if (tA) { bbRenderRosterTable(); }
            } catch (e) { if (tA) { bbRenderRosterTable(); } }
          }
        }
        // Team B
        if (payload.teamB && Array.isArray(payload.teamB.players)) {
          if (payload.teamB.players.length === 0) {
            state.teamB.players = [];
            pCount.B = 0;
            try { const tB = document.getElementById('tbodyB'); if (tB) tB.innerHTML = ''; } catch(_) {}
          } else {
            state.teamB.players = payload.teamB.players.map(function(p){
              return Object.assign({ id: p.id || null, no: p.no || '', name: p.name || '', pts: p.pts || 0, foul: p.foul || 0, reb: p.reb || 0, ast: p.ast || 0, blk: p.blk || 0, stl: p.stl || 0, techFoul: p.techFoul || 0, techReason: p.techReason || '', selected: false}, p);
            });
            pCount.B = Math.max(0, state.teamB.players.length || 0);
            const tB = document.getElementById('tbodyB');
            try {
              if (tB) { bbRenderRosterTable(); }
            } catch (e) { if (tB) { bbRenderRosterTable(); } }
          }
        }
      }
    } catch (e) { /* ignore roster render errors */ }

    try { syncSelectAll('A'); syncSelectAll('B'); } catch(_) {}

    // Update scores display directly
    try {
      const sA = (payload.teamA && typeof payload.teamA.score === 'number') ? payload.teamA.score : (payload.teamA && payload.teamA.manualScore ? payload.teamA.manualScore : null);
      const sB = (payload.teamB && typeof payload.teamB.score === 'number') ? payload.teamB.score : (payload.teamB && payload.teamB.manualScore ? payload.teamB.manualScore : null);
      if (sA !== null) { const sc = document.getElementById('scoreA'); if (sc) sc.textContent = sA; }
      if (sB !== null) { const sc = document.getElementById('scoreB'); if (sc) sc.textContent = sB; }
    } catch (e) {}

    // Timers (update numeric/display values only). When a payload includes
    // a timestamp (`ts`) for a running timer, recalculate the live remaining
    // value as: remaining_at_start - (now - ts). Do NOT auto-start loops
    // here — explicit remote control messages drive loop start/stop.
    if (payload.gameTimer) {
      try {
        const g = payload.gameTimer;
        if (typeof g.total === 'number') gtTotalSecs = g.total;
        // Interpret explicit boolean running only when provided; otherwise do not override local loop state
        const serverRunning = (typeof g.running === 'boolean') ? g.running : (typeof g.is_running === 'boolean' ? g.is_running : null);
        const tsVal = (typeof g.ts === 'number') ? g.ts : (typeof g.start_timestamp === 'number' ? g.start_timestamp : null);

        // Complete state synchronization for server control updates
        if (serverRunning === true && tsVal !== null && typeof g.remaining === 'number') {
          const incomingRemaining = Math.max(0, g.remaining - ((Date.now() - Number(tsVal)) / 1000));
          const diff = Math.abs((typeof gtRemaining === 'number' ? gtRemaining : 0) - incomingRemaining);
          if (!gtRunning || diff > 0.5) {
            gtRunning = true;
            gtRemainingAtAnchor = g.remaining;
            gtAnchorTs = Number(tsVal);
            gtRemaining = incomingRemaining;
            gtLastTick = null;
          } else {
            if (diff > 0.1) {
              gtRemaining = incomingRemaining;
            }
          }
        } else if (serverRunning === false) {
          // Only stop if this is an explicit timer control signal
          // (play/pause/reset from immediatePersistControl).
          const _isExplicit = !!(
            (payload._timerControl === true) ||
            (payload.meta && payload.meta.control)
          );
          if (_isExplicit) {
            gtRunning = false;
            gtAnchorTs = null;
            gtRemainingAtAnchor = null;
            gtRemaining = (typeof g.paused_remaining === 'number') ? g.paused_remaining : ((typeof g.remaining === 'number') ? g.remaining : gtRemaining);
            gtLastTick = null;
          }
        } else {
          // Server omitted explicit running flag — update numeric values only, do not change running/loops
          if (typeof g.remaining === 'number') gtRemaining = g.remaining;
        }
        gtRender();
        try { applyTimerButtonState('game', gtRunning); } catch(_){ }
      } catch (e) {}
    }
    if (payload.shotClock) {
      try {
        const s = payload.shotClock;
        if (typeof s.total === 'number') { scTotal = s.total; scPresetVal = s.total; refreshScPresetActive(); }
        const serverRunning = (typeof s.running === 'boolean') ? s.running : (typeof s.is_running === 'boolean' ? s.is_running : null);
        const scTsVal = (typeof s.ts === 'number') ? s.ts : (typeof s.start_timestamp === 'number' ? s.start_timestamp : null);
        if (serverRunning === true && scTsVal !== null && typeof s.remaining === 'number') {
          const incomingRemaining = Math.max(0, s.remaining - ((Date.now() - Number(scTsVal)) / 1000));
          const diff = Math.abs((typeof scRemaining === 'number' ? scRemaining : 0) - incomingRemaining);
          if (!scRunning || diff > 0.5) {
            scRunning = true;
            scRemainingAtAnchor = s.remaining;
            scAnchorTs = Number(scTsVal);
            scRemaining = incomingRemaining;
            scLastTick = null;
          } else {
            if (diff > 0.1) {
              scRemaining = incomingRemaining;
            }
          }
        } else if (serverRunning === false) {
          const _isExplicitSc = !!(
            (payload._timerControl === true) ||
            (payload.meta && payload.meta.control)
          );
          if (_isExplicitSc) {
            scRunning = false;
            scAnchorTs = null;
            scRemainingAtAnchor = null;
            scRemaining = (typeof s.paused_remaining === 'number') ? s.paused_remaining : ((typeof s.remaining === 'number') ? s.remaining : scRemaining);
            scLastTick = null;
          }
        } else {
          if (typeof s.remaining === 'number') {
            const diff = Math.abs((typeof scRemaining === 'number' ? scRemaining : 0) - s.remaining);
            if (diff > 0.1) scRemaining = s.remaining;
          }
        }
        scRenderFrame();
        try { applyTimerButtonState('shot', scRunning); } catch(_){ }
      } catch (e) {}
    }

    // Committee
    if (payload.committee !== undefined) {
      try { const ci = document.getElementById('bbCommitteeInput'); if (ci) ci.value = payload.committee || ''; } catch(e){}
    }

    // Safety: ensure roster and team inputs remain editable for admins
    try {
      // Default to editable when role is not injected; otherwise honor role.
      const isAdmin = (typeof window.__role === 'undefined') || (window.__role === 'admin' || window.__role === 'superadmin');
      if (isAdmin) {
        const selectors = ['#teamAName', '#teamBName', '#bbCommitteeInput', '#tbodyA input', '#tbodyB input', '.team-name-input'];
        selectors.forEach(function(sel) {
          try { document.querySelectorAll(sel).forEach(function(el) { try { el.disabled = false; el.readOnly = false; } catch(_){} }); } catch(_) {}
        });
      }
      // Ensure loops respect running flags: if incoming payload indicates
      // timers are stopped, make sure local loops are not running.
      try {
        const _isExplicitTimerControl = !!(
          (payload && payload._timerControl === true) ||
          (payload && payload.meta && payload.meta.control)
        );
        if (_isExplicitTimerControl) {
          // Only explicit control signals (play/pause/reset from immediatePersistControl)
          // may stop timers. Passive echoes and roster/counter updates must not.
          if (payload.gameTimer && typeof payload.gameTimer.running === 'boolean' && payload.gameTimer.running === false) {
            gtRunning = false; gtLastTick = null;
            try { applyTimerButtonState('game', false); } catch(_){ }
          }
          if (payload.shotClock && typeof payload.shotClock.running === 'boolean' && payload.shotClock.running === false) {
            scRunning = false; scLastTick = null;
            try { applyTimerButtonState('shot', false); } catch(_){ }
          }
        }
      } catch(_) {}
    } catch(_) {}

    // Restore focus to previously active input if it was in roster
    if (activeInputInfo) {
      setTimeout(() => {
        const tbody = document.getElementById('tbodyA') || document.getElementById('tbodyB');
        if (tbody) {
          const tr = tbody.querySelector(`tr[data-player-id="${activeInputInfo.playerId}"]`);
          if (tr) {
            const inp = tr.querySelector(`input.${activeInputInfo.className.split(' ').join('.')}`);
            if (inp) {
              inp.value = activeInputInfo.value;
              inp.focus();
              // Set cursor at end
              inp.setSelectionRange(activeInputInfo.value.length, activeInputInfo.value.length);
            }
          }
        }
      }, 0);
    }

  } finally {
    _appApplyingRemote = false;
  }
}

// FIX: isolated from timer — Apply ONLY roster/team/shared/committee fields (NO timers)
function applyRosterState(payload) {
  // FIX: isolated from timer
  if (!payload || _appApplyingRemote) return;
  if (typeof payload.resetAt === 'number' && payload.resetAt < _lastStateResetTs) return;
  _appApplyingRemote = true;
  try {
    // Preserve active input to restore focus after render
    let activeInputInfo = null;
    const activeEl = document.activeElement;
    if (activeEl && activeEl.tagName === 'INPUT' && activeEl.closest('#tbodyA, #tbodyB')) {
      const tr = activeEl.closest('tr[data-player-id]');
      if (tr) {
        activeInputInfo = {
          playerId: tr.dataset.playerId,
          className: activeEl.className,
          value: activeEl.value
        };
      }
    }
    if (typeof payload.resetAt === 'number' && payload.resetAt > _lastStateResetTs) {
      _lastStateResetTs = payload.resetAt;
    }
    // Teams and names
    if (payload.teamA && payload.teamA.name !== undefined) {
      state.teamA.name = payload.teamA.name || state.teamA.name;
      const el = document.getElementById('teamAName'); if (el) el.value = state.teamA.name;
      const label = document.getElementById('labelA'); if (label) label.textContent = state.teamA.name || 'TEAM A';
    }
    if (payload.teamB && payload.teamB.name !== undefined) {
      state.teamB.name = payload.teamB.name || state.teamB.name;
      const el = document.getElementById('teamBName'); if (el) el.value = state.teamB.name;
      const label = document.getElementById('labelB'); if (label) label.textContent = state.teamB.name || 'TEAM B';
    }

    // Shared
    if (payload.shared && typeof payload.shared === 'object') {
      state.shared = Object.assign({}, state.shared, payload.shared);
      const q = document.getElementById('bbQuarterVal'); if (q) q.textContent = state.shared.quarter;
      const perQ = document.getElementById('bbPerQuarterVal'); if (perQ) perQ.textContent = state.shared.quarter;
      const f = document.getElementById('foulVal'); if (f) f.textContent = state.shared.foul;
      const t = document.getElementById('timeoutVal'); if (t) t.textContent = state.shared.timeout;
    }

    // Team-level counters
    if (payload.teamA) {
      state.teamA.foul = typeof payload.teamA.foul === 'number' ? payload.teamA.foul : state.teamA.foul;
      state.teamA.timeout = typeof payload.teamA.timeout === 'number' ? payload.teamA.timeout : state.teamA.timeout;
      state.teamA.manualScore = typeof payload.teamA.manualScore === 'number' ? payload.teamA.manualScore : state.teamA.manualScore;
      const elF = document.getElementById('bbTsbAFoul'); if (elF) elF.textContent = state.teamA.foul;
      const elT = document.getElementById('bbTsbATimeout'); if (elT) elT.textContent = state.teamA.timeout;
      const elRF = document.getElementById('bbRightTsbAFoul'); if (elRF) elRF.textContent = state.teamA.foul;
      const elRT = document.getElementById('bbRightTsbATimeout'); if (elRT) elRT.textContent = state.teamA.timeout;
    }
    if (payload.teamB) {
      state.teamB.foul = typeof payload.teamB.foul === 'number' ? payload.teamB.foul : state.teamB.foul;
      state.teamB.timeout = typeof payload.teamB.timeout === 'number' ? payload.teamB.timeout : state.teamB.timeout;
      state.teamB.manualScore = typeof payload.teamB.manualScore === 'number' ? payload.teamB.manualScore : state.teamB.manualScore;
      const elF = document.getElementById('bbTsbBFoul'); if (elF) elF.textContent = state.teamB.foul;
      const elT = document.getElementById('bbTsbBTimeout'); if (elT) elT.textContent = state.teamB.timeout;
      const elRF = document.getElementById('bbRightTsbBFoul'); if (elRF) elRF.textContent = state.teamB.foul;
      const elRT = document.getElementById('bbRightTsbBTimeout'); if (elRT) elRT.textContent = state.teamB.timeout;
    }

    // Rosters — replace and render without triggering user-input handlers
    // RESETFIX: handle empty array explicitly so reset_match clears remote rosters
    try {
      // Team A
      if (payload.teamA && Array.isArray(payload.teamA.players)) {
        if (payload.teamA.players.length === 0) {
          // Explicit clear — wipe state, pCount, and DOM table
          state.teamA.players = [];
          pCount.A = 0;
          try { const tA = document.getElementById('tbodyA'); if (tA) tA.innerHTML = ''; } catch(_) {}
        } else {
          state.teamA.players = payload.teamA.players.map(function(p){
            return Object.assign({ id: p.id || null, no: p.no || '', name: p.name || '', pts: p.pts || 0, foul: p.foul || 0, reb: p.reb || 0, ast: p.ast || 0, blk: p.blk || 0, stl: p.stl || 0, techFoul: p.techFoul || 0, techReason: p.techReason || '', selected: false}, p);
          });
          pCount.A = Math.max(0, state.teamA.players.length || 0);
          const tA = document.getElementById('tbodyA');
          try {
            if (tA) { bbRenderRosterTable(); }
          } catch (e) { if (tA) { bbRenderRosterTable(); } }
          try { syncSelectAll('A'); } catch (_) {}
        }
      }
      // Team B
      if (payload.teamB && Array.isArray(payload.teamB.players)) {
        if (payload.teamB.players.length === 0) {
          // Explicit clear — wipe state, pCount, and DOM table
          state.teamB.players = [];
          pCount.B = 0;
          try { const tB = document.getElementById('tbodyB'); if (tB) tB.innerHTML = ''; } catch(_) {}
        } else {
          state.teamB.players = payload.teamB.players.map(function(p){
            return Object.assign({ id: p.id || null, no: p.no || '', name: p.name || '', pts: p.pts || 0, foul: p.foul || 0, reb: p.reb || 0, ast: p.ast || 0, blk: p.blk || 0, stl: p.stl || 0, techFoul: p.techFoul || 0, techReason: p.techReason || '', selected: false}, p);
          });
          pCount.B = Math.max(0, state.teamB.players.length || 0);
          const tB = document.getElementById('tbodyB');
          try {
            if (tB) { bbRenderRosterTable(); }
          } catch (e) { if (tB) { bbRenderRosterTable(); } }
        }
      }
    } catch (e) { /* ignore roster render errors */ }

    try { syncSelectAll('A'); syncSelectAll('B'); } catch(_) {}

    // Update scores display directly
    try {
      const sA = (payload.teamA && typeof payload.teamA.score === 'number') ? payload.teamA.score : (payload.teamA && payload.teamA.manualScore ? payload.teamA.manualScore : null);
      const sB = (payload.teamB && typeof payload.teamB.score === 'number') ? payload.teamB.score : (payload.teamB && payload.teamB.manualScore ? payload.teamB.manualScore : null);
      if (sA !== null) { const sc = document.getElementById('scoreA'); if (sc) sc.textContent = sA; }
      if (sB !== null) { const sc = document.getElementById('scoreB'); if (sc) sc.textContent = sB; }
    } catch (e) {}

    // Committee
    if (payload.committee !== undefined) {
      try { const ci = document.getElementById('bbCommitteeInput'); if (ci) ci.value = payload.committee || ''; } catch(e){}
    }

    // Safety: ensure roster and team inputs remain editable for admins
    try {
      // Default to editable when role is not injected; otherwise honor role.
      const isAdmin = (typeof window.__role === 'undefined') || (window.__role === 'admin' || window.__role === 'superadmin');
      if (isAdmin) {
        const selectors = ['#teamAName', '#teamBName', '#bbCommitteeInput', '#tbodyA input', '#tbodyB input', '.team-name-input'];
        selectors.forEach(function(sel) {
          try { document.querySelectorAll(sel).forEach(function(el) { try { el.disabled = false; el.readOnly = false; } catch(_){} }); } catch(_) {}
        });
      }
    } catch(_) {}

    // Restore focus to previously active input if it was in roster
    if (activeInputInfo) {
      setTimeout(() => {
        const tbody = document.getElementById('tbodyA') || document.getElementById('tbodyB');
        if (tbody) {
          const tr = tbody.querySelector(`tr[data-player-id="${activeInputInfo.playerId}"]`);
          if (tr) {
            const inp = tr.querySelector(`input.${activeInputInfo.className.split(' ').join('.')}`);
            if (inp) {
              inp.value = activeInputInfo.value;
              inp.focus();
              // Set cursor at end
              inp.setSelectionRange(activeInputInfo.value.length, activeInputInfo.value.length);
            }
          }
        }
      }, 0);
    }

  } finally {
    _appApplyingRemote = false;
  }
}

// FIX: game timer path only — Apply ONLY game_timer field (NO shot_clock or roster)
function applyGameTimer(payload) {
  // FIX: game timer path only
  if (!payload) return;
  try {
    const g = payload;
    if (typeof g.total === 'number') gtTotalSecs = g.total;
    // Interpret explicit boolean running only when provided; otherwise do not override local loop state
    const serverRunning = (typeof g.running === 'boolean') ? g.running : (typeof g.is_running === 'boolean' ? g.is_running : null);
    let tsVal = (typeof g.ts === 'number') ? g.ts : (typeof g.start_timestamp === 'number' ? g.start_timestamp : null);

    // Copilot: Insert one line immediately before the `if (serverRunning === true && tsVal !== null` block
    // in BOTH applyGameTimer() and applyShotClock() in app.js.
    // MUST add: if (typeof tsVal === 'number' && tsVal > Date.now()) tsVal = Date.now(); // FIX: clamp future anchor
    // Reason: if the server or a peer admin sends a ts that is ahead of local clock (clock skew, timezone error,
    //         or unit confusion), the _mainLoop computes a negative elapsed time, producing huge remaining values
    //         like 54601 which format as "910:01". Clamping ts to now ensures elapsed >= 0.
    // MUST NOT change the variable name tsVal. MUST NOT modify the if-block logic below this line.
    // Apply this same single-line fix to applyShotClock() at the equivalent location.
    if (typeof tsVal === 'number' && tsVal > Date.now()) tsVal = Date.now(); // FIX: clamp future anchor

    // Complete state synchronization for server control updates
    if (serverRunning === true && tsVal !== null && typeof g.remaining === 'number') {
      const incomingRemaining = Math.max(0, g.remaining - ((Date.now() - Number(tsVal)) / 1000));
      const diff = Math.abs((typeof gtRemaining === 'number' ? gtRemaining : 0) - incomingRemaining);
      if (!gtRunning || diff > 0.5) {
        gtRunning = true;
        gtRemainingAtAnchor = g.remaining;
        gtAnchorTs = Number(tsVal);
        gtRemaining = incomingRemaining;
        gtLastTick = null;
      } else {
        if (diff > 0.1) {
          gtRemaining = incomingRemaining;
        }
      }
    } else if (serverRunning === false) {
      // Only stop if this is an explicit timer control signal
      const _isExplicit = !!(
        (payload._timerControl === true) ||
        (payload.meta && payload.meta.control)
      );
      if (_isExplicit) {
        gtRunning = false;
        gtAnchorTs = null;
        gtRemainingAtAnchor = null;
        gtRemaining = (typeof g.paused_remaining === 'number') ? g.paused_remaining : ((typeof g.remaining === 'number') ? g.remaining : gtRemaining);
        gtLastTick = null;
      }
    } else {
      // Server omitted explicit running flag — update numeric values only, do not change running/loops
      if (typeof g.remaining === 'number') gtRemaining = g.remaining;
    }
    gtRender();
    try { applyTimerButtonState('game', gtRunning); } catch(_){ }
  } catch (e) {}
}

// FIX: shot clock path only — Apply ONLY shot_clock field (NO game_timer or roster)
function applyShotClock(payload) {
  // FIX: shot clock path only
  if (!payload) return;
  try {
    const s = payload;
    if (typeof s.total === 'number') { scTotal = s.total; scPresetVal = s.total; refreshScPresetActive(); }
    const serverRunning = (typeof s.running === 'boolean') ? s.running : (typeof s.is_running === 'boolean' ? s.is_running : null);
    let scTsVal = (typeof s.ts === 'number') ? s.ts : (typeof s.start_timestamp === 'number' ? s.start_timestamp : null);
    if (typeof scTsVal === 'number' && scTsVal > Date.now()) scTsVal = Date.now(); // FIX: clamp future anchor
    if (serverRunning === true && scTsVal !== null && typeof s.remaining === 'number') {
      const incomingRemaining = Math.max(0, s.remaining - ((Date.now() - Number(scTsVal)) / 1000));
      const diff = Math.abs((typeof scRemaining === 'number' ? scRemaining : 0) - incomingRemaining);
      if (!scRunning || diff > 0.5) {
        scRunning = true;
        scRemainingAtAnchor = s.remaining;
        scAnchorTs = Number(scTsVal);
        scRemaining = incomingRemaining;
        scLastTick = null;
      } else {
        if (diff > 0.1) {
          scRemaining = incomingRemaining;
        }
      }
    } else if (serverRunning === false) {
      const _isExplicitSc = !!(
        (payload._timerControl === true) ||
        (payload.meta && payload.meta.control)
      );
      if (_isExplicitSc) {
        scRunning = false;
        scAnchorTs = null;
        scRemainingAtAnchor = null;
        scRemaining = (typeof s.paused_remaining === 'number') ? s.paused_remaining : ((typeof s.remaining === 'number') ? s.remaining : scRemaining);
        scLastTick = null;
      }
    } else {
      if (typeof s.remaining === 'number') {
        const diff = Math.abs((typeof scRemaining === 'number' ? scRemaining : 0) - s.remaining);
        if (diff > 0.1) scRemaining = s.remaining;
      }
    }
    scRenderFrame();
    try { applyTimerButtonState('shot', scRunning); } catch(_){ }
  } catch (e) {}
}

// FIX: isolated from roster — Apply ONLY timer fields (NO roster) — DEPRECATED: use applyGameTimer() and applyShotClock() separately
function applyTimerState(payload) {
  // FIX: isolated from roster — for backwards compat, delegate to separate functions
  if (!payload) return;
  if (payload.gameTimer) applyGameTimer(payload.gameTimer);
  if (payload.shotClock) applyShotClock(payload.shotClock);
}

// Build the canonical full-state payload used for broadcasts and caching
function buildStatePayload() {
  const committee = document.getElementById('bbCommitteeInput')?.value?.trim() || '';
  const scoreA = state.teamA.players.reduce((s, p) => s + (p.pts || 0), 0) + (typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0);
  const scoreB = state.teamB.players.reduce((s, p) => s + (p.pts || 0), 0) + (typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0);
  return {
    teamA: {
      name: state.teamA.name,
      score: scoreA,
      foul: state.teamA.foul,
      timeout: state.teamA.timeout,
      manualScore: typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0,
      quarter: state.shared.quarter,
      players: state.teamA.players.map(p => ({ id: p.id, no: p.no, name: p.name, pts: p.pts, foul: p.foul, reb: p.reb, ast: p.ast, blk: p.blk, stl: p.stl, techFoul: p.techFoul, techReason: p.techReason, selected: !!p.selected }))
    },
    teamB: {
      name: state.teamB.name,
      score: scoreB,
      foul: state.teamB.foul,
      timeout: state.teamB.timeout,
      manualScore: typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0,
      quarter: state.shared.quarter,
      players: state.teamB.players.map(p => ({ id: p.id, no: p.no, name: p.name, pts: p.pts, foul: p.foul, reb: p.reb, ast: p.ast, blk: p.blk, stl: p.stl, techFoul: p.techFoul, techReason: p.techReason, selected: !!p.selected }))
    },
    shared: { ...state.shared },
    resetAt: _lastStateResetTs,
    committee,
  };
}

// FIX: decoupled from timer — Build ONLY roster/team/shared/committee payload (NO timers)
function buildRosterOnlyPayload() {
  // FIX: decoupled from timer
  const committee = document.getElementById('bbCommitteeInput')?.value?.trim() || '';
  const scoreA = state.teamA.players.reduce((s, p) => s + (p.pts || 0), 0) + (typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0);
  const scoreB = state.teamB.players.reduce((s, p) => s + (p.pts || 0), 0) + (typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0);
  return {
    teamA: {
      name: state.teamA.name,
      score: scoreA,
      foul: state.teamA.foul,
      timeout: state.teamA.timeout,
      manualScore: typeof state.teamA.manualScore === 'number' ? state.teamA.manualScore : 0,
      quarter: state.shared.quarter,
      players: state.teamA.players.map(p => ({ id: p.id, no: p.no, name: p.name, pts: p.pts, foul: p.foul, reb: p.reb, ast: p.ast, blk: p.blk, stl: p.stl, techFoul: p.techFoul, techReason: p.techReason, selected: !!p.selected }))
    },
    teamB: {
      name: state.teamB.name,
      score: scoreB,
      foul: state.teamB.foul,
      timeout: state.teamB.timeout,
      manualScore: typeof state.teamB.manualScore === 'number' ? state.teamB.manualScore : 0,
      quarter: state.shared.quarter,
      players: state.teamB.players.map(p => ({ id: p.id, no: p.no, name: p.name, pts: p.pts, foul: p.foul, reb: p.reb, ast: p.ast, blk: p.blk, stl: p.stl, techFoul: p.techFoul, techReason: p.techReason, selected: !!p.selected }))
    },
    shared: { ...state.shared },
    resetAt: _lastStateResetTs,
    committee,
    // FIX: decoupled from timer — NEVER include gameTimer or shotClock here
  };
}

// FIX: decoupled from timer — Build ONLY timer payload (game_timer, shot_clock)
function buildTimerOnlyPayload() {
  // FIX: decoupled from timer
  const nowMs = Date.now();
  const currentGameRemaining = (typeof gtRemainingAtAnchor === 'number' && typeof gtAnchorTs === 'number')
    ? Math.max(0, gtRemainingAtAnchor - ((nowMs - Number(gtAnchorTs)) / 1000))
    : Math.max(0, typeof gtRemaining === 'number' ? gtRemaining : 0);
  const currentShotRemaining = (typeof scRemainingAtAnchor === 'number' && typeof scAnchorTs === 'number')
    ? Math.max(0, scRemainingAtAnchor - ((nowMs - Number(scAnchorTs)) / 1000))
    : Math.max(0, typeof scRemaining === 'number' ? scRemaining : 0);
  return {
    gameTimer: {
      total: typeof gtTotalSecs === 'number' ? gtTotalSecs : 600,
      remaining: currentGameRemaining,
      running: !!gtRunning,
      ts: gtRunning ? nowMs : null
    },
    shotClock: {
      total: typeof scTotal === 'number' ? scTotal : 24,
      remaining: currentShotRemaining,
      running: !!scRunning,
      ts: scRunning ? nowMs : null
    }
  };
}

// Ensure viewers are notified if admin unloads/reloads — write synchronously
// NOTE: flush any pending roster/state persistence that was debounced,
// and send a final timer notification to viewers.
function flushStateOnUnload() {
  try {
    // Flush any pending debounced persist to ensure roster is saved before unload
    if (_persistTimer) {
      clearTimeout(_persistTimer);
      _persistTimer = null;
      try {
        const mid = getMatchId();
        if (mid && String(mid) !== '0') {
          // Build full payload and persist immediately (synchronously)
          const payload = buildStatePayload();
          const extraHeaders = { 'Content-Type': 'application/json', 'X-Unload-Flush': '1' };
          try {
            if (window && window.__userId) extraHeaders['X-SS-UID'] = String(window.__userId);
            if (window && window.__role) extraHeaders['X-SS-ROLE'] = String(window.__role);
          } catch (_) {}
          // Strip timers so we don't overwrite server timers; preserve roster
          const outgoing = JSON.parse(JSON.stringify(payload || {}));
          try { delete outgoing.gameTimer; } catch(_){}
          try { delete outgoing.shotClock; } catch(_){}
          // Use fetch with keepalive so the request survives page unload
          try {
            fetch('state.php', {
              method: 'POST',
              headers: extraHeaders,
              credentials: 'include',
              body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload: outgoing }),
              keepalive: true
            }).catch(()=>{});
          } catch (_) {}
        }
      } catch (_) {}
    }

    // Timer unload WS disabled. Timer authority is timer.php -> WS /emit only.
  } catch (_) {}
}

// Send final state when page is being unloaded so viewers don't keep running stale timers
window.addEventListener('pagehide', function () { flushStateOnUnload(); });
window.addEventListener('beforeunload', function () { flushStateOnUnload(); });

// If we returned from a save->report redirect, some browsers may restore the previous
// admin page from bfcache. Detect that case and clear persisted admin snapshot so
// the prior match is not reused when the user navigates back.
window.addEventListener('pageshow', function (e) {
  try {
    if (sessionStorage.getItem('shouldClearPersistedOnBack:basketball') === '1') {
      sessionStorage.removeItem('shouldClearPersistedOnBack:basketball');
      try { localStorage.removeItem('basketball_viewMode'); } catch (err) {}
      // Force a reload when the page was restored from bfcache to ensure
      // a clean session. For normal navigations (back button), attempt a
      // server-first rehydration so the admin rejoins the canonical state
      // and remains synchronized with other admins.
      if (e && e.persisted) {
        window.location.reload();
      } else {
        try { loadStateFromServerIfMissing(true).then(function(applied){ if (!applied) { try { broadcastState(); } catch(_){} } }).catch(function(){}); } catch (_) {}
      }
    }
  } catch (err) {}
});

// ----- persist to server (debounced) -----
let _persistTimer = null;
let _rosterTypingDebounce = null; // debounce handle for name/no/techReason input
function schedulePersistToServer(payload) {
  try {
    // Do not perform automatic server persists until initial server-first
    // hydration has completed. This prevents a newly-loading admin from
    // writing default/local state into the canonical `match_state` row.
    if (!_initialHydrationDone) return;
    // Don't attempt server persist if we don't have a valid match id yet
    const mid = getMatchId();
    // Avoid sending match_id=0 (state.php treats it as missing/invalid)
    if (!mid || String(mid) === '0' || isNaN(parseInt(mid,10)) || parseInt(mid,10) <= 0) return;
    if (_persistTimer) clearTimeout(_persistTimer);
    _persistTimer = setTimeout(() => {
      _persistTimer = null;
      try {
        const extraHeaders = { 'Content-Type': 'application/json' };
        try {
          if (window && window.__userId) extraHeaders['X-SS-UID'] = String(window.__userId);
          if (window && window.__role)   extraHeaders['X-SS-ROLE'] = String(window.__role);
        } catch (_) {}

        // Do not overwrite server timers here — timers are canonical in `timer.php`.
        // Strip timer fields from automatic debounced persists so a reloading
        // client cannot clobber running timers saved in the timer store.
        // The server-side preserves existing timers when no control is present.
        try {
          const outgoing = JSON.parse(JSON.stringify(payload || {}));
          try { delete outgoing.gameTimer; } catch(_){}
          try { delete outgoing.shotClock; } catch(_){}
          fetch('state.php', {
            method: 'POST',
            headers: extraHeaders,
            credentials: 'include',
            body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload: outgoing }),
            keepalive: true
          }).then(r => r.json()).catch(()=>{});
        } catch (_) {
          // Fallback: send payload as-is if serialization fails (very unlikely)
          fetch('state.php', { method: 'POST', headers: extraHeaders, credentials: 'include', body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload }), keepalive: true }).catch(()=>{});
        }
      } catch (_) {}
    }, 400);
  } catch (_) {}
}

// Persist state immediately to server (for critical updates like shared counters)
function persistStateImmediately(payload) {
  try {
    const mid = getMatchId();
    const extraHeaders = { 'Content-Type': 'application/json' };
    try {
      if (window && window.__userId) extraHeaders['X-SS-UID'] = String(window.__userId);
      if (window && window.__role)   extraHeaders['X-SS-ROLE'] = String(window.__role);
    } catch (_) {}

    // Strip timer fields from immediate persists so a reloading
    // client cannot clobber running timers saved in the timer store.
    try {
      const outgoing = JSON.parse(JSON.stringify(payload || {}));
      try { delete outgoing.gameTimer; } catch(_){}
      try { delete outgoing.shotClock; } catch(_){}
      fetch('state.php', {
        method: 'POST',
        headers: extraHeaders,
        credentials: 'include',
        body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload: outgoing }),
        keepalive: true
      }).then(r => r.json()).catch(()=> {});
    } catch (_) {
      // Fallback: send payload as-is if serialization fails (very unlikely)
      fetch('state.php', { method: 'POST', headers: extraHeaders, credentials: 'include', body: JSON.stringify({ match_id: (mid && String(mid) !== '0') ? mid : 'live', payload }), keepalive: true }).catch(()=> {});
    }
  } catch (_) {}
}

// Persist an explicit control (start/pause/reset) immediately to server
// control: 'start' | 'pause' | 'reset'
// timerType: 'game' | 'shot' | undefined (when omitted, use current local state)
function immediatePersistControl(control, timerType) {
  // Return a Promise that resolves when the canonical timer.php write completes.
  return new Promise(async (resolve, reject) => {
    try {
      const mid = getMatchId();
      if (!mid || String(mid) === '0') { try { sessionStorage.setItem('basketball_match_id','live'); localStorage.setItem('basketball_match_id','live'); } catch(_) {} }
      const midFinal = (!mid || String(mid) === '0') ? 'live' : String(mid);
      const extraHeaders = { 'Content-Type': 'application/json' };
      try {
        if (window && window.__userId) extraHeaders['X-SS-UID'] = String(window.__userId);
        if (window && window.__role)   extraHeaders['X-SS-ROLE'] = String(window.__role);
      } catch (_) {}

      const nowMs = Date.now();
      const computeCurrentGameRemaining = () => {
        try {
          if (typeof gtRemainingAtAnchor === 'number' && typeof gtAnchorTs === 'number') {
            return Math.max(0, gtRemainingAtAnchor - ((nowMs - Number(gtAnchorTs)) / 1000));
          }
        } catch (_) {}
        return Math.max(0, typeof gtRemaining === 'number' ? gtRemaining : 0);
      };
      const computeCurrentShotRemaining = () => {
        try {
          if (typeof scRemainingAtAnchor === 'number' && typeof scAnchorTs === 'number') {
            return Math.max(0, scRemainingAtAnchor - ((nowMs - Number(scAnchorTs)) / 1000));
          }
        } catch (_) {}
        return Math.max(0, typeof scRemaining === 'number' ? scRemaining : 0);
      };
      const makeGameTimerPayload = (applyControl) => {
        const currentRemaining = computeCurrentGameRemaining();
        const total = applyControl === 'reset' ? 600 : Math.round(typeof gtTotalSecs === 'number' ? gtTotalSecs : 600);
        if (applyControl === 'start') {
          return { total: total, remaining: currentRemaining, running: true, ts: nowMs };
        } else if (applyControl === 'pause') {
          return { total: total, remaining: currentRemaining, running: false, ts: null };
        } else if (applyControl === 'reset') {
          return { total: total, remaining: total, running: false, ts: null };
        }
        return { total: total, remaining: currentRemaining, running: !!gtRunning, ts: gtRunning ? nowMs : null };
      };
      const makeShotClockPayload = (applyControl) => {
        const currentRemaining = computeCurrentShotRemaining();
        const total = applyControl === 'reset' ? 24 : Math.round(typeof scTotal === 'number' ? scTotal : 24);
        if (applyControl === 'start') {
          return { total: total, remaining: currentRemaining, running: true, ts: nowMs };
        } else if (applyControl === 'pause') {
          return { total: total, remaining: currentRemaining, running: false, ts: null };
        } else if (applyControl === 'reset') {
          return { total: total, remaining: total, running: false, ts: null };
        }
        return { total: total, remaining: currentRemaining, running: !!scRunning, ts: scRunning ? nowMs : null };
      };

      const applyTimerPayloadToLocalState = (latestPayload) => {
        if (!latestPayload) return;
        try {
          const g = latestPayload.gameTimer || latestPayload.game_timer || null;
          if (g) {
            if (typeof g.total === 'number') gtTotalSecs = g.total;
            if (typeof g.remaining === 'number') gtRemaining = g.remaining;
            const serverRunning = (typeof g.running === 'boolean') ? g.running : null;
            const tsVal = (typeof g.ts === 'number') ? g.ts : (typeof g.start_timestamp === 'number' ? g.start_timestamp : null);
            if (serverRunning === true && tsVal !== null) {
              gtRunning = true;
              // Copilot: Fix the anchor assignment for both game timer and shot clock below.
              // Current (wrong):  gtRemainingAtAnchor = gtRemaining;
              // Correct:          gtRemainingAtAnchor = (typeof g.remaining === 'number') ? g.remaining : gtRemaining;
              // Apply the same pattern for scRemainingAtAnchor:
              // Current (wrong):  scRemainingAtAnchor = scRemaining;
              // Correct:          scRemainingAtAnchor = (typeof s.remaining === 'number') ? s.remaining : scRemaining;
              // Reason: gtRemaining is the live decrementing value; g.remaining is what the server confirmed.
              //         Using the live value causes Admin A's anchor to diverge from what timer.php persisted.
              // MUST NOT: change any other line in applyTimerPayloadToLocalState.
              // MUST NOT: change the tsVal or serverRunning logic above these lines.
              gtRemainingAtAnchor = (typeof g.remaining === 'number') ? g.remaining : gtRemaining;
              gtAnchorTs = Number(tsVal);
              gtLastTick = null;
            } else if (serverRunning === false) {
              gtRunning = false;
              gtAnchorTs = null;
              gtRemainingAtAnchor = null;
              gtLastTick = null;
            }
            gtRender();
            try { applyTimerButtonState('game', gtRunning); } catch(_){}
          }
          const s = latestPayload.shotClock || latestPayload.shot_clock || null;
          if (s) {
            if (typeof s.total === 'number') { scTotal = s.total; scPresetVal = s.total; refreshScPresetActive(); }
            if (typeof s.remaining === 'number') scRemaining = s.remaining;
            const serverRunning = (typeof s.running === 'boolean') ? s.running : null;
            const tsVal = (typeof s.ts === 'number') ? s.ts : (typeof s.start_timestamp === 'number' ? s.start_timestamp : null);
            if (serverRunning === true && tsVal !== null) {
              scRunning = true;
              scRemainingAtAnchor = (typeof s.remaining === 'number') ? s.remaining : scRemaining;
              scAnchorTs = Number(tsVal);
              scLastTick = null;
            } else if (serverRunning === false) {
              scRunning = false;
              scAnchorTs = null;
              scRemainingAtAnchor = null;
              scLastTick = null;
            }
            scRenderFrame();
            try { applyTimerButtonState('shot', scRunning); } catch(_){}
          }
        } catch (_) {}
      };
      const resetBoth = String(control) === 'reset' && timerType === 'game';
      const gameTimerPayload = makeGameTimerPayload(timerType === 'game' ? String(control) : undefined);
      const shotClockPayload = makeShotClockPayload((timerType === 'shot' || resetBoth) ? String(control) : undefined);
      const metaObj = { control: String(control), clientId: CLIENT_ID };
      try { if (typeof timerType === 'string' && timerType) metaObj.timer = timerType; } catch(_) {}
      const body = JSON.stringify({
        match_id: (typeof midFinal !== 'undefined' ? midFinal : mid),
        game_time: Math.round(gameTimerPayload.remaining),
        shot_clock: Math.round(shotClockPayload.remaining),
        is_running: !!(gameTimerPayload.running || shotClockPayload.running),
        last_update_at: nowMs,
        gameTimer: gameTimerPayload,
        shotClock: shotClockPayload,
        meta: metaObj
      });
      // Debug: log outgoing timer control request
      try { console.debug('[immediatePersistControl] POST body:', gameTimerPayload, shotClockPayload, 'meta control=', control, 'timerType=', timerType); } catch(_) {}
      fetch('timer.php', { method: 'POST', headers: extraHeaders, credentials: 'include', body, keepalive: true })
        .then(res => res.json())
        .then(j => {
          try { console.debug('[immediatePersistControl] timer.php response:', j); } catch(_) {}
          if (j && j.success) {
            try { if (j.payload) applyTimerPayloadToLocalState(j.payload); } catch(_) {}
            resolve(j);
          } else {
            // If server returned failure, try starting locally as fallback
            try { console.warn('[immediatePersistControl] server persist failed, attempting local fallback', j); } catch(_) {}
            try {
              if (control === 'start') {
                if (timerType === 'game') try { _origGtPlay(); } catch(_) {}
                if (timerType === 'shot') try { _origScPlay(); } catch(_) {}
              } else if (control === 'pause') {
                if (timerType === 'game') try { _origGtPause(); } catch(_) {}
                if (timerType === 'shot') try { _origScPause(); } catch(_) {}
              } else if (control === 'reset') {
                if (timerType === 'game') try { _origGtReset(); } catch(_) {}
                if (timerType === 'shot') try { _origScReset(); } catch(_) {}
              }
            } catch(_){}
            reject(j || { success:false, error: 'timer persist failed' });
          }
        }).catch(err => {
          try { console.error('[immediatePersistControl] fetch error:', err); } catch(_){}
          // Network error: try local fallback so UX isn't blocked
          try {
            if (control === 'start') {
              if (timerType === 'game') try { _origGtPlay(); } catch(_) {}
              if (timerType === 'shot') try { _origScPlay(); } catch(_) {}
            } else if (control === 'pause') {
              if (timerType === 'game') try { _origGtPause(); } catch(_) {}
              if (timerType === 'shot') try { _origScPause(); } catch(_) {}
            } else if (control === 'reset') {
              if (timerType === 'game') try { _origGtReset(); } catch(_) {}
              if (timerType === 'shot') try { _origScReset(); } catch(_) {}
            }
          } catch(_){}
          reject(err);
        });
    } catch (e) { reject(e); }
  });
}

// Hook broadcastState into every state-mutating function
const _origRecalcScore  = recalcScore;
recalcScore = function (team) { _origRecalcScore(team); try { markRosterDirty(); } catch(_) {} };

const _origAdjustShared = adjustShared;
/* Note: `adjustShared` and `adjustTsb` already call `broadcastState()`
  internally. Removing redundant wrapper broadcasts to avoid duplicate
  broadcasts when these helpers are invoked. */

const _origOnTeamName = onTeamName;
onTeamName = function (team) { _origOnTeamName(team); try { markRosterDirty(); } catch(_) {} };

// Committee input broadcasting is attached in init with a dataset guard
// to ensure a single listener is present (see bottom of file).

// FIX: game timer path only — Emit ONLY game_timer field to remote admins
function postGameTimerUpdate(opts) {
  // WS-SERVER-DRIVEN TIMER: the browser must not fan out timer state.
  // Timer changes are POSTed to timer.php; the backend emits the final authoritative state to the WS server.
  return;
}

// FIX: shot clock path only — Emit ONLY shot_clock field to remote admins
function postShotClockUpdate(opts) {
  // WS-SERVER-DRIVEN TIMER: no browser-origin timer fan-out.
  return;
}

function postImmediateTimerUpdate(opts) {
  // Deprecated by WS-server-driven timer sync.
  // Do not write timer state to BroadcastChannel, localStorage, or client WebSocket.
  // Authoritative timer updates must flow: admin -> timer.php -> WS /emit -> match room clients.
  return;
}

function scheduleTimerPersist(control) {
  try {
    if (_timerPersistTimeout) clearTimeout(_timerPersistTimeout);
    const delay = (control === 'start' || control === 'reset') ? 0 : TIMER_PERSIST_DEBOUNCE_MS;
    _timerPersistTimeout = setTimeout(function () {
      try { persistTimersToServer(control); } catch(_){}
      _timerPersistTimeout = null;
    }, delay);
  } catch (_) {}
}

function persistTimersToServer(control) {
  try {
    const mid = getMatchId();
    if (!mid || String(mid) === '0') return;
    const nowMs = Date.now();
    const currentGameRemaining = (typeof gtRemainingAtAnchor === 'number' && typeof gtAnchorTs === 'number')
      ? Math.max(0, gtRemainingAtAnchor - ((nowMs - Number(gtAnchorTs)) / 1000))
      : Math.max(0, typeof gtRemaining === 'number' ? gtRemaining : 0);
    const currentShotRemaining = (typeof scRemainingAtAnchor === 'number' && typeof scAnchorTs === 'number')
      ? Math.max(0, scRemainingAtAnchor - ((nowMs - Number(scAnchorTs)) / 1000))
      : Math.max(0, typeof scRemaining === 'number' ? scRemaining : 0);
    const body = {
      match_id: mid,
      game_time: Math.round(currentGameRemaining),
      shot_clock: Math.round(currentShotRemaining),
      is_running: !!(gtRunning || scRunning),
      last_update_at: nowMs,
      gameTimer: {
        total: typeof gtTotalSecs === 'number' ? gtTotalSecs : 0,
        remaining: currentGameRemaining,
        running: !!gtRunning,
        ts: gtRunning ? nowMs : null
      },
      shotClock: {
        total: typeof scTotal === 'number' ? scTotal : 0,
        remaining: currentShotRemaining,
        running: !!scRunning,
        ts: scRunning ? nowMs : null
      }
    };
    if (control) body.meta = { control: control, clientId: CLIENT_ID };
    try {
      fetch('timer.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).catch(function(){});
    } catch (_) {}
  } catch (_) {}
}

// WS status indicator (dismissible)
function _ensureWSIndicator() {
  try {
    if (window.__wsStatusDismissed) return;
    if (document.getElementById('wsStatus')) return;
    const bar = document.createElement('div');
    bar.id = 'wsStatus';
    bar.style.position = 'fixed';
    bar.style.right = '12px';
    bar.style.bottom = '12px';
    bar.style.padding = '6px 10px';
    bar.style.borderRadius = '6px';
    bar.style.background = '#ddd';
    bar.style.color = '#111';
    bar.style.fontSize = '12px';
    bar.style.zIndex = '9999';
    bar.style.display = 'flex';
    bar.style.alignItems = 'center';
    const label = document.createElement('span'); label.id = 'wsStatusLabel'; label.textContent = 'WS: unknown'; label.style.marginRight = '8px';
    const closeBtn = document.createElement('button'); closeBtn.type = 'button'; closeBtn.textContent = '✕'; closeBtn.title = 'Dismiss WS status'; closeBtn.style.border = 'none'; closeBtn.style.background = 'transparent'; closeBtn.style.cursor = 'pointer'; closeBtn.style.fontSize = '12px'; closeBtn.onclick = function () { window.__wsStatusDismissed = true; const el = document.getElementById('wsStatus'); if (el) el.remove(); };
    bar.appendChild(label); bar.appendChild(closeBtn); document.body.appendChild(bar);
  } catch (e) {}
}

function _setWSStatus(s) {
  try {
    if (window.__wsStatusDismissed) return;
    _ensureWSIndicator();
    const label = document.getElementById('wsStatusLabel');
    const el = document.getElementById('wsStatus');
    if (!el || !label) return;
    if (s === 'connected') { el.style.background = '#dff0d8'; label.style.color = '#155724'; label.textContent = 'WS: connected'; }
    else if (s === 'disconnected') { el.style.background = '#f8d7da'; label.style.color = '#721c24'; label.textContent = 'WS: disconnected'; }
    else if (s === 'error') { el.style.background = '#fce5cd'; label.style.color = '#7a4100'; label.textContent = 'WS: error'; }
    else { el.style.background = '#e2e3e5'; label.style.color = '#383d41'; label.textContent = 'WS: ' + String(s || 'unknown'); }
  } catch (e) {}
}

_setWSStatus('connecting');

// Helper: apply server timer response (from state.php immediate persist)
function applyServerTimerResponse(timerType, j) {
  try {
    if (!j || !j.payload) return;
    if (timerType === 'game') {
      const g = j.payload && (j.payload.gameTimer || j.payload.game_timer) ? (j.payload.gameTimer || j.payload.game_timer) : null;
      if (g && g.running && (typeof g.ts === 'number' || typeof g.start_timestamp === 'number')) {
        gtRemainingAtAnchor = (typeof g.remaining === 'number') ? g.remaining : (typeof g.remaining_ms === 'number' ? g.remaining_ms / 1000 : gtRemaining);
        gtAnchorTs = Number(g.ts || g.start_timestamp);
      } else {
        gtAnchorTs = null; gtRemainingAtAnchor = null;
      }
    } else if (timerType === 'shot') {
      const s = j.payload && (j.payload.shotClock || j.payload.shot_clock) ? (j.payload.shotClock || j.payload.shot_clock) : null;
      if (s && s.running && (typeof s.ts === 'number' || typeof s.start_timestamp === 'number')) {
        scRemainingAtAnchor = (typeof s.remaining === 'number') ? s.remaining : (typeof s.remaining_ms === 'number' ? s.remaining_ms / 1000 : scRemaining);
        scAnchorTs = Number(s.ts || s.start_timestamp);
      } else {
        scAnchorTs = null; scRemainingAtAnchor = null;
      }
    }
  } catch (_) {}
}

// Helper: flash document title temporarily
function flashTitle(msg, times, intervalMs) {
  try {
    const _times = typeof times === 'number' ? times : 8;
    const _interval = typeof intervalMs === 'number' ? intervalMs : 450;
    const orig = document.title;
    let i = 0;
    const id = setInterval(() => {
      try { document.title = i++ % 2 === 0 ? msg : orig; } catch(_) {}
      if (i >= _times) { clearInterval(id); try { document.title = orig; } catch(_) {} }
    }, _interval);
  } catch(_) {}
}

// FIX: game timer path only — Hook into game timer tick to emit ONLY game_timer updates\nconst _origGtTick = gtTick;\ngtTick = function () { _origGtTick(); try { postGameTimerUpdate(); } catch(_) {} };\n\n// FIX: shot clock path only — Hook into shot clock tick to emit ONLY shot_clock updates\nconst _origScTick = scTick;\nscTick = function () { _origScTick(); try { postShotClockUpdate(); } catch(_) {} };

// FIX: game timer path only — Hook into game timer tick to emit ONLY game_timer updates// FIX: game timer path only — Game timer control handlers emit ONLY game_timer
const _origGtPlay = gtPlay;
gtPlay = function () {
  if (gtRunning || gtRemaining <= 0) return;
  // Optimistic local render for admin; timer.php/WS remains authoritative and will override immediately.
  try { _origGtPlay(); gtRemainingAtAnchor = gtRemaining; gtAnchorTs = Date.now(); } catch(_) {}
  immediatePersistControl('start', 'game').catch(err => console.error('game start failed', err));
};

const _origGtPause = gtPause;
gtPause = function () {
  if (!gtRunning) return;
  immediatePersistControl('pause', 'game').catch(err => console.error('game pause failed', err));
};

const _origGtReset = gtReset;
gtReset = function () {
  // Critical reset rule: final state only. Never emit 00:00/0 as an intermediate reset state.
  gtTotalSecs = 600;
  immediatePersistControl('reset', 'game').catch(err => console.error('game reset failed', err));
};

const _origScPlay = scPlay;
scPlay = function () {
  if (scRunning || scRemaining <= 0) return;
  // Optimistic local render for admin; timer.php/WS remains authoritative and will override immediately.
  try { _origScPlay(); scRemainingAtAnchor = scRemaining; scAnchorTs = Date.now(); } catch(_) {}
  immediatePersistControl('start', 'shot').catch(err => console.error('shot start failed', err));
};

const _origScPause = scPause;
scPause = function () {
  if (!scRunning) return;
  immediatePersistControl('pause', 'shot').catch(err => console.error('shot pause failed', err));
};

const _origScReset = scReset;
scReset = function () {
  scPresetVal = 24;
  scTotal = 24;
  immediatePersistControl('reset', 'shot').catch(err => console.error('shot reset failed', err));
};

// FIX: shot clock path only — Handle shot clock preset changes
const _origScPreset = scPreset;
scPreset = function (s) {
  const value = parseInt(s, 10) || 24;
  scPresetVal = value;
  scTotal = value;
  scRemaining = value;
  scRunning = false;
  scAnchorTs = null;
  scRemainingAtAnchor = null;
  scLastTick = null;
  try { refreshScPresetActive(); applyTimerButtonState('shot', false); scRenderFrame(); } catch(_) {}
  immediatePersistControl('reset', 'shot').catch(err => console.error('shot preset failed', err));
};

// FIX: game timer path only — Handle game timer duration changes
const _origGtSetDuration = gtSetDuration;
gtSetDuration = function () {
  const mins  = parseInt(document.getElementById('gtInputMin')?.value || '0', 10) || 0;
  const secs  = parseInt(document.getElementById('gtInputSec')?.value || '0', 10) || 0;
  const total = Math.max(1, mins * 60 + secs);
  gtTotalSecs = total;
  gtRemaining = total;
  gtRunning = false;
  gtAnchorTs = null;
  gtRemainingAtAnchor = null;
  gtLastTick = null;
  try { applyTimerButtonState('game', false); gtRender(); } catch(_) {}
  immediatePersistControl('sync', 'game').catch(err => console.error('game set failed', err));
};

// Backwards compatible alias for renderRosterTable
window.renderRosterTable = bbRenderRosterTable;

// Create a new match via server and reset local UI to canonical fresh state
async function bbNewMatch() { // FIX: new match broadcast
  try { // FIX: new match broadcast
    if (!confirm('Create a new match and reset live state for all admins?')) return; // FIX: new match broadcast
    try {
      const recentMatch = localStorage.getItem('basketball_new_match');
      if (recentMatch) {
        const recent = JSON.parse(recentMatch);
        if (recent && recent.match_id && typeof recent.ts === 'number' && (Date.now() - recent.ts) < 5000) {
          adoptBasketballMatch({ match_id: String(recent.match_id) });
          return;
        }
      }
    } catch (_) {}
    if (window.__newMatchPending) return;
    window.__newMatchPending = true;
    const res = await fetch('new_match.php', { method: 'POST', credentials: 'include' }); // FIX: new match broadcast
    const j = await res.json(); // FIX: new match broadcast
    if (!j || !j.success) { window.__newMatchPending = false; try { showToast('Failed to create new match'); } catch(_) {} return; } // FIX: new match broadcast

    const newMatchId = String(j.match_id); // FIX: new match broadcast
    const newTs = Date.now(); // FIX: new match broadcast

    // FIX: new match broadcast — 1. Adopt new match id locally FIRST
    try { sessionStorage.setItem('basketball_match_id', newMatchId); } catch (_) {} // FIX: new match broadcast
    try { localStorage.setItem('basketball_match_id', newMatchId); } catch (_) {} // FIX: new match broadcast
    try { window.__matchId = newMatchId; } catch (_) {} // FIX: new match broadcast

    // FIX: new match broadcast — 2. Reset ALL local state (timers + roster + counters)
    gtTotalSecs = 10 * 60; gtRemaining = gtTotalSecs; gtRunning = false; // FIX: new match broadcast
    gtAnchorTs = null; gtRemainingAtAnchor = null; // FIX: new match broadcast
    scPresetVal = 24; scTotal = 24; scRemaining = 24; scRunning = false; // FIX: new match broadcast
    scAnchorTs = null; scRemainingAtAnchor = null; // FIX: new match broadcast
    try { applyTimerButtonState('game', false); } catch(_) {} // FIX: new match broadcast
    try { applyTimerButtonState('shot', false); } catch(_) {} // FIX: new match broadcast
    try { gtRender(); } catch(_) {} // FIX: new match broadcast
    try { scRenderFrame(); } catch(_) {} // FIX: new match broadcast

    state.teamA = { name: 'TEAM A', players: [], foul: 0, timeout: 0, manualScore: 0 }; // FIX: new match broadcast
    state.teamB = { name: 'TEAM B', players: [], foul: 0, timeout: 0, manualScore: 0 }; // FIX: new match broadcast
    state.shared = { foul: 0, timeout: 0, quarter: 1 }; // FIX: new match broadcast
    pCount = { A: 0, B: 0 }; // FIX: new match broadcast
    _lastStateResetTs = newTs; // FIX: new match broadcast
    savedState = null; // FIX: new match broadcast
    clearRosterDirty(); // FIX: new match broadcast

    try { document.getElementById('tbodyA').innerHTML = ''; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('tbodyB').innerHTML = ''; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('teamAName').value = 'TEAM A'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('teamBName').value = 'TEAM B'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('labelA').textContent = 'TEAM A'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('labelB').textContent = 'TEAM B'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('scoreA').textContent = '0'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('scoreB').textContent = '0'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('bbTsbAFoul').textContent = '0'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('bbTsbATimeout').textContent = '0'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('bbTsbBFoul').textContent = '0'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('bbTsbBTimeout').textContent = '0'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('bbQuarterVal').textContent = '1'; } catch(_) {} // FIX: new match broadcast
    try { document.getElementById('bbPerQuarterVal').textContent = '1'; } catch(_) {} // FIX: new match broadcast
    try { syncRightPanelCounters(); } catch(_) {} // FIX: new match broadcast

    // FIX: new match broadcast — 3. Rejoin WS room under new match_id (creating admin)
    if (_ws && _ws.readyState === WebSocket.OPEN) { // FIX: new match broadcast
      const role = (window.__role) ? String(window.__role) : 'unknown'; // FIX: new match broadcast
      try { _ws.send(JSON.stringify({ type: 'join', match_id: newMatchId, role })); } catch(_) {} // FIX: new match broadcast

      // FIX: new match broadcast — 4. Broadcast new_match to all other clients — timers explicitly stopped
      const broadcastPayload = { // FIX: new match broadcast
        teamA: { name: 'TEAM A', score: 0, foul: 0, timeout: 0, manualScore: 0, players: [] }, // FIX: new match broadcast
        teamB: { name: 'TEAM B', score: 0, foul: 0, timeout: 0, manualScore: 0, players: [] }, // FIX: new match broadcast
        shared: { foul: 0, timeout: 0, quarter: 1 }, // FIX: new match broadcast
        committee: '', // FIX: new match broadcast
        resetAt: newTs, // FIX: new match broadcast
        gameTimer: { total: 600, remaining: 600, running: false, ts: null }, // FIX: new match broadcast
        shotClock: { total: 24, remaining: 24, running: false, ts: null } // FIX: new match broadcast
      }; // FIX: new match broadcast
      try { _ws.send(JSON.stringify({ type: 'new_match', sport: 'basketball', match_id: newMatchId, payload: broadcastPayload, ts: newTs, meta: { clientId: CLIENT_ID, ts: newTs } })); } catch(_) {} // FIX: new match broadcast
    } // FIX: new match broadcast

    // FIX: new match broadcast — 5. BroadcastChannel for same-browser tabs
    if (_bkBC) { // FIX: new match broadcast
      try { _bkBC.postMessage({ type: 'new_match', match_id: newMatchId, payload: { teamA: { name:'TEAM A', score:0, foul:0, timeout:0, manualScore:0, players:[] }, teamB: { name:'TEAM B', score:0, foul:0, timeout:0, manualScore:0, players:[] }, shared: { foul:0, timeout:0, quarter:1 }, committee:'', resetAt: newTs, gameTimer: { total:600, remaining:600, running:false, ts:null }, shotClock: { total:24, remaining:24, running:false, ts:null } }, ts: newTs, meta: { clientId: CLIENT_ID } }); } catch(_) {} // FIX: new match broadcast
    } // FIX: new match broadcast

    // FIX: new match broadcast — 6. Write localStorage key so same-browser tabs that missed BC also adopt
    try { localStorage.setItem('basketball_new_match', JSON.stringify({ match_id: newMatchId, ts: newTs })); } catch(_) {} // FIX: new match broadcast

    try { showToast('New match created: ' + newMatchId); } catch(_) {} // FIX: new match broadcast
    window.__newMatchPending = false;
  } catch (e) { window.__newMatchPending = false; console.error('newMatch error', e); try { showToast('Error creating new match'); } catch(_) {} } // FIX: new match broadcast
}

// addPlayer uses bbRenderRosterTable and event delegation for inputs

// Load persisted state (if any) then broadcast on page load so viewer sees state immediately


// Helper: show confirmation for timer reset and execute if confirmed
function showResetWarning(timerType) {
  const timerName = timerType === 'game' ? 'Game Timer' : 'Shot Clock';
  const message = `Reset the ${timerName}? This will sync to all connected users.`;
  if (confirm(message)) {
    if (timerType === 'game') {
      gtReset();
    } else if (timerType === 'shot') {
      scReset();
    }
  }
}

// Helper: ensure timer control buttons are bound to current functions
function rebindTimerControls() {
  try {
    
    const p = document.getElementById('gtPlayBtn');
    const pa = document.getElementById('gtPauseBtn');
    const r = document.getElementById('gtResetBtn');
    if (p) p.onclick = function () { try { gtPlay(); } catch (e) {} };
    if (pa) pa.onclick = function () { try { gtPause(); } catch (e) {} };
    if (r) r.onclick = function () { try { showResetWarning('game'); } catch (e) {} };

    const sp = document.getElementById('scPlayBtn');
    const spa = document.getElementById('scPauseBtn');
    const sr = document.getElementById('scResetBtn');
    if (sp) sp.onclick = function () { try { scPlay(); } catch (e) {} };
    if (spa) spa.onclick = function () { try { scPause(); } catch (e) {} };
    if (sr) sr.onclick = function () { try { showResetWarning('shot'); } catch (e) {} };
    const btn24 = document.getElementById('preset24');
    const btn14 = document.getElementById('preset14');
    if (btn24) btn24.onclick = function () { try { scPreset(24); } catch(e) {} };
    if (btn14) btn14.onclick = function () { try { scPreset(14); } catch(e) {} };
    } catch (_) {}
}

// Bind controls, then hydrate from server (server-first). If the server

(async function() {
  try {
    // FIX: decoupled from timer — STEP A: Restore ROSTER from localStorage FIRST
    const saved = localStorage.getItem('basketball_state');
    if (saved) {
      Object.assign(state, JSON.parse(saved));
      if (!Array.isArray(state.teamA.players)) state.teamA.players = [];
      if (!Array.isArray(state.teamB.players)) state.teamB.players = [];
    }
    // FIX: decoupled from timer — STEP B: Render DOM with roster (INDEPENDENT of timers)
    bbRenderRosterTable();
    syncRightPanelCounters();
    
    // FIX: decoupled from timer — STEP C: Then hydrate ROSTER from server async (INDEPENDENT of timers)
    // Mark hydration in progress to prevent this client's initial
    // local writes from stomping the server SSOT while we fetch it.
    _hydrationPending = true;
    const applied = await loadStateFromServerIfMissing(true);
    _hydrationPending = false;
    // mark hydration complete so automatic debounced writes are allowed
    _initialHydrationDone = true;
    if (!applied) {
      // No local persisted roster/counter state on reload; defer to server/broadcast.
    }

    // FIX: decoupled from timer — STEP D: Initialize TIMERS from server state INDEPENDENTLY
    // This runs AFTER roster is loaded, but timers are completely independent
    try {
      initializeTimersFromServerState();
    } catch (e) {
      console.warn('Timer initialization failed:', e);
      // Timers will use defaults if server fetch fails
    }

  } catch (e) {
    _hydrationPending = false;
    _initialHydrationDone = true;
    // Do NOT use local persisted roster/counter state on hydration error.
  }
})();
// Announce sport selection so viewers can auto-switch to this sport
function broadcastSportChange(sport) {
  try {
    const mid = getMatchId();
    const payload = { sport: sport };
    if (_bkBC) try { _bkBC.postMessage({ type: 'sport_change', match_id: mid, sport, payload }); } catch (_) {}
    try { if (_ws && _ws.readyState === WebSocket.OPEN) _ws.send(JSON.stringify({ type: 'sport_change', match_id: mid, sport: sport, payload })); } catch (_) {}
    try { localStorage.setItem('_last_sport', JSON.stringify({ match_id: mid, sport })); } catch (_) {}
  } catch (_) {}
}
try { broadcastSportChange('basketball'); } catch (_) {}
// Initialize view and right-panel counters
try {
  // ensure state defaults are applied to DOM on load (overrides any leftover values)
  state.teamA.foul = state.teamA.foul || 0;
  state.teamA.timeout = state.teamA.timeout || 0;
  state.teamB.foul = state.teamB.foul || 0;
  state.teamB.timeout = state.teamB.timeout || 0;
  state.shared.quarter = typeof state.shared.quarter === 'number' ? state.shared.quarter : 1;
  applyViewMode();
  syncRightPanelCounters();
    // Role-aware: make team name inputs editable for admins only
    try {
      // If role isn't explicitly injected on the page, assume editable
      // (legacy pages may not set window.__role). If role exists, honor it.
      const isAdmin = (typeof window.__role === 'undefined') || (window.__role === 'admin' || window.__role === 'superadmin');
      const tA = document.getElementById('teamAName');
      const tB = document.getElementById('teamBName');
      if (tA) { tA.readOnly = !isAdmin; tA.tabIndex = isAdmin ? 0 : -1; }
      if (tB) { tB.readOnly = !isAdmin; tB.tabIndex = isAdmin ? 0 : -1; }
      // FIX: decoupled from timer — Ensure committee input broadcasts roster state when edited
      try {
        const ci = document.getElementById('bbCommitteeInput');
        if (ci && !ci.dataset._bk) {
          ci.addEventListener('input', function () { try { saveRosterState(); } catch(_) {} }); // FIX: decoupled from timer — use roster-only save
          ci.dataset._bk = '1';
        }
      } catch(_) {}
    } catch(_) {}
} catch (e) { /* ignore early load errors */ }

// Bind timer control buttons to current wrapped handlers
try { rebindTimerControls(); } catch(_) {}
document.addEventListener('DOMContentLoaded', function() {
  try { rebindTimerControls(); } catch(_) {}
});

/* ================================================================
   FINAL TIMER/STATS SYNC OVERRIDE
   Purpose:
   - Make timer play/pause/reset work immediately.
   - Use one room id even when match_id is missing: "live".
   - Send final timer state to timer.php AND directly to WebSocket as a
     compatibility fan-out, because some deployed WS servers do not support
     backend /emit yet.
   - Send roster/stat/committee state to viewer with the same room id.
   ================================================================ */
(function () {
  function _bbMid() {
    try {
      const m = getMatchId && getMatchId();
      if (m && String(m).trim() !== '' && String(m) !== '0') return String(m).trim();
    } catch (_) {}
    return 'live';
  }

  function _headers() {
    const h = { 'Content-Type': 'application/json' };
    try { if (window.__userId) h['X-SS-UID'] = String(window.__userId); } catch (_) {}
    try { if (window.__role) h['X-SS-ROLE'] = String(window.__role); } catch (_) {}
    return h;
  }

  function _sendWS(msg) {
    try {
      if (typeof _ws !== 'undefined' && _ws && _ws.readyState === WebSocket.OPEN) {
        _ws.send(JSON.stringify(msg));
        return true;
      }
    } catch (_) {}
    return false;
  }

  function _currentGame(now) {
    try {
      if (gtRunning && typeof gtAnchorTs === 'number' && typeof gtRemainingAtAnchor === 'number') {
        return Math.max(0, gtRemainingAtAnchor - ((now - Number(gtAnchorTs)) / 1000));
      }
    } catch (_) {}
    return Math.max(0, typeof gtRemaining === 'number' ? gtRemaining : 600);
  }

  function _currentShot(now) {
    try {
      if (scRunning && typeof scAnchorTs === 'number' && typeof scRemainingAtAnchor === 'number') {
        return Math.max(0, scRemainingAtAnchor - ((now - Number(scAnchorTs)) / 1000));
      }
    } catch (_) {}
    return Math.max(0, typeof scRemaining === 'number' ? scRemaining : 24);
  }

  function _timerState(control, target) {
    const now = Date.now();
    let gTotal = Math.max(1, Number(gtTotalSecs || 600));
    let sTotal = Math.max(1, Number(scTotal || 24));
    let gRem = _currentGame(now);
    let sRem = _currentShot(now);
    let gRun = !!gtRunning;
    let sRun = !!scRunning;

    if (control === 'start' && target === 'game') {
      if (gRem <= 0) gRem = gTotal;
      gRun = true;
      gtRunning = true;
      gtRemaining = gRem;
      gtRemainingAtAnchor = gRem;
      gtAnchorTs = now;
      gtLastTick = null;
    }
    if (control === 'start' && target === 'shot') {
      if (sRem <= 0) sRem = sTotal;
      sRun = true;
      scRunning = true;
      scRemaining = sRem;
      scRemainingAtAnchor = sRem;
      scAnchorTs = now;
      scLastTick = null;
    }
    if (control === 'pause' && target === 'game') {
      gRun = false;
      gtRunning = false;
      gtRemaining = gRem;
      gtAnchorTs = null;
      gtRemainingAtAnchor = null;
      gtLastTick = null;
    }
    if (control === 'pause' && target === 'shot') {
      sRun = false;
      scRunning = false;
      scRemaining = sRem;
      scAnchorTs = null;
      scRemainingAtAnchor = null;
      scLastTick = null;
    }
    if (control === 'reset' && target === 'game') {
      gTotal = 600; gRem = 600; gRun = false;
      sTotal = 24; sRem = 24; sRun = false;
      gtTotalSecs = 600; gtRemaining = 600; gtRunning = false; gtAnchorTs = null; gtRemainingAtAnchor = null; gtLastTick = null;
      scTotal = 24; scPresetVal = 24; scRemaining = 24; scRunning = false; scAnchorTs = null; scRemainingAtAnchor = null; scLastTick = null;
    }
    if (control === 'reset' && target === 'shot') {
      // Respect current shot-clock preset. This keeps the 14s button working.
      sTotal = Math.max(1, Number(scTotal || scPresetVal || 24));
      sRem = sTotal; sRun = false;
      scTotal = sTotal; scPresetVal = sTotal; scRemaining = sTotal; scRunning = false; scAnchorTs = null; scRemainingAtAnchor = null; scLastTick = null;
    }

    try { applyTimerButtonState('game', !!gtRunning); } catch (_) {}
    try { applyTimerButtonState('shot', !!scRunning); } catch (_) {}
    try { gtRender(); } catch (_) {}
    try { scRenderFrame(); } catch (_) {}

    return {
      match_id: _bbMid(),
      game_time: Math.round(gRem),
      shot_clock: Math.round(sRem),
      is_running: !!(gRun || sRun),
      last_update_at: now,
      gameTimer: { total: gTotal, remaining: gRem, running: !!gRun, ts: gRun ? now : null },
      shotClock: { total: sTotal, remaining: sRem, running: !!sRun, ts: sRun ? now : null },
      meta: { control: control || 'sync', timer: target || 'both', clientId: CLIENT_ID, ts: now }
    };
  }

  function _publishTimer(control, target) {
    const p = _timerState(control, target);
    const msg = {
      type: 'timer_update',
      sport: 'basketball',
      match_id: p.match_id,
      game_time: p.game_time,
      shot_clock: p.shot_clock,
      is_running: p.is_running,
      last_update_at: p.last_update_at,
      gameTimer: p.gameTimer,
      shotClock: p.shotClock,
      payload: p,
      ts: p.last_update_at,
      meta: p.meta
    };

    // Direct WS compatibility path for deployed WS servers that relay client messages.
    _sendWS(msg);

    // Persist authoritative state for reload/reconnect and for backend /emit when available.
    try {
      fetch('timer.php', {
        method: 'POST',
        credentials: 'include',
        headers: _headers(),
        body: JSON.stringify(p),
        keepalive: true
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j && j.success && j.payload) {
          const pp = j.payload;
          _sendWS({
            type: 'timer_update', sport: 'basketball', match_id: p.match_id,
            game_time: pp.game_time, shot_clock: pp.shot_clock, is_running: pp.is_running,
            last_update_at: pp.last_update_at, gameTimer: pp.gameTimer, shotClock: pp.shotClock,
            payload: pp, ts: pp.last_update_at, meta: pp.meta || p.meta
          });
        }
      }).catch(function () {});
    } catch (_) {}
    return Promise.resolve({ success: true, payload: p });
  }

  // Replace timer control functions with deterministic working versions.
  gtPlay = function () { if (gtRunning) return; return _publishTimer('start', 'game'); };
  gtPause = function () { if (!gtRunning) return; return _publishTimer('pause', 'game'); };
  gtReset = function () { return _publishTimer('reset', 'game'); };
  scPlay = function () { if (scRunning) return; return _publishTimer('start', 'shot'); };
  scPause = function () { if (!scRunning) return; return _publishTimer('pause', 'shot'); };
  scReset = function () { return _publishTimer('reset', 'shot'); };
  immediatePersistControl = function (control, timerType) { return _publishTimer(control || 'sync', timerType || 'both'); };


  // FIX: game timer Set button — use the entered duration instead of forcing 10:00.
  gtSetDuration = function () {
    const minEl = document.getElementById('gtInputMin');
    const secEl = document.getElementById('gtInputSec');
    const mins = parseInt(minEl && minEl.value ? minEl.value : '0', 10) || 0;
    const secs = parseInt(secEl && secEl.value ? secEl.value : '0', 10) || 0;
    const total = Math.max(1, (mins * 60) + secs);

    gtTotalSecs = total;
    gtRemaining = total;
    gtRunning = false;
    gtAnchorTs = null;
    gtRemainingAtAnchor = null;
    gtLastTick = null;

    try { applyTimerButtonState('game', false); } catch (_) {}
    try { gtRender(); } catch (_) {}

    return _publishTimer('sync', 'game');
  };

  // FIX: shot-clock preset buttons — keep selected preset, including 14s, and sync it.
  scPreset = function (secs) {
    const value = parseInt(secs, 10) || 24;
    scPresetVal = value;
    scTotal = value;
    scRemaining = value;
    scRunning = false;
    scAnchorTs = null;
    scRemainingAtAnchor = null;
    scLastTick = null;

    try { refreshScPresetActive(); } catch (_) {}
    try { applyTimerButtonState('shot', false); } catch (_) {}
    try { scRenderFrame(); } catch (_) {}

    return _publishTimer('reset', 'shot');
  };

  // Replace roster/stat sync so it works even with no numeric match_id.
  function _rosterPayload(payload) {
    try { return payload || buildRosterOnlyPayload(); } catch (_) { return payload || {}; }
  }

  function _publishRoster(payload, reason) {
    const mid = _bbMid();
    const p = _rosterPayload(payload);
    const msg = {
      type: 'basketball_state',
      sport: 'basketball',
      match_id: mid,
      payload: p,
      meta: { clientId: CLIENT_ID, ts: Date.now(), source: reason || 'admin' }
    };
    try { _lastOutgoingSerialized = JSON.stringify(p); } catch (_) {}
    _sendWS(msg);
    try {
      fetch('state.php', {
        method: 'POST', credentials: 'include', headers: _headers(), keepalive: true,
        body: JSON.stringify({ match_id: mid, payload: p })
      }).catch(function () {});
    } catch (_) {}
    return p;
  }

  syncRosterState = function (payload) { return _publishRoster(payload, 'syncRosterState'); };
  syncBasketballState = function (payload) { return _publishRoster(payload, 'syncBasketballState'); };
  broadcastState = function () { return _publishRoster(null, 'broadcastState'); };
  sendBasketballStatsSync = function (reason) { return _publishRoster(null, reason || 'stats-sync'); };

  // Rebind buttons after all original handlers have loaded.
  setTimeout(function () {
    try { const p = document.getElementById('gtPlayBtn'); if (p) p.onclick = function () { gtPlay(); }; } catch (_) {}
    try { const p = document.getElementById('gtPauseBtn'); if (p) p.onclick = function () { gtPause(); }; } catch (_) {}
    try { const p = document.getElementById('gtResetBtn'); if (p) p.onclick = function () { try { showResetWarning('game'); } catch (_) { gtReset(); } }; } catch (_) {}
    try { const p = document.getElementById('scPlayBtn'); if (p) p.onclick = function () { scPlay(); }; } catch (_) {}
    try { const p = document.getElementById('scPauseBtn'); if (p) p.onclick = function () { scPause(); }; } catch (_) {}
    try { const p = document.getElementById('scResetBtn'); if (p) p.onclick = function () { try { showResetWarning('shot'); } catch (_) { scReset(); } }; } catch (_) {}
    try { const p = document.querySelector('.gt-set-btn'); if (p) p.onclick = function () { gtSetDuration(); }; } catch (_) {}
    try { const p = document.getElementById('preset24'); if (p) p.onclick = function () { scPreset(24); }; } catch (_) {}
    try { const p = document.getElementById('preset14'); if (p) p.onclick = function () { scPreset(14); }; } catch (_) {}
  }, 0);
})();


// ============================================================
// ADMIN PAGE LEAVE WARNING
// Warn admin before leaving/reloading because live sync may disconnect.
// This does not change timer, match state, WebSocket, or viewer logic.
// ============================================================
(function basketballAdminLeaveWarning() {
  try {
    if (window.__basketballLeaveWarningInstalled) return;
    window.__basketballLeaveWarningInstalled = true;

    window.addEventListener('beforeunload', function (event) {
      const message = 'You may be disconnected from the live basketball match if you leave this page.';
      event.preventDefault();
      event.returnValue = message;
      return message;
    });
  } catch (_) {}
})();


// ============================================================
// ADMIN QUICK-RETURN TIMER RESYNC FIX
// Scope: admin timer/shot-clock rejoin only.
// Does not change roster, viewer, server, timer controls, or stat sync.
// Problem fixed: when admin quickly navigates Home -> Basketball, the WS
// admin_present message can arrive before timer hydration finishes, leaving
// this admin's timer UI stale while other clients are correct.
// ============================================================
(function basketballAdminQuickReturnTimerResyncFix(){
  try {
    if (window.__basketballAdminQuickReturnTimerResyncFixInstalled) return;
    window.__basketballAdminQuickReturnTimerResyncFixInstalled = true;

    let _adminTimerResyncSeq = 0;
    const _adminTimerResyncDelays = [0, 120, 350, 800, 1500, 2500];

    function _normalizeTimerPayload(timer) {
      if (!timer || typeof timer !== 'object') return null;
      const total = (typeof timer.total === 'number') ? timer.total :
        ((typeof timer.total_ms === 'number') ? timer.total_ms / 1000 : undefined);
      const remaining = (typeof timer.remaining === 'number') ? timer.remaining :
        ((typeof timer.remaining_ms === 'number') ? timer.remaining_ms / 1000 :
        ((typeof timer.paused_remaining_ms === 'number') ? timer.paused_remaining_ms / 1000 : undefined));
      const running = (typeof timer.running === 'boolean') ? timer.running :
        ((typeof timer.is_running === 'boolean') ? timer.is_running : undefined);
      const ts = (typeof timer.ts === 'number') ? timer.ts :
        ((typeof timer.start_timestamp === 'number') ? timer.start_timestamp : null);
      const out = {};
      if (typeof total === 'number') out.total = total;
      if (typeof remaining === 'number') out.remaining = remaining;
      if (typeof running === 'boolean') out.running = running;
      out.ts = ts;
      // Mark stopped timer payloads as explicit so local stale running/stopped state cannot win.
      out._timerControl = true;
      out.meta = { control: 'admin_rejoin_timer_resync' };
      return out;
    }

    async function _fetchAndApplyAdminTimer(mid, source) {
      try {
        const matchId = mid ? String(mid) : (typeof getMatchId === 'function' ? String(getMatchId()) : 'live');
        if (!matchId || matchId === '0') return false;

        const res = await fetch('timer.php?match_id=' + encodeURIComponent(matchId) + '&t=' + Date.now(), {
          cache: 'no-store',
          credentials: 'include'
        });
        const j = await res.json();
        if (!j || !j.success || !j.payload) return false;

        const p = j.payload || {};
        const gtRaw = p.gameTimer || p.game_timer || null;
        const scRaw = p.shotClock || p.shot_clock || null;
        const gt = _normalizeTimerPayload(gtRaw);
        const sc = _normalizeTimerPayload(scRaw);

        if (gt && typeof applyGameTimer === 'function') applyGameTimer(gt);
        if (sc && typeof applyShotClock === 'function') applyShotClock(sc);

        try { if (typeof gtRender === 'function') gtRender(); } catch (_) {}
        try { if (typeof scRenderFrame === 'function') scRenderFrame(); } catch (_) {}

        console.log('[Basketball Admin] timer rejoin resync applied:', matchId, source || 'unknown');
        return true;
      } catch (e) {
        console.warn('[Basketball Admin] timer rejoin resync failed:', e);
        return false;
      }
    }

    function _storeAndJoinAdminMatch(mid, source) {
      try {
        if (!mid || String(mid) === '0') return;
        const matchId = String(mid);
        try { sessionStorage.setItem('basketball_match_id', matchId); } catch (_) {}
        try { localStorage.setItem('basketball_match_id', matchId); } catch (_) {}
        try { window.__matchId = matchId; } catch (_) {}

        if (typeof _ws !== 'undefined' && _ws && _ws.readyState === WebSocket.OPEN) {
          const role = (window && window.__role) ? String(window.__role) : 'admin';
          try { _ws.send(JSON.stringify({ type: 'join', match_id: matchId, role: role, meta: { source: source || 'admin_quick_return_resync', clientId: (typeof CLIENT_ID !== 'undefined' ? CLIENT_ID : null), ts: Date.now() } })); } catch (_) {}
          try { _ws.send(JSON.stringify({ type: 'get_state', match_id: matchId, role: role, meta: { source: source || 'admin_quick_return_resync', clientId: (typeof CLIENT_ID !== 'undefined' ? CLIENT_ID : null), ts: Date.now() } })); } catch (_) {}
        }
      } catch (_) {}
    }

    function scheduleAdminTimerRejoinResync(mid, source) {
      try {
        const matchId = mid ? String(mid) : (typeof getMatchId === 'function' ? String(getMatchId()) : 'live');
        if (!matchId || matchId === '0') return;
        const seq = ++_adminTimerResyncSeq;
        _storeAndJoinAdminMatch(matchId, source);

        _adminTimerResyncDelays.forEach(function(delay) {
          setTimeout(function() {
            try {
              // If a newer rejoin cycle started, stop old attempts.
              if (seq !== _adminTimerResyncSeq) return;
              _fetchAndApplyAdminTimer(matchId, source);
            } catch (_) {}
          }, delay);
        });
      } catch (_) {}
    }

    window.scheduleAdminTimerRejoinResync = scheduleAdminTimerRejoinResync;

    // Listen independently so we do not modify existing WS message logic.
    function _attachAdminTimerResyncListener() {
      try {
        if (typeof _ws === 'undefined' || !_ws || _ws.__adminTimerResyncListenerAttached) return;
        _ws.__adminTimerResyncListenerAttached = true;
        _ws.addEventListener('message', function(ev) {
          try {
            const msg = JSON.parse(ev.data);
            if (!msg || (msg.sport && msg.sport !== 'basketball')) return;
            if (msg.type === 'admin_present' || msg.type === 'active_match') {
              const mid = msg.match_id || (msg.payload && msg.payload.match_id) || (typeof getMatchId === 'function' ? getMatchId() : null);
              if (mid) scheduleAdminTimerRejoinResync(mid, msg.type);
            }
          } catch (_) {}
        });
      } catch (_) {}
    }

    _attachAdminTimerResyncListener();
    setTimeout(_attachAdminTimerResyncListener, 250);
    setTimeout(_attachAdminTimerResyncListener, 1000);

    // Fast-return browser events: resync timers repeatedly for a short window.
    window.addEventListener('pageshow', function(){
      try { scheduleAdminTimerRejoinResync((typeof getMatchId === 'function' ? getMatchId() : null), 'pageshow'); } catch (_) {}
    });
    window.addEventListener('focus', function(){
      try { scheduleAdminTimerRejoinResync((typeof getMatchId === 'function' ? getMatchId() : null), 'focus'); } catch (_) {}
    });
    document.addEventListener('visibilitychange', function(){
      try { if (!document.hidden) scheduleAdminTimerRejoinResync((typeof getMatchId === 'function' ? getMatchId() : null), 'visibilitychange'); } catch (_) {}
    });
  } catch (_) {}
})();
