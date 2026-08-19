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
  $currentYear = $currentYear ?? now()->year;

  $user = auth()->user();
  $isProvincialUser = $user->isProvincialUser();
  $canManageUsers = $user->canManageMunicipalStaff();
  $canManageOperations = $user->canManageOperationalData();
  $municipalityName = $user->municipality?->name;
  $officeLabel = $isProvincialUser
      ? 'Provincial Agriculture Office of Tarlac'
      : (($municipalityName ?: 'Unassigned') . ' Municipal Agriculture Office');
  $scopeLabel = $isProvincialUser
      ? 'Province-wide operational view'
      : (($municipalityName ?: 'Municipal') . ' records only');

  $mappingCoverage = max(0, min(100, (float) ($stats['mapping_coverage'] ?? 0)));
  $seedLabels = collect($charts['seed_variety_labels'] ?? [])->take(5)->values();
  $seedValues = collect($charts['seed_variety_values'] ?? [])->take(5)->map(fn ($value) => (float) $value)->values();
  $seedMaximum = max((float) ($seedValues->max() ?? 0), 1);

  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return 'No activity yet';

      try {
          return \Illuminate\Support\Carbon::parse($value)->format($format);
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
        <span class="ops-live-dot" aria-hidden="true"></span>
        {{ $officeLabel }}
      </div>
      <h1>Operations dashboard</h1>
      <p>{{ $scopeLabel }} · Updated {{ now()->format('M d, Y · h:i A') }}</p>
    </div>

    <div class="ops-actions" aria-label="Quick actions">
      <a class="ops-button ops-button-secondary" href="{{ route('farmers.index') }}#farmersMapModule">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path><path d="M9 3v15M15 6v15"></path></svg>
        Open farm map
      </a>
      @if($canManageOperations)
        <a class="ops-button ops-button-secondary" href="{{ route('farmers.create') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0M19 5v6M16 8h6"></path></svg>
          Add farmer
        </a>
        <a class="ops-button ops-button-primary" href="{{ route('rice-seed-distributions.create') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5ZM16 11c3 0 5-2 5-5-3 0-5 2-5 5Z"></path><path d="M8 17h8"></path></svg>
          Record distribution
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
  </header>

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
        <span>{{ number_format((int) ($stats['farmers_missing_ffrs'] ?? 0)) }} missing FFRS</span>
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
      <div class="ops-progress" role="progressbar" aria-valuenow="{{ $mappingCoverage }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $mappingCoverage }}%"></span>
      </div>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['total_farm_plots'] ?? 0)) }} plots · {{ number_format((float) ($stats['total_mapped_area'] ?? 0), 2) }} ha</span>
        <a href="{{ route('farmers.index') }}#farmersMapModule">Map parcels</a>
      </div>
    </article>

    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Seed distributed</span>
        <span class="ops-icon ops-icon-amber">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5ZM16 11c3 0 5-2 5-5-3 0-5 2-5 5Z"></path></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((float) ($stats['total_kgs_distributed'] ?? 0), 2) }} <small>kg</small></strong>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['total_distribution_records'] ?? 0)) }} release records</span>
        <a href="{{ route('rice-seed-distributions.index') }}">View records</a>
      </div>
    </article>

    <article class="ops-kpi">
      <div class="ops-kpi-top">
        <span class="ops-kpi-label">Anti-rabies vaccinations</span>
        <span class="ops-icon ops-icon-red">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8l2-4Z"></path><path d="M9 4V2h6v2M9 11h6M12 8v6"></path></svg>
        </span>
      </div>
      <strong class="ops-kpi-value">{{ number_format((int) ($stats['total_vaccinations'] ?? 0)) }}</strong>
      <div class="ops-kpi-foot">
        <span>{{ number_format((int) ($stats['monthly_vaccinations'] ?? 0)) }} this month</span>
        <a href="{{ route('anti-rabies-vaccinations.index') }}">View records</a>
      </div>
    </article>
  </section>

  <section class="ops-month-strip" aria-label="Current month summary">
    <div class="ops-month-label"><span>This month</span><strong>{{ now()->format('F Y') }}</strong></div>
    <div class="ops-month-stat"><span>Rice releases</span><strong>{{ number_format((int) ($stats['monthly_distribution_records'] ?? 0)) }}</strong></div>
    <div class="ops-month-stat"><span>Seed volume</span><strong>{{ number_format((float) ($stats['monthly_kgs_distributed'] ?? 0), 2) }} kg</strong></div>
    <div class="ops-month-stat"><span>Vaccinations</span><strong>{{ number_format((int) ($stats['monthly_vaccinations'] ?? 0)) }}</strong></div>
    <div class="ops-month-stat"><span>Cooperatives</span><strong>{{ number_format((int) ($stats['total_cooperatives'] ?? 0)) }}</strong></div>
    @unless($user->isSuperAdmin())<div class="ops-month-stat"><span>Backup files</span><strong>{{ number_format((int) ($stats['total_backup_files'] ?? 0)) }}</strong></div>@endunless
  </section>

  @if($user->isSuperAdmin())
    <section class="ops-panel ops-municipality-panel" id="municipalityPerformance">
      <div class="ops-panel-header ops-municipality-heading">
        <div>
          <span class="ops-panel-kicker">Province administration</span>
          <h2>Municipality performance</h2>
          <p>Compare local offices, program delivery, staffing, parcel coverage, and records that need attention.</p>
        </div>
        <span class="ops-period">Active municipalities</span>
      </div>

      <div class="ops-province-summary" aria-label="Province municipality summary">
        <article>
          <span>Active municipalities</span>
          <strong>{{ number_format((int) ($provinceOverview['active_municipalities'] ?? 0)) }}</strong>
          <small>Included in province monitoring</small>
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
        <article class="{{ (int) ($provinceOverview['unassigned_records'] ?? 0) > 0 ? 'ops-summary-alert' : '' }}">
          <span>Unassigned records</span>
          <strong>{{ number_format((int) ($provinceOverview['unassigned_records'] ?? 0)) }}</strong>
          <small>Records without a municipality</small>
        </article>
      </div>

      <div class="ops-municipality-toolbar">
        <label class="ops-municipality-search" for="municipalitySearch">
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
            <option value="seed">Most seed released</option>
          </select>
        </label>
        <span class="ops-visible-count" id="municipalityVisibleCount">{{ $municipalityStats->count() }} municipalities</span>
      </div>

      @if($municipalityStats->isNotEmpty())
        <div class="ops-table-wrap ops-municipality-table-wrap">
          <table class="ops-table ops-municipality-table">
            <thead>
              <tr>
                <th>Municipality</th>
                <th>Farmers</th>
                <th>Parcel mapping</th>
                <th>Rice program</th>
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
                    <small class="ops-cell-note">{{ number_format($municipality['distribution_records']) }} release records</small>
                  </td>
                  <td>
                    <strong class="ops-cell-value">{{ number_format($municipality['vaccinations']) }}</strong>
                    <small class="ops-cell-note">Vaccinations recorded</small>
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
                    <a class="ops-row-link ops-directory-link" href="{{ route('farmers.index', ['q' => $municipality['name']]) }}">Open directory</a>
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
    </section>
  @endif

  <div class="ops-layout">
    <main class="ops-main-column">
      <section class="ops-panel">
        <div class="ops-panel-header">
          <div><span class="ops-panel-kicker">Program monitoring</span><h2>Rice distribution trend</h2><p>Monthly kilograms released during {{ $currentYear }}.</p></div>
          <span class="ops-period">Jan–Dec {{ $currentYear }}</span>
        </div>
        <div class="ops-chart-wrap">
          <canvas id="chartRiceMonthly"></canvas>
          @if(collect($charts['rice_monthly'] ?? [])->sum() <= 0)
            <div class="ops-chart-empty">No rice releases have been recorded for {{ $currentYear }}.</div>
          @endif
        </div>
      </section>

      <section class="ops-panel">
        <div class="ops-panel-header">
          <div><span class="ops-panel-kicker">Latest transactions</span><h2>Recent rice distributions</h2><p>The five most recently recorded releases in your access scope.</p></div>
          <a class="ops-text-link" href="{{ route('rice-seed-distributions.index') }}">View all</a>
        </div>
        @if($recentRecipients->isNotEmpty())
          <div class="ops-table-wrap">
            <table class="ops-table">
              <thead><tr><th>Recipient</th><th>FFRS</th><th class="ops-numeric">Quantity</th><th>Date received</th><th><span class="sr-only">Action</span></th></tr></thead>
              <tbody>
                @foreach($recentRecipients as $item)
                  <tr>
                    <td><strong>{{ $item->last_name }}, {{ $item->first_name }}</strong></td>
                    <td class="ops-mono">{{ $item->ffrs ?: 'Not assigned' }}</td>
                    <td class="ops-numeric"><strong>{{ number_format((float) $item->kgs_received, 2) }} kg</strong></td>
                    <td>{{ $fmtDate($item->date_received) }}</td>
                    <td>@if($canManageOperations)<a class="ops-row-link" href="{{ route('rice-seed-distributions.edit', $item) }}">Review</a>@else<span class="ops-row-link">Read only</span>@endif</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="ops-empty"><strong>No distribution activity</strong><span>New rice release records will appear here.</span></div>
        @endif
      </section>

      <section class="ops-panel">
        <div class="ops-panel-header">
          <div><span class="ops-panel-kicker">Animal health</span><h2>Latest vaccination records</h2><p>Recent anti-rabies services recorded by the office.</p></div>
          <a class="ops-text-link" href="{{ route('anti-rabies-vaccinations.index') }}">View all</a>
        </div>
        @if($recentVaccinations->isNotEmpty())
          <div class="ops-record-list">
            @foreach($recentVaccinations as $item)
              <{{ $canManageOperations ? 'a' : 'div' }} class="ops-record-row" @if($canManageOperations) href="{{ route('anti-rabies-vaccinations.edit', $item) }}" @endif>
                <span class="ops-record-mark ops-record-mark-red">AR</span>
                <span class="ops-record-body"><strong>{{ $item->pet_name ?: 'Unnamed pet' }}</strong><small>{{ $item->owner_name }} · {{ $item->barangay ?: 'Barangay not recorded' }}</small></span>
                <time>{{ $fmtDate($item->vaccination_date, 'M d') }}</time>
              </{{ $canManageOperations ? 'a' : 'div' }}>
            @endforeach
          </div>
        @else
          <div class="ops-empty"><strong>No vaccination activity</strong><span>Recorded vaccinations will appear here.</span></div>
        @endif
      </section>
    </main>

    <aside class="ops-side-column">
      <section class="ops-panel">
        <div class="ops-panel-header ops-panel-header-compact"><div><span class="ops-panel-kicker">Data quality</span><h2>Attention needed</h2></div></div>
        <div class="ops-attention-list">
          <a href="{{ route('farmers.index') }}#farmersMapModule" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['unmapped_farmers'] ?? 0)) }}</span><span><strong>Farmers without mapped parcels</strong><small>Open the map and capture boundaries.</small></span><span class="ops-chevron">›</span></a>
          <a href="{{ route('farmers.index') }}" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['farmers_missing_ffrs'] ?? 0)) }}</span><span><strong>Profiles missing FFRS</strong><small>Complete registration identifiers.</small></span><span class="ops-chevron">›</span></a>
          <a href="{{ route('farmers.index') }}" class="ops-attention-row"><span class="ops-attention-count">{{ number_format((int) ($stats['farmers_missing_location'] ?? 0)) }}</span><span><strong>Profiles missing farm location</strong><small>Add a location before geocoding.</small></span><span class="ops-chevron">›</span></a>
        </div>
      </section>

      <section class="ops-panel">
        <div class="ops-panel-header ops-panel-header-compact"><div><span class="ops-panel-kicker">Seed program</span><h2>Leading varieties</h2></div></div>
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
      </section>

      <section class="ops-panel">
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
      </section>

      <section class="ops-panel">
        <div class="ops-panel-header ops-panel-header-compact"><div><span class="ops-panel-kicker">Office status</span><h2>Records and access</h2></div></div>
        <dl class="ops-status-list">
          <div><dt>Signed in as</dt><dd>{{ $user->role_label }}</dd></div>
          <div><dt>Latest parcel update</dt><dd>{{ $fmtDate($stats['latest_plot_at'] ?? null) }}</dd></div>
          @unless($user->isSuperAdmin())<div><dt>Latest backup</dt><dd>{{ $fmtDate($stats['latest_backup_at'] ?? null, 'M d, Y · h:i A') }}</dd></div>@endunless
          <div><dt>Staff accounts in scope</dt><dd>{{ number_format((int) ($stats['total_admins'] ?? 0)) }}</dd></div>
        </dl>
        <div class="ops-panel-actions">
          @unless($user->isSuperAdmin())<a class="ops-button ops-button-secondary" href="{{ route('backups.index') }}">Manage backups</a>@endunless
          @if($canManageUsers)<a class="ops-button ops-button-secondary" href="{{ route('admins.index') }}">Manage staff</a>@endif
        </div>
      </section>
    </aside>
  </div>
