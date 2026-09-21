@extends('layouts.app')

@section('title', 'Operations Dashboard')

@php
  $stats = $stats ?? [];
  $charts = $charts ?? [];
  $recentRecipients = $recentRecipients ?? collect();
  $recentVaccinations = $recentVaccinations ?? collect();
  $recentPlots = $recentPlots ?? collect();
  $municipalityStats = collect($municipalityStats ?? []);
  $provinceOverview = $provinceOverview ?? [];

  // Presentation tests render this view directly, so every indicator falls back to an
  // empty metric rather than an undefined index. An empty metric renders as
  // "Not recorded", which is also what an office with no data entered should see.
  $providedMetrics = $dashboardMetrics ?? [];
  $blankMetric = fn (string $title) => ['title' => $title, 'labels' => [], 'series' => [], 'not_recorded' => null];
  $dashboardMetrics = [
      'farmers_by_municipality' => $providedMetrics['farmers_by_municipality'] ?? $blankMetric('Registered farmers by municipality'),
      'assistance_by_category' => $providedMetrics['assistance_by_category'] ?? $blankMetric('Assistance by category'),
      'assistance_by_municipality' => $providedMetrics['assistance_by_municipality'] ?? $blankMetric('Assistance by municipality'),
      'quantity_by_unit' => $providedMetrics['quantity_by_unit'] ?? $blankMetric('Quantity released by unit'),
      'mapping_coverage' => $providedMetrics['mapping_coverage'] ?? $blankMetric('Farm mapping coverage'),
      'animal_health_by_service' => $providedMetrics['animal_health_by_service'] ?? $blankMetric('Animal-health services by type'),
      'animal_health_by_month' => $providedMetrics['animal_health_by_month'] ?? $blankMetric('Animal-health services by month'),
      'fisheries_assistance' => $providedMetrics['fisheries_assistance'] ?? $blankMetric('Fisheries assistance'),
      'fingerlings_by_municipality' => $providedMetrics['fingerlings_by_municipality'] ?? $blankMetric('Fingerlings distributed by municipality'),
      'machinery_condition_by_type' => $providedMetrics['machinery_condition_by_type'] ?? $blankMetric('Machinery condition by equipment type'),
      'machinery_availability_by_type' => $providedMetrics['machinery_availability_by_type'] ?? $blankMetric('Machinery availability by equipment type'),
      'production_by_commodity' => $providedMetrics['production_by_commodity'] ?? $blankMetric('Recorded production by commodity'),
      'municipality_comparison' => $providedMetrics['municipality_comparison'] ?? null,
  ];
  // Assigned here rather than in an inline PHP directive further down the file. Blade
  // pairs raw PHP blocks by scanning forward for the next closing directive, so an
  // inline one sitting between two blocks lower in this file gets paired with the
  // wrong closing tag and silently swallows all the markup in between.
  $comparison = $dashboardMetrics['municipality_comparison'];
  $localNow = \App\Support\LocalTime::now();
  $currentYear = $currentYear ?? $localNow->year;
  $reportYear = $reportYear ?? $currentYear;
  $reportYears = $reportYears ?? [$reportYear];

  $user = auth()->user();
  $isProvincialUser = $user->isProvincialUser();
  $canManageUsers = $user->canManageMunicipalStaff();
  $canManageOperations = $user->canManageOperationalData();
  $municipalityName = $user->municipality?->name;
  $officeLabel = $user->isSystemOwner()
      ? 'System administration'
      : ($isProvincialUser
          ? (($user->province?->name ?: 'Unassigned province') . ' Provincial Agriculture Office')
          : (($municipalityName ?: 'Unassigned') . ' Municipal Agriculture Office'));
  $scopeLabel = $user->scopeLabel();
  if ($user->isRegionalHead()) $officeLabel = $scopeLabel . ' Regional Agriculture Office';

  $mappingCoverage = max(0, min(100, (float) ($stats['mapping_coverage'] ?? 0)));
  $attentionCategories = collect(['unmapped_farmers', 'farmers_missing_ffrs', 'farmers_missing_location', 'machineries_needing_attention'])
      ->filter(fn ($key) => (int) ($stats[$key] ?? 0) > 0)->count();
  $seedLabels = collect($charts['seed_variety_labels'] ?? [])->take(5)->values();
  $seedValues = collect($charts['seed_variety_values'] ?? [])->take(5)->map(fn ($value) => (float) $value)->values();
  $seedMaximum = max((float) ($seedValues->max() ?? 0), 1);

  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return 'No activity yet';

      try {
          return \App\Support\LocalTime::fromUtc($value)?->format($format) ?? 'No activity yet';
      } catch (\Throwable $e) {
          return 'No activity yet';
      }
  };
@endphp

