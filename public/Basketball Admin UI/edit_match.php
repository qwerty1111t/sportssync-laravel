<?php
// ============================================================
// edit_match.php — Edit Match Details
// Usage: edit_match.php?match_id=N
// ============================================================

$_base = __DIR__;
if (!file_exists($_base . '/auth.php') && file_exists($_base . '/../auth.php')) {
    $_base = realpath(__DIR__ . '/..');
}
require_once $_base . '/auth.php';
require_once $_base . '/db.php';
$user = requireRole('admin');

$matchId = isset($_GET['match_id']) ? (int) $_GET['match_id'] : 0;

if ($matchId <= 0) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><body style="background:#111;color:#f00;font-family:monospace;padding:40px">Invalid or missing match_id parameter.</body></html>';
    exit;
}

// Fetch match
$stmtMatch = $pdo->prepare('SELECT * FROM `matches` WHERE match_id = :id LIMIT 1');
$stmtMatch->execute([':id' => $matchId]);
$match = $stmtMatch->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="background:#111;color:#f00;font-family:monospace;padding:40px">Match ID ' . htmlspecialchars((string)$matchId) . ' not found.</body></html>';
    exit;
}

// Fetch all players
$stmtPlayers = $pdo->prepare('SELECT * FROM `match_players` WHERE match_id = :id ORDER BY team ASC, jersey_no ASC');
$stmtPlayers->execute([':id' => $matchId]);
$allPlayers = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

