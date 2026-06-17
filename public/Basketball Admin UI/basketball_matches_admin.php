<?php
// auth.php and db.php live in the parent directory (sportssync-laravel/public/)
$_base = __DIR__;
if (!file_exists($_base . '/auth.php') && file_exists($_base . '/../auth.php')) {
    $_base = realpath(__DIR__ . '/..');
}
require_once $_base . '/auth.php';
require_once $_base . '/db.php';
$user = requireRole('admin');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(300, max(10, intval($_GET['per_page']))) : 50;
$offset = ($page - 1) * $perPage;
$export = isset($_GET['export']) ? trim($_GET['export']) : '';

$sqlBase = "SELECT match_id, team_a_name, team_b_name, team_a_score, team_b_score, team_a_quarter, match_result, committee, saved_at AS created_at FROM `matches`";
$where = [];
$bindVals = [];
if ($q !== '') { $where[] = "(match_id = ? OR team_a_name LIKE ? OR team_b_name LIKE ?)"; $bindVals[] = intval($q); $like = '%'.$q.'%'; $bindVals[] = $like; $bindVals[] = $like; }
if ($statusFilter !== '') { $where[] = "match_result = ?"; $bindVals[] = $statusFilter; }
if (!empty($where)) $sqlBase .= ' WHERE ' . implode(' AND ', $where);

$matches = [];
$totalMatches = 0;
$totalPages = 1;
try {
  if ($export === 'csv') {
    $stmt = $pdo->prepare($sqlBase . ' ORDER BY saved_at DESC');
    if (!empty($bindVals)) $stmt->execute($bindVals); else $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="basketball_matches.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
      fputcsv($out, array_keys($rows[0]));
      foreach ($rows as $r) fputcsv($out, $r);
    } else {
      fputcsv($out, ['match_id','team_a_name','team_b_name','team_a_score','team_b_score','team_a_quarter','match_result','committee','created_at']);
    }
    fclose($out);
    exit;
  }

  $countSql = 'SELECT COUNT(*) FROM `matches`';
  if (!empty($where)) $countSql .= ' WHERE ' . implode(' AND ', $where);
  $countStmt = $pdo->prepare($countSql);
  if (!empty($bindVals)) $countStmt->execute($bindVals); else $countStmt->execute();
  $totalMatches = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalMatches / $perPage));

  $sqlPage = $sqlBase . ' ORDER BY saved_at DESC LIMIT ' . (int)$offset . ',' . (int)$perPage;
  $stmt = $pdo->prepare($sqlPage);
  if (!empty($bindVals)) $stmt->execute($bindVals); else $stmt->execute();
  $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $matches = []; $totalMatches = 0; $totalPages = 1; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Basketball Matches — SportSync Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════
   SportSync Admin — Unified Design System
   ═══════════════════════════════════════════════════════ */
:root {
  --bg:          #0a0a0a;
  --bg-mid:      #111111;
  --surface:     #141414;
  --surface-alt: #1a1a1a;
  --nav-bg:      rgba(10,10,10,0.96);
  --text:        #e8eaed;
  --text-muted:  #888888;
  --text-dim:    #555555;
  --primary:     #FFD700;
  --primary-fg:  #0a0a0a;
  --primary-dim: rgba(255,215,0,0.12);
  --primary-glow:rgba(255,215,0,0.28);
  --blue:        #1565C0;
  --danger:      #cc3333;
  --danger-dim:  rgba(204,51,51,0.13);
  --success:     #27ae60;
  --border:      rgba(255,215,0,0.12);
  --border-s:    #252525;
  --font-head:   'Oswald', sans-serif;
  --font-body:   'DM Sans', sans-serif;
  --shadow:      0 4px 24px rgba(0,0,0,0.5);
  --shadow-gold: 0 4px 20px rgba(255,215,0,0.10);
  --radius:      6px;
  --nav-h:       60px;
  --tr:          all 0.25s ease;
}
.light-mode {
  --bg:          #f5f5f0;
  --bg-mid:      #edecea;
  --surface:     #ffffff;
  --surface-alt: #f2f0eb;
  --nav-bg:      rgba(10,10,10,0.97);
  --text:        #1a1a2e;
  --text-muted:  #555555;
  --text-dim:    #888888;
  --primary:     #d97706;
  --primary-fg:  #ffffff;
  --primary-dim: rgba(217,119,6,0.10);
  --primary-glow:rgba(217,119,6,0.22);
  --blue:        #1e3a8a;
  --danger:      #b91c1c;
  --danger-dim:  rgba(185,28,28,0.10);
  --border:      rgba(0,0,0,0.07);
  --border-s:    #ddd;
  --shadow:      0 2px 12px rgba(0,0,0,0.08);
  --shadow-gold: 0 2px 10px rgba(0,0,0,0.05);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);background:var(--bg);color:var(--text);line-height:1.5;min-height:100vh;font-size:clamp(14px,2.5vw,16px);overflow-x:hidden}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--primary);border-radius:4px}