@section('content')
<div class="ops-dashboard">
  <header class="ops-header">
    <div class="ops-header-copy">
      <div class="ops-context">
        {{ $officeLabel }}
      </div>
      <h1>Operations dashboard</h1>
      <p>Monitor field coverage, assistance delivery, and work that needs attention.</p>
      <a class="ops-text-link" href="#dashboardReports" data-open-dashboard-section="dashboardReports">View graphs and reports <span aria-hidden="true">↓</span></a>
    </div>

    <div class="ops-date"><strong>{{ $localNow->format('l, F j, Y') }}</strong><span>As of {{ $localNow->format('h:i A') }} PHT</span></div>
  </header>

  <section class="ops-overview" aria-label="Office overview">
    <div class="ops-overview-copy">
      <span class="ops-scope"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $scopeLabel }}</span>
    <div class="ops-actions" aria-label="Quick actions">
      <a class="ops-button ops-button-secondary" href="{{ $user->canOverseeSystem() ? '#municipalityPerformance' : route('farmers.index').'#farmersMapModule' }}" @if($user->canOverseeSystem()) data-open-dashboard-section="municipalityPerformance" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path><path d="M9 3v15M15 6v15"></path></svg>
        {{ $user->canOverseeSystem() ? 'Compare municipalities' : 'Open parcel map' }}
      </a>
      @if($canManageOperations)
        <a class="ops-button ops-button-secondary" href="{{ route('farmers.create') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0M19 5v6M16 8h6"></path></svg>
          Add farmer
        </a>
        <a class="ops-button ops-button-primary" href="{{ route('rice-seed-distributions.create') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5ZM16 11c3 0 5-2 5-5-3 0-5 2-5 5Z"></path><path d="M8 17h8"></path></svg>
          Record assistance
        </a>
      @elseif($canManageUsers)
        @if($user->canViewAuditTrail())
          <a class="ops-button ops-button-secondary" href="{{ route('audit-logs.index') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="M9 11l2 2 4-4"></path></svg>
            Review audit trail
          </a>
        @endif
        <a class="ops-button ops-button-primary" href="{{ route('admins.index') }}">Manage users</a>
      @endif
    </div>
    </div>
  </section>

  <div class="ops-section-heading">
    <div><span class="ops-panel-kicker">Your workspace</span><h2>Key figures</h2></div>
    @if($user->canOverseeSystem())<a class="ops-text-link" href="#municipalityPerformance" data-open-dashboard-section="municipalityPerformance">Compare municipalities <span aria-hidden="true">↓</span></a>@endif
  </div>

  <section class="ops-kpi-grid" aria-label="Key performance indicators">
    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Registered farmers</span>
        <span class="ops-icon ops-icon-green">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((int) ($stats['total_farmers'] ?? 0)) }}</strong>
      <div class="ops-kpi-foot">
        <span>All registered records</span>
        <a href="{{ route('farmers.index') }}">Directory</a>
      </div>
    </article>

    <article class="ops-kpi ops-kpi-wide-detail">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Parcel mapping coverage</span>
        <span class="ops-icon ops-icon-blue">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path><path d="M9 3v15M15 6v15"></path></svg>
        </span>
      </div>
      <div class="ops-kpi-value-row">
        <strong class="ops-kpi-value">{{ number_format($mappingCoverage, 1) }}%</strong>
        <span>{{ number_format((int) ($stats['mapped_farmers'] ?? 0)) }} of {{ number_format((int) ($stats['total_farmers'] ?? 0)) }} farmers</span>
      </div>
      <div class="ops-progress" role="progressbar" aria-label="Farmers with mapped parcels" aria-valuenow="{{ $mappingCoverage }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $mappingCoverage }}%"></span>
      </div>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['total_farm_plots'] ?? 0)) }} plots · {{ number_format((float) ($stats['total_mapped_area'] ?? 0), 2) }} ha</span>
        <a href="{{ route('farmers.index') }}#farmersMapModule">Map parcels</a>
      </div>
    </article>

    <article class="ops-kpi">
      <div class="ops-kpi-top"><span class="ops-kpi-label">Assistance this month</span><span class="ops-period">{{ $localNow->format('M Y') }}</span></div>
      <strong class="ops-kpi-value">{{ number_format((int) ($stats['monthly_distribution_records'] ?? 0)) }}</strong>
      <div class="ops-kpi-foot"><span>Agriculture and fisheries releases</span><a href="{{ route('rice-seed-distributions.index', ['received_from' => $localNow->copy()->startOfMonth()->toDateString(), 'received_to' => $localNow->toDateString()]) }}">View releases</a></div>
    </article>

    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Machinery available</span>
        <span class="ops-icon ops-icon-green">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 15h18v4H3z"></path><path d="M6 15V9h8l3 6M9 9V6h4"></path><circle cx="7" cy="19" r="2"></circle><circle cx="18" cy="19" r="2"></circle></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((int) ($stats['available_machineries'] ?? 0)) }}</strong>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['total_machineries'] ?? 0)) }} total assets</span>
        <a href="{{ route('machinery-inventory.index', ['availability_status' => 'available']) }}">Open inventory</a>
      </div>
    </article>
  </section>

  <div class="ops-layout ops-daily-layout">
    <div class="ops-main-column">      <section class="ops-panel">
        <div class="ops-panel-header">
          <div><span class="ops-panel-kicker">Latest transactions</span><h2>Recent assistance distributions</h2><p>The five latest agriculture or fisheries releases in your access scope.</p></div>
          <a class="ops-text-link" href="{{ route('rice-seed-distributions.index') }}">View all</a>
        </div>
        @if($recentRecipients->isNotEmpty())
          <div class="ops-table-wrap">
            <table class="ops-table">
              <thead><tr><th>Recipient</th><th>FFRS</th><th class="ops-numeric">Quantity</th><th>Date received</th><th><span class="sr-only">Action</span></th></tr></thead>
              <tbody>
                @foreach($recentRecipients->take(5) as $item)
                  <tr>
                    <td><strong>{{ $item->last_name }}, {{ $item->first_name }}</strong><small>{{ $item->seed_variety_claimed ?: $item->inputCategoryLabel() }}</small></td>
                    <td class="ops-mono">{{ $item->ffrs ?: 'Not assigned' }}</td>
                    <td class="ops-numeric"><strong>{{ number_format((float) $item->kgs_received, 2) }} {{ $item->quantityUnitLabel() }}</strong></td>
                    <td>{{ $fmtDate($item->date_received) }}</td>
                    <td>@if($canManageOperations)<a class="ops-row-link" href="{{ route('rice-seed-distributions.edit', $item) }}">Review</a>@else<span class="ops-row-link">Read only</span>@endif</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="ops-empty"><strong>No distribution activity</strong><span>New agriculture and fisheries assistance releases will appear here.</span></div>
        @endif
      </section></div>
    <aside class="ops-side-column">      <section class="ops-panel ops-attention-panel">
        <div class="ops-panel-header ops-panel-header-compact"><div><span class="ops-panel-kicker">Next steps</span><h2>Attention needed</h2></div><span class="ops-attention-badge">{{ $attentionCategories }} {{ Str::plural('area', $attentionCategories) }}</span></div>
        @if($attentionCategories === 0)<p class="ops-all-clear">No outstanding items in these four checks.</p>@endif
        <div class="ops-attention-list">
          <a href="{{ route('farmers.index', ['mapping' => 'unmapped']) }}" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['unmapped_farmers'] ?? 0)) }}</span><span><strong>Farmers without mapped parcels</strong><small>Review farmers who still need boundaries.</small></span><span class="ops-chevron">›</span></a>
          <a href="{{ route('farmers.index', ['quality' => 'missing_ffrs']) }}" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['farmers_missing_ffrs'] ?? 0)) }}</span><span><strong>Profiles missing FFRS</strong><small>Review registration identifiers.</small></span><span class="ops-chevron">›</span></a>
          <a href="{{ route('farmers.index', ['quality' => 'missing_location']) }}" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['farmers_missing_location'] ?? 0)) }}</span><span><strong>Profiles missing farm location</strong><small>Review incomplete location records.</small></span><span class="ops-chevron">›</span></a>
          <a href="{{ route('machinery-inventory.index', ['maintenance' => 'attention']) }}" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['machineries_needing_attention'] ?? 0)) }}</span><span><strong>Machinery service alerts</strong><small>Review repairs and upcoming maintenance.</small></span><span class="ops-chevron">›</span></a>
        </div>
      </section></aside>
  </div>

  <details class="ops-reports" id="dashboardReports" @if(request()->has('report_year')) open @endif>
    <summary><span><strong>Reports and office details</strong><small>Monthly figures, program totals, recent services, and parcel work</small></span></summary>
    <div class="ops-reports-content">
  <p class="ops-report-status" data-report-status role="status">Charts load when you open this section. Every figure is also listed as a table.</p>
  <section class="ops-month-strip" aria-label="Current month summary">
    <div class="ops-month-label"><span>This month</span><strong>{{ $localNow->format('F Y') }}</strong></div>
    <div class="ops-month-stat"><span>Input releases</span><strong>{{ number_format((int) ($stats['monthly_distribution_records'] ?? 0)) }}</strong></div>
    <div class="ops-month-stat"><span>Weight-based volume</span><strong>{{ number_format((float) ($stats['monthly_kgs_distributed'] ?? 0), 2) }} kg</strong></div>
    <div class="ops-month-stat"><span>Fisheries releases</span><strong>{{ number_format((int) ($stats['monthly_fisheries_releases'] ?? 0)) }}</strong></div>
    <div class="ops-month-stat"><span>Animal health</span><strong>{{ number_format((int) ($stats['monthly_vaccinations'] ?? 0)) }}</strong></div>
  </section>
      <section class="ops-report-kpis" aria-label="All-time program totals">
    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Fisheries assistance</span>
        <span class="ops-icon ops-icon-blue">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12c4-5 10-6 15-2l3-2v8l-3-2c-5 4-11 3-15-2Z"></path><circle cx="15" cy="11" r=".6"></circle></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((int) ($stats['total_fisheries_releases'] ?? 0)) }}</strong>
      <div class="ops-kpi-foot">
        <span>{{ number_format((float) ($stats['total_fingerlings_released'] ?? 0), 0) }} fingerlings issued</span>
        <a href="{{ route('rice-seed-distributions.index', ['assistance_sector' => 'fisheries']) }}">Open fisheries</a>
      </div>
    </article>
    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Weight-based inputs</span>
        <span class="ops-icon ops-icon-amber">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5ZM16 11c3 0 5-2 5-5-3 0-5 2-5 5Z"></path></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((float) ($stats['total_kgs_distributed'] ?? 0), 2) }} <small>kg</small></strong>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['total_distribution_records'] ?? 0)) }} total release records</span>
        <a href="{{ route('rice-seed-distributions.index') }}">View records</a>
      </div>
    </article>
    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Animal health services</span>
        <span class="ops-icon ops-icon-red">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8l2-4Z"></path><path d="M9 4V2h6v2M9 11h6M12 8v6"></path></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((int) ($stats['total_vaccinations'] ?? 0)) }}</strong>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['total_animals_served'] ?? 0)) }} animals served</span>
        <a href="{{ route('anti-rabies-vaccinations.index') }}">View records</a>
      </div>
    </article>
      </section>
      <div class="ops-layout"><div class="ops-main-column">      <section class="ops-panel">
        <div class="ops-panel-header">
          <div><span class="ops-panel-kicker">Program monitoring</span><h2>Weight-based distribution trend</h2><p>Agriculture and fisheries inputs released in kilograms during {{ $currentYear }}.</p></div>
          <span class="ops-period">Jan–Dec {{ $currentYear }}</span>
        </div>
        <div class="ops-chart-wrap module-chart-body">
          <canvas id="chartRiceMonthly" role="img" aria-label="Monthly kilograms of inputs released in {{ $currentYear }}"></canvas>
          @if(collect($charts['rice_monthly'] ?? [])->sum() <= 0)
            <div class="ops-chart-empty">No kilogram-based releases have been recorded for {{ $currentYear }}.</div>
          @endif
        </div>
        <div class="ops-chart-data"><h3>Monthly figures</h3><dl>@foreach(($charts['months'] ?? []) as $index => $month)<div><dt>{{ $month }}</dt><dd>{{ number_format((float) ($charts['rice_monthly'][$index] ?? 0), 2) }} kg</dd></div>@endforeach</dl></div>
      </section>      <section class="ops-panel">
        <div class="ops-panel-header">
          <div><span class="ops-panel-kicker">Animal health</span><h2>Latest animal-health services</h2><p>Recent vaccination, deworming, vitamins, and treatment records.</p></div>
          <a class="ops-text-link" href="{{ route('anti-rabies-vaccinations.index') }}">View all</a>
        </div>
        @if($recentVaccinations->isNotEmpty())
          <div class="ops-record-list">
            @foreach($recentVaccinations as $item)
              <{{ $canManageOperations ? 'a' : 'div' }} class="ops-record-row" @if($canManageOperations) href="{{ route('anti-rabies-vaccinations.edit', $item) }}" @endif>
                <span class="ops-record-mark ops-record-mark-red">AH</span>
                <span class="ops-record-body"><strong>{{ $item->service_name ?: 'Anti-rabies vaccination' }}</strong><small>{{ number_format($item->animalsServed()) }} {{ $item->animalTypeLabel() }} · {{ $item->pet_name ?: 'Group not named' }} · {{ $item->owner_name ?: 'Owner not recorded' }}</small></span>
                <time>{{ $fmtDate($item->vaccination_date, 'M d') }}</time>
              </{{ $canManageOperations ? 'a' : 'div' }}>
            @endforeach
          </div>
        @else
          <div class="ops-empty"><strong>No animal-health activity</strong><span>Vaccination, deworming, vitamins, and treatment services will appear here.</span></div>
        @endif
      </section></div>
      <aside class="ops-side-column">      <section class="ops-panel">
        {{-- Named for what the query actually selects: rice seed released in
             kilograms. The assistance module calls the same figures the same thing. --}}
        <div class="ops-panel-header ops-panel-header-compact"><div><span class="ops-panel-kicker">Seed program</span><h2>Leading rice seed varieties</h2></div></div>
        <div class="ops-ranking">
          @forelse($seedLabels as $index => $label)
            @php
              $seedValue = (float) ($seedValues[$index] ?? 0);
              $seedWidth = min(100, ($seedValue / $seedMaximum) * 100);
            @endphp
            <div class="ops-rank-row">
              <div class="ops-rank-meta"><span>{{ $label }}</span><strong>{{ number_format($seedValue, 2) }} kg</strong></div>
              <div class="ops-rank-bar"><span style="width: {{ $seedWidth }}%"></span></div>
            </div>
          @empty
            <div class="ops-empty ops-empty-small"><strong>No variety data</strong><span>Add distribution records to build this ranking.</span></div>
          @endforelse
        </div>
      </section>      <section class="ops-panel">
        <div class="ops-panel-header ops-panel-header-compact">
          <div><span class="ops-panel-kicker">Land management</span><h2>Recent parcel work</h2></div>
          <a class="ops-text-link" href="{{ route('farmers.index') }}#farmersMapModule">Open map</a>
        </div>
        @if($recentPlots->isNotEmpty())
          <div class="ops-record-list">
            @foreach($recentPlots as $plot)
              <a class="ops-record-row" href="{{ route('farmers.index', ['q' => $plot->farmer?->last_name]) }}#farmersMapModule">
                <span class="ops-record-mark">P</span>
                <span class="ops-record-body"><strong>{{ $plot->name ?: 'Plot #' . $plot->id }}</strong><small>{{ trim(($plot->farmer?->last_name ?? '') . ', ' . ($plot->farmer?->first_name ?? ''), ', ') ?: 'Farmer unavailable' }} · {{ number_format((float) $plot->area_ha, 2) }} ha</small></span>
                <time>{{ $fmtDate($plot->created_at, 'M d') }}</time>
              </a>
            @endforeach
          </div>
        @else
          <div class="ops-empty ops-empty-small"><strong>No parcels mapped yet</strong><span>Select a farmer in the mapping workspace to begin.</span></div>
        @endif
      </section>      <section class="ops-panel">
        <div class="ops-panel-header ops-panel-header-compact"><div><span class="ops-panel-kicker">Office status</span><h2>Records and access</h2></div></div>
        <dl class="ops-status-list">
          <div><dt>Signed in as</dt><dd>{{ $user->role_label }}</dd></div>
          <div><dt>Registered cooperatives</dt><dd>{{ number_format((int) ($stats['total_cooperatives'] ?? 0)) }}</dd></div>
          @unless($user->canOverseeSystem())<div><dt>Stored backup files</dt><dd>{{ number_format((int) ($stats['total_backup_files'] ?? 0)) }}</dd></div>@endunless
          <div><dt>Latest parcel update</dt><dd>{{ $fmtDate($stats['latest_plot_at'] ?? null) }}</dd></div>
          @unless($user->canOverseeSystem())<div><dt>Latest backup</dt><dd>{{ $fmtDate($stats['latest_backup_at'] ?? null, 'M d, Y · h:i A') }}</dd></div>@endunless
          <div><dt>Staff accounts in scope</dt><dd>{{ number_format((int) ($stats['total_admins'] ?? 0)) }}</dd></div>
        </dl>
        <div class="ops-panel-actions">
          @unless($user->canOverseeSystem())<a class="ops-button ops-button-secondary" href="{{ route('backups.index') }}">Manage backups</a>@endunless
          @if($canManageUsers)<a class="ops-button ops-button-secondary" href="{{ route('admins.index') }}">Manage staff</a>@endif
        </div>
      </section></aside></div>

      <section class="ops-panel" aria-labelledby="dashboardIndicatorsHeading">
        <div class="ops-panel-header">
          <div>
            <span class="ops-panel-kicker">Program indicators</span>
            <h2 id="dashboardIndicatorsHeading">Program activity and equipment</h2>
            <p>Each indicator is measured on its own terms. What was released is counted as a release, never as production or yield.</p>
          </div>
        </div>
        <form class="ops-report-filter" method="GET" action="{{ route('dashboard') }}#dashboardReports" aria-label="Dashboard reporting year">
          <label class="ops-metric-picker" for="dashboardReportYear">
            <span>Reporting year</span>
            <select id="dashboardReportYear" name="report_year" aria-describedby="dashboardReportYearHelp">
              @foreach($reportYears as $availableReportYear)
                <option value="{{ $availableReportYear }}" @selected((int) $availableReportYear === (int) $reportYear)>{{ $availableReportYear }}</option>
              @endforeach
            </select>
          </label>
          <button type="submit" class="ops-button ops-button-primary">Apply year</button>
          <p id="dashboardReportYearHelp">Applies to monthly animal-health services, fingerling quantities, and production in the municipality comparison. Other program totals cover all recorded dates; machinery shows the current inventory.</p>
        </form>
        <div class="ops-metric-grid">
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['farmers_by_municipality'],
            'metricId' => 'chartFarmersByMunicipality',
            'metricLabel' => 'Municipality',
            'metricNote' => 'Farmers on the register, counted where they are registered.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['assistance_by_category'],
            'metricId' => 'chartAssistanceByCategory',
            'metricNote' => 'Releases are hand-overs. A farmer who collected three times is three releases but one beneficiary.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['assistance_by_municipality'],
            'metricId' => 'chartAssistanceByMunicipality',
            'metricLabel' => 'Municipality',
            'metricNote' => 'The same two measures, by office.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['quantity_by_unit'],
            'metricChart' => false,
            'metricLabel' => 'Unit',
            'metricNote' => 'Shown as figures only. Nothing here converts kilograms into pieces or sacks, so these rows cannot be added together or drawn on one axis.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['mapping_coverage'],
            'metricId' => 'chartMappingCoverage',
            'metricLabel' => 'Coverage',
            'metricNote' => 'Counted in farmers, not parcels. The office has no inventory of the parcels that exist in the field, so unmapped parcels cannot be counted.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['fisheries_assistance'],
            'metricId' => 'chartFisheriesAssistance',
            'metricNote' => 'All recorded fisheries releases and beneficiaries. Fingerling quantities for the selected year appear separately below.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['fingerlings_by_municipality'],
            'metricId' => 'chartFingerlingsByMunicipality',
            'metricLabel' => 'Municipality',
            'metricNote' => $reportYear . ' · Fingerlings issued, in pieces. These are quantities released, not the number of farmers served.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['animal_health_by_service'],
            'metricId' => 'chartAnimalHealth',
            'metricLabel' => 'Service type',
            'metricNote' => 'All recorded dates. One service record can cover many animals, so services and animals are counted separately.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['animal_health_by_month'],
            'metricId' => 'chartAnimalHealthMonthly',
            'metricLabel' => 'Month',
            'metricNote' => $reportYear . ' · Number of service records by month and service type. Animals treated are not counted as separate service records.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['machinery_condition_by_type'],
            'metricId' => 'chartMachineryCondition',
            'metricLabel' => 'Equipment type',
            'metricNote' => 'Current inventory · Equipment units in each operating condition, grouped by equipment type.',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['production_by_commodity'],
            'metricId' => 'chartProductionByCommodity',
            'metricLabel' => 'Harvest year',
            'metricNote' => 'All recorded harvest years. Choose one commodity and unit to follow its production over time; sacks and kilograms are never combined.',
            'metricPickerId' => 'productionIndicatorPicker',
          ])
          @include('partials.dashboard-metric', [
            'metric' => $dashboardMetrics['machinery_availability_by_type'],
            'metricId' => 'chartMachineryAvailability',
            'metricLabel' => 'Equipment type',
            'metricNote' => 'Current inventory · Whether each unit can be used now, grouped by equipment type. An excellent unit can still be in use or unavailable.',
          ])
        </div>
      </section>

      @if($dashboardMetrics['municipality_comparison'])
        <section class="ops-panel" aria-labelledby="dashboardComparisonHeading">
          <div class="ops-panel-header ops-comparison-header">
            <div>
              <span class="ops-panel-kicker">Comparison</span>
              <h2 id="dashboardComparisonHeading">Municipalities, one indicator at a time</h2>
              <p>Compare one indicator at a time. Production uses {{ $reportYear }} harvest records, with each commodity and unit kept separate. Farmer and assistance counts cover all recorded dates. Missing production is shown as “Not recorded,” not zero.</p>
            </div>
            <label class="ops-metric-picker">
              <span>Indicator</span>
              <select id="municipalityIndicatorPicker">
                @foreach($comparison['series'] as $comparisonIndex => $comparisonSeries)
                  <option value="{{ $comparisonIndex }}">{{ $comparisonSeries['name'] }} ({{ $comparisonSeries['unit'] }})</option>
                @endforeach
              </select>
            </label>
          </div>
          <div class="ops-comparison-body">
            @include('partials.dashboard-municipality-chart', ['metric' => $comparison, 'metricId' => 'chartMunicipalityComparison'])
            @include('partials.dashboard-metric-figures', [
              'metric' => $comparison,
              'metricLabel' => 'Municipality',
            ])
          </div>
        </section>
      @endif
    </div>
  </details>

  @if($user->canOverseeSystem())
    <details class="ops-reports ops-municipality-panel" id="municipalityPerformance">
      <summary><span><strong>Compare municipalities</strong><small>Office staffing, mapping coverage, and program delivery</small></span></summary>
      <div class="ops-reports-content">
      <div class="ops-panel-header ops-municipality-heading">
        <div>
          <span class="ops-panel-kicker">{{ $user->isSystemOwner() ? 'System administration' : ($user->isRegionalHead() ? 'Regional administration' : 'Province administration') }}</span>
          <h2>Municipality performance</h2>
          <p>Compare local offices, program delivery, staffing, parcel coverage, and records that need attention.</p>
        </div>
        <span class="ops-period">Active municipalities</span>
      </div>

      <div class="ops-province-summary" aria-label="Province municipality summary">
        <article>
          <span>Active municipalities</span>
          <strong>{{ number_format((int) ($provinceOverview['active_municipalities'] ?? 0)) }}</strong>
          <small>Within your oversight scope</small>
        </article>
        <article>
          <span>Heads assigned</span>
          <strong>{{ number_format((int) ($provinceOverview['municipalities_with_head'] ?? 0)) }}<small>/{{ number_format((int) ($provinceOverview['active_municipalities'] ?? 0)) }}</small></strong>
          <small>Offices with an active head</small>
        </article>
        <article>
          <span>Municipal accounts</span>
          <strong>{{ number_format((int) ($provinceOverview['municipal_accounts'] ?? 0)) }}</strong>
          <small>Active heads and staff</small>
        </article>
        <article>
          <span>Mapping started</span>
          <strong>{{ number_format((int) ($provinceOverview['mapped_municipalities'] ?? 0)) }}<small>/{{ number_format((int) ($provinceOverview['active_municipalities'] ?? 0)) }}</small></strong>
          <small>Municipalities with mapped farmers</small>
        </article>
        <article class="ops-summary-alert">
          <span>Offices needing attention</span>
          <strong>{{ number_format((int) ($provinceOverview['municipalities_needing_attention'] ?? 0)) }}</strong>
          <small>Based on staffing, records, and mapping</small>
        </article>
        @if($user->isSystemOwner())
        <article class="{{ (int) ($provinceOverview['unassigned_records'] ?? 0) > 0 ? 'ops-summary-alert' : '' }}">
          <span>Unassigned records</span>
          <strong>{{ number_format((int) ($provinceOverview['unassigned_records'] ?? 0)) }}</strong>
          <small>Records without a municipality</small>
        </article>
        @endif
      </div>

      <div class="ops-municipality-toolbar">
        <label class="ops-municipality-search" for="municipalitySearch">
          <span class="sr-only">Search municipality</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
          <input id="municipalitySearch" type="search" placeholder="Search municipality" autocomplete="off">
        </label>
        <label class="ops-toolbar-field" for="municipalityStatus">
          <span>Status</span>
          <select id="municipalityStatus">
            <option value="all">All offices</option>
            <option value="attention">Attention required</option>
            <option value="missing_head">Head needed</option>
            <option value="needs_mapping">Mapping behind</option>
            <option value="no_records">No farmer records</option>
            <option value="operational">On track</option>
          </select>
        </label>
        <label class="ops-toolbar-field" for="municipalitySort">
          <span>Sort</span>
          <select id="municipalitySort">
            <option value="attention">Priority</option>
            <option value="name">Municipality</option>
            <option value="farmers">Most farmers</option>
            <option value="coverage">Highest mapping</option>
            <option value="seed">Most kilograms released</option>
            <option value="machinery">Most machinery</option>
          </select>
        </label>
        <span class="ops-visible-count" id="municipalityVisibleCount" role="status" aria-live="polite">{{ $municipalityStats->count() }} municipalities</span>
      </div>

      @if($municipalityStats->isNotEmpty())
        <div class="ops-table-wrap ops-municipality-table-wrap">
          <table class="ops-table ops-municipality-table">
            <thead>
              <tr>
                <th>Municipality</th>
                <th>Farmers</th>
                <th>Parcel mapping</th>
                <th>Assistance</th>
                <th>Machinery</th>
                <th>Animal health</th>
                <th>Office access</th>
                <th>Data quality</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="municipalityTableBody">
              @foreach($municipalityStats as $municipality)
                @php
                  $initials = collect(preg_split('/\s+/', trim($municipality['name'])))
                      ->filter()
                      ->take(2)
                      ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                      ->implode('');
                  $dataGaps = $municipality['missing_ffrs'] + $municipality['missing_location'];
                @endphp
                <tr
                  data-municipality-row
                  data-name="{{ mb_strtolower($municipality['name']) }}"
                  data-status="{{ $municipality['status'] }}"
                  data-priority="{{ $municipality['attention_priority'] }}"
                  data-farmers="{{ $municipality['total_farmers'] }}"
                  data-coverage="{{ $municipality['mapping_coverage'] }}"
                  data-seed="{{ $municipality['total_kgs'] }}"
                  data-machinery="{{ $municipality['total_machinery'] }}"
                >
                  <td>
                    <div class="ops-municipality-identity">
                      <span>{{ $initials ?: 'LGU' }}</span>
                      <div>
                        <strong>{{ $municipality['name'] }}</strong>
                        <small>{{ number_format($municipality['cooperatives']) }} {{ Str::plural('cooperative', $municipality['cooperatives']) }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <strong class="ops-cell-value">{{ number_format($municipality['total_farmers']) }}</strong>
                    <small class="ops-cell-note">{{ number_format($municipality['unmapped_farmers']) }} without a parcel</small>
                  </td>
                  <td class="ops-mapping-cell">
                    <div class="ops-cell-value-row"><strong>{{ number_format($municipality['mapping_coverage'], 1) }}%</strong><span>{{ number_format($municipality['mapped_farmers']) }}/{{ number_format($municipality['total_farmers']) }}</span></div>
                    <div class="ops-mini-progress"><span style="width: {{ min(100, $municipality['mapping_coverage']) }}%"></span></div>
                    <small class="ops-cell-note">{{ number_format($municipality['total_plots']) }} plots · {{ number_format($municipality['mapped_area'], 2) }} ha</small>
                  </td>
                  <td>
                    <strong class="ops-cell-value">{{ number_format($municipality['total_kgs'], 2) }} kg</strong>
                    <small class="ops-cell-note">{{ number_format($municipality['distribution_records']) }} releases · {{ number_format($municipality['fisheries_records']) }} fisheries</small>
                    <a class="ops-cell-link" href="{{ route('rice-seed-distributions.index', ['municipality_id' => $municipality['id']]) }}">Open assistance</a>
                  </td>
                  <td>
                    <strong class="ops-cell-value">{{ number_format($municipality['total_machinery']) }} assets</strong>
                    <small class="ops-cell-note">{{ number_format($municipality['available_machinery']) }} available · {{ number_format($municipality['machinery_attention']) }} flagged</small>
                    <a class="ops-cell-link" href="{{ route('machinery-inventory.index', ['municipality_id' => $municipality['id']]) }}">Open inventory</a>
                  </td>
                  <td>
                    <strong class="ops-cell-value">{{ number_format($municipality['animals_served']) }} animals</strong>
                    <small class="ops-cell-note">{{ number_format($municipality['vaccinations']) }} health services</small>
                  </td>
                  <td>
                    <strong class="ops-cell-value">{{ number_format($municipality['municipal_heads']) }} head · {{ number_format($municipality['municipal_staff']) }} staff</strong>
                    <a class="ops-cell-link" href="{{ route('admins.index', ['municipality_id' => $municipality['id']]) }}">Manage accounts</a>
                  </td>
                  <td>
                    <strong class="ops-cell-value {{ $dataGaps > 0 ? 'ops-text-warning' : '' }}">{{ number_format($dataGaps) }} gaps</strong>
                    <small class="ops-cell-note">{{ number_format($municipality['missing_ffrs']) }} FFRS · {{ number_format($municipality['missing_location']) }} location</small>
                  </td>
                  <td>
                    <span class="ops-status-pill ops-status-{{ str_replace('_', '-', $municipality['status']) }}">{{ $municipality['status_label'] }}</span>
                    <a class="ops-row-link ops-directory-link" href="{{ route('farmers.index', ['municipality_id' => $municipality['id']]) }}">Open directory</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="ops-filter-empty" id="municipalityFilterEmpty" hidden>
          <strong>No municipalities match these filters</strong>
          <span>Try another municipality name or status.</span>
        </div>
        <div class="ops-municipality-note">Attention flags identify an unassigned municipal head, no farmer records, or parcel coverage below 50%.</div>
      @else
        <div class="ops-empty"><strong>No active municipalities</strong><span>Activate municipalities to begin province monitoring.</span></div>
      @endif
      </div>
    </details>
  @endif
</div>
@endsection

@push('styles')
  @include('dashboard.partials.styles')
@endpush

@push('scripts')
<script src="{{ asset('js/dashboard-chart-pages.js') }}"></script>
<script>
  (() => {
    const body = document.getElementById('municipalityTableBody');
    if (!body) return;

    const rows = Array.from(body.querySelectorAll('[data-municipality-row]'));
    const search = document.getElementById('municipalitySearch');
    const status = document.getElementById('municipalityStatus');
    const sort = document.getElementById('municipalitySort');
    const visibleCount = document.getElementById('municipalityVisibleCount');
    const empty = document.getElementById('municipalityFilterEmpty');

    const numeric = (row, key) => Number(row.dataset[key] || 0);

    const compareRows = (left, right) => {
      switch (sort?.value) {
        case 'name':
          return left.dataset.name.localeCompare(right.dataset.name);
        case 'farmers':
          return numeric(right, 'farmers') - numeric(left, 'farmers') || left.dataset.name.localeCompare(right.dataset.name);
        case 'coverage':
          return numeric(right, 'coverage') - numeric(left, 'coverage') || left.dataset.name.localeCompare(right.dataset.name);
        case 'seed':
          return numeric(right, 'seed') - numeric(left, 'seed') || left.dataset.name.localeCompare(right.dataset.name);
        case 'machinery':
          return numeric(right, 'machinery') - numeric(left, 'machinery') || left.dataset.name.localeCompare(right.dataset.name);
        default:
          return numeric(right, 'priority') - numeric(left, 'priority') || left.dataset.name.localeCompare(right.dataset.name);
      }
    };

    const refreshMunicipalities = () => {
      const query = (search?.value || '').trim().toLocaleLowerCase();
      const selectedStatus = status?.value || 'all';
      let shown = 0;

      rows.sort(compareRows).forEach(row => {
        const matchesName = !query || row.dataset.name.includes(query);
        const matchesStatus = selectedStatus === 'all'
          || (selectedStatus === 'attention' && row.dataset.status !== 'operational')
          || row.dataset.status === selectedStatus;
        const isVisible = matchesName && matchesStatus;

        row.hidden = !isVisible;
        if (isVisible) shown += 1;
        body.appendChild(row);
      });

      if (visibleCount) {
        visibleCount.textContent = `${shown} of ${rows.length} municipalities`;
      }
      if (empty) empty.hidden = shown !== 0;
    };

    search?.addEventListener('input', refreshMunicipalities);
    status?.addEventListener('change', refreshMunicipalities);
    sort?.addEventListener('change', refreshMunicipalities);
    refreshMunicipalities();
  })();

  (() => {
    /*
      Dashboard charts.

      Chart.js is fetched by partials.operational-report-loader the first time the
      Reports section is opened, which is why every drawing routine here hangs off
      renderOperationalCharts instead of running at parse time. If the CDN never
      answers, the loader hides the canvases and the figures tables underneath carry
      the entire report on their own.
    */
    const METRICS = @json($dashboardMetrics);

    // Legends and figures identify every series independently of its colour.
    const SERIES_COLORS = ['#2e7d52', '#2f6fb5'];
    const SERVICE_COLORS = ['#2e7d52', '#2f6fb5', '#b07016', '#87548e', '#4a5852'];
    const CONDITION_COLORS = ['#186b40', '#6fb586', '#c9ccc6', '#d18a5a', '#9e3f39'];
    const AVAILABILITY_COLORS = ['#2e7d52', '#2f6fb5', '#b07016', '#4a5852'];
    const COVERAGE_COLORS = ['#2e7d52', '#9aa8a0'];

    const INK_MUTED = '#607269';
    const GRID_INK = 'rgba(32,54,44,.08)';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const formatValue = (value, decimals) => value == null ? 'Not recorded' : Number(value).toLocaleString(undefined, {
      minimumFractionDigits: decimals || 0,
      maximumFractionDigits: decimals || 0,
    });

    const freshCanvas = id => {
      const canvas = document.getElementById(id);
      if (!canvas) return null;
      const existing = Chart.getChart(canvas);
      if (existing) existing.destroy();
      return canvas;
    };

    const tooltipFor = metric => ({
      backgroundColor: '#17211b',
      padding: 10,
      cornerRadius: 6,
      displayColors: metric.series.length > 1,
      titleFont: { weight: '700' },
      bodyFont: { weight: '400' },
      callbacks: {
        label: context => {
          const series = metric.series[context.datasetIndex] || metric.series[0];
          return `${series.name}: ${formatValue(context.raw, series.decimals)} ${series.unit}`;
        },
      },
    });

    const wrapCategory = (label, limit) => {
      const lines = [''];
      for (const word of String(label).split(/\s+/)) {
        for (const part of word.match(new RegExp('.{1,' + limit + '}', 'g')) || ['']) {
          const last = lines.length - 1;
          if (lines[last] && lines[last].length + part.length + 1 > limit) lines.push(part);
          else lines[last] += (lines[last] ? ' ' : '') + part;
        }
      }
      return lines;
    };

    const drawBars = (id, metric, colors, settings = {}) => {
      const canvas = freshCanvas(id);
      if (!canvas || !metric || !metric.labels.length) return;

      // Named categories read far better stacked down the page than squeezed across
      // it; a province with fifty municipalities is unreadable as vertical bars.
      const horizontal = settings.horizontal ?? (metric.labels.length > 8);
      const axisUnit = new Set(metric.series.map(series => series.unit)).size === 1 ? metric.series[0]?.unit : 'Count';
      if (horizontal) {
        canvas.parentElement.style.height = (settings.paginated
          ? Math.min(400, Math.max(270, metric.labels.length * 38 + 90))
          : Math.max(310, metric.labels.length * 44 + 90)) + 'px';
      }

      new Chart(canvas, {
        type: 'bar',
        data: {
          labels: horizontal ? metric.labels.map(label => wrapCategory(label, canvas.parentElement.clientWidth < 360 ? 18 : 26)) : metric.labels,
          datasets: metric.series.map((series, index) => ({
            label: series.name,
            data: series.values,
            backgroundColor: colors?.[index] ?? '#6b7280',
            borderRadius: 4,
            borderWidth: 0,
            maxBarThickness: 34,
          })),
        },
        options: {
          indexAxis: horizontal ? 'y' : 'x',
          responsive: true,
          maintainAspectRatio: false,
          animation: reduceMotion ? false : { duration: 400 },
          plugins: {
            // A single series is already named by the heading above the chart; two or
            // more need the key, so identity never rests on colour alone.
            legend: {
              display: metric.series.length > 1,
              position: 'bottom',
              labels: { boxWidth: 9, usePointStyle: true, color: INK_MUTED, font: { size: 12 } },
            },
            tooltip: tooltipFor(metric),
          },
          scales: {
            x: {
              stacked: !!settings.stacked,
              title: { display: horizontal, text: axisUnit || '', color: INK_MUTED },
              beginAtZero: horizontal,
              grid: { display: horizontal, color: GRID_INK },
              ticks: { color: INK_MUTED, font: { size: 12 }, precision: 0 },
            },
            y: {
              stacked: !!settings.stacked,
              title: { display: !horizontal, text: axisUnit || '', color: INK_MUTED },
              beginAtZero: !horizontal,
              grid: { display: !horizontal, color: GRID_INK },
              ticks: { color: INK_MUTED, font: { size: 12 }, precision: 0, autoSkip: !horizontal },
            },
          },
        },
      });
    };

    const drawLines = (id, metric) => {
      const canvas = freshCanvas(id);
      if (!canvas || !metric || !metric.labels.length) return;

      new Chart(canvas, {
        type: 'line',
        data: {
          labels: metric.labels,
          datasets: metric.series.map((series, index) => ({
            label: series.name + ' (' + series.unit + ')',
            data: series.values,
            borderColor: SERIES_COLORS[index % SERIES_COLORS.length],
            backgroundColor: 'transparent',
            pointBackgroundColor: SERIES_COLORS[index % SERIES_COLORS.length],
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2,
            tension: .25,
          })),
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: reduceMotion ? false : { duration: 400 },
          interaction: { mode: 'index', intersect: false },
          plugins: {
            // The production picker keeps incompatible units off the same axis.
            legend: {
              display: true,
              position: 'bottom',
              labels: { boxWidth: 9, usePointStyle: true, color: INK_MUTED, font: { size: 12 } },
            },
            tooltip: tooltipFor(metric),
          },
          scales: {
            x: { grid: { display: false }, ticks: { color: INK_MUTED, font: { size: 12 } } },
            y: { beginAtZero: true, grid: { color: GRID_INK }, ticks: { color: INK_MUTED, font: { size: 12 } } },
          },
        },
      });
    };

    const drawDoughnut = (id, metric, colors) => {
      const canvas = freshCanvas(id);
      if (!canvas || !metric || !metric.labels.length) return;
      new Chart(canvas, {
        type: 'doughnut',
        data: {
          labels: metric.labels,
          datasets: [{
            data: metric.series[0].values,
            backgroundColor: colors,
            // A 2px surface ring keeps touching slices from bleeding into each other.
            borderColor: '#fff',
            borderWidth: 2,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          animation: reduceMotion ? false : { duration: 400 },
          plugins: {
            legend: {
              position: 'bottom',
              labels: { boxWidth: 9, usePointStyle: true, color: INK_MUTED, font: { size: 12 } },
            },
            tooltip: tooltipFor(metric),
          },
        },
      });
    };

    const drawMonthly = () => {
      const canvas = freshCanvas('chartRiceMonthly');
      if (!canvas) return;
      new Chart(canvas, {
        type: 'line',
        data: {
          labels: @json($charts['months'] ?? []),
          datasets: [{ label: 'Kilograms released', data: @json($charts['rice_monthly'] ?? []), borderColor: '#2e7d52', backgroundColor: 'rgba(46,125,82,.08)', pointBackgroundColor: '#2e7d52', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2, tension: .28, fill: true }]
        },
        options: {
          responsive: true, maintainAspectRatio: false, animation: reduceMotion ? false : { duration: 450 }, interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: false }, tooltip: { backgroundColor: '#17211b', padding: 10, titleFont: { weight: '700' }, bodyFont: { weight: '400' }, cornerRadius: 6, callbacks: { label: context => `${Number(context.raw || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })} kg` } } },
          scales: { x: { grid: { display: false }, ticks: { color: INK_MUTED, font: { size: 12, weight: '400' } } }, y: { beginAtZero: true, grid: { color: GRID_INK }, ticks: { color: INK_MUTED, font: { size: 12, weight: '400' } } } }
        }
      });
    };

    /*
      The comparison shows one indicator at a time. Indicators are not weighed against
      one another or combined into a score, because the office has never defined how
      they would be weighed — any single number would be invented here, not measured.
    */
    const municipalityPages = new Map();
    const drawMunicipalities = (id, metric, colors) => {
      const root = document.querySelector(`[data-chart-pages="${id}"]`);
      if (!root || !metric) return;
      if (municipalityPages.has(id)) {
        municipalityPages.get(id)(metric);
        return;
      }
      municipalityPages.set(id, DashboardChartPages.mount(root, metric, visible => {
        drawBars(id, visible, colors, { horizontal: true, paginated: true });
      }));
    };
    const comparisonPicker = document.getElementById('municipalityIndicatorPicker');
    const drawComparison = () => {
      const metric = METRICS.municipality_comparison;
      if (!metric) return;
      const index = Number(comparisonPicker ? comparisonPicker.value : 0) || 0;
      const chosen = metric.series[index] || metric.series[0];
      drawMunicipalities('chartMunicipalityComparison', {
        title: metric.title,
        labels: metric.labels,
        series: [chosen],
        not_recorded: metric.not_recorded,
      }, [SERIES_COLORS[0]]);
    };

    const productionPicker = document.getElementById('productionIndicatorPicker');
    const drawProduction = () => {
      const metric = METRICS.production_by_commodity;
      if (!metric?.series.length) return;
      const chosen = metric.series[Number(productionPicker?.value) || 0] || metric.series[0];
      drawLines('chartProductionByCommodity', { ...metric, series: [chosen] });
    };

    const reports = document.getElementById('dashboardReports');
    if (reports) {
      reports.renderOperationalCharts = () => {
        drawMonthly();
        drawMunicipalities('chartFarmersByMunicipality', METRICS.farmers_by_municipality, [SERIES_COLORS[0]]);
        drawBars('chartAssistanceByCategory', METRICS.assistance_by_category, SERIES_COLORS);
        drawMunicipalities('chartAssistanceByMunicipality', METRICS.assistance_by_municipality, SERIES_COLORS);
        drawBars('chartFisheriesAssistance', METRICS.fisheries_assistance, SERIES_COLORS);
        drawMunicipalities('chartFingerlingsByMunicipality', METRICS.fingerlings_by_municipality, [SERIES_COLORS[0]]);
        drawBars('chartAnimalHealth', METRICS.animal_health_by_service, SERIES_COLORS);
        drawBars('chartAnimalHealthMonthly', METRICS.animal_health_by_month, SERVICE_COLORS, { horizontal: false, stacked: true });
        drawProduction();
        drawDoughnut('chartMappingCoverage', METRICS.mapping_coverage, COVERAGE_COLORS);
        drawBars('chartMachineryCondition', METRICS.machinery_condition_by_type, CONDITION_COLORS, { horizontal: true, stacked: true });
        drawBars('chartMachineryAvailability', METRICS.machinery_availability_by_type, AVAILABILITY_COLORS, { horizontal: true, stacked: true });
        drawComparison();
      };
    }

    if (comparisonPicker) {
      comparisonPicker.addEventListener('change', () => {
        if (typeof Chart !== 'undefined') drawComparison();
      });
    }
    if (productionPicker) {
      productionPicker.addEventListener('change', () => {
        if (typeof Chart !== 'undefined') drawProduction();
      });
    }

    /*
      Deep links from the summary cards open the matching section. This is navigation,
      not chart behaviour, and has to keep working whether or not Chart.js ever loads.
    */
    const openSection = id => {
      const section = document.getElementById(id);
      if (!(section instanceof HTMLDetailsElement)) return;
      section.open = true;
      section.scrollIntoView({ block: 'start' });
    };
    document.querySelectorAll('[data-open-dashboard-section]').forEach(link => {
      link.addEventListener('click', () => openSection(link.dataset.openDashboardSection));
    });
    const openHashSection = () => openSection(window.location.hash.slice(1));
    window.addEventListener('hashchange', openHashSection);
    openHashSection();
  })();
</script>
@include('partials.operational-report-loader', ['reportId' => 'dashboardReports'])
@endpush
