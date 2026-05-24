(function () {
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  function intVal(id) {
    const el = document.getElementById(id);
    const n = parseInt(el ? el.value : '0', 10);
    return isNaN(n) ? 0 : n;
  }
  function val(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
  }
  function csrfHeaders() {
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) headers['X-CSRF-TOKEN'] = meta.content;
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (m) headers['X-XSRF-TOKEN'] = decodeURIComponent(m[1]);
    return headers;
  }
  function ensureModal() {
    if (document.getElementById('mhEditModal')) return;
    const style = document.createElement('style');
    style.textContent = '.mh-modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:9999}.mh-modal.show{display:flex}.mh-box{background:var(--surface);color:var(--text);width:min(860px,calc(100vw - 24px));max-height:88vh;overflow:auto;border-radius:8px;padding:16px;box-shadow:0 18px 50px rgba(0,0,0,.35);border:1px solid var(--border)}.mh-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.mh-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.mh-field label{display:block;font-size:12px;font-weight:700;margin-bottom:4px;color:var(--text)}.mh-field input,.mh-field select,.mh-field textarea{width:100%;padding:8px;border:1px solid var(--border-s);border-radius:4px;background:var(--bg-mid);color:var(--text)}.mh-field textarea{min-height:90px;resize:vertical}.mh-section{font-weight:700;margin:14px 0 6px;border-top:1px solid var(--border);padding-top:12px;color:var(--primary)}.mh-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}.mh-btn{padding:8px 12px;border:0;border-radius:4px;background:var(--primary);color:var(--primary-fg);cursor:pointer;font-size:13px}.mh-btn.secondary{background:var(--surface-alt);color:var(--text);border:1px solid var(--border-s)}.mh-btn.danger{background:var(--danger);color:#fff;padding:4px 8px;font-size:11px}.mh-set-header,.mh-set-row{display:grid;grid-template-columns:52px 70px 70px 46px 46px 68px 68px 32px;gap:6px;margin-bottom:6px;align-items:center}.mh-set-header{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}.mh-set-header span{text-align:center}.mh-set-row input[type="number"]{text-align:center;padding:6px 4px}.mh-set-row input[type="checkbox"]{width:18px;height:18px;margin:auto;display:block;accent-color:var(--primary);cursor:pointer}.mh-set-row select{padding:6px 4px;font-size:12px}.mh-player-row{display:grid;grid-template-columns:70px 1fr 1fr;gap:8px;margin-bottom:8px}.mh-error{color:var(--danger);margin-top:8px;font-size:13px}@media(max-width:640px){.mh-grid{grid-template-columns:1fr}.mh-set-header,.mh-set-row{grid-template-columns:40px 60px 60px 36px 36px 56px 56px 28px;gap:4px}.mh-player-row{grid-template-columns:1fr}}';
    document.head.appendChild(style);
    const modal = document.createElement('div');
    modal.id = 'mhEditModal';
    modal.className = 'mh-modal';
    modal.innerHTML = '<div class="mh-box"><div class="mh-head"><h3 id="mhTitle">Edit Match</h3><button type="button" class="mh-btn secondary" onclick="closeMatchEdit()">Close</button></div><form id="mhForm"></form><div id="mhError" class="mh-error"></div></div>';
    document.body.appendChild(modal);
  }
  function field(id, label, value, type) {
    return '<div class="mh-field"><label for="' + id + '">' + esc(label) + '</label><input id="' + id + '" type="' + (type || 'text') + '" value="' + esc(value) + '"></div>';
  }
  function select(id, label, value, opts) {
    return '<div class="mh-field"><label for="' + id + '">' + esc(label) + '</label><select id="' + id + '">' + opts.map(function (o) {
      return '<option value="' + esc(o) + '"' + (String(value || '') === String(o) ? ' selected' : '') + '>' + esc(o) + '</option>';
    }).join('') + '</select></div>';
  }
  function renderPlayers(players, jerseyLabel) {
    if (!players || !players.length) return '';
    return '<div class="mh-section">Players</div>' + players.map(function (p) {
      return '<div class="mh-player-row" data-player-id="' + esc(p.id) + '">' +
        '<input value="' + esc(p.team || p.player_number || '') + '" disabled>' +
        '<input class="mh-player-name" placeholder="Player name" value="' + esc(p.player_name || '') + '">' +
        '<input class="mh-player-jersey" placeholder="' + esc(jerseyLabel || 'Team/Jersey') + '" value="' + esc(p.jersey_no || p.team_name || '') + '">' +
      '</div>';
    }).join('');
  }
  function renderPlayerThrowsForLegs(legs) {
    if (!Array.isArray(legs) || !legs.length) return '';
    let html = '';
    legs.forEach(function (leg) {
      html += '<div class="mh-section">Leg ' + esc(leg.leg_number) + ' Throws</div>';
      if (Array.isArray(leg.players) && leg.players.length) {
        leg.players.forEach(function (p) {
          var lines = '';
          if (Array.isArray(p.throws) && p.throws.length) {
            lines = p.throws.map(function (t) {
              var value = t.throw_value != null ? t.throw_value : t.throwValue != null ? t.throwValue : '';
              var bust = t.is_bust || t.isBust || false;
              return value + (bust ? ' bust' : '');
            }).join('\n');
          }
          html += '<div class="mh-field"><label for="mh_throws_' + esc(leg.leg_number) + '_' + esc(p.id || p.player_number || '') + '">Throws for ' + esc(p.player_name || ('Player ' + p.player_number)) + '</label>' +
            '<textarea id="mh_throws_' + esc(leg.leg_number) + '_' + esc(p.id || p.player_number || '') + '" rows="5">' + esc(lines) + '</textarea></div>';
        });
      }
    });
    return html;
  }
  function renderLegSelector(availableLegs, currentLeg) {
    if (!Array.isArray(availableLegs) || availableLegs.length <= 1) return '';
    return '<div class="mh-field"><label for="mh_leg_select">Leg Number</label><select id="mh_leg_select">' + availableLegs.map(function (leg) {
      return '<option value="' + esc(leg) + '"' + (leg === currentLeg ? ' selected' : '') + '>' + esc(leg) + '</option>';
    }).join('') + '</select></div>';
  }
  function renderSets(sets) {
    if (!sets || !sets.length) sets = [{ set_number: 1, team_a_score: 0, team_b_score: 0, team_a_timeout_used: 0, team_b_timeout_used: 0, serving_team: 'A', set_winner: '' }];
    var header = '<div class="mh-set-header">' +
      '<span>Set #</span>' +
      '<span>Score A</span>' +
      '<span>Score B</span>' +
      '<span title="Team A Timeout Used">TO-A</span>' +
      '<span title="Team B Timeout Used">TO-B</span>' +
      '<span>Serving</span>' +
      '<span>Winner</span>' +
      '<span></span>' +
    '</div>';
    return '<div class="mh-section">Set Scores</div>' + header + '<div id="mhSets">' + sets.map(function (s) {
      var w = s.set_winner || '';
      var srv = (s.serving_team || 'A') === 'B' ? 'B' : 'A';
      var aTO = s.team_a_timeout_used ? ' checked' : '';
      var bTO = s.team_b_timeout_used ? ' checked' : '';
      return '<div class="mh-set-row">' +
        '<input class="mh-set-num" type="number" min="1" value="' + esc(s.set_number || 1) + '">' +
        '<input class="mh-set-a" type="number" min="0" value="' + esc(s.team_a_score || 0) + '">' +
        '<input class="mh-set-b" type="number" min="0" value="' + esc(s.team_b_score || 0) + '">' +
        '<input class="mh-set-ato" type="checkbox"' + aTO + ' title="Team A timeout used">' +
        '<input class="mh-set-bto" type="checkbox"' + bTO + ' title="Team B timeout used">' +
        '<select class="mh-set-srv"><option value="A"' + (srv === 'A' ? ' selected' : '') + '>A</option><option value="B"' + (srv === 'B' ? ' selected' : '') + '>B</option></select>' +
        '<select class="mh-set-w"><option value="">Auto</option><option value="A"' + (w === 'A' ? ' selected' : '') + '>A</option><option value="B"' + (w === 'B' ? ' selected' : '') + '>B</option></select>' +
        '<button type="button" class="mh-btn danger mh-set-remove" onclick="removeMatchEditSet(this)" title="Remove set">✕</button>' +
      '</div>';
    }).join('') + '</div>' +
    '<div style="display:flex;gap:8px;margin-top:6px">' +
      '<button type="button" class="mh-btn secondary" onclick="addMatchEditSet()">+ Add Set</button>' +
    '</div>';
  }
  function buildForm(cfg, data) {
    const m = data.match || {};
    let html = '';
    if (cfg.kind === 'set') {
      html += '<div class="mh-grid">' +
        field('mh_team_a_name', 'Team A', m.team_a_name || '') +
        field('mh_team_b_name', 'Team B', m.team_b_name || '') +
        select('mh_match_type', 'Match Type', m.match_type || 'Singles', ['Singles','Doubles','Mixed Doubles']) +
        field('mh_best_of', 'Best Of', m.best_of || 3, 'number') +
        field('mh_team_a_player1', 'Team A Player 1', m.team_a_player1 || '') +
        field('mh_team_a_player2', 'Team A Player 2', m.team_a_player2 || '') +
        field('mh_team_b_player1', 'Team B Player 1', m.team_b_player1 || '') +
        field('mh_team_b_player2', 'Team B Player 2', m.team_b_player2 || '') +
        select('mh_status', 'Status', m.status || 'ongoing', ['ongoing','completed','reset']) +
        field('mh_winner_name', 'Winner', m.winner_name || '') +
        field('mh_committee', 'Committee / Official', m.committee_official || m.committee || '') +
      '</div>' + renderSets(data.sets || []);
    } else if (cfg.kind === 'score') {
      html += '<div class="mh-grid">' +
        field('mh_team_a_name', 'Team A', m.team_a_name || '') +
        field('mh_team_b_name', 'Team B', m.team_b_name || '') +
        field('mh_team_a_score', 'Team A Score', m.team_a_score || 0, 'number') +
        field('mh_team_b_score', 'Team B Score', m.team_b_score || 0, 'number') +
        field('mh_period', cfg.periodLabel || 'Period', m.team_a_quarter || m.current_set || 1, 'number') +
        select('mh_match_result', 'Result', m.match_result || 'DRAW', ['TEAM A WINS','TEAM B WINS','DRAW','IN PROGRESS','ONGOING']) +
        field('mh_committee', 'Committee / Official', m.committee || '') +
      '</div>' + renderPlayers(data.players || [], 'Jersey');
    } else {
      html += '<div class="mh-grid">' +
        select('mh_game_type', 'Game Type', m.game_type || '301', ['301','501','701']) +
        field('mh_legs_to_win', 'Legs To Win', m.legs_to_win || 3, 'number') +
        select('mh_mode', 'Mode', m.mode || 'one-sided', ['one-sided','two-sided']) +
        select('mh_status', 'Status', m.status || 'ongoing', ['ongoing','completed']) +
        field('mh_winner_name', 'Winner', m.winner_name || '') +
      '</div>' +
      renderPlayers(data.players || [], 'Team') +
      renderPlayerThrowsForLegs(data.legs || []);
    }
    html += '<div class="mh-actions"><button type="button" class="mh-btn secondary" onclick="closeMatchEdit()">Cancel</button><button type="submit" class="mh-btn">Save</button></div>';
    return html;
  }
  function collectPayload(cfg, id, data) {
    const payload = { match_id: id };
    if (cfg.kind === 'set') {
      Object.assign(payload, {
        team_a_name: val('mh_team_a_name'), team_b_name: val('mh_team_b_name'),
        match_type: val('mh_match_type'), best_of: intVal('mh_best_of'),
        team_a_player1: val('mh_team_a_player1'), team_a_player2: val('mh_team_a_player2'),
        team_b_player1: val('mh_team_b_player1'), team_b_player2: val('mh_team_b_player2'),
        status: val('mh_status'), winner_name: val('mh_winner_name'), committee_official: val('mh_committee'), committee: val('mh_committee'),
        sets: Array.from(document.querySelectorAll('.mh-set-row')).map(function (r) {
          return {
            set_number: parseInt(r.querySelector('.mh-set-num').value, 10) || 1,
            team_a_score: parseInt(r.querySelector('.mh-set-a').value, 10) || 0,
            team_b_score: parseInt(r.querySelector('.mh-set-b').value, 10) || 0,
            team_a_timeout_used: r.querySelector('.mh-set-ato') ? (r.querySelector('.mh-set-ato').checked ? 1 : 0) : 0,
            team_b_timeout_used: r.querySelector('.mh-set-bto') ? (r.querySelector('.mh-set-bto').checked ? 1 : 0) : 0,
            serving_team: r.querySelector('.mh-set-srv') ? r.querySelector('.mh-set-srv').value : 'A',
            set_winner: r.querySelector('.mh-set-w').value || ''
          };
        })
      });
    } else if (cfg.kind === 'score') {
      Object.assign(payload, {
        team_a_name: val('mh_team_a_name'), team_b_name: val('mh_team_b_name'),
        team_a_score: intVal('mh_team_a_score'), team_b_score: intVal('mh_team_b_score'),
        match_result: val('mh_match_result'), committee: val('mh_committee')
      });
      payload[cfg.periodField || 'team_a_quarter'] = intVal('mh_period');
      payload.players = collectPlayers();
    } else {
      Object.assign(payload, {
        game_type: val('mh_game_type'), legs_to_win: intVal('mh_legs_to_win'),
        mode: val('mh_mode'), status: val('mh_status'), winner_name: val('mh_winner_name')
      });
      payload.players = collectPlayers(true);
      payload.legs = [];
      var legsData = data.legs || [];
      legsData.forEach(function (leg) {
        var legPayload = { leg_number: leg.leg_number, players: [] };
        leg.players.forEach(function (p) {
          var throws = collectThrowsForLeg(leg.leg_number, p.id || p.player_number);
          legPayload.players.push({
            id: p.id,
            player_number: p.player_number,
            throws: throws
          });
        });
        payload.legs.push(legPayload);
      });
    }
    return payload;
  }
  function collectPlayers(teamField) {
    return Array.from(document.querySelectorAll('.mh-player-row')).map(function (r) {
      const out = { id: parseInt(r.dataset.playerId, 10) || 0, player_number: parseInt(r.querySelector('input').value, 10) || 0, player_name: r.querySelector('.mh-player-name').value.trim() };
      if (teamField) out.team_name = r.querySelector('.mh-player-jersey').value.trim();
      else out.jersey_no = r.querySelector('.mh-player-jersey').value.trim();
      return out;
    });
  }
  function collectThrows(key) {
    if (!key && key !== 0) return [];
    const textarea = document.getElementById('mh_throws_' + key);
    if (!textarea) return [];
    return textarea.value.split('\n').map(function (line) {
      const cleaned = line.trim();
      if (!cleaned) return null;
      const bust = /\b(bust|b)\b/i.test(cleaned);
      const numericText = cleaned.replace(/[^0-9\-]/g, '');
      const value = parseInt(numericText, 10);
      return {
        throw_value: isNaN(value) ? 0 : value,
        score_before: 0,
        score_after: 0,
        is_bust: bust ? 1 : 0
      };
    }).filter(Boolean);
  }
  function collectThrowsForLeg(legNumber, key) {
    if (!key && key !== 0) return [];
    const textarea = document.getElementById('mh_throws_' + legNumber + '_' + key);
    if (!textarea) return [];
    return textarea.value.split('\n').map(function (line) {
      const cleaned = line.trim();
      if (!cleaned) return null;
      const bust = /\b(bust|b)\b/i.test(cleaned);
      const numericText = cleaned.replace(/[^0-9\-]/g, '');
      const value = parseInt(numericText, 10);
      return {
        throw_value: isNaN(value) ? 0 : value,
        score_before: 0,
        score_after: 0,
        is_bust: bust ? 1 : 0
      };
    }).filter(Boolean);
  }
  function loadMatchEditForm(id, cfg, endpoint, legNumber) {
    const form = document.getElementById('mhForm');
    form.innerHTML = '<div style="padding:20px;color:#666">Loading...</div>';
    const url = new URL(endpoint, window.location.href);
    url.searchParams.set('match_id', id);
    if (legNumber) url.searchParams.set('leg_number', legNumber);
    fetch(url.href, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) return r.text().then(function (t) { throw new Error(t || ('Server error: ' + r.status)); });
        return r.json().catch(function () { throw new Error('Invalid JSON response from server'); });
      })
      .then(function (data) {
        if (!data || !data.success) throw new Error((data && data.message) || 'Unable to load match');
        form.innerHTML = buildForm(cfg, data);
        const legSelect = document.getElementById('mh_leg_select');
        if (legSelect) {
          legSelect.addEventListener('change', function () {
            const nextLeg = legSelect.value;
            const hidden = document.getElementById('mh_leg_number');
            if (hidden) hidden.value = nextLeg;
            loadMatchEditForm(id, cfg, endpoint, nextLeg);
          });
        }
        form.onsubmit = function (e) {
          e.preventDefault();
          const payload = collectPayload(cfg, id, data);
          const saveBtn = form.querySelector('button[type="submit"]');
          if (saveBtn) { saveBtn.disabled = true; saveBtn.dataset.origText = saveBtn.textContent; saveBtn.textContent = 'Saving...'; }
          document.getElementById('mhError').textContent = '';
          fetch(endpoint, { method: 'POST', credentials: 'include', headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(function (r) {
              if (!r.ok) return r.text().then(function (t) { throw new Error(t || ('Server error: ' + r.status)); });
              return r.json().catch(function () { throw new Error('Invalid JSON response from server'); });
            })
            .then(function (j) {
              if (!j || !j.success) throw new Error((j && j.message) || 'Save failed');
              updateRow(cfg, j.match || payload);
              closeMatchEdit();
            })
            .catch(function (err) { document.getElementById('mhError').textContent = err.message || 'Save failed'; console.error('Save error', err); })
            .finally(function () { if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = saveBtn.dataset.origText || 'Save'; } });
        };
      })
      .catch(function (err) { form.innerHTML = ''; document.getElementById('mhError').textContent = err.message || 'Unable to load match'; console.error('Load error', err); });
  }
  function updateRow(cfg, match) {
    const row = document.getElementById((cfg.rowPrefix || 'match-row-') + (match.match_id || match.id));
    if (!row) return;
    const teams = row.querySelector('.js-teams'); if (teams) teams.textContent = (match.team_a_name || '') + ' vs ' + (match.team_b_name || '');
    const score = row.querySelector('.js-score'); if (score) score.textContent = (match.team_a_score || 0) + ' - ' + (match.team_b_score || 0);
    const period = row.querySelector('.js-period'); if (period) period.textContent = match.team_a_quarter || match.current_set || '';
    const result = row.querySelector('.js-result'); if (result) result.textContent = match.match_result || match.status || '';
    const committee = row.querySelector('.js-committee'); if (committee) committee.textContent = match.committee || '';
    const type = row.querySelector('.js-type'); if (type) type.textContent = match.match_type || match.game_type || '';
    const best = row.querySelector('.js-best'); if (best) best.textContent = match.best_of || match.legs_to_win || '';
    const winner = row.querySelector('.js-winner'); 
    if (winner) { 
      winner.textContent = match.winner_name || '—'; 
      winner.style.color = match.winner_name ? 'var(--primary)' : 'var(--text-muted)'; 
    }
    const status = row.querySelector('.js-status span'); 
    if (status) { 
      status.textContent = match.status || ''; 
      status.className = 'ss-badge '; 
      if (match.status === 'completed') status.className += 'ss-badge-completed'; 
      else if (match.status === 'ongoing') status.className += 'ss-badge-ongoing'; 
      else status.className += 'ss-badge-reset'; 
    }
    const mode = row.querySelector('.js-mode'); if (mode) mode.textContent = match.mode || '';
  }
  window.closeMatchEdit = function () {
    const m = document.getElementById('mhEditModal');
    if (m) m.classList.remove('show');
  };
  window.addMatchEditSet = function () {
    const box = document.getElementById('mhSets');
    if (!box) return;
    const next = box.querySelectorAll('.mh-set-row').length + 1;
    const row = document.createElement('div');
    row.className = 'mh-set-row';
    row.innerHTML = '<input class="mh-set-num" type="number" min="1" value="' + next + '">' +
      '<input class="mh-set-a" type="number" min="0" value="0">' +
      '<input class="mh-set-b" type="number" min="0" value="0">' +
      '<input class="mh-set-ato" type="checkbox" title="Team A timeout used">' +
      '<input class="mh-set-bto" type="checkbox" title="Team B timeout used">' +
      '<select class="mh-set-srv"><option value="A">A</option><option value="B">B</option></select>' +
      '<select class="mh-set-w"><option value="">Auto</option><option value="A">A</option><option value="B">B</option></select>' +
      '<button type="button" class="mh-btn danger mh-set-remove" onclick="removeMatchEditSet(this)" title="Remove set">✕</button>';
    box.appendChild(row);
  };
  window.removeMatchEditSet = function (btn) {
    const row = btn.closest('.mh-set-row');
    if (!row) return;
    const box = document.getElementById('mhSets');
    if (box && box.querySelectorAll('.mh-set-row').length <= 1) { alert('At least one set is required.'); return; }
    row.remove();
    // Re-number set rows sequentially
    if (box) { box.querySelectorAll('.mh-set-row').forEach(function(r, i) { var num = r.querySelector('.mh-set-num'); if (num) num.value = i + 1; }); }
  };
  window.openMatchEdit = function (id) {
    const cfg = window.MATCH_HISTORY_EDIT_CONFIG || {};
    const endpoint = new URL(cfg.endpoint, window.location.href).href;
    ensureModal();
    document.getElementById('mhError').textContent = '';
    document.getElementById('mhTitle').textContent = 'Edit Match #' + id;
    document.getElementById('mhForm').innerHTML = '<div style="padding:20px;color:#666">Loading...</div>';
    document.getElementById('mhEditModal').classList.add('show');
    loadMatchEditForm(id, cfg, endpoint, null);
  };
})();