// Split by team
$playersA = array_values(array_filter($allPlayers, fn($p) => $p['team'] === 'A'));
$playersB = array_values(array_filter($allPlayers, fn($p) => $p['team'] === 'B'));

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update match header
        $teamAName = trim($_POST['team_a_name'] ?? '');
        $teamBName = trim($_POST['team_b_name'] ?? '');
        $teamAScore = (int)($_POST['team_a_score'] ?? 0);
        $teamBScore = (int)($_POST['team_b_score'] ?? 0);
        $teamAQuarter = (int)($_POST['team_a_quarter'] ?? 1);
        $teamBQuarter = (int)($_POST['team_b_quarter'] ?? 1);
        $teamAFoul = (int)($_POST['team_a_foul'] ?? 0);
        $teamBFoul = (int)($_POST['team_b_foul'] ?? 0);
        $teamATimeout = (int)($_POST['team_a_timeout'] ?? 0);
        $teamBTimeout = (int)($_POST['team_b_timeout'] ?? 0);
        $committee = trim($_POST['committee'] ?? '');
        $matchResult = trim($_POST['match_result'] ?? '');

        // Validate inputs
        if (empty($teamAName) || empty($teamBName)) {
            throw new Exception('Team names cannot be empty');
        }

        // Update match
        $stmtUpdate = $pdo->prepare('
            UPDATE `matches` SET
                team_a_name = :team_a_name,
                team_b_name = :team_b_name,
                team_a_score = :team_a_score,
                team_b_score = :team_b_score,
                team_a_quarter = :team_a_quarter,
                team_b_quarter = :team_b_quarter,
                team_a_foul = :team_a_foul,
                team_b_foul = :team_b_foul,
                team_a_timeout = :team_a_timeout,
                team_b_timeout = :team_b_timeout,
                committee = :committee,
                match_result = :match_result
            WHERE match_id = :match_id
        ');
        $stmtUpdate->execute([
            ':team_a_name' => $teamAName,
            ':team_b_name' => $teamBName,
            ':team_a_score' => $teamAScore,
            ':team_b_score' => $teamBScore,
            ':team_a_quarter' => $teamAQuarter,
            ':team_b_quarter' => $teamBQuarter,
            ':team_a_foul' => $teamAFoul,
            ':team_b_foul' => $teamBFoul,
            ':team_a_timeout' => $teamATimeout,
            ':team_b_timeout' => $teamBTimeout,
            ':committee' => $committee,
            ':match_result' => $matchResult,
            ':match_id' => $matchId,
        ]);

        // Update player data and stats
        foreach ($playersA as $p) {
            $playerId = $p['player_id'];
            $playerName = trim($_POST["player_name_$playerId"] ?? '');
            $jerseyNo = trim($_POST["jersey_no_$playerId"] ?? '');
            $position = trim($_POST["position_$playerId"] ?? '');
            $pts = (int)($_POST["pts_$playerId"] ?? 0);
            $foul = (int)($_POST["foul_$playerId"] ?? 0);
            $reb = (int)($_POST["reb_$playerId"] ?? 0);
            $ast = (int)($_POST["ast_$playerId"] ?? 0);
            $blk = (int)($_POST["blk_$playerId"] ?? 0);
            $stl = (int)($_POST["stl_$playerId"] ?? 0);
            $techFoul = (int)($_POST["tech_foul_$playerId"] ?? 0);
            $techReason = trim($_POST["tech_reason_$playerId"] ?? '');

            $stmtPlayer = $pdo->prepare('
                UPDATE `match_players` SET
                    player_name = :player_name,
                    jersey_no = :jersey_no,
                    position = :position,
                    pts = :pts,
                    foul = :foul,
                    reb = :reb,
                    ast = :ast,
                    blk = :blk,
                    stl = :stl,
                    tech_foul = :tech_foul,
                    tech_reason = :tech_reason
                WHERE player_id = :player_id AND match_id = :match_id
            ');
            $stmtPlayer->execute([
                ':player_name' => $playerName,
                ':jersey_no' => $jerseyNo,
                ':position' => $position,
                ':pts' => $pts,
                ':foul' => $foul,
                ':reb' => $reb,
                ':ast' => $ast,
                ':blk' => $blk,
                ':stl' => $stl,
                ':tech_foul' => $techFoul,
                ':tech_reason' => $techReason,
                ':player_id' => $playerId,
                ':match_id' => $matchId,
            ]);
        }

        foreach ($playersB as $p) {
            $playerId = $p['player_id'];
            $playerName = trim($_POST["player_name_$playerId"] ?? '');
            $jerseyNo = trim($_POST["jersey_no_$playerId"] ?? '');
            $position = trim($_POST["position_$playerId"] ?? '');
            $pts = (int)($_POST["pts_$playerId"] ?? 0);
            $foul = (int)($_POST["foul_$playerId"] ?? 0);
            $reb = (int)($_POST["reb_$playerId"] ?? 0);
            $ast = (int)($_POST["ast_$playerId"] ?? 0);
            $blk = (int)($_POST["blk_$playerId"] ?? 0);
            $stl = (int)($_POST["stl_$playerId"] ?? 0);
            $techFoul = (int)($_POST["tech_foul_$playerId"] ?? 0);
            $techReason = trim($_POST["tech_reason_$playerId"] ?? '');

            $stmtPlayer = $pdo->prepare('
                UPDATE `match_players` SET
                    player_name = :player_name,
                    jersey_no = :jersey_no,
                    position = :position,
                    pts = :pts,
                    foul = :foul,
                    reb = :reb,
                    ast = :ast,
                    blk = :blk,
                    stl = :stl,
                    tech_foul = :tech_foul,
                    tech_reason = :tech_reason
                WHERE player_id = :player_id AND match_id = :match_id
            ');
            $stmtPlayer->execute([
                ':player_name' => $playerName,
                ':jersey_no' => $jerseyNo,
                ':position' => $position,
                ':pts' => $pts,
                ':foul' => $foul,
                ':reb' => $reb,
                ':ast' => $ast,
                ':blk' => $blk,
                ':stl' => $stl,
                ':tech_foul' => $techFoul,
                ':tech_reason' => $techReason,
                ':player_id' => $playerId,
                ':match_id' => $matchId,
            ]);
        }

        $message = 'Match updated successfully!';
        $messageType = 'success';

        // Refresh match data
        $stmtMatch->execute([':id' => $matchId]);
        $match = $stmtMatch->fetch(PDO::FETCH_ASSOC);
        $stmtPlayers->execute([':id' => $matchId]);
        $allPlayers = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);
        $playersA = array_values(array_filter($allPlayers, fn($p) => $p['team'] === 'A'));
        $playersB = array_values(array_filter($allPlayers, fn($p) => $p['team'] === 'B'));

    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Match #<?= $matchId ?> — Basketball Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Barlow+Condensed:wght@400;500;600&display=swap">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0a0a0a;
    --surface: #141414;
    --surface2: #1f1f1f;
    --surface3: #0f0f0f;
    --border: #333333;
    --text: #ffffff;
    --text-muted: #a0a0a0;
    --yellow: #F5C518;
    --green: #27ae60;
    --blue: #4a7cc7;
    --red: #c0392b;
  }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 14px;
    min-height: 100vh;
    padding-bottom: 60px;
  }
  .edit-header {
    background: #0a0a0a;
    border-bottom: 1.5px solid #333333;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 50;
  }
  .edit-header h1 {
    font-family: 'Oswald', sans-serif;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--yellow);
  }
  .edit-header .header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
  }
  .btn {
    border: none;
    cursor: pointer;
    font-family: 'Oswald', sans-serif;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    padding: 10px 20px;
    border-radius: 5px;
    text-transform: uppercase;
    transition: filter 0.15s, transform 0.1s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn:hover  { filter: brightness(1.15); }
  .btn:active { transform: scale(0.96); }
  .btn-save { background: var(--green); color: #fff; }
  .btn-back { background: transparent; color: var(--yellow); border: 1px solid var(--yellow); }

  .edit-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 24px;
  }

  .message {
    padding: 16px 20px;
    border-radius: 6px;
    margin-bottom: 24px;
    font-family: 'Oswald', sans-serif;
    font-weight: 600;
    letter-spacing: 1px;
  }
  .message.success {
    background: rgba(39,174,96,0.2);
    color: #5fd085;
    border: 1.5px solid rgba(39,174,96,0.5);
  }
  .message.error {
    background: rgba(192,57,43,0.2);
    color: #ff8080;
    border: 1.5px solid rgba(192,57,43,0.5);
  }

  .form-section {
    background: #141414;
    border: 1.5px solid #333333;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
  }

  .form-section h2 {
    font-family: 'Oswald', sans-serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--yellow);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #333333;
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .form-group label {
    font-family: 'Oswald', sans-serif;
    font-size: 11px;
    letter-spacing: 1.5px;
    color: #c0c0c0;
    text-transform: uppercase;
    font-weight: 600;
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    background: #0f0f0f;
    color: #ffffff;
    border: 1.5px solid #333333;
    border-radius: 4px;
    padding: 10px 12px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 14px;
    transition: border-color 0.15s;
  }

  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus {
    outline: none;
    border-color: var(--yellow);
    box-shadow: 0 0 8px rgba(245,197,24,0.25);
  }

  .form-group input[type="number"] {
    width: 100%;
  }

  .form-group textarea {
    resize: vertical;
    min-height: 60px;
    font-family: 'Barlow Condensed', sans-serif;
  }

  .player-row {
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #1f1f1f;
    padding: 18px 16px;
    border-radius: 6px;
    margin-bottom: 14px;
    border: 1.5px solid #333333;
    transition: all 0.25s ease;
  }

  .player-row.hidden {
    display: none;
  }

  .player-info-row {
    display: grid;
    grid-template-columns: 1fr 100px 120px;
    gap: 12px;
  }

  .player-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 12px;
  }

  .player-row:hover {
    background: #252525;
    border-color: rgba(245, 197, 24, 0.5);
    box-shadow: 0 4px 12px rgba(245, 197, 24, 0.1);
  }

  .player-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
  }

  .player-input-group label {
    font-family: 'Oswald', sans-serif;
    font-size: 10px;
    letter-spacing: 1.5px;
    color: #c0c0c0;
    text-transform: uppercase;
    font-weight: 600;
    line-height: 1.2;
    min-height: 12px;
  }

  .player-row input,
  .player-row textarea {
    width: 100%;
    text-align: center;
    background: #0f0f0f;
    color: #ffffff;
    border: 1.5px solid #333333;
    border-radius: 4px;
    padding: 10px 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    min-height: 38px;
    box-sizing: border-box;
  }

  .player-info-row .player-input-group:first-child input {
    text-align: left;
  }

  .player-row input:hover,
  .player-row textarea:hover {
    border-color: rgba(245, 197, 24, 0.6);
    background: #1a1a1a;
  }

  .player-row input:focus,
  .player-row textarea:focus {
    outline: none;
    border-color: var(--yellow);
    background: rgba(245, 197, 24, 0.08);
    box-shadow: 0 0 10px rgba(245, 197, 24, 0.25);
  }

  .player-row input::placeholder,
  .player-row textarea::placeholder {
    color: var(--text-dim);
    opacity: 0.7;
  }

  .player-row-label {
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
    font-size: 12px;
  }

  .player-row-tech {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 12px;
    align-items: center;
    background: var(--surface3);
    padding: 10px;
    border-radius: 3px;
    margin-top: 8px;
  }

  .player-row-tech label {
    font-size: 10px;
    color: var(--text-muted);
    letter-spacing: 1px;
    text-transform: uppercase;
  }

  .player-row-tech input {
    width: 100%;
  }

  .team-section {
    margin-bottom: 32px;
  }

  .team-section h3 {
    font-family: 'Oswald', sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #b0b0b0;
    margin-bottom: 12px;
    padding: 8px 0;
    border-bottom: 1.5px solid #333333;
  }

  .team-filter-buttons {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }

  .team-filter-btn {
    border: 2px solid #404040;
    background: transparent;
    color: #b0b0b0;
    cursor: pointer;
    padding: 10px 20px;
    border-radius: 5px;
    font-family: 'Oswald', sans-serif;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.2s ease;
  }

  .team-filter-btn:hover {
    border-color: rgba(245, 197, 24, 0.7);
    color: #ffffff;
  }

  .team-filter-btn.active {
    background: var(--yellow);
    color: #000000;
    border-color: var(--yellow);
  }

  .pagination {
    display: flex;
    gap: 8px;
    margin-top: 20px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
  }

  .pagination button {
    border: 1.5px solid #404040;
    background: transparent;
    color: #b0b0b0;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 12px;
    transition: all 0.2s ease;
  }

  .pagination button:hover {
    border-color: rgba(245, 197, 24, 0.7);
    color: #ffffff;
  }

  .pagination button.active {
    background: var(--yellow);
    color: #000000;
    border-color: var(--yellow);
  }

  .pagination button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
  }

  .pagination-info {
    color: #b0b0b0;
    font-size: 12px;
    min-width: 150px;
    text-align: center;
  }

  @media (max-width: 768px) {
    .edit-header {
      flex-direction: column;
      gap: 12px;
      align-items: flex-start;
    }
    .edit-header .header-actions {
      width: 100%;
      justify-content: flex-end;
    }
    .form-grid {
      grid-template-columns: 1fr;
    }
    .player-info-row {
      grid-template-columns: 1fr !important;
    }
    .player-stats-row {
      grid-template-columns: repeat(2, 1fr) !important;
    }
  }
