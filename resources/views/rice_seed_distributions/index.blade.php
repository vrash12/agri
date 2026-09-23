@extends('layouts.app')

@section('title', 'Agriculture & Fisheries Assistance')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .assistance-sector-switch{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:8px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface)}
    .assistance-sector-link{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:8px 16px;border:1px solid transparent;border-radius:var(--ui-radius-control);color:var(--ui-text-muted);font-size:14px;font-weight:500;text-decoration:none}
    .assistance-sector-link:hover{background:var(--ui-surface-subtle);color:var(--ui-primary)}
    .assistance-sector-link.is-active{color:var(--ui-primary);border-color:var(--ui-primary);background:var(--ui-accent-soft);font-weight:700}
    .assistance-page .module-badge-fisheries{color:var(--ui-info);background:var(--ui-info-soft)}
    .assistance-page>.module-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}
    .assistance-search-fields{display:grid;grid-template-columns:minmax(240px,2fr) repeat(2,minmax(180px,1fr));gap:16px;margin-bottom:16px}
    .assistance-search-fields .module-field{grid-column:auto}
    .assistance-search-fields.is-municipal{grid-template-columns:minmax(240px,2fr) minmax(180px,1fr)}
    .assistance-page .module-filter-summary{padding:12px 16px;border-top:1px solid var(--ui-border);background:var(--ui-surface-subtle)}
    .assistance-page .module-person-copy strong,.assistance-page .module-person-copy small{white-space:normal;overflow-wrap:anywhere}
    .assistance-page .module-row-actions{flex-wrap:wrap;white-space:normal}
    .assistance-page .module-detail-grid dd{font-size:14px;font-weight:400}
    .assistance-detail-actions{display:flex;justify-content:flex-end;padding:0 16px 16px}
    @media(max-width:1000px){.assistance-search-fields,.assistance-search-fields.is-municipal{grid-template-columns:repeat(2,minmax(0,1fr))}.assistance-search-fields .module-field-search{grid-column:1/-1}}
    @media(max-width:900px){.assistance-page>.module-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){
      .assistance-register .module-table{display:block;min-width:0}
      .assistance-register .module-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
      .assistance-register .module-table tbody{display:block}
      .assistance-register .assistance-record{display:grid;grid-template-columns:1fr 1fr;padding:16px;gap:12px;border-top:1px solid var(--ui-border)}
      .assistance-register .assistance-record:first-child{border-top:0}
      .assistance-register .assistance-record td{display:block;min-width:0;border:0;padding:0;text-align:left;overflow-wrap:anywhere}
      .assistance-record td[data-label]::before{content:attr(data-label);display:block;margin-bottom:4px;color:var(--ui-text-muted);font-size:12px;font-weight:500}
      .assistance-register .assistance-recipient,.assistance-register .assistance-actions{grid-column:1/-1}
      .assistance-register .module-person{min-width:0}
      .assistance-register .module-row-actions{justify-content:flex-start}
      .assistance-register .module-button-small{min-height:44px}
      .assistance-register .module-detail-row:not([hidden]),.assistance-register .module-detail-row>td{display:block}
      .assistance-register .module-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .assistance-register .module-detail-grid div{border-right:0}
    }
    @media(max-width:560px){.assistance-search-fields,.assistance-search-fields.is-municipal{grid-template-columns:1fr}.assistance-sector-link{flex:1 1 100%}.assistance-register .assistance-record{grid-template-columns:1fr}}
  </style>
@endpush

