<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Darts Admin (Legacy)</title>
  <script>window.LEGACY_BASE_PATH = '/darts-admin/';</script>
  <style>
    :root {
      --bg: #111;
      --card-bg: #1a1a1a;
      --text: #f0f0f0;
      --subtext: #aaa;
      --border: #333;
      --yellow: #FFE600;
      --green: #00cc44;
      --red: #CC0000;
      --blue: #003399;
      --orange: #E65C00;
      --input-bg: #222;
    }
    .light-mode {
      --bg: #f4f4f4;
      --card-bg: #ffffff;
      --text: #111;
      --subtext: #555;
      --border: #ccc;
      --input-bg: #eee;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); font-family: Arial, Helvetica, sans-serif; min-height: 100vh; transition: background .2s, color .2s; }

    /* NAV */
    #nav { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background: #000; border-bottom: 2px solid var(--yellow); flex-wrap: wrap; gap: 8px; }
    #nav h1 { color: var(--yellow); font-size: 1.3rem; letter-spacing: 2px; }
    #nav-btns { display: flex; gap: 8px; flex-wrap: wrap; }
    .nav-btn { background: #222; color: var(--yellow); border: 1px solid var(--yellow); padding: 6px 14px; cursor: pointer; font-weight: bold; font-size: .85rem; border-radius: 3px; }
    .nav-btn:hover { background: var(--yellow); color: #000; }

    /* SETTINGS BAR */
    #settings { background: #0a0a0a; border-bottom: 1px solid #333; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
    .light-mode #settings { background: #e8e8e8; }
    .setting-group { display: flex; align-items: center; gap: 6px; font-size: .85rem; color: var(--subtext); }
    .setting-group label { font-weight: bold; color: var(--text); }
    .seg-btn { background: #222; color: #aaa; border: 1px solid #444; padding: 5px 12px; cursor: pointer; font-size: .82rem; font-weight: bold; }
    .light-mode .seg-btn { background: #ddd; color: #555; border-color: #bbb; }
    .seg-btn.active { background: var(--yellow); color: #000; border-color: var(--yellow); }
    .seg-btn:first-child { border-radius: 3px 0 0 3px; }
    .seg-btn:last-child { border-radius: 0 3px 3px 0; }
    #legs-to-win-input { width: 48px; background: var(--input-bg); color: var(--text); border: 1px solid #555; padding: 5px; text-align: center; font-size: .9rem; font-weight: bold; border-radius: 3px; }
    .toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; }
    .toggle-switch input { display: none; }
    .toggle-slider { position: absolute; inset: 0; background: #444; border-radius: 22px; cursor: pointer; transition: .2s; }
    .toggle-slider:before { content:''; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
    input:checked + .toggle-slider { background: var(--yellow); }
    input:checked + .toggle-slider:before { transform: translateX(20px); background: #000; }

    /* PLAYER CARDS AREA */
    #cards-area { display: flex; gap: 10px; padding: 12px; flex-wrap: wrap; }
    .player-card { flex: 1 1 200px; min-width: 160px; background: var(--card-bg); border: 2px solid #333; border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; position: relative; cursor: pointer; transition: box-shadow .15s; }
    .player-card.active-card { box-shadow: 0 0 0 3px #FFE600, 0 0 18px #FFE60088; border-color: var(--yellow); }
    .card-header { padding: 8px 10px; display: flex; justify-content: space-between; align-items: flex-start; }
    .player-names { flex: 1; }
    .player-name-edit { font-size: 1rem; font-weight: bold; color: #fff; background: transparent; border: none; outline: none; width: 100%; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; }
    .player-name-edit:focus { border-bottom: 1px dashed rgba(255,255,255,.5); cursor: text; }
    .team-name-edit { font-size: .75rem; color: rgba(255,255,255,.7); background: transparent; border: none; outline: none; width: 100%; cursor: pointer; margin-top: 2px; }
    .team-name-edit:focus { border-bottom: 1px dashed rgba(255,255,255,.4); cursor: text; }
    .save-checkbox-wrap { display: flex; align-items: center; gap: 4px; font-size: .7rem; color: rgba(255,255,255,.7); white-space: nowrap; }
    .save-checkbox-wrap input[type=checkbox] { accent-color: var(--yellow); width: 14px; height: 14px; cursor: pointer; }

    /* Score display */
    .score-area { background: #0d0d0d; text-align: center; padding: 14px 8px; }
    .score-number { font-size: 3rem; font-weight: bold; color: var(--yellow); letter-spacing: 2px; font-variant-numeric: tabular-nums; line-height: 1; }
    .score-label { font-size: .65rem; color: #666; margin-top: 3px; text-transform: uppercase; letter-spacing: 1px; }

    /* Leg won */
    .leg-won-area { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-top: 1px solid #2a2a2a; }
    .leg-won-label { font-size: .65rem; color: var(--subtext); text-transform: uppercase; letter-spacing: 1px; }
    .leg-won-counter { display: flex; align-items: center; gap: 6px; }
    .leg-won-count { font-size: 1.4rem; font-weight: bold; color: var(--yellow); border: 2px solid var(--yellow); min-width: 38px; text-align: center; padding: 2px 6px; border-radius: 3px; }
    .lw-btn { width: 26px; height: 26px; border: none; border-radius: 3px; font-size: 1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; }
    .lw-plus { background: var(--green); color: #fff; }
    .lw-minus { background: var(--red); color: #fff; }

    /* Last throws chips */
    .last-throws-area { padding: 6px 10px 10px; display: flex; gap: 5px; flex-wrap: wrap; min-height: 36px; }
    .throw-chip { background: var(--yellow); color: #000; font-weight: bold; font-size: .8rem; padding: 3px 8px; border-radius: 3px; }
    .throw-chip.bust { background: var(--red); color: #fff; }

    /* INPUT PANEL */
    #input-panel { padding: 14px 16px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
    #input-panel h2 { font-size: .8rem; color: var(--subtext); letter-spacing: 2px; text-transform: uppercase; }
    #throw-input-row { display: flex; align-items: center; gap: 8px; }
    #throw-display { font-size: 2rem; font-weight: bold; color: var(--yellow); background: #000; border: 2px solid #444; padding: 8px 20px; min-width: 120px; text-align: center; border-radius: 4px; letter-spacing: 4px; }
    .arrow-btn { background: #222; color: var(--yellow); border: 2px solid #555; width: 44px; height: 44px; font-size: 1.3rem; cursor: pointer; border-radius: 4px; font-weight: bold; display: flex; align-items: center; justify-content: center; }
    .arrow-btn:hover { background: #333; }
    .arrow-btn:disabled { opacity: .3; cursor: default; }
    #numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7px; max-width: 220px; width: 100%; }
    .num-btn { height: 56px; font-size: 1.1rem; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; transition: filter .1s; user-select: none; -webkit-tap-highlight-color: transparent; }
    .num-btn:active { filter: brightness(1.3); }
    .num-digit { background: var(--red); color: #fff; }
    .num-clear { background: #555; color: #fff; }
    .num-enter { background: var(--green); color: #fff; font-size: .9rem; }

    /* Two-sided layout */
    #cards-area.two-sided { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    #cards-area.two-sided .side-group { display: flex; flex-direction: column; gap: 10px; }

    /* MODAL */
    #modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 100; align-items: center; justify-content: center; }
    #modal-overlay.show { display: flex; }
    #modal-box { background: #1a1a1a; border: 2px solid var(--yellow); border-radius: 8px; padding: 30px; max-width: 400px; width: 90%; text-align: center; }
    #modal-box h2 { color: var(--yellow); font-size: 1.5rem; margin-bottom: 10px; }
    #modal-box p { color: #ccc; margin-bottom: 20px; }
    .modal-btn { background: var(--yellow); color: #000; border: none; padding: 12px 24px; font-size: 1rem; font-weight: bold; cursor: pointer; border-radius: 4px; margin: 5px; }
    .modal-btn.secondary { background: #333; color: var(--yellow); border: 1px solid var(--yellow); }
    .modal-btn:hover { filter: brightness(1.1); }

    /* TOAST */
    #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #222; color: var(--yellow); border: 1px solid var(--yellow); padding: 10px 24px; border-radius: 4px; font-weight: bold; display: none; z-index: 200; font-size: .9rem; }
    #toast.show { display: block; animation: fadeInOut 2.5s forwards; }
    @keyframes fadeInOut { 0%{opacity:0} 10%{opacity:1} 70%{opacity:1} 100%{opacity:0} }

    /* Responsive */
    @media (max-width: 768px) {
      #cards-area { flex-direction: row; flex-wrap: wrap; }
      .player-card { flex: 1 1 calc(50% - 10px); min-width: 140px; }
      #cards-area.two-sided { grid-template-columns: 1fr; }
      .score-number { font-size: 2.4rem; }
      #numpad { max-width: 200px; }
    }
  </style>
</head>
<body>
  {!! $legacy_html !!}
  <script src="{{ asset('DARTS ADMIN UI/darst_admin.js') }}"></script>
</body>
</html>