</style>
</head>
<body>

<div class="edit-header">
  <h1>Edit Match #<?= $matchId ?></h1>
  <div class="header-actions">
    <button type="button" class="btn btn-back" onclick="if(confirm('Discard changes and go back?')) window.history.back()">← Back</button>
  </div>
</div>

<div class="edit-page">

  <?php if ($message): ?>
  <div class="message <?= $messageType ?>">
    <?= h($message) ?>
  </div>
  <?php endif; ?>

  <form method="POST">
    <!-- MATCH HEADER SECTION -->
    <div class="form-section">
      <h2>Match Info</h2>
      <div class="form-grid">
        <div class="form-group">
          <label>Team A Name</label>
          <input type="text" name="team_a_name" value="<?= h($match['team_a_name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Team B Name</label>
          <input type="text" name="team_b_name" value="<?= h($match['team_b_name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Committee / Official</label>
          <input type="text" name="committee" value="<?= h($match['committee'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- SCORE & GAME STATUS SECTION -->
    <div class="form-section">
      <h2>Score & Game Status</h2>
      <div class="form-grid">
        <div class="form-group">
          <label>Team A Score</label>
          <input type="number" name="team_a_score" value="<?= (int)$match['team_a_score'] ?>" min="0" required>
        </div>
        <div class="form-group">
          <label>Team B Score</label>
          <input type="number" name="team_b_score" value="<?= (int)$match['team_b_score'] ?>" min="0" required>
        </div>
        <div class="form-group">
          <label>Team A Quarter</label>
          <input type="number" name="team_a_quarter" value="<?= (int)$match['team_a_quarter'] ?>" min="1" max="4" required>
        </div>
        <div class="form-group">
          <label>Team B Quarter</label>
          <input type="number" name="team_b_quarter" value="<?= (int)$match['team_b_quarter'] ?>" min="1" max="4" required>
        </div>
        <div class="form-group">
          <label>Team A Fouls</label>
          <input type="number" name="team_a_foul" value="<?= (int)$match['team_a_foul'] ?>" min="0" required>
        </div>
        <div class="form-group">
          <label>Team B Fouls</label>
          <input type="number" name="team_b_foul" value="<?= (int)$match['team_b_foul'] ?>" min="0" required>
        </div>
        <div class="form-group">
          <label>Team A Timeouts</label>
          <input type="number" name="team_a_timeout" value="<?= (int)$match['team_a_timeout'] ?>" min="0" required>
        </div>
        <div class="form-group">
          <label>Team B Timeouts</label>
          <input type="number" name="team_b_timeout" value="<?= (int)$match['team_b_timeout'] ?>" min="0" required>
        </div>
        <div class="form-group">
          <label>Match Result</label>
          <select name="match_result" required>
            <option value="TEAM A WINS" <?= $match['match_result'] === 'TEAM A WINS' ? 'selected' : '' ?>>Team A Wins</option>
            <option value="TEAM B WINS" <?= $match['match_result'] === 'TEAM B WINS' ? 'selected' : '' ?>>Team B Wins</option>
            <option value="DRAW" <?= $match['match_result'] === 'DRAW' ? 'selected' : '' ?>>Draw</option>
          </select>
        </div>
      </div>
    </div>

    <!-- TEAM A PLAYERS SECTION -->
    <?php if (!empty($playersA) || !empty($playersB)): ?>
    <div class="form-section">
      <h2>Player Stats</h2>

      <!-- TEAM FILTER BUTTONS -->
      <div class="team-filter-buttons">
        <button type="button" class="team-filter-btn active" data-team="all" onclick="filterTeam('all', event)">
          ✓ All Players
        </button>
        <button type="button" class="team-filter-btn" data-team="a" onclick="filterTeam('a', event)">
          Team A Only
        </button>
        <button type="button" class="team-filter-btn" data-team="b" onclick="filterTeam('b', event)">
          Team B Only
        </button>
      </div>

      <?php if (!empty($playersA)): ?>
      <div class="team-section" data-team-section="a">
        <h3><?= h($match['team_a_name']) ?> — Players</h3>
        <div data-pagination-container="a"></div>
        <?php foreach ($playersA as $idx => $p): ?>
        <div class="player-row" data-team="a" data-player-id="<?= $p['player_id'] ?>" data-page-a="<?= intdiv($idx, 5) ?>">
          <!-- Player Info Row -->
          <div class="player-info-row">
            <div class="player-input-group">
              <label>Player Name</label>
              <input type="text" name="player_name_<?= $p['player_id'] ?>" value="<?= h($p['player_name'] ?? '') ?>" placeholder="Enter player name">
            </div>
            <div class="player-input-group">
              <label>Jersey</label>
              <input type="text" name="jersey_no_<?= $p['player_id'] ?>" value="<?= h($p['jersey_no'] ?? '') ?>" placeholder="No." maxlength="3">
            </div>
            <div class="player-input-group">
              <label>Position</label>
              <input type="text" name="position_<?= $p['player_id'] ?>" value="<?= h($p['position'] ?? '') ?>" placeholder="e.g., PG" maxlength="5">
            </div>
          </div>

          <!-- Player Stats Row -->
          <div class="player-stats-row">
            <div class="player-input-group">
              <label>PTS</label>
              <input type="number" name="pts_<?= $p['player_id'] ?>" value="<?= (int)$p['pts'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>REB</label>
              <input type="number" name="reb_<?= $p['player_id'] ?>" value="<?= (int)$p['reb'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>AST</label>
              <input type="number" name="ast_<?= $p['player_id'] ?>" value="<?= (int)$p['ast'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>BLK</label>
              <input type="number" name="blk_<?= $p['player_id'] ?>" value="<?= (int)$p['blk'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>STL</label>
              <input type="number" name="stl_<?= $p['player_id'] ?>" value="<?= (int)$p['stl'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>FOUL</label>
              <input type="number" name="foul_<?= $p['player_id'] ?>" value="<?= (int)$p['foul'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>Tech Foul</label>
              <input type="number" name="tech_foul_<?= $p['player_id'] ?>" value="<?= (int)$p['tech_foul'] ?>" min="0" placeholder="0">
            </div>
            <div class="player-input-group">
              <label>TF Reason</label>
              <input type="text" name="tech_reason_<?= $p['player_id'] ?>" value="<?= h($p['tech_reason'] ?? '') ?>" placeholder="e.g., Unsportsmanlike">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($playersB)): ?>
      <div class="team-section" data-team-section="b">
        <h3><?= h($match['team_b_name']) ?> — Players</h3>
        <div data-pagination-container="b"></div>
        <?php foreach ($playersB as $idx => $p): ?>
        <div class="player-row" data-team="b" data-player-id="<?= $p['player_id'] ?>" data-page-b="<?= intdiv($idx, 5) ?>">
          <!-- Player Info Row -->
          <div class="player-info-row">
            <div class="player-input-group">
              <label>Player Name</label>
              <input type="text" name="player_name_<?= $p['player_id'] ?>" value="<?= h($p['player_name'] ?? '') ?>" placeholder="Enter player name">
            </div>
            <div class="player-input-group">
              <label>Jersey</label>
              <input type="text" name="jersey_no_<?= $p['player_id'] ?>" value="<?= h($p['jersey_no'] ?? '') ?>" placeholder="No." maxlength="3">
            </div>
            <div class="player-input-group">
              <label>Position</label>
              <input type="text" name="position_<?= $p['player_id'] ?>" value="<?= h($p['position'] ?? '') ?>" placeholder="e.g., PG" maxlength="5">
            </div>
          </div>

          <!-- Player Stats Row -->
          <div class="player-stats-row">
            <div class="player-input-group">
              <label>PTS</label>
              <input type="number" name="pts_<?= $p['player_id'] ?>" value="<?= (int)$p['pts'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>REB</label>
              <input type="number" name="reb_<?= $p['player_id'] ?>" value="<?= (int)$p['reb'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>AST</label>
              <input type="number" name="ast_<?= $p['player_id'] ?>" value="<?= (int)$p['ast'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>BLK</label>
              <input type="number" name="blk_<?= $p['player_id'] ?>" value="<?= (int)$p['blk'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>STL</label>
              <input type="number" name="stl_<?= $p['player_id'] ?>" value="<?= (int)$p['stl'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>FOUL</label>
              <input type="number" name="foul_<?= $p['player_id'] ?>" value="<?= (int)$p['foul'] ?>" min="0">
            </div>
            <div class="player-input-group">
              <label>Tech Foul</label>
              <input type="number" name="tech_foul_<?= $p['player_id'] ?>" value="<?= (int)$p['tech_foul'] ?>" min="0" placeholder="0">
            </div>
            <div class="player-input-group">
              <label>TF Reason</label>
              <input type="text" name="tech_reason_<?= $p['player_id'] ?>" value="<?= h($p['tech_reason'] ?? '') ?>" placeholder="e.g., Unsportsmanlike">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- FORM ACTIONS -->
    <div style="display: flex; gap: 12px; justify-content: flex-end;">
      <button type="button" class="btn btn-back" onclick="if(confirm('Discard changes and go back?')) window.history.back()">Cancel</button>
      <button type="submit" class="btn btn-save">✓ Save Changes</button>
    </div>
  </form>