@php
  $charts = $charts ?? [];
  $latestReceived = $stats['latestReceived'] ?? null;
  $trendYear = $stats['trendYear'] ?? now()->year;
  $filterLabels = ['q' => 'Search', 'input_category' => 'Category', 'seed_variety_claimed' => 'Item', 'received_from' => 'From', 'received_to' => 'To', 'gender' => 'Gender', 'kgs_min' => 'Minimum quantity', 'kgs_max' => 'Maximum quantity', 'last_name' => 'Last name', 'first_name' => 'First name', 'middle_name' => 'Middle name', 'ffrs' => 'FFRS', 'farm_location' => 'Farm location', 'is_arb' => 'ARB', 'is_4ps' => '4Ps', 'is_ip' => 'IP', 'is_pwd' => 'PWD', 'is_sc' => 'SC', 'is_ofw' => 'OFW', 'farm_area_min' => 'Minimum farm area', 'farm_area_max' => 'Maximum farm area', 'dob_from' => 'Birth date from', 'dob_to' => 'Birth date to'];
  $activeFilters = collect($filterLabels)->filter(fn ($label, $key) => is_scalar(request($key)) && filled(request($key)));
  $hasFilters = $activeFilters->isNotEmpty();
  $hasMoreFilters = collect(['seed_variety_claimed', 'received_from', 'received_to', 'gender', 'kgs_min', 'kgs_max'])->contains(fn ($key) => filled(request($key))) || (int) ($perPage ?? 10) !== 10;
  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return 'Not recorded';
      try { return \Illuminate\Support\Carbon::parse($value)->format($format); }
      catch (\Throwable $e) { return 'Not recorded'; }
  };
  $canManageOperations = auth()->user()->canManageOperationalData();
  $unitShortLabels = ['kg' => 'kg', 'sack' => 'sacks', 'pack' => 'packs', 'g' => 'g', 'l' => 'L', 'ml' => 'mL', 'bottle' => 'bottles', 'piece' => 'pieces', 'set' => 'sets', 'roll' => 'rolls', 'box' => 'boxes', 'bundle' => 'bundles'];
  $selectedSector = in_array(request('assistance_sector'), ['agriculture', 'fisheries'], true) ? request('assistance_sector') : '';
  $sectorQuery = collect(request()->query())->except(['page', 'input_category'])->all();
  $workspaceQuery = array_filter(['municipality_id' => ($canChooseMunicipality ?? false) ? ($selectedMunicipalityId ?? null) : null, 'assistance_sector' => $selectedSector ?: null]);
  $scopeName = ($canChooseMunicipality ?? false)
      ? (($municipalities ?? collect())->firstWhere('id', $selectedMunicipalityId ?? null)?->name ?? auth()->user()->scopeLabel())
      : auth()->user()->scopeLabel();
@endphp

