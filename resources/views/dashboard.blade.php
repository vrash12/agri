@extends('layouts.app')

@section('title', 'Dashboard')

@php
  $stats = $stats ?? [
    'total_farmers' => 0,
    'total_distribution_records' => 0,
    'total_kgs_distributed' => 0,
    'total_vaccinations' => 0,
    'total_backup_files' => 0,
    'total_admins' => 0,
    'latest_distribution_date' => null,
    'latest_vaccination_date' => null,
    'latest_backup_at' => null,
  ];

  $highlights = $highlights ?? [
    'avg_kgs_per_recipient' => 0,
    'avg_farm_area' => 0,
    'top_seed_variety' => null,
    'top_farm_location' => null,
    'top_vacc_barangay' => null,
  ];

  $charts = $charts ?? [
    'months' => [],
    'rice_monthly' => [],
    'top_locations_labels' => [],
    'top_locations_values' => [],
    'seed_variety_labels' => [],
    'seed_variety_values' => [],
    'vacc_barangay_labels' => [],
    'vacc_barangay_values' => [],
    'gender_labels' => [],
    'gender_values' => [],
  ];

  $recentRecipients = $recentRecipients ?? collect();
  $recentVaccinations = $recentVaccinations ?? collect();
  $recentBackups = $recentBackups ?? collect();
  $currentYear = $currentYear ?? now()->year;

  $isHeadAdmin = (auth()->user()->role ?? null) === 'head_admin';

  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return '—';
      try {
          return \Illuminate\Support\Carbon::parse($value)->format($format);
      } catch (\Throwable $e) {
          return '—';
      }
  };

  $latestDistribution = $fmtDate($stats['latest_distribution_date'] ?? null);
  $latestVaccination  = $fmtDate($stats['latest_vaccination_date'] ?? null);
  $latestBackup       = $fmtDate($stats['latest_backup_at'] ?? null, 'M d, Y h:i A');
@endphp

