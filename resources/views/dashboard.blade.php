@extends('layouts.landing')

@section('title', 'SportSync — Data-Driven Sports Analytics')

@section('main')
<!-- ══════════════════════════ SPORTS SELECTION ══════════════════════════ -->
<section class="sports-section" id="sports">
  <div class="section-container">
    <div class="section-header reveal">
      <span class="section-label">CHOOSE YOUR SPORT</span>
      <h2 class="section-title">Pick Your Arena</h2>
    </div>
    <div class="sports-grid">

      @php
        $user = Auth::user();
        // Treat superadmin (and legacy scorekeeper) as admin for admin UI access (case-insensitive)
        $isAdmin = false;
        if ($user) {
          $r = strtolower((string)($user->role ?? ''));
          $isAdmin = in_array($r, ['admin', 'superadmin', 'scorekeeper'], true);
        }
        $bbRoute = $isAdmin ? route('basketball.admin') : route('basketball.viewer');
        $bbNext = $user ? $bbRoute : route('login') . '?next=' . urlencode($bbRoute);
        $vbRoute = $isAdmin ? route('volleyball.admin') : route('volleyball.viewer');
        $vbNext = $user ? $vbRoute : route('login') . '?next=' . urlencode($vbRoute);
        $bdRoute = $isAdmin ? route('badminton.admin') : route('badminton.viewer');
        $bdNext = $user ? $bdRoute : route('login') . '?next=' . urlencode($bdRoute);
        $ttRoute = $isAdmin ? route('tabletennis.admin') : route('tabletennis.viewer');
        $ttNext = $user ? $ttRoute : route('login') . '?next=' . urlencode($ttRoute);
        $drRoute = route('darts.admin');
        $drNext = $user ? $drRoute : route('login') . '?next=' . urlencode($drRoute);

        $analyticsRoute = route('analytics');
        $analyticsNext = $user ? $analyticsRoute : route('login') . '?next=' . urlencode($analyticsRoute);
        $playersRoute = route('players');
        $playersNext = $user ? $playersRoute : route('login') . '?next=' . urlencode($playersRoute);
      @endphp

      <a href="{{ $bbNext }}" class="sport-card reveal" data-sport="basketball">
        <div class="sport-icon">🏀</div>
        <h3 class="sport-name">Basketball</h3>
        <p class="sport-desc">Track points, assists & rebounds live</p>
        <div class="sport-arrow">→</div>
      </a>

      <a href="{{ $vbNext }}" class="sport-card reveal" data-sport="volleyball">
        <div class="sport-icon">🏐</div>
        <h3 class="sport-name">Volleyball</h3>
        <p class="sport-desc">Set-by-set scoring & rally analytics</p>
        <div class="sport-arrow">→</div>
      </a>

      <a href="{{ $bdNext }}" class="sport-card reveal" data-sport="badminton">
        <div class="sport-icon">🏸</div>
        <h3 class="sport-name">Badminton</h3>
        <p class="sport-desc">Shuttle speed, rally depth & match stats</p>
        <div class="sport-arrow">→</div>
      </a>

      <a href="{{ $ttNext }}" class="sport-card reveal" data-sport="tabletennis">
        <div class="sport-icon">🏓</div>
        <h3 class="sport-name">Table Tennis</h3>
        <p class="sport-desc">Per-game breakdowns & spin analytics</p>
        <div class="sport-arrow">→</div>
      </a>

      <a href="{{ $drNext }}" class="sport-card reveal" data-sport="darts">
        <div class="sport-icon">🎯</div>
        <h3 class="sport-name">Darts</h3>
        <p class="sport-desc">Leg averages, finishes & checkout %</p>
        <div class="sport-arrow">→</div>
      </a>

    </div>
  </div>
</section>

<!-- ════════════════════════════ FEATURES ════════════════════════════════ -->
<section class="features-section" id="features">
  <div class="section-container">
    <div class="section-header reveal">
      <span class="section-label">CAPABILITIES</span>
      <h2 class="section-title">Powerful Features Built<br>for Competitors</h2>
    </div>
    <div class="features-grid">

      <div class="feature-card reveal">
        <div class="feature-icon">🏆</div>
        <h3 class="feature-title">Live Scoring</h3>
        <p class="feature-desc">Update scores in real-time during matches with instant broadcast to all connected devices.</p>
        <div class="feature-line"></div>
      </div>

      <a href="{{ $analyticsNext }}" class="feature-card reveal" data-feature="analytics">
        <div class="feature-icon">📊</div>
        <h3 class="feature-title">Analytics Dashboard</h3>
        <p class="feature-desc">Visual stats, trends, and performance charts that reveal patterns invisible to the naked eye.</p>
        <div class="feature-line"></div>
      </a>

      <div class="feature-card reveal">
        <div class="feature-icon">📋</div>
        <h3 class="feature-title">Match Reports</h3>
        <p class="feature-desc">Auto-generated post-match summaries delivered the moment the final buzzer sounds.</p>
        <div class="feature-line"></div>
      </div>

      <div class="feature-card reveal">
        <div class="feature-icon">📤</div>
        <h3 class="feature-title">Export Data</h3>
        <p class="feature-desc">Download results as PDF or Excel with one click — ready for coaches and sponsors.</p>
        <div class="feature-line"></div>
      </div>

      <a href="{{ $playersNext }}" class="feature-card reveal" data-feature="profiles">
        <div class="feature-icon">👤</div>
        <h3 class="feature-title">Player Profiles</h3>
        <p class="feature-desc">Track individual and team statistics across every match, tournament, and season.</p>
        <div class="feature-line"></div>
      </a>

      <div class="feature-card reveal">
        <div class="feature-icon">🗓️</div>
        <h3 class="feature-title">Tournament Bracket</h3>
        <p class="feature-desc">Manage schedules and brackets automatically — from group stage to grand finals.</p>
        <div class="feature-line"></div>
      </div>

    </div>
  </div>
</section>

<!-- ═══════════════════════════════ CTA BANNER ═══════════════════════════ -->
<section class="cta-section" id="about">
  <div class="cta-bg-lines"></div>
  <div class="cta-content reveal">
    <div class="cta-eyebrow">GET STARTED TODAY</div>
    @if($user)
      @php $role = strtolower((string)($user->role ?? '')); @endphp
      @if($role === 'superadmin')
        <h2 class="cta-title">Superadmin Console</h2>
        <p class="cta-sub">Access the legacy admin landing page and full system controls.</p>
        <a href="{{ route('legacy.adminlanding') }}" class="btn btn-cta">Open Admin Landing</a>
      @elseif(in_array($role, ['admin','scorekeeper']))
        <h2 class="cta-title">SportSync Admin Dashboard</h2>
        <p class="cta-sub">Open your dashboard to start scoring and analyzing matches.</p>
        <a href="#sports" class="btn btn-cta">Start Scoring Now</a>
      @elseif($role === 'viewer')
        <h2 class="cta-title">SportSync — Stay Updated. Stay Ahead.</h2>
        <p class="cta-sub">Explore live scores and analytics for your favorite sports.</p>
      <a href="#sports" class="btn btn-cta">Open Viewer</a>
      @else
        <a href="#sports" class="btn btn-cta">Open</a>
      @endif
    @else
      <a href="{{ route('register') }}" class="btn btn-cta">Start Scoring Now</a>
    @endif
  </div>
</section>



@endsection