</div><!-- /edit-page -->

<script>
const PLAYERS_PER_PAGE = 5;
let currentTeamFilter = 'all';
let currentPageA = 0;
let currentPageB = 0;

function getPlayersForTeam(team) {
  return document.querySelectorAll(`.player-row[data-team="${team}"]`);
}

function getTotalPagesForTeam(team) {
  const players = getPlayersForTeam(team);
  return Math.ceil(players.length / PLAYERS_PER_PAGE);
}

function filterTeam(team, event) {
  if (event) event.preventDefault();
  currentTeamFilter = team;
  currentPageA = 0;
  currentPageB = 0;

  // Update button states
  document.querySelectorAll('.team-filter-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  document.querySelector(`[data-team="${team}"]`).classList.add('active');

  // Show/hide teams and sections
  const teamASections = document.querySelectorAll('[data-team-section="a"]');
  const teamBSections = document.querySelectorAll('[data-team-section="b"]');
  const teamARows = getPlayersForTeam('a');
  const teamBRows = getPlayersForTeam('b');

  if (team === 'all') {
    teamASections.forEach(s => s.style.display = '');
    teamBSections.forEach(s => s.style.display = '');
    teamARows.forEach(row => row.classList.remove('hidden'));
    teamBRows.forEach(row => row.classList.remove('hidden'));
  } else if (team === 'a') {
    teamASections.forEach(s => s.style.display = '');
    teamBSections.forEach(s => s.style.display = 'none');
    teamARows.forEach(row => row.classList.remove('hidden'));
    teamBRows.forEach(row => row.classList.add('hidden'));
  } else if (team === 'b') {
    teamASections.forEach(s => s.style.display = 'none');
    teamBSections.forEach(s => s.style.display = '');
    teamARows.forEach(row => row.classList.add('hidden'));
    teamBRows.forEach(row => row.classList.remove('hidden'));
  }

  updatePaginationDisplay();
}

function updatePaginationDisplay() {
  const teamA = currentTeamFilter === 'all' || currentTeamFilter === 'a';
  const teamB = currentTeamFilter === 'all' || currentTeamFilter === 'b';

  if (teamA) {
    showPageForTeam('a', currentPageA);
  }
  if (teamB) {
    showPageForTeam('b', currentPageB);
  }
}

function showPageForTeam(team, pageNum) {
  if (team === 'a') currentPageA = pageNum;
  if (team === 'b') currentPageB = pageNum;

  const players = getPlayersForTeam(team);
  const totalPages = getTotalPagesForTeam(team);

  // Hide all players for this team
  players.forEach(p => p.style.display = 'none');

  // Show only players on the current page
  const start = pageNum * PLAYERS_PER_PAGE;
  const end = start + PLAYERS_PER_PAGE;
  for (let i = start; i < end && i < players.length; i++) {
    players[i].style.display = '';
  }

  // Rebuild pagination buttons
  const container = document.querySelector(`[data-pagination-container="${team}"]`);
  container.innerHTML = '';

  if (totalPages > 1) {
    const paginationDiv = document.createElement('div');
    paginationDiv.className = 'pagination';

    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.textContent = '← Prev';
    prevBtn.disabled = pageNum === 0;
    prevBtn.onclick = (e) => {
      e.preventDefault();
      if (pageNum > 0) showPageForTeam(team, pageNum - 1);
    };
    paginationDiv.appendChild(prevBtn);

    // Page info
    const info = document.createElement('span');
    info.className = 'pagination-info';
    info.textContent = `Page ${pageNum + 1} of ${totalPages}`;
    paginationDiv.appendChild(info);

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.textContent = 'Next →';
    nextBtn.disabled = pageNum >= totalPages - 1;
    nextBtn.onclick = (e) => {
      e.preventDefault();
      if (pageNum < totalPages - 1) showPageForTeam(team, pageNum + 1);
    };
    paginationDiv.appendChild(nextBtn);

    container.appendChild(paginationDiv);
  }
}

// Initialize pagination on page load
window.addEventListener('DOMContentLoaded', function() {
  if (document.querySelectorAll('[data-team="a"]').length > 0) {
    showPageForTeam('a', 0);
  }
  if (document.querySelectorAll('[data-team="b"]').length > 0) {
    showPageForTeam('b', 0);
  }
});
</script>

</body>
</html>