@push('styles')
<style>
  .dash-page{
    display:grid;
    gap:14px;
  }

  .dash-hero{
    position:relative;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:18px;
    background:
      radial-gradient(700px 240px at 0% 0%, rgba(250,204,21,.18), transparent 58%),
      radial-gradient(600px 260px at 100% 20%, rgba(34,197,94,.18), transparent 58%),
      linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.96));
    box-shadow: var(--shadow);
  }

  .dash-hero::after{
    content:"";
    position:absolute;
    inset:auto -30px -45px auto;
    width:240px;
    height:240px;
    border-radius:50%;
    background: radial-gradient(circle, rgba(34,197,94,.14), transparent 62%);
    pointer-events:none;
  }

  .dash-hero-inner{
    position:relative;
    z-index:1;
    padding:18px;
    display:grid;
    gap:14px;
    grid-template-columns: minmax(0, 1.5fr) minmax(320px, .9fr);
  }

  .dash-hero-title{
    margin:0;
    font-size:28px;
    font-weight:900;
    line-height:1.05;
    color:#0b1220;
    letter-spacing:-.4px;
  }

  .dash-hero-copy{
    margin:8px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.55;
    max-width:760px;
  }

  .dash-chip-row,
  .dash-action-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:12px;
  }

  .dash-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    background:#fff;
    border:1px solid var(--border);
    font-size:12px;
    font-weight:900;
    color:#0b1220;
    box-shadow: 0 8px 18px rgba(2,6,23,.04);
  }

  .dash-chip strong{
    color:var(--green);
  }

  .dash-hero-panel{
    display:grid;
    gap:10px;
    align-self:stretch;
  }

  .hero-mini-card{
    border:1px solid rgba(2,6,23,.08);
    border-radius:18px;
    background:rgba(255,255,255,.86);
    backdrop-filter: blur(8px);
    padding:14px;
    box-shadow: 0 14px 28px rgba(2,6,23,.06);
  }

  .hero-mini-label{
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.35px;
    color:var(--muted);
  }

  .hero-mini-value{
    margin-top:6px;
    font-size:24px;
    font-weight:900;
    color:#0b1220;
    letter-spacing:-.35px;
  }

  .hero-mini-sub{
    margin-top:5px;
    font-size:12px;
    color:var(--muted);
    line-height:1.45;
  }

  .dash-grid-kpi{
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap:12px;
  }

  .kpi-card{
    border:1px solid var(--border);
    border-radius:18px;
    background:#fff;
    padding:16px;
    box-shadow: 0 10px 24px rgba(2,6,23,.05);
    position:relative;
    overflow:hidden;
  }

  .kpi-card::before{
    content:"";
    position:absolute;
    inset:0 auto 0 0;
    width:5px;
    background: linear-gradient(180deg, var(--green2), var(--yellow));
  }

  .kpi-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .kpi-label{
    font-size:12px;
    color:var(--muted);
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.25px;
  }

  .kpi-icon{
    width:38px;
    height:38px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(34,197,94,.10);
    border:1px solid rgba(34,197,94,.18);
    color:var(--green);
    font-size:16px;
    font-weight:900;
    flex:0 0 auto;
  }

  .kpi-value{
    margin-top:12px;
    font-size:30px;
    line-height:1;
    font-weight:900;
    color:#0b1220;
    letter-spacing:-.55px;
  }

  .kpi-sub{
    margin-top:8px;
    font-size:12px;
    color:var(--muted);
    line-height:1.5;
  }

  .dash-grid-main{
    display:grid;
    grid-template-columns: minmax(0, 2fr) minmax(320px, .95fr);
    gap:14px;
  }

  .dash-left-stack,
  .dash-right-stack{
    display:grid;
    gap:14px;
  }

  .dash-section{
    border:1px solid var(--border);
    border-radius:18px;
    background:#fff;
    box-shadow: 0 12px 30px rgba(2,6,23,.05);
    overflow:hidden;
  }

  .dash-section-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    background:
      linear-gradient(180deg, rgba(248,250,252,.95), rgba(255,255,255,.95));
  }

  .dash-section-title{
    margin:0;
    font-size:15px;
    font-weight:900;
    color:#0b1220;
  }

  .dash-section-sub{
    margin:4px 0 0;
    font-size:12px;
    color:var(--muted);
    line-height:1.45;
  }

  .dash-section-body{
    padding:16px;
  }

  .dash-insight-grid{
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap:12px;
  }

  .insight-card{
    border:1px solid var(--border);
    border-radius:16px;
    background:linear-gradient(180deg, #fff, #fbfefb);
    padding:14px;
  }

  .insight-label{
    font-size:12px;
    font-weight:900;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.25px;
  }

  .insight-value{
    margin-top:8px;
    font-size:21px;
    font-weight:900;
    color:#0b1220;
    line-height:1.2;
  }

  .insight-note{
    margin-top:8px;
    font-size:12px;
    color:var(--muted);
    line-height:1.5;
  }

  .dash-chart-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap:12px;
  }

  .chart-card{
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    background:#fff;
  }

  .chart-card-head{
    padding:12px 14px;
    border-bottom:1px solid var(--border);
    background:rgba(248,250,252,.9);
  }

  .chart-title{
    margin:0;
    font-size:14px;
    font-weight:900;
    color:#0b1220;
  }

  .chart-sub{
    margin-top:4px;
    font-size:12px;
    color:var(--muted);
  }

  .chart-body{
    padding:14px;
  }

  .chart-wrap{
    height:270px;
    position:relative;
  }

  .activity-list{
    display:grid;
    gap:10px;
  }

  .activity-item{
    border:1px solid var(--border);
    border-radius:16px;
    padding:12px;
    background:#fff;
    display:grid;
    grid-template-columns: 44px minmax(0, 1fr);
    gap:12px;
    align-items:flex-start;
  }

  .activity-icon{
    width:44px;
    height:44px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background: rgba(250,204,21,.18);
    border:1px solid rgba(250,204,21,.30);
    font-size:16px;
    font-weight:900;
    color:#854d0e;
  }

  .activity-title{
    font-size:13px;
    font-weight:900;
    color:#0b1220;
    line-height:1.35;
  }

  .activity-meta{
    margin-top:4px;
    font-size:12px;
    color:var(--muted);
    line-height:1.5;
  }

  .dash-table-wrap{
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    background:#fff;
  }

  .dash-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    font-size:13px;
  }

  .dash-table thead th{
    background:#f8fafc;
    border-bottom:1px solid var(--border);
    text-align:left;
    padding:12px;
    font-weight:900;
    color:#0b1220;
  }

  .dash-table tbody td{
    padding:12px;
    border-bottom:1px solid var(--border);
    vertical-align:top;
  }

  .dash-table tbody tr:nth-child(odd){
    background: rgba(248,250,252,.45);
  }

  .dash-table tbody tr:hover{
    background: rgba(34,197,94,.05);
  }

  .dash-table tbody tr:last-child td{
    border-bottom:none;
  }

  .mono{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  }

  .row-title{
    font-weight:900;
    color:#0b1220;
  }

  .row-sub{
    margin-top:4px;
    font-size:12px;
    color:var(--muted);
  }

  .pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid var(--border);
    background:#fff;
    font-size:12px;
    font-weight:900;
    white-space:nowrap;
  }

  .pill-green{
    border-color: rgba(34,197,94,.20);
    background: rgba(34,197,94,.10);
    color: var(--green);
  }

  .pill-yellow{
    border-color: rgba(250,204,21,.28);
    background: rgba(250,204,21,.18);
    color: #854d0e;
  }

  .pill-gray{
    background: rgba(2,6,23,.03);
    color:#0b1220;
  }

  .empty-state{
    border:1px dashed var(--border);
    border-radius:16px;
    padding:20px;
    text-align:center;
    color:var(--muted);
    font-size:13px;
    background:#fff;
  }

  .account-box{
    border:1px solid var(--border);
    border-radius:16px;
    padding:14px;
    background:#fff;
  }

  .account-name{
    font-size:18px;
    font-weight:900;
    color:#0b1220;
  }

  .account-meta{
    margin-top:6px;
    font-size:13px;
    color:var(--muted);
    line-height:1.5;
  }

  .account-actions{
    margin-top:12px;
    display:grid;
    gap:10px;
  }

  .micro-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap:10px;
    margin-top:12px;
  }

  .micro-stat{
    border:1px solid var(--border);
    border-radius:14px;
    padding:12px;
    background:#fff;
  }

  .micro-stat-label{
    font-size:11px;
    color:var(--muted);
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.25px;
  }

  .micro-stat-value{
    margin-top:6px;
    font-size:18px;
    font-weight:900;
    color:#0b1220;
  }

  @media (max-width: 1250px){
    .dash-grid-kpi{
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dash-insight-grid{
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 1024px){
    .dash-hero-inner,
    .dash-grid-main,
    .dash-chart-grid{
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 760px){
    .dash-grid-kpi,
    .dash-insight-grid,
    .micro-grid{
      grid-template-columns: 1fr;
    }

    .dash-hero-title{
      font-size:24px;
    }

    .dash-table-wrap{
      overflow:auto;
    }

    .dash-table{
      min-width: 640px;
    }
  }
</style>
@endpush

@section('content')
  <div class="dash-page">

    <section class="dash-hero">
      <div class="dash-hero-inner">
        <div>
          <h1 class="dash-hero-title">LGU Agriculture Operations Dashboard</h1>
          <p class="dash-hero-copy">
            Welcome back, <strong>{{ auth()->user()->name }}</strong>. This view gives you a cleaner at-a-glance summary of farmers, rice distribution, vaccination activity, and backup status so staff can see operations faster without opening each module.
          </p>

          <div class="dash-chip-row">
            <span class="dash-chip">Latest rice release <strong>{{ $latestDistribution }}</strong></span>
            <span class="dash-chip">Latest vaccination <strong>{{ $latestVaccination }}</strong></span>
            <span class="dash-chip">Latest backup <strong>{{ $latestBackup }}</strong></span>
          </div>

          <div class="dash-action-row">
            <a class="btn" href="{{ route('rice-seed-distributions.create') }}">+ Add Recipient</a>
            <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('farmers.index') }}">Farmers</a>
            <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('anti-rabies-vaccinations.index') }}">Vaccinations</a>
            <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('backups.index') }}">Backups</a>
            @if($isHeadAdmin)
              <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('admins.index') }}">Admins</a>
            @endif
          </div>
        </div>

        <div class="dash-hero-panel">
          <div class="hero-mini-card">
            <div class="hero-mini-label">This year</div>
            <div class="hero-mini-value">{{ $currentYear }}</div>
            <div class="hero-mini-sub">Current reporting year for the dashboard trend cards and quick visual summaries.</div>
          </div>

          <div class="hero-mini-card">
            <div class="hero-mini-label">Average release size</div>
            <div class="hero-mini-value">{{ number_format((float) ($highlights['avg_kgs_per_recipient'] ?? 0), 2) }} kg</div>
            <div class="hero-mini-sub">Average kilograms distributed per rice seed record.</div>
          </div>

          <div class="hero-mini-card">
            <div class="hero-mini-label">Average farm area</div>
            <div class="hero-mini-value">{{ number_format((float) ($highlights['avg_farm_area'] ?? 0), 2) }} ha</div>
            <div class="hero-mini-sub">Average encoded farm size from farmer master records.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="dash-grid-kpi">
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Total Farmers</div>
          <div class="kpi-icon">F</div>
        </div>
        <div class="kpi-value">{{ number_format((int) ($stats['total_farmers'] ?? 0)) }}</div>
        <div class="kpi-sub">Registered farmer profiles available for lookup and plotting.</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Rice Records</div>
          <div class="kpi-icon">R</div>
        </div>
        <div class="kpi-value">{{ number_format((int) ($stats['total_distribution_records'] ?? 0)) }}</div>
        <div class="kpi-sub">Total rice seed distribution entries encoded in the system.</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Total Kgs Released</div>
          <div class="kpi-icon">K</div>
        </div>
        <div class="kpi-value">{{ number_format((float) ($stats['total_kgs_distributed'] ?? 0), 2) }}</div>
        <div class="kpi-sub">Total rice seed volume distributed across all records.</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Vaccinations</div>
          <div class="kpi-icon">V</div>
        </div>
        <div class="kpi-value">{{ number_format((int) ($stats['total_vaccinations'] ?? 0)) }}</div>
        <div class="kpi-sub">Anti-rabies vaccination records currently stored.</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Backup Files</div>
          <div class="kpi-icon">B</div>
        </div>
        <div class="kpi-value">{{ number_format((int) ($stats['total_backup_files'] ?? 0)) }}</div>
        <div class="kpi-sub">Available backup files for archive, restore, and audit use.</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">{{ $isHeadAdmin ? 'Admin Accounts' : 'System Role' }}</div>
          <div class="kpi-icon">{{ $isHeadAdmin ? 'A' : 'U' }}</div>
        </div>
        <div class="kpi-value">
          {{ $isHeadAdmin ? number_format((int) ($stats['total_admins'] ?? 0)) : strtoupper(auth()->user()->role ?? 'admin') }}
        </div>
        <div class="kpi-sub">
          {{ $isHeadAdmin ? 'Number of staff accounts with admin access.' : 'Your current permission role in the system.' }}
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Top Seed Variety</div>
          <div class="kpi-icon">S</div>
        </div>
        <div class="kpi-value" style="font-size:24px;">
          {{ $highlights['top_seed_variety']->seed_variety_claimed ?? 'No data' }}
        </div>
        <div class="kpi-sub">
          @if(!empty($highlights['top_seed_variety']))
            {{ number_format((float) $highlights['top_seed_variety']->total_kgs, 2) }} kg distributed.
          @else
            Add more rice data to surface the top variety.
          @endif
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-label">Top Vaccination Barangay</div>
          <div class="kpi-icon">G</div>
        </div>
        <div class="kpi-value" style="font-size:24px;">
          {{ $highlights['top_vacc_barangay']->barangay ?? 'No data' }}
        </div>
        <div class="kpi-sub">
          @if(!empty($highlights['top_vacc_barangay']))
            {{ number_format((int) $highlights['top_vacc_barangay']->total_count) }} recorded vaccinations.
          @else
            Add anti-rabies records to populate this card.
          @endif
        </div>
      </div>
    </section>

    <section class="dash-grid-main">
      <div class="dash-left-stack">

        <div class="dash-section">
          <div class="dash-section-head">
            <div>
              <h2 class="dash-section-title">Operational Insights</h2>
              <p class="dash-section-sub">High-value summaries that help staff see where most activity is happening.</p>
            </div>
            <span class="pill pill-green">Live summary</span>
          </div>
          <div class="dash-section-body">
            <div class="dash-insight-grid">
              <div class="insight-card">
                <div class="insight-label">Average release size</div>
                <div class="insight-value">{{ number_format((float) ($highlights['avg_kgs_per_recipient'] ?? 0), 2) }} kg</div>
                <div class="insight-note">Useful for spotting whether typical releases are increasing or shrinking over time.</div>
              </div>

              <div class="insight-card">
                <div class="insight-label">Average farm area</div>
                <div class="insight-value">{{ number_format((float) ($highlights['avg_farm_area'] ?? 0), 2) }} ha</div>
                <div class="insight-note">A quick estimate of average farm size among encoded farmers.</div>
              </div>

              <div class="insight-card">
                <div class="insight-label">Top farm location</div>
                <div class="insight-value">{{ $highlights['top_farm_location']->farm_location ?? 'No data yet' }}</div>
                <div class="insight-note">
                  @if(!empty($highlights['top_farm_location']))
                    {{ number_format((int) $highlights['top_farm_location']->total_count) }} farmer record(s) from this location.
                  @else
                    Add farm location values to populate this insight.
                  @endif
                </div>
              </div>

              <div class="insight-card">
                <div class="insight-label">Top seed variety</div>
                <div class="insight-value">{{ $highlights['top_seed_variety']->seed_variety_claimed ?? 'No data yet' }}</div>
                <div class="insight-note">
                  @if(!empty($highlights['top_seed_variety']))
                    {{ number_format((float) $highlights['top_seed_variety']->total_kgs, 2) }} kg released for this variety.
                  @else
                    Rice data is still too limited to rank varieties.
                  @endif
                </div>
              </div>

              <div class="insight-card">
                <div class="insight-label">Top vaccination barangay</div>
                <div class="insight-value">{{ $highlights['top_vacc_barangay']->barangay ?? 'No data yet' }}</div>
                <div class="insight-note">
                  @if(!empty($highlights['top_vacc_barangay']))
                    {{ number_format((int) $highlights['top_vacc_barangay']->total_count) }} vaccination record(s) logged here.
                  @else
                    Add anti-rabies records to surface barangay activity.
                  @endif
                </div>
              </div>

              <div class="insight-card">
                <div class="insight-label">Dashboard tip</div>
                <div class="insight-value">Use this as a control room</div>
                <div class="insight-note">Track totals, trends, and recent activity here first before opening deeper module pages.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="dash-section">
          <div class="dash-section-head">
            <div>
              <h2 class="dash-section-title">Visual Trends</h2>
              <p class="dash-section-sub">More readable chart cards with better grouping and spacing.</p>
            </div>
            <span class="pill pill-yellow">{{ $currentYear }}</span>
          </div>
          <div class="dash-section-body">
            <div class="dash-chart-grid">
              <div class="chart-card">
                <div class="chart-card-head">
                  <h3 class="chart-title">Monthly Rice Distribution</h3>
                  <div class="chart-sub">Total kilograms released in {{ $currentYear }}</div>
                </div>
                <div class="chart-body">
                  <div class="chart-wrap">
                    <canvas id="chartRiceMonthly"></canvas>
                  </div>
                </div>
              </div>

              <div class="chart-card">
                <div class="chart-card-head">
                  <h3 class="chart-title">Top Locations by Distributed Kgs</h3>
                  <div class="chart-sub">Highest-volume farm locations</div>
                </div>
                <div class="chart-body">
                  <div class="chart-wrap">
                    <canvas id="chartTopLocations"></canvas>
                  </div>
                </div>
              </div>

              <div class="chart-card">
                <div class="chart-card-head">
                  <h3 class="chart-title">Top Seed Varieties</h3>
                  <div class="chart-sub">Claimed varieties ranked by total kilograms</div>
                </div>
                <div class="chart-body">
                  <div class="chart-wrap">
                    <canvas id="chartSeedVarieties"></canvas>
                  </div>
                </div>
              </div>

              <div class="chart-card">
                <div class="chart-card-head">
                  <h3 class="chart-title">Vaccinations by Barangay</h3>
                  <div class="chart-sub">Top barangays by anti-rabies activity</div>
                </div>
                <div class="chart-body">
                  <div class="chart-wrap">
                    <canvas id="chartVaccBarangay"></canvas>
                  </div>
                </div>
              </div>

              <div class="chart-card" style="grid-column: 1 / -1;">
                <div class="chart-card-head">
                  <h3 class="chart-title">Farmer Gender Distribution</h3>
                  <div class="chart-sub">Breakdown of farmer profiles by gender</div>
                </div>
                <div class="chart-body">
                  <div class="chart-wrap" style="height:320px;">
                    <canvas id="chartFarmerGender"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="dash-section">
          <div class="dash-section-head">
            <div>
              <h2 class="dash-section-title">Recent Rice Distributions</h2>
              <p class="dash-section-sub">Quick access to the latest distribution activity.</p>
            </div>
            <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('rice-seed-distributions.index') }}">View All</a>
          </div>
          <div class="dash-section-body">
            @if($recentRecipients->count())
              <div class="dash-table-wrap">
                <table class="dash-table">
                  <thead>
                    <tr>
                      <th>Recipient</th>
                      <th>Kgs</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($recentRecipients as $item)
                      <tr>
                        <td>
                          <div class="row-title">{{ $item->last_name }}, {{ $item->first_name }}</div>
                          <div class="row-sub mono">{{ $item->ffrs ?? 'No FFRS' }}</div>
                        </td>
                        <td><span class="pill pill-green">{{ number_format((float) $item->kgs_received, 2) }}</span></td>
                        <td>{{ $fmtDate($item->date_received) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="empty-state">No rice distribution records yet.</div>
            @endif
          </div>
        </div>

      </div>

      <div class="dash-right-stack">

        <div class="dash-section">
          <div class="dash-section-head">
            <div>
              <h2 class="dash-section-title">Account</h2>
              <p class="dash-section-sub">Current user session and quick control actions.</p>
            </div>
          </div>
          <div class="dash-section-body">
            <div class="account-box">
              <div class="account-name">{{ auth()->user()->name }}</div>
              <div class="account-meta">{{ auth()->user()->email }}</div>
              <div class="account-meta">
                Role:
                <span class="pill {{ $isHeadAdmin ? 'pill-yellow' : 'pill-green' }}">
                  {{ strtoupper(auth()->user()->role ?? 'admin') }}
                </span>
              </div>

              <div class="micro-grid">
                <div class="micro-stat">
                  <div class="micro-stat-label">Rice records</div>
                  <div class="micro-stat-value">{{ number_format((int) ($stats['total_distribution_records'] ?? 0)) }}</div>
                </div>
                <div class="micro-stat">
                  <div class="micro-stat-label">Backup files</div>
                  <div class="micro-stat-value">{{ number_format((int) ($stats['total_backup_files'] ?? 0)) }}</div>
                </div>
              </div>

              <div class="account-actions">
                <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                  @csrf
                  <button type="submit" class="btn btn-danger" style="width:100%;">Logout</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="dash-section">
          <div class="dash-section-head">
            <div>
              <h2 class="dash-section-title">Latest Vaccinations</h2>
              <p class="dash-section-sub">Recent anti-rabies entries for quick review.</p>
            </div>
            <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('anti-rabies-vaccinations.index') }}">View All</a>
          </div>
          <div class="dash-section-body">
            @if($recentVaccinations->count())
              <div class="activity-list">
                @foreach($recentVaccinations as $item)
                  <div class="activity-item">
                    <div class="activity-icon">V</div>
                    <div>
                      <div class="activity-title">{{ $item->owner_name }}</div>
                      <div class="activity-meta">
                        Pet: <strong>{{ $item->pet_name ?: 'Unnamed Pet' }}</strong><br>
                        Barangay: {{ $item->barangay ?: '—' }}<br>
                        Date: {{ $fmtDate($item->vaccination_date) }}
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="empty-state">No vaccination records yet.</div>
            @endif
          </div>
        </div>

        <div class="dash-section">
          <div class="dash-section-head">
            <div>
              <h2 class="dash-section-title">Latest Backups</h2>
              <p class="dash-section-sub">Recently uploaded backup files and archive activity.</p>
            </div>
            <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('backups.index') }}">View All</a>
          </div>
          <div class="dash-section-body">
            @if($recentBackups->count())
              <div class="activity-list">
                @foreach($recentBackups as $file)
                  <div class="activity-item">
                    <div class="activity-icon">B</div>
                    <div>
                      <div class="activity-title">{{ $file->original_name }}</div>
                      <div class="activity-meta">
                        Folder: {{ $file->folder ?: '—' }}<br>
                        Size: {{ number_format(($file->size ?? 0) / 1048576, 2) }} MB<br>
                        Uploaded: {{ $fmtDate($file->created_at, 'M d, Y h:i A') }}
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="empty-state">No backup files yet.</div>
            @endif
          </div>
        </div>

      </div>
    </section>
  </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (() => {
    if (typeof Chart === 'undefined') return;

    const css = getComputedStyle(document.documentElement);
    const gridColor = 'rgba(15,23,42,.08)';
    const green = css.getPropertyValue('--green2').trim() || '#22c55e';
    const greenDeep = css.getPropertyValue('--green').trim() || '#166534';
    const yellow = css.getPropertyValue('--yellow').trim() || '#facc15';

    function destroyIfExists(id){
      const el = document.getElementById(id);
      if (!el) return null;
      const existing = Chart.getChart(el);
      if (existing) existing.destroy();
      return el;
    }

    function makeChart(id, config) {
      const el = destroyIfExists(id);
      if (!el) return null;
      return new Chart(el, config);
    }

    makeChart('chartRiceMonthly', {
      type: 'line',
      data: {
        labels: @json($charts['months']),
        datasets: [{
          label: 'Kgs Distributed',
          data: @json($charts['rice_monthly']),
          borderColor: greenDeep,
          backgroundColor: 'rgba(34,197,94,.12)',
          pointBackgroundColor: greenDeep,
          pointRadius: 3,
          pointHoverRadius: 4,
          borderWidth: 2.5,
          tension: .35,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: gridColor } }
        }
      }
    });

    makeChart('chartTopLocations', {
      type: 'bar',
      data: {
        labels: @json($charts['top_locations_labels']),
        datasets: [{
          label: 'Total Kgs',
          data: @json($charts['top_locations_values']),
          backgroundColor: 'rgba(250,204,21,.55)',
          borderColor: yellow,
          borderWidth: 1.4,
          borderRadius: 8
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { beginAtZero: true, grid: { color: gridColor } },
          y: { grid: { display: false } }
        }
      }
    });

    makeChart('chartSeedVarieties', {
      type: 'bar',
      data: {
        labels: @json($charts['seed_variety_labels']),
        datasets: [{
          label: 'Total Kgs',
          data: @json($charts['seed_variety_values']),
          backgroundColor: 'rgba(34,197,94,.55)',
          borderColor: green,
          borderWidth: 1.4,
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: gridColor } }
        }
      }
    });

    makeChart('chartVaccBarangay', {
      type: 'bar',
      data: {
        labels: @json($charts['vacc_barangay_labels']),
        datasets: [{
          label: 'Vaccinations',
          data: @json($charts['vacc_barangay_values']),
          backgroundColor: 'rgba(22,101,52,.75)',
          borderColor: greenDeep,
          borderWidth: 1.4,
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: gridColor } }
        }
      }
    });

    makeChart('chartFarmerGender', {
      type: 'doughnut',
      data: {
        labels: @json($charts['gender_labels']),
        datasets: [{
          data: @json($charts['gender_values']),
          backgroundColor: [
            'rgba(34,197,94,.78)',
            'rgba(250,204,21,.82)',
            'rgba(15,23,42,.45)',
            'rgba(59,130,246,.70)',
            'rgba(168,85,247,.72)'
          ],
          borderColor: '#ffffff',
          borderWidth: 3,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              boxWidth: 10,
              padding: 16,
              font: { size: 12, weight: 700 }
            }
          }
        },
        cutout: '62%'
      }
    });
  })();
</script>
@endpush