</div>
@endsection

@push('styles')
<style>
  :root { --ops-ink:#17211b; --ops-muted:#66736b; --ops-border:#dfe6e1; --ops-surface:#fff; --ops-subtle:#f5f7f5; --ops-green:#17643a; --ops-green-soft:#e7f3eb; --ops-blue:#2563a9; --ops-blue-soft:#eaf1f8; --ops-amber:#9a650e; --ops-amber-soft:#faf1dc; --ops-red:#b33a3a; --ops-red-soft:#faeaea; }
  .ops-dashboard{display:flex;flex-direction:column;gap:18px;color:var(--ops-ink)}
  .ops-header{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding:4px 2px 2px}.ops-context{display:flex;align-items:center;gap:8px;color:var(--ops-green);font-size:12px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}.ops-live-dot{width:8px;height:8px;border-radius:50%;background:#2d8a50;box-shadow:0 0 0 4px rgba(45,138,80,.12)}.ops-header h1{margin:8px 0 4px;font-size:clamp(27px,3vw,36px);line-height:1.08;letter-spacing:-.035em;font-weight:800}.ops-header p{margin:0;color:var(--ops-muted);font-size:13px}.ops-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
  .ops-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:39px;padding:9px 13px;border:1px solid var(--ops-border);border-radius:8px;text-decoration:none;font-size:12px;font-weight:800;transition:border-color .15s ease,background .15s ease}.ops-button svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.ops-button-secondary{color:#33443a;background:#fff}.ops-button-secondary:hover{color:var(--ops-green);border-color:#a8bcb0;background:#f8faf8}.ops-button-primary{color:#fff;border-color:var(--ops-green);background:var(--ops-green)}.ops-button-primary:hover{color:#fff;background:#10532f}
  .ops-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ops-kpi{min-width:0;padding:17px;border:1px solid var(--ops-border);border-radius:11px;background:var(--ops-surface);box-shadow:0 2px 8px rgba(20,40,27,.035)}.ops-kpi-top,.ops-kpi-value-row,.ops-kpi-foot{display:flex;align-items:center;justify-content:space-between;gap:12px}.ops-kpi-label{color:var(--ops-muted);font-size:11px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}.ops-icon{width:32px;height:32px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px}.ops-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.ops-icon-green{color:var(--ops-green);background:var(--ops-green-soft)}.ops-icon-blue{color:var(--ops-blue);background:var(--ops-blue-soft)}.ops-icon-amber{color:var(--ops-amber);background:var(--ops-amber-soft)}.ops-icon-red{color:var(--ops-red);background:var(--ops-red-soft)}.ops-kpi-value{display:block;margin:17px 0 15px;color:var(--ops-ink);font-size:30px;line-height:1;font-weight:800;letter-spacing:-.035em}.ops-kpi-value small{color:var(--ops-muted);font-size:13px;letter-spacing:0}.ops-kpi-value-row .ops-kpi-value{margin-bottom:10px}.ops-kpi-value-row>span{color:var(--ops-muted);font-size:11px;font-weight:700;text-align:right}.ops-kpi-foot{padding-top:12px;border-top:1px solid #edf1ee;color:var(--ops-muted);font-size:11px}.ops-kpi-foot a,.ops-text-link,.ops-row-link{color:var(--ops-green);font-weight:800;text-decoration:none}.ops-kpi-foot a:hover,.ops-text-link:hover,.ops-row-link:hover{text-decoration:underline}.ops-progress{height:6px;margin:0 0 11px;overflow:hidden;border-radius:999px;background:#edf1ee}.ops-progress span{display:block;height:100%;border-radius:inherit;background:var(--ops-green)}
  .ops-month-strip{display:grid;grid-template-columns:1.15fr repeat(5,1fr);overflow:hidden;border:1px solid var(--ops-border);border-radius:10px;background:#fff}.ops-month-label,.ops-month-stat{min-width:0;padding:13px 16px;border-right:1px solid var(--ops-border)}.ops-month-label{background:#1d2c23;color:#fff}.ops-month-label span,.ops-month-stat span{display:block;margin-bottom:3px;color:#849087;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.ops-month-label span{color:#b6c2ba}.ops-month-label strong,.ops-month-stat strong{display:block;overflow:hidden;font-size:13px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.ops-month-stat:last-child{border-right:0}
  .ops-layout{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(310px,.75fr);gap:16px;align-items:start}.ops-main-column,.ops-side-column{display:flex;flex-direction:column;gap:16px;min-width:0}.ops-panel{overflow:hidden;border:1px solid var(--ops-border);border-radius:11px;background:#fff;box-shadow:0 2px 8px rgba(20,40,27,.03)}.ops-panel-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 18px;border-bottom:1px solid var(--ops-border)}.ops-panel-header-compact{padding-top:14px;padding-bottom:14px}.ops-panel-kicker{display:block;margin-bottom:4px;color:var(--ops-green);font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.ops-panel-header h2{margin:0;color:var(--ops-ink);font-size:16px;font-weight:800;letter-spacing:-.015em}.ops-panel-header p{margin:4px 0 0;color:var(--ops-muted);font-size:12px;line-height:1.45}.ops-period{padding:5px 8px;border-radius:6px;color:#506057;background:var(--ops-subtle);font-size:10px;font-weight:800;white-space:nowrap}.ops-text-link{align-self:center;font-size:11px;white-space:nowrap}.ops-chart-wrap{position:relative;height:305px;padding:18px}.ops-chart-empty{position:absolute;inset:18px;display:grid;place-items:center;border:1px dashed #ccd6cf;border-radius:8px;color:var(--ops-muted);background:rgba(255,255,255,.9);font-size:12px;text-align:center}
  .ops-table-wrap{overflow-x:auto}.ops-table{width:100%;border-collapse:collapse}.ops-table th{padding:10px 14px;color:var(--ops-muted);background:#f8faf8;border-bottom:1px solid var(--ops-border);font-size:10px;font-weight:800;letter-spacing:.045em;text-align:left;text-transform:uppercase;white-space:nowrap}.ops-table td{padding:13px 14px;border-bottom:1px solid #edf1ee;color:#435047;font-size:12px;white-space:nowrap}.ops-table tbody tr:last-child td{border-bottom:0}.ops-table tbody tr:hover td{background:#fbfcfb}.ops-table td strong{color:var(--ops-ink);font-weight:750}.ops-numeric{text-align:right!important}.ops-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11px!important}
  .ops-record-list{display:flex;flex-direction:column}.ops-record-row{display:grid;grid-template-columns:32px minmax(0,1fr) auto;align-items:center;gap:11px;padding:12px 16px;border-bottom:1px solid #edf1ee;color:inherit;text-decoration:none}.ops-record-row:last-child{border-bottom:0}.ops-record-row:hover{background:#f9fbf9}.ops-record-mark{width:32px;height:32px;display:grid;place-items:center;border-radius:7px;color:var(--ops-green);background:var(--ops-green-soft);font-size:10px;font-weight:900}.ops-record-mark-red{color:var(--ops-red);background:var(--ops-red-soft)}.ops-record-body{min-width:0}.ops-record-body strong,.ops-record-body small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ops-record-body strong{color:var(--ops-ink);font-size:12px;font-weight:800}.ops-record-body small{margin-top:3px;color:var(--ops-muted);font-size:10px}.ops-record-row time{color:var(--ops-muted);font-size:10px;font-weight:750}
  .ops-attention-list{display:flex;flex-direction:column}.ops-attention-row{display:grid;grid-template-columns:38px minmax(0,1fr) 12px;gap:11px;align-items:center;padding:13px 16px;border-bottom:1px solid #edf1ee;color:inherit;text-decoration:none}.ops-attention-row:last-child{border-bottom:0}.ops-attention-row:hover{background:#fafbf9}.ops-attention-count{color:var(--ops-amber);font-size:18px;font-weight:800;text-align:center}.ops-attention-row strong,.ops-attention-row small{display:block}.ops-attention-row strong{color:var(--ops-ink);font-size:11px;font-weight:800}.ops-attention-row small{margin-top:3px;color:var(--ops-muted);font-size:10px;line-height:1.35}.ops-chevron{color:#97a39b;font-size:18px}
  .ops-ranking{padding:15px 16px}.ops-rank-row+.ops-rank-row{margin-top:14px}.ops-rank-meta{display:flex;justify-content:space-between;gap:10px;margin-bottom:6px;font-size:11px}.ops-rank-meta span{overflow:hidden;color:var(--ops-ink);font-weight:750;text-overflow:ellipsis;white-space:nowrap}.ops-rank-meta strong{color:var(--ops-muted);font-size:10px;white-space:nowrap}.ops-rank-bar{height:5px;overflow:hidden;border-radius:999px;background:#edf1ee}.ops-rank-bar span{display:block;height:100%;border-radius:inherit;background:#4b8a62}
  .ops-municipality-panel{overflow:visible}.ops-municipality-heading{border-radius:11px 11px 0 0}.ops-province-summary{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));border-bottom:1px solid var(--ops-border);background:#fbfcfb}.ops-province-summary article{min-width:0;padding:14px 16px;border-right:1px solid var(--ops-border)}.ops-province-summary article:last-child{border-right:0}.ops-province-summary span,.ops-province-summary small{display:block}.ops-province-summary span{color:var(--ops-muted);font-size:9px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}.ops-province-summary strong{display:block;margin:7px 0 4px;color:var(--ops-ink);font-size:21px;line-height:1;font-weight:850;letter-spacing:-.025em}.ops-province-summary strong small{display:inline;margin-left:2px;color:#8b968f;font-size:11px}.ops-province-summary>article>small{overflow:hidden;color:#7b887f;font-size:9px;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}.ops-province-summary .ops-summary-alert strong{color:var(--ops-amber)}
  .ops-municipality-toolbar{display:flex;align-items:flex-end;gap:10px;padding:12px 14px;border-bottom:1px solid var(--ops-border);background:#fff}.ops-municipality-search{position:relative;display:flex;align-items:center;min-width:230px;max-width:360px;flex:1}.ops-municipality-search svg{position:absolute;left:11px;width:15px;height:15px;fill:none;stroke:#77857c;stroke-width:2;stroke-linecap:round}.ops-municipality-search input,.ops-toolbar-field select{width:100%;height:36px;border:1px solid #d8e0da;border-radius:7px;color:var(--ops-ink);background:#fff;font:inherit;font-size:11px;outline:none}.ops-municipality-search input{padding:0 12px 0 34px}.ops-municipality-search input:focus,.ops-toolbar-field select:focus{border-color:#76a388;box-shadow:0 0 0 3px rgba(23,100,58,.08)}.ops-toolbar-field{display:grid;grid-template-columns:auto minmax(125px,auto);align-items:center;gap:7px;color:var(--ops-muted);font-size:9px;font-weight:800;text-transform:uppercase}.ops-toolbar-field select{padding:0 28px 0 9px;text-transform:none;font-weight:700}.ops-visible-count{margin-left:auto;padding-bottom:10px;color:var(--ops-muted);font-size:10px;font-weight:750;white-space:nowrap}
  .ops-municipality-table-wrap{max-height:560px;overflow:auto;border-radius:0;scrollbar-color:#b8c4bc #f3f6f4;scrollbar-width:thin}.ops-municipality-table{min-width:1220px}.ops-municipality-table th{position:sticky;top:0;z-index:1}.ops-municipality-table td{vertical-align:middle}.ops-municipality-identity{display:flex;align-items:center;gap:9px}.ops-municipality-identity>span{width:32px;height:32px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px;color:#fff;background:#294c37;font-size:10px;font-weight:900;letter-spacing:.03em}.ops-municipality-identity strong,.ops-municipality-identity small,.ops-cell-value,.ops-cell-note,.ops-cell-link{display:block}.ops-municipality-identity small,.ops-cell-note{margin-top:3px;color:var(--ops-muted);font-size:9px}.ops-cell-value{color:var(--ops-ink)!important;font-size:11px;font-weight:800}.ops-cell-link{margin-top:4px;color:var(--ops-green);font-size:9px;font-weight:800;text-decoration:none}.ops-cell-link:hover{text-decoration:underline}.ops-cell-value-row{display:flex;align-items:center;justify-content:space-between;gap:12px}.ops-cell-value-row strong{color:var(--ops-ink);font-size:11px}.ops-cell-value-row span{color:var(--ops-muted);font-size:9px}.ops-mapping-cell{min-width:150px}.ops-mini-progress{height:4px;margin-top:6px;overflow:hidden;border-radius:999px;background:#e9eeeb}.ops-mini-progress span{display:block;height:100%;border-radius:inherit;background:#3d8056}.ops-text-warning{color:var(--ops-amber)!important}.ops-status-pill{display:inline-flex;align-items:center;min-height:22px;padding:4px 7px;border-radius:999px;font-size:9px;font-weight:850;white-space:nowrap}.ops-status-operational{color:#17643a;background:#e5f3e9}.ops-status-missing-head{color:#a23b32;background:#fae9e7}.ops-status-needs-mapping{color:#96600d;background:#faf0dc}.ops-status-no-records{color:#5f6570;background:#eceef1}.ops-directory-link{display:block;margin-top:6px;font-size:9px}.ops-filter-empty{display:flex;flex-direction:column;align-items:center;padding:28px;color:var(--ops-muted);text-align:center}.ops-filter-empty[hidden]{display:none}.ops-filter-empty strong{color:var(--ops-ink);font-size:12px}.ops-filter-empty span{margin-top:4px;font-size:10px}.ops-municipality-note{padding:9px 14px;border-top:1px solid #edf1ee;border-radius:0 0 11px 11px;color:#748078;background:#fafbfa;font-size:9px}
  .ops-status-list{margin:0;padding:4px 16px}.ops-status-list>div{display:flex;justify-content:space-between;gap:15px;padding:11px 0;border-bottom:1px solid #edf1ee}.ops-status-list>div:last-child{border-bottom:0}.ops-status-list dt{color:var(--ops-muted);font-size:10px}.ops-status-list dd{margin:0;color:var(--ops-ink);font-size:10px;font-weight:800;text-align:right}.ops-panel-actions{display:flex;gap:8px;padding:12px 16px 16px}.ops-panel-actions .ops-button{flex:1}.ops-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:150px;padding:24px;color:var(--ops-muted);text-align:center}.ops-empty strong{margin-bottom:5px;color:var(--ops-ink);font-size:13px}.ops-empty span{font-size:11px}.ops-empty-small{min-height:110px;padding:16px 6px}
  @media(max-width:1180px){.ops-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ops-province-summary{grid-template-columns:repeat(3,minmax(0,1fr))}.ops-province-summary article:nth-child(3){border-right:0}.ops-province-summary article:nth-child(-n+3){border-bottom:1px solid var(--ops-border)}.ops-layout{grid-template-columns:1fr}.ops-side-column{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start}}
  @media(max-width:860px){.ops-header{align-items:flex-start;flex-direction:column}.ops-actions{justify-content:flex-start}.ops-month-strip{grid-template-columns:repeat(3,1fr)}.ops-month-label,.ops-month-stat{border-bottom:1px solid var(--ops-border)}.ops-municipality-toolbar{align-items:stretch;flex-wrap:wrap}.ops-municipality-search{max-width:none;flex-basis:100%}.ops-toolbar-field{flex:1}.ops-visible-count{margin-left:0;padding:10px 0 0}.ops-side-column{grid-template-columns:1fr}}
  @media(max-width:620px){.ops-kpi-grid,.ops-month-strip,.ops-province-summary{grid-template-columns:1fr}.ops-actions,.ops-actions .ops-button{width:100%}.ops-month-label,.ops-month-stat,.ops-province-summary article{border-right:0;border-bottom:1px solid var(--ops-border)!important}.ops-province-summary article:last-child{border-bottom:0!important}.ops-toolbar-field{grid-template-columns:1fr;flex-basis:calc(50% - 5px)}.ops-visible-count{width:100%}.ops-panel-header{align-items:flex-start}.ops-chart-wrap{height:255px;padding:12px}.ops-panel-actions{flex-direction:column}}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
    if (typeof Chart === 'undefined') return;
    const canvas = document.getElementById('chartRiceMonthly');
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    new Chart(canvas, {
      type: 'line',
      data: {
        labels: @json($charts['months'] ?? []),
        datasets: [{ label: 'Kilograms released', data: @json($charts['rice_monthly'] ?? []), borderColor: '#17643a', backgroundColor: 'rgba(23,100,58,.08)', pointBackgroundColor: '#17643a', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2, tension: .28, fill: true }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#17211b', padding: 10, titleFont: { weight: '700' }, bodyFont: { weight: '600' }, cornerRadius: 6, callbacks: { label: context => `${Number(context.raw || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })} kg` } } },
        scales: { x: { grid: { display: false }, ticks: { color: '#66736b', font: { size: 10, weight: '600' } } }, y: { beginAtZero: true, grid: { color: 'rgba(23,33,27,.07)' }, ticks: { color: '#66736b', font: { size: 10, weight: '600' } } } }
      }
    });
  })();
</script>
@endpush