@section('content')
<div class="module-page assistance-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Agriculture and fisheries operations</div>
      <h1>Assistance distribution</h1>
      <p>{{ $canManageOperations ? 'Find a recipient, review past assistance, or record a new release.' : 'Review recipients and assistance released across your assigned area.' }}</p>
      <p class="module-scope-note">Viewing: <strong>{{ $scopeName }}</strong>@unless($canChooseMunicipality ?? false) · Assigned municipality @endunless</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('assistance-coverage.index') }}">Assistance coverage map</a>
      {{-- Guarded so the assistance register keeps working if the sheet routes are not registered. --}}
      @if(Route::has('rice-distribution-batches.index'))<a class="module-button" href="{{ route('rice-distribution-batches.index', collect($workspaceQuery)->only('municipality_id')->all()) }}"><svg viewBox="0 0 24 24"><path d="M7 3h10v18H7zM10 8h4M10 12h4M10 16h4"></path></svg>Rice seed sheets</a>@endif
      @if($canManageOperations)<a class="module-button" href="{{ route('rice-seed-distributions.import.form') }}"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 8l5-5 5 5M5 21h14"></path></svg>Import NRP workbook</a>@endif
      <a class="module-button" href="{{ route('rice-seed-distributions.export', request()->query()) }}"><svg viewBox="0 0 24 24"><path d="M12 15V3M7 10l5 5 5-5M5 21h14"></path></svg>Export CSV</a>
      @if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('rice-seed-distributions.create', ['municipality_id' => $selectedMunicipalityId ?? null, 'assistance_sector' => $selectedSector ?: null]) }}"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>Record release</a>@else<span class="module-badge module-badge-green">Read-only oversight</span>@endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <nav class="assistance-sector-switch" aria-label="Assistance sector">
    <a class="assistance-sector-link {{ $selectedSector === '' ? 'is-active' : '' }}" @if($selectedSector === '') aria-current="page" @endif href="{{ route('rice-seed-distributions.index', collect($sectorQuery)->except('assistance_sector')->all()) }}">All assistance</a>
    <a class="assistance-sector-link {{ $selectedSector === 'agriculture' ? 'is-active' : '' }}" @if($selectedSector === 'agriculture') aria-current="page" @endif href="{{ route('rice-seed-distributions.index', array_merge($sectorQuery, ['assistance_sector' => 'agriculture'])) }}">Crops &amp; farm inputs</a>
    <a class="assistance-sector-link {{ $selectedSector === 'fisheries' ? 'is-active' : '' }}" @if($selectedSector === 'fisheries') aria-current="page" @endif href="{{ route('rice-seed-distributions.index', array_merge($sectorQuery, ['assistance_sector' => 'fisheries'])) }}">Fisheries assistance</a>
  </nav>

  <section class="module-kpis module-kpis-compact" aria-label="Agriculture and fisheries assistance summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Release records</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M7 3h10v18H7zM10 7h4M10 11h4M10 15h4"></path></svg></span></div>
      <strong>{{ number_format((int) ($totalRecords ?? 0)) }}</strong>
      <small>Records matching the current filters</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Weight-based releases</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5M16 11c3 0 5-2 5-5-3 0-5 2-5 5"></path></svg></span></div>
      <strong>{{ number_format((float) ($totalKgs ?? 0), 2) }} <small>kg</small></strong>
      <small>Only releases recorded in kilograms</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Recipients served</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span></div>
      <strong>{{ number_format((int) ($uniqueRecipients ?? 0)) }}</strong>
      <small>Distinct linked farmer profiles</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Fingerlings issued</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><path d="M3 12c4-5 10-6 15-2l3-2v8l-3-2c-5 4-11 3-15-2Z"></path><circle cx="15" cy="11" r=".6"></circle></svg></span></div>
      <strong>{{ number_format((float) ($fingerlingsReleased ?? 0), 0) }} <small>pcs</small></strong>
      <small>{{ number_format((int) ($fisheriesRecords ?? 0)) }} fisheries releases · Fingerlings counted by piece</small>
    </article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Find releases</h2><p>Search by name, FFRS, or item. Use More filters for dates and quantities.</p></div></div>
    <form class="module-filter" method="GET" action="{{ route('rice-seed-distributions.index') }}">
      <input type="hidden" name="assistance_sector" value="{{ $selectedSector }}">
      <div class="assistance-search-fields {{ ($canChooseMunicipality ?? false) ? '' : 'is-municipal' }}">
        <div class="module-field module-field-search">
          <label for="riceSearch">Search recipient or item</label>
          <div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input class="module-input" id="riceSearch" type="search" name="q" value="{{ request('q') }}" placeholder="AgriGOV ID, name, FFRS, seed, fingerlings, feed, or gear"></div>
        </div>
        @if($canChooseMunicipality ?? false)
          <div class="module-field"><label for="riceMunicipality">Municipality</label><select class="module-input" id="riceMunicipality" name="municipality_id"><option value="">All municipalities</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) ($selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
        @endif
        <div class="module-field"><label for="riceInputCategory">Assistance category</label><select class="module-input" id="riceInputCategory" name="input_category"><option value="">All categories</option>@foreach(($inputCategoryOptions ?? []) as $value => $label)<option value="{{ $value }}" @selected(request('input_category') === $value)>{{ $label }}</option>@endforeach</select></div>
      </div>
      <details class="module-more" id="assistanceMoreFilters" @if($hasMoreFilters) open @endif>
        <summary>More filters @if($hasMoreFilters)<span class="module-badge">In use</span>@endif</summary>
        <div class="module-more-content"><div class="module-filter-grid">
        <div class="module-field"><label for="riceVariety">Item, species, or variety</label><input class="module-input" id="riceVariety" name="seed_variety_claimed" value="{{ request('seed_variety_claimed') }}" placeholder="Any agriculture or fisheries item"></div>
        <div class="module-field"><label for="riceFrom">Received from</label><input class="module-input" id="riceFrom" type="date" name="received_from" value="{{ request('received_from') }}"></div>
        <div class="module-field"><label for="riceTo">Received to</label><input class="module-input" id="riceTo" type="date" name="received_to" value="{{ request('received_to') }}"></div>
        <div class="module-field"><label for="riceGender">Gender</label><select class="module-input" id="riceGender" name="gender"><option value="">All genders</option>@foreach(\App\Models\Farmer::GENDERS as $gender)<option value="{{ $gender }}" @selected(request('gender') === $gender)>{{ $gender }}</option>@endforeach</select></div>
        <div class="module-field"><label for="riceKgMin">Minimum quantity</label><input class="module-input" id="riceKgMin" type="number" min="0" step="0.01" name="kgs_min" value="{{ request('kgs_min') }}" placeholder="0.00"></div>
        <div class="module-field"><label for="riceKgMax">Maximum quantity</label><input class="module-input" id="riceKgMax" type="number" min="0" step="0.01" name="kgs_max" value="{{ request('kgs_max') }}" placeholder="Any"></div>
        <div class="module-field"><label for="ricePerPage">Rows per page</label><select class="module-input" id="ricePerPage" name="per_page">@foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected((int) $perPage === $n)>{{ $n }} rows</option>@endforeach</select></div>
      </div>
        </div>
      </details>
      <div class="module-filter-actions"><span>Totals and reports follow this view.</span><div class="module-filter-buttons">@if($hasFilters)<a class="module-button" href="{{ route('rice-seed-distributions.index', $workspaceQuery) }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
    @if($hasFilters)
      <div class="module-filter-summary" aria-label="Applied filters">
        <strong>Filtered by:</strong>
        @foreach($activeFilters as $key => $label)
          @php
            $filterValue = $key === 'input_category' ? ($inputCategoryOptions[request($key)] ?? request($key)) : request($key);
            if (str_starts_with($key, 'is_') && in_array($filterValue, ['0', '1'], true)) $filterValue = $filterValue === '1' ? 'Yes' : 'No';
          @endphp
          <a class="module-filter-chip" href="{{ route('rice-seed-distributions.index', collect(request()->query())->except([$key, 'page'])->all()) }}" aria-label="Remove {{ $label }} filter: {{ $filterValue }}"><span>{{ $label }}: {{ $filterValue }}</span><span aria-hidden="true">×</span></a>
        @endforeach
      </div>
    @endif
  </section>

  <section class="module-panel assistance-register">
    <div class="module-table-tools"><div><strong>Release records</strong><span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · Most recent releases first</span></div></div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table" role="table">
          <caption class="sr-only">Assistance releases for {{ $scopeName }}. Select Details to review a release.</caption>
          <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Recipient</th><th scope="col" role="columnheader">Farm location</th><th scope="col" role="columnheader">Input issued</th><th scope="col" class="module-numeric" role="columnheader">Release</th><th scope="col" role="columnheader"><span class="sr-only">Actions</span></th></tr></thead>
          <tbody role="rowgroup">
            @foreach($records as $record)
              @php
                $name = trim($record->last_name . ', ' . $record->first_name . ' ' . ($record->middle_name ?? '') . ' ' . ($record->ext_name ?? ''));
                $initials = mb_strtoupper(mb_substr($record->first_name ?? '', 0, 1) . mb_substr($record->last_name ?? '', 0, 1));
                $eligibility = collect(['ARB' => $record->is_arb, '4Ps' => $record->is_4ps, 'IP' => $record->is_ip, 'PWD' => $record->is_pwd, 'SC' => $record->is_sc, 'OFW' => $record->is_ofw])->filter()->keys();
                $category = $record->input_category ?: 'rice_seed';
                $categoryLabel = $inputCategoryOptions[$category] ?? Str::headline($category);
                $isFisheries = $record->isFisheriesInput();
                $categoryBadge = $isFisheries ? 'module-badge-fisheries' : (str_contains($category, 'seed') ? 'module-badge-green' : ($category === 'fertilizer' ? 'module-badge-amber' : 'module-badge-blue'));
                $unit = $record->quantity_unit ?: 'kg';
                $unitLabel = $unitShortLabels[$unit] ?? $unit;
              @endphp
              <tr class="assistance-record" role="row">
                <td class="assistance-recipient" role="cell"><div class="module-person"><span class="module-avatar" aria-hidden="true">{{ $initials ?: 'FR' }}</span><span class="module-person-copy"><strong>{{ $name ?: 'Unnamed recipient' }}</strong><small>FFRS {{ $record->ffrs ?: 'not assigned' }}</small></span></div></td>
                <td data-label="Farm location" role="cell"><strong>{{ $record->farm_municipality ?: 'Municipality not recorded' }}</strong><small>{{ $record->farm_location ?: 'Farm location not recorded' }}</small></td>
                <td data-label="Input issued" role="cell"><strong>{{ $record->seed_variety_claimed ?: 'Not recorded' }}</strong><small><span class="module-badge {{ $categoryBadge }}">{{ $categoryLabel }}</span> · {{ $record->assistanceSectorLabel() }}@if(str_contains($category, 'seed')) · {{ $record->seed_class ?: 'Class not recorded' }}@endif</small></td>
                <td class="module-numeric" data-label="Release" role="cell"><strong>{{ number_format((float) $record->kgs_received, 2) }} {{ $unitLabel }}</strong><small>{{ $fmtDate($record->date_received) }}</small></td>
                <td class="assistance-actions" role="cell"><div class="module-row-actions"><button class="module-button module-button-small" type="button" data-row-detail="rice-detail-{{ $record->id }}" aria-controls="rice-detail-{{ $record->id }}" aria-expanded="false" aria-label="Details for {{ $name }}">Details</button>@if($canManageOperations)<a class="module-button module-button-small" href="{{ route('rice-seed-distributions.edit', $record) }}" aria-label="Edit release for {{ $name }}">Edit</a>@endif</div></td>
              </tr>
              <tr class="module-detail-row" id="rice-detail-{{ $record->id }}" role="row" hidden>
                <td colspan="5" role="cell"><dl class="module-detail-grid">
                  <div><dt>Gender</dt><dd>{{ $record->gender ?: 'Not recorded' }}</dd></div><div><dt>Claimed area</dt><dd>{{ $record->claimed_area_ha !== null ? number_format((float) $record->claimed_area_ha, 2).' ha' : 'Not recorded' }}</dd></div><div><dt>Claimed seeds</dt><dd>{{ $record->claimed_seeds_kg !== null ? number_format((float) $record->claimed_seeds_kg, 2).' kg' : 'Not recorded' }}</dd></div><div><dt>Eligibility</dt><dd>{{ $eligibility->implode(', ') ?: 'None recorded' }}</dd></div>
                  <div><dt>Contact</dt><dd>{{ $record->contact_number ?: '—' }}</dd></div><div><dt>Date of birth</dt><dd>{{ $fmtDate($record->date_of_birth) }}</dd></div><div><dt>Farm area</dt><dd>{{ $record->farm_area_ha !== null ? number_format((float) $record->farm_area_ha, 2).' ha' : '—' }}</dd></div><div><dt>Ecosystem</dt><dd>{{ $record->ecosystem ?: '—' }}</dd></div><div><dt>Ecosystem source</dt><dd>{{ $record->ecosystem_source ?: '—' }}</dd></div>
                  <div><dt>Assistance sector</dt><dd>{{ $record->assistanceSectorLabel() }}</dd></div><div><dt>Lot / batch</dt><dd>{{ $record->lot_series ?: '—' }}</dd></div><div><dt>Release notes</dt><dd>{{ $record->input_notes ?: '—' }}</dd></div><div><dt>Sowing schedule</dt><dd>{{ $record->date_of_sowing_label ?: 'Not applicable / not recorded' }}</dd></div><div><dt>Average bag weight</dt><dd>{{ $record->avg_weight_per_bag_kg !== null ? $record->avg_weight_per_bag_kg.' kg' : '—' }}</dd></div><div><dt>Production</dt><dd>{{ $record->total_production_bags !== null ? number_format($record->total_production_bags).' bags' : '—' }}</dd></div><div><dt>Harvested area</dt><dd>{{ $record->avg_area_harvested_ha !== null ? number_format((float) $record->avg_area_harvested_ha, 2).' ha' : '—' }}</dd></div>
                  <div><dt>Variety planted</dt><dd>{{ $record->seed_variety_planted ?: '—' }}</dd></div><div><dt>Province</dt><dd>{{ $record->farm_province ?: '—' }}</dd></div>
                </dl>
                @if($canManageOperations)<div class="assistance-detail-actions"><form method="POST" action="{{ route('rice-seed-distributions.destroy', $record) }}" onsubmit="return confirm('Delete this distribution record?')">@csrf @method('DELETE')<button class="module-button module-button-danger module-button-small" type="submit" aria-label="Delete release for {{ $name }}">Delete release</button></form></div>@endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5M16 11c3 0 5-2 5-5-3 0-5 2-5 5"></path></svg></span><strong>{{ $hasFilters ? 'No matching releases' : 'No releases in this view yet' }}</strong><span>{{ $hasFilters ? 'Try a different name or item, or clear the filters to see other releases in this workspace.' : 'Releases recorded for this workspace will appear here.' }}</span>@if($hasFilters)<a class="module-button" href="{{ route('rice-seed-distributions.index', $workspaceQuery) }}">Clear filters</a>@elseif($canManageOperations)<a class="module-button module-button-primary" href="{{ route('rice-seed-distributions.create', $workspaceQuery) }}">Record release</a>@endif</div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'distribution record'])
  </section>
  <details class="module-more" id="assistanceReports">
    <summary>Reports — assistance totals and trends</summary>
    <div class="module-more-content">
      <p class="module-hint" data-report-status role="status">Charts load when you open Reports. Figures are also available in tables.</p>
      <p class="module-hint">Average weight-based release: {{ number_format((float) ($averageKgs ?? 0), 2) }} kg. Fisheries release records: {{ number_format((int) ($fisheriesRecords ?? 0)) }}. Latest release: {{ $fmtDate($latestReceived) }}.</p>
      <div class="module-analytics-grid">
        <article class="module-chart">
          <div class="module-chart-head"><h3>Monthly weight-based trend</h3></div>
          <div class="module-chart-body"><canvas id="riceMonthlyChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Monthly weight-based trend', 'reportLabels' => $charts['monthly_labels'] ?? [], 'reportValues' => $charts['monthly_values'] ?? [], 'reportUnit' => 'Kilograms in '. $trendYear, 'reportDecimals' => 2])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Leading rice seed varieties</h3></div>
          <div class="module-chart-body"><canvas id="riceVarietyChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Leading rice seed varieties', 'reportLabels' => $charts['seed_variety_labels'] ?? [], 'reportValues' => $charts['seed_variety_values'] ?? [], 'reportUnit' => 'Kilograms', 'reportDecimals' => 2])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Assistance category mix</h3></div>
          <div class="module-chart-body"><canvas id="riceInputCategoryChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Assistance category mix', 'reportLabels' => $charts['input_category_labels'] ?? [], 'reportValues' => $charts['input_category_values'] ?? [], 'reportUnit' => 'Release records', 'reportDecimals' => 0])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Top farm locations</h3></div>
          <div class="module-chart-body"><canvas id="riceLocationChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Top farm locations', 'reportLabels' => $charts['toploc_labels'] ?? [], 'reportValues' => $charts['toploc_values'] ?? [], 'reportUnit' => 'Kilograms', 'reportDecimals' => 2])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Farm area by municipality</h3></div>
          <div class="module-chart-body"><canvas id="riceAreaChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Farm area by municipality', 'reportLabels' => $charts['area_mun_labels'] ?? [], 'reportValues' => $charts['area_mun_values'] ?? [], 'reportUnit' => 'Hectares', 'reportDecimals' => 2])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Recipient gender</h3></div>
          <div class="module-chart-body"><canvas id="riceGenderChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Recipient gender', 'reportLabels' => $charts['gender_labels'] ?? [], 'reportValues' => $charts['gender_values'] ?? [], 'reportUnit' => 'Release records', 'reportDecimals' => 0])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Recipient age groups</h3></div>
          <div class="module-chart-body"><canvas id="riceAgeChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Recipient age groups', 'reportLabels' => $charts['age_labels'] ?? [], 'reportValues' => $charts['age_values'] ?? [], 'reportUnit' => 'Release records', 'reportDecimals' => 0])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Crop establishment</h3></div>
          <div class="module-chart-body"><canvas id="riceCropChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Crop establishment', 'reportLabels' => $charts['crop_est_labels'] ?? [], 'reportValues' => $charts['crop_est_values'] ?? [], 'reportUnit' => 'Release records', 'reportDecimals' => 0])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Leading planted varieties</h3></div>
          <div class="module-chart-body"><canvas id="riceYieldChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Leading planted varieties', 'reportLabels' => $charts['yield_variety_labels'] ?? [], 'reportValues' => $charts['yield_variety_values'] ?? [], 'reportUnit' => 'Production bags', 'reportDecimals' => 0])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Seed class</h3></div>
          <div class="module-chart-body"><canvas id="riceClassChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Seed class', 'reportLabels' => $charts['seed_class_labels'] ?? [], 'reportValues' => $charts['seed_class_values'] ?? [], 'reportUnit' => 'Release records', 'reportDecimals' => 0])
        </article>
        <article class="module-chart">
          <div class="module-chart-head"><h3>Eligibility groups</h3></div>
          <div class="module-chart-body"><canvas id="riceEligibilityChart" aria-hidden="true"></canvas></div>
          @include('partials.operational-report-figures', ['reportTitle' => 'Eligibility groups', 'reportLabels' => $charts['elig_labels'] ?? [], 'reportValues' => $charts['elig_values'] ?? [], 'reportUnit' => 'Tagged release records', 'reportDecimals' => 0])
        </article>
      </div>
    </div>
  </details>

</div>
@endsection

@push('scripts')
<script>
(() => {
  document.querySelectorAll('[data-row-detail]').forEach(button => {
    button.addEventListener('click', () => {
      const detail = document.getElementById(button.dataset.rowDetail);
      if (!detail) return;
      const opening = detail.hidden;
      detail.hidden = !opening;
      button.setAttribute('aria-expanded', String(opening));
      button.textContent = opening ? 'Hide' : 'Details';
    });
  });

  document.getElementById('assistanceReports').renderOperationalCharts = () => {
  const charts = @json($charts);
  const grid = 'rgba(23,33,27,.07)';
  const ticks = { color:'#68756d', font:{ size:12, weight:'600' } };
  const baseOptions = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'#17211b', padding:9, cornerRadius:6 } }, scales:{ x:{ grid:{ display:false }, ticks }, y:{ beginAtZero:true, grid:{ color:grid }, ticks } } };
  const create = (id, type, labels, data, options = {}, color = '#17643a') => {
    const canvas = document.getElementById(id); if (!canvas) return;
    new Chart(canvas, { type, data:{ labels:labels || [], datasets:[{ data:data || [], backgroundColor:type === 'line' ? 'rgba(23,100,58,.09)' : color, borderColor:color, borderWidth:type === 'line' ? 2 : 0, pointRadius:3, tension:.28, fill:type === 'line' }] }, options:{ ...baseOptions, ...options } });
  };
  create('riceMonthlyChart','line',charts.monthly_labels,charts.monthly_values);
  create('riceVarietyChart','bar',charts.seed_variety_labels,charts.seed_variety_values,{ ...baseOptions, indexAxis:'y' },'#3f8659');
  create('riceInputCategoryChart','doughnut',charts.input_category_labels,charts.input_category_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:12}}}} },['#17643a','#d5a034','#3575b5','#7b68a6','#8d9690','#9c6549','#2f7891','#4b91aa','#5c7f91','#70a5ae','#5c8795']);
  create('riceLocationChart','bar',charts.toploc_labels,charts.toploc_values,{ ...baseOptions, indexAxis:'y' },'#b47a19');
  create('riceAreaChart','bar',charts.area_mun_labels,charts.area_mun_values,{ ...baseOptions, indexAxis:'y' },'#3575b5');
  create('riceGenderChart','doughnut',charts.gender_labels,charts.gender_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:12}}}} },['#3575b5','#d5a034','#85928a']);
  create('riceAgeChart','bar',charts.age_labels,charts.age_values,{},'#588d6a');
  create('riceCropChart','doughnut',charts.crop_est_labels,charts.crop_est_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:12}}}} },['#17643a','#d5a034']);
  create('riceYieldChart','bar',charts.yield_variety_labels,charts.yield_variety_values,{ ...baseOptions, indexAxis:'y' },'#6b75aa');
  create('riceClassChart','doughnut',charts.seed_class_labels,charts.seed_class_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:12}}}} },['#17643a','#d5a034','#8d9690']);
  create('riceEligibilityChart','bar',charts.elig_labels,charts.elig_values,{},'#4d8762');
  };
})();
</script>
@include('partials.operational-report-loader', ['reportId' => 'assistanceReports'])
@endpush