*{scrollbar-width:thin;scrollbar-color:var(--primary) var(--bg)}
.ss-nav{position:sticky;top:0;z-index:100;height:var(--nav-h);background:var(--nav-bg);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
.ss-nav-inner{max-width:1200px;margin:0 auto;height:100%;padding:0 24px;display:flex;align-items:center;gap:16px}
.ss-brand{display:flex;align-items:center;gap:6px;font-family:var(--font-head);font-size:1.2rem;font-weight:700;letter-spacing:0.06em;color:#e8eaed;white-space:nowrap}
.ss-bolt{color:var(--primary)}
.ss-brand-accent{color:var(--primary)}
.ss-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-head);font-size:0.82rem;letter-spacing:0.08em;color:#888;border-left:1px solid var(--border);padding-left:16px}
.ss-bc-sep{color:var(--primary);opacity:0.6}
.ss-bc-current{color:var(--primary);font-weight:600;letter-spacing:0.1em}
.ss-nav-end{display:flex;align-items:center;gap:10px;margin-left:auto}
.ss-btn-back{font-family:var(--font-head);font-size:0.78rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;padding:7px 16px;border-radius:var(--radius);border:1.5px solid rgba(255,255,255,0.18);color:#e8eaed;background:transparent;white-space:nowrap;cursor:pointer;transition:var(--tr);display:inline-flex;align-items:center;gap:6px}
.ss-btn-back:hover{border-color:var(--primary);color:var(--primary)}
.ss-theme-btn{background:transparent;border:1px solid rgba(255,255,255,0.1);color:#e8eaed;padding:7px 10px;border-radius:var(--radius);cursor:pointer;font-size:1rem;transition:var(--tr);display:inline-flex;align-items:center}
.ss-theme-btn:hover{border-color:var(--primary)}
.ss-main{padding:24px 16px 48px;max-width:1200px;margin:0 auto}
.ss-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.ss-page-title{font-family:var(--font-head);font-size:clamp(1.4rem,3vw,1.9rem);font-weight:700;letter-spacing:0.04em;text-transform:uppercase;border-left:3px solid var(--primary);padding-left:12px}
.ss-match-id{display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--text-muted);background:var(--surface);border:1px solid var(--border-s);padding:6px 14px;border-radius:var(--radius)}
.ss-match-id strong{color:var(--primary);font-family:var(--font-head);font-size:1rem}
.ss-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow-gold);margin-bottom:16px}
.ss-search-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.ss-input{font-family:var(--font-body);font-size:0.9rem;background:var(--bg-mid);color:var(--text);border:1px solid var(--border-s);border-radius:var(--radius);padding:10px 14px;transition:var(--tr);min-height:42px}
.ss-input:focus{outline:2px solid var(--primary);outline-offset:1px;border-color:transparent}
.ss-input-wide{width:clamp(180px,30vw,300px)}
.ss-select{font-family:var(--font-body);font-size:0.88rem;background:var(--bg-mid);color:var(--text);border:1px solid var(--border-s);border-radius:var(--radius);padding:10px 14px;cursor:pointer;transition:var(--tr);min-height:42px}
.ss-select:focus{outline:2px solid var(--primary);outline-offset:1px}
.ss-btn{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-head);font-size:0.82rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;padding:10px 20px;border-radius:var(--radius);border:2px solid transparent;cursor:pointer;transition:var(--tr);white-space:nowrap;min-height:42px;min-width:44px}
.ss-btn-primary{background:var(--primary);color:var(--primary-fg);border-color:var(--primary)}
.ss-btn-primary:hover{background:#ffca00;border-color:#ffca00;box-shadow:0 4px 18px var(--primary-glow);transform:translateY(-1px)}
.ss-btn-secondary{background:transparent;color:var(--text);border-color:var(--border-s)}
.ss-btn-secondary:hover{border-color:var(--primary);color:var(--primary)}
.ss-btn-danger{background:transparent;color:var(--danger);border-color:var(--danger)}
.ss-btn-danger:hover{background:var(--danger-dim)}
.ss-btn-ghost{background:var(--surface-alt);color:var(--text-muted);border-color:var(--border-s)}
.ss-btn-ghost:hover{color:var(--text);border-color:var(--border-s)}
.ss-btn-sm{padding:6px 14px;font-size:0.76rem;min-height:34px}
.ss-btn-link{background:none;border:none;color:var(--primary);font-size:0.82rem;cursor:pointer;text-decoration:underline;text-underline-offset:3px;padding:4px 6px;font-family:var(--font-body);transition:var(--tr)}
.ss-btn-link:hover{color:#ffca00}
.ss-ml-auto{margin-left:auto}
.ss-action-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px}
.ss-table-wrap{overflow-x:auto;border-radius:var(--radius)}
.ss-table{width:100%;border-collapse:collapse;font-size:0.88rem;min-width:700px}
.ss-table thead{position:sticky;top:var(--nav-h);z-index:10}
.ss-table th{background:var(--bg-mid);font-family:var(--font-head);font-size:0.78rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--primary);padding:12px 10px;border-bottom:2px solid var(--border);border-right:1px solid var(--border-s);white-space:nowrap;text-align:left}
.ss-table th:last-child{border-right:none}
.ss-table td{padding:11px 10px;border-bottom:1px solid var(--border-s);border-right:1px solid var(--border-s);color:var(--text);vertical-align:middle}
.ss-table td:last-child{border-right:none}
.ss-table tbody tr:nth-child(even) td{background:var(--surface-alt)}
.ss-table tbody tr:hover td{background:var(--primary-dim)}
.ss-table tbody tr:last-child td{border-bottom:none}
.ss-table input[type="checkbox"]{width:16px;height:16px;cursor:pointer;accent-color:var(--primary)}
.ss-empty-row td{text-align:center;color:var(--text-muted);padding:32px;font-style:italic}
.ss-badge{display:inline-block;padding:3px 9px;border-radius:20px;font-family:var(--font-head);font-size:0.7rem;letter-spacing:0.08em;font-weight:600;text-transform:uppercase;white-space:nowrap}
.ss-badge-win{background:rgba(39,174,96,0.15);color:#4ade80;border:1px solid rgba(39,174,96,0.3)}
.ss-badge-draw{background:rgba(136,136,136,0.15);color:#aaa;border:1px solid rgba(136,136,136,0.25)}
.ss-badge-ongoing{background:rgba(21,101,192,0.15);color:#60a5fa;border:1px solid rgba(21,101,192,0.3)}
.ss-pagination{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:12px 0}
.ss-pg-info{font-size:0.82rem;color:var(--text-muted)}
.ss-pg-btns{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.ss-pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;font-family:var(--font-head);font-size:0.78rem;font-weight:600;background:var(--surface-alt);color:var(--text-muted);border:1px solid var(--border-s);border-radius:var(--radius);cursor:pointer;transition:var(--tr);text-decoration:none}
.ss-pg-btn:hover{border-color:var(--primary);color:var(--primary)}
.ss-pg-btn.active{background:var(--primary);color:var(--primary-fg);border-color:var(--primary);font-weight:700}
.ss-pg-btn.disabled{opacity:0.35;cursor:not-allowed;pointer-events:none}
.ss-pg-ellipsis{color:var(--text-dim);padding:0 4px}
.ss-pg-prev,.ss-pg-next{padding:0 12px}
@media(max-width:768px){
  .ss-main{padding:16px 12px 40px}
  .ss-nav-inner{padding:0 16px}
  .ss-breadcrumb{display:none}
  .ss-page-header{flex-direction:column;align-items:flex-start}
  .ss-action-bar{flex-direction:column;align-items:stretch}
  .ss-action-bar .ss-btn{justify-content:center}
  .ss-ml-auto{margin-left:0}
}
@media(max-width:480px){
  .ss-brand-name{display:none}
  .ss-search-row{flex-direction:column;align-items:stretch}
  .ss-input,.ss-select,.ss-input-wide{width:100%}
  .ss-btn{width:100%;justify-content:center}
}
</style>
</head>
<body>

<nav class="ss-nav">
  <div class="ss-nav-inner">
    <div class="ss-brand">
      <span class="ss-bolt">⚡</span>
      <span class="ss-brand-name">SPORT<span class="ss-brand-accent">SYNC</span></span>
    </div>
    <div class="ss-breadcrumb">
      <span>Admin</span>
      <span class="ss-bc-sep">›</span>
      <span class="ss-bc-current">Basketball Matches</span>
    </div>
    <div class="ss-nav-end">
      <a href="/" class="ss-btn-back">← Dashboard</a>
      <button id="themeToggle" class="ss-theme-btn" title="Toggle light/dark mode">🌙</button>
    </div>
  </div>
</nav>

<main class="ss-main">
  <div class="ss-page-header">
    <h1 class="ss-page-title">🏀 Basketball Matches</h1>
    <div class="ss-match-id">
      <span>Current Admin Match ID:</span>
      <strong id="currentMatchId">(none)</strong>
    </div>
  </div>

  <div class="ss-card">
    <form id="searchForm" method="GET" class="ss-search-row">
      <input class="ss-input ss-input-wide" type="text" name="q"
             placeholder="Search by match ID or team name"
             value="<?= htmlspecialchars($q) ?>">
      <select class="ss-select" name="status">
        <option value="">All Results</option>
        <option value="TEAM A WINS" <?= $statusFilter==='TEAM A WINS' ? 'selected' : '' ?>>Team A Wins</option>
        <option value="TEAM B WINS" <?= $statusFilter==='TEAM B WINS' ? 'selected' : '' ?>>Team B Wins</option>
        <option value="DRAW" <?= $statusFilter==='DRAW' ? 'selected' : '' ?>>Draw</option>
      </select>
      <select class="ss-select" name="per_page" id="perPageSelect">
        <option value="10" <?= $perPage==10 ? 'selected' : '' ?>>10 / page</option>
        <option value="25" <?= $perPage==25 ? 'selected' : '' ?>>25 / page</option>
        <option value="50" <?= $perPage==50 ? 'selected' : '' ?>>50 / page</option>
        <option value="100" <?= $perPage==100 ? 'selected' : '' ?>>100 / page</option>
        <option value="200" <?= $perPage==200 ? 'selected' : '' ?>>200 / page</option>
      </select>
      <button class="ss-btn ss-btn-primary" type="submit">Filter</button>
    </form>
  </div>

  <div class="ss-action-bar">
    <button id="resetSelected" class="ss-btn ss-btn-secondary">↺ Reset Selected</button>
    <button id="deleteSelected" class="ss-btn ss-btn-danger">✕ Delete Selected</button>
    <button id="exportCsvBtn" class="ss-btn ss-btn-ghost">⬇ Export CSV</button>
    <button id="refreshBtn" class="ss-btn ss-btn-ghost ss-ml-auto">↻ Refresh</button>
  </div>

  <div class="ss-card">
    <!-- Pagination Top -->
    <div class="ss-pagination">
      <div class="ss-pg-info">
        Showing <?= ($totalMatches>0 ? ($offset+1) : 0) ?>–<?= min($offset+$perPage,$totalMatches) ?> of <?= $totalMatches ?> matches
      </div>
      <div class="ss-pg-btns">
        <?php
          $qs = $_GET;
          if ($page > 1) { $qs['page']=$page-1; echo '<a class="ss-pg-btn ss-pg-prev" href="?'.htmlspecialchars(http_build_query($qs)).'">← Prev</a>'; }
          else echo '<span class="ss-pg-btn ss-pg-prev disabled">← Prev</span>';
          for ($i=1;$i<=$totalPages;$i++){
            if($totalPages>7&&$i>2&&$i<$totalPages-1&&abs($i-$page)>1){
              if($i===3||$i===$totalPages-2) echo '<span class="ss-pg-ellipsis">…</span>';
              continue;
            }
            $qs['page']=$i;
            $cls='ss-pg-btn'.($i===$page?' active':'');
            echo '<a class="'.$cls.'" href="?'.htmlspecialchars(http_build_query($qs)).'">'.$i.'</a>';
          }
          if ($page<$totalPages){ $qs['page']=$page+1; echo '<a class="ss-pg-btn ss-pg-next" href="?'.htmlspecialchars(http_build_query($qs)).'">Next →</a>'; }
          else echo '<span class="ss-pg-btn ss-pg-next disabled">Next →</span>';
        ?>
      </div>
    </div>

    <div class="ss-table-wrap">
      <form id="matchesForm">
      <table class="ss-table">
        <thead>
          <tr>
            <th style="width:36px"><input id="chkAll" type="checkbox"></th>
            <th>Match ID</th>
            <th>Teams</th>
            <th>Score</th>
            <th>Quarter</th>
            <th>Result</th>
            <th>Committee</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
<?php if (empty($matches)): ?>
          <tr class="ss-empty-row"><td colspan="9">No matches found.</td></tr>
<?php else: foreach ($matches as $m):
  // Compute display result from final scores (avoid relying on stored text)
  $scoreA = (int)$m['team_a_score'];
  $scoreB = (int)$m['team_b_score'];
  $badgeCls = 'ss-badge ';
  if ($scoreA > $scoreB) {
    $res = htmlspecialchars($m['team_a_name'] . ' (Team A)');
    $badgeCls .= 'ss-badge-win';
  } elseif ($scoreB > $scoreA) {
    $res = htmlspecialchars($m['team_b_name'] . ' (Team B)');
    $badgeCls .= 'ss-badge-win';
  } else {
    $res = htmlspecialchars('Draw');
    $badgeCls .= 'ss-badge-draw';
  }
?>
          <tr>
            <td><input class="chk" type="checkbox" name="match_ids[]" value="<?= (int)$m['match_id'] ?>"></td>
            <td><strong style="color:var(--primary)"><?= (int)$m['match_id'] ?></strong></td>
            <td><?= htmlspecialchars($m['team_a_name']) ?> <span style="color:var(--text-muted)">vs</span> <?= htmlspecialchars($m['team_b_name']) ?></td>
            <td><strong><?= (int)$m['team_a_score'] ?></strong> <span style="color:var(--text-dim)">–</span> <strong><?= (int)$m['team_b_score'] ?></strong></td>
            <td><?= (int)$m['team_a_quarter'] ?></td>
            <td><span class="<?= $badgeCls ?>"><?= $res ?></span></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($m['committee'] ?? '') ?></td>
            <td style="color:var(--text-muted);font-size:0.82rem;white-space:nowrap"><?= htmlspecialchars($m['created_at']) ?></td>
            <td style="white-space:nowrap;display:flex;gap:8px;">
              <a class="ss-btn-link" href="/basketball-admin/report?match_id=<?= (int)$m['match_id'] ?>" target="_blank">Report</a>
              <a class="ss-btn ss-btn-sm ss-btn-secondary" href="/basketball-admin/edit_match?match_id=<?= (int)$m['match_id'] ?>">✎ Edit</a>
              <button type="button" class="ss-btn ss-btn-sm ss-btn-danger" onclick="bbResetMatch(<?= (int)$m['match_id'] ?>")>Reset</button>
            </td>
          </tr>
<?php endforeach; endif; ?>
        </tbody>
      </table>
      </form>
    </div>

    <!-- Pagination Bottom -->
    <div class="ss-pagination">
      <div class="ss-pg-info">
        Showing <?= ($totalMatches>0 ? ($offset+1) : 0) ?>–<?= min($offset+$perPage,$totalMatches) ?> of <?= $totalMatches ?> matches
      </div>
      <div class="ss-pg-btns">
        <?php
          $qs = $_GET;
          if ($page > 1) { $qs['page']=$page-1; echo '<a class="ss-pg-btn ss-pg-prev" href="?'.htmlspecialchars(http_build_query($qs)).'">← Prev</a>'; }
          else echo '<span class="ss-pg-btn ss-pg-prev disabled">← Prev</span>';
          for ($i=1;$i<=$totalPages;$i++){
            if($totalPages>7&&$i>2&&$i<$totalPages-1&&abs($i-$page)>1){
              if($i===3||$i===$totalPages-2) echo '<span class="ss-pg-ellipsis">…</span>';
              continue;
            }
            $qs['page']=$i;
            $cls='ss-pg-btn'.($i===$page?' active':'');
            echo '<a class="'.$cls.'" href="?'.htmlspecialchars(http_build_query($qs)).'">'.$i.'</a>';
          }
          if ($page<$totalPages){ $qs['page']=$page+1; echo '<a class="ss-pg-btn ss-pg-next" href="?'.htmlspecialchars(http_build_query($qs)).'">Next →</a>'; }
          else echo '<span class="ss-pg-btn ss-pg-next disabled">Next →</span>';
        ?>
      </div>
    </div>
  </div>
</main>

<script>
(function(){ try{ const el=document.getElementById('currentMatchId'); const id = sessionStorage.getItem('basketball_match_id'); if (id) el.textContent = id; else el.textContent = '(none)'; }catch(e){} })();
document.getElementById('chkAll').addEventListener('change', function(){ document.querySelectorAll('.chk').forEach(c=>c.checked=this.checked); });
document.getElementById('refreshBtn').addEventListener('click', function(){ location.reload(); });
function bbResetMatch(id) { if (!confirm('Reset match ' + id + '? This will clear saved data.')) return; fetch('/basketball-admin/delete_match', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ match_id: id }) }).then(r => r.json()).then(j => { if (j && j.success) { alert('Match reset'); location.reload(); } else { alert('Reset failed: ' + (j && j.error ? j.error : 'Unknown')); } }).catch(e=>{console.error(e); alert('Reset request failed');}); }
document.getElementById('deleteSelected').addEventListener('click', function(){ 
  const ids = Array.from(document.querySelectorAll('.chk:checked')).map(i=>parseInt(i.value,10)); 
  if (!ids.length) { 
    alert('Select at least one match to delete.'); 
    return; 
  } 
  const msg = `Delete ${ids.length} match(es)?\n\n⚠️  This will permanently remove:\n• Match records\n• All player statistics\n• Game reports\n\nThis action CANNOT be undone.`;
  if (!confirm(msg)) 
    return; 
  fetch('/basketball-admin/delete_match', {
    method: 'POST', 
    credentials: 'include', 
    headers: { 'Content-Type': 'application/json' }, 
    body: JSON.stringify({ match_ids: ids }) 
  }).then(r=>r.json())
    .then(j=>{ 
      if (j && j.success) { 
        alert(`✓ Successfully deleted ${ids.length} match(es)`); 
        location.reload(); 
      } else { 
        alert('Delete failed: ' + (j && j.message ? j.message : 'Unknown')); 
      } 
    })
    .catch(e=>{console.error(e); alert('Delete request failed');}); 
});
document.getElementById('resetSelected').addEventListener('click', function(){ const ids = Array.from(document.querySelectorAll('.chk:checked')).map(i=>parseInt(i.value,10)); if (!ids.length) { alert('Select at least one match to reset.'); return; } if (!confirm('Reset selected match(es)?')) return; Promise.all(ids.map(id => fetch('/basketball-admin/delete_match', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ match_id: id }) }).then(r=>r.json()).catch(()=>({success:false})) )).then(results=>{ alert('Reset completed'); location.reload(); }).catch(e=>{console.error(e); alert('Reset request failed'); }); });
try {
  const expBtn = document.getElementById('exportCsvBtn');
  if (expBtn) expBtn.addEventListener('click', function(){ const params = new URLSearchParams(location.search); params.set('export','csv'); location.href = '?' + params.toString(); });
  const perSel = document.getElementById('perPageSelect');
  if (perSel) perSel.addEventListener('change', function(){ const params = new URLSearchParams(location.search); params.set('per_page', this.value); params.set('page', 1); location.href = '?' + params.toString(); });
} catch (e) {}
</script>

<script>
(function(){
  var btn = document.getElementById('themeToggle');
  var stored = localStorage.getItem('theme-preference');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  var isDark = stored ? stored==='dark' : prefersDark;
  if (!isDark) document.body.classList.add('light-mode');
  btn.textContent = isDark ? '🌙' : '☀️';
  btn.addEventListener('click', function(){
    var isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('theme-preference', isLight ? 'light' : 'dark');
    btn.textContent = isLight ? '☀️' : '🌙';
  });
})();
</script>
</body>
</html>