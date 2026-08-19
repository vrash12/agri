@extends('layouts.app')

@section('title', 'Rice Seed Distribution')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@php
  $charts = $charts ?? [];
  $latestReceived = $stats['latestReceived'] ?? null;
  $trendYear = $stats['trendYear'] ?? now()->year;
  $hasFilters = collect([
      'q', 'municipality_id', 'seed_variety_claimed', 'received_from',
      'received_to', 'gender', 'kgs_min', 'kgs_max'
  ])->contains(fn ($key) => filled(request($key))) || (int) ($perPage ?? 10) !== 10;
  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return 'No releases yet';
      try { return \Illuminate\Support\Carbon::parse($value)->format($format); }
      catch (\Throwable $e) { return 'Not recorded'; }
  };
  $canManageOperations = auth()->user()->canManageOperationalData();
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Rice program operations</div>
      <h1>Rice seed distribution</h1>
      <p>Record releases, verify recipient details, monitor seed utilization, and export the current filtered dataset.</p>
    </div>
    <div class="module-actions">
      @if($canManageOperations)<a class="module-button" href="{{ route('rice-seed-distributions.import.form') }}"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 8l5-5 5 5M5 21h14"></path></svg>Import workbook</a>@endif
      <a class="module-button" href="{{ route('rice-seed-distributions.export', request()->query()) }}"><svg viewBox="0 0 24 24"><path d="M12 15V3M7 10l5 5 5-5M5 21h14"></path></svg>Export CSV</a>
      @if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('rice-seed-distributions.create', ['municipality_id' => $selectedMunicipalityId ?? null]) }}"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>Record release</a>@else<span class="module-badge module-badge-green">Read-only oversight</span>@endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <section class="module-kpis" aria-label="Rice distribution summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Release records</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M7 3h10v18H7zM10 7h4M10 11h4M10 15h4"></path></svg></span></div>
      <strong>{{ number_format((int) ($totalRecords ?? 0)) }}</strong>
      <small>Records matching the current filters</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Seed released</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5M16 11c3 0 5-2 5-5-3 0-5 2-5 5"></path></svg></span></div>
      <strong>{{ number_format((float) ($totalKgs ?? 0), 2) }} <small>kg</small></strong>
      <small>Total distribution volume</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Recipients served</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span></div>
      <strong>{{ number_format((int) ($uniqueRecipients ?? 0)) }}</strong>
      <small>Distinct linked farmer profiles</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Average release</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"></path></svg></span></div>
      <strong>{{ number_format((float) ($averageKgs ?? 0), 2) }} <small>kg</small></strong>
      <small>Latest release: {{ $fmtDate($latestReceived) }}</small>
    </article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Find distribution records</h2><p>Use a broad search or narrow by municipality, variety, date, gender, and quantity.</p></div>@if($hasFilters)<span class="module-panel-tag">Filtered view</span>@endif</div>
    <form class="module-filter" method="GET" action="{{ route('rice-seed-distributions.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search">
          <label for="riceSearch">Search recipient</label>
          <div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input class="module-input" id="riceSearch" type="search" name="q" value="{{ request('q') }}" placeholder="Name, FFRS, or farm location"></div>
        </div>
        @if($canChooseMunicipality ?? false)
          <div class="module-field"><label for="riceMunicipality">Municipality</label><select class="module-input" id="riceMunicipality" name="municipality_id"><option value="">All municipalities</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) ($selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
        @endif
        <div class="module-field"><label for="riceVariety">Seed variety</label><select class="module-input" id="riceVariety" name="seed_variety_claimed"><option value="">All varieties</option>@foreach(($seedVarietyClaimedOptions ?? []) as $variety)<option value="{{ $variety }}" @selected(request('seed_variety_claimed') === $variety)>{{ $variety }}</option>@endforeach</select></div>
        <div class="module-field"><label for="riceFrom">Received from</label><input class="module-input" id="riceFrom" type="date" name="received_from" value="{{ request('received_from') }}"></div>
        <div class="module-field"><label for="riceTo">Received to</label><input class="module-input" id="riceTo" type="date" name="received_to" value="{{ request('received_to') }}"></div>
        <div class="module-field"><label for="riceGender">Gender</label><select class="module-input" id="riceGender" name="gender"><option value="">All genders</option>@foreach(['Male','Female','Other'] as $gender)<option value="{{ $gender }}" @selected(request('gender') === $gender)>{{ $gender }}</option>@endforeach</select></div>
        <div class="module-field"><label for="riceKgMin">Minimum kg</label><input class="module-input" id="riceKgMin" type="number" min="0" step="0.01" name="kgs_min" value="{{ request('kgs_min') }}" placeholder="0.00"></div>
        <div class="module-field"><label for="riceKgMax">Maximum kg</label><input class="module-input" id="riceKgMax" type="number" min="0" step="0.01" name="kgs_max" value="{{ request('kgs_max') }}" placeholder="Any"></div>
        <div class="module-field"><label for="ricePerPage">Rows per page</label><select class="module-input" id="ricePerPage" name="per_page">@foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected((int) $perPage === $n)>{{ $n }} rows</option>@endforeach</select></div>
      </div>
      <div class="module-filter-actions"><span>@if($hasFilters)<span class="module-active-filter">Totals and charts reflect these filters</span>@else Totals and charts reflect all accessible records @endif</span><div class="module-filter-buttons">@if($hasFilters)<a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
  </section>

  <section class="module-analytics-grid" aria-label="Rice program analytics">
    <article class="module-chart">
      <div class="module-chart-head"><h3>Monthly release trend</h3><p>Kilograms distributed during {{ $trendYear }}</p></div>
      <div class="module-chart-body"><canvas id="riceMonthlyChart"></canvas>@if(collect($charts['monthly_values'] ?? [])->sum() <= 0)<div class="module-chart-empty">No releases recorded for {{ $trendYear }}.</div>@endif</div>
    </article>
    <article class="module-chart">
      <div class="module-chart-head"><h3>Leading seed varieties</h3><p>Top claimed varieties by released kilograms</p></div>
      <div class="module-chart-body"><canvas id="riceVarietyChart"></canvas>@if(collect($charts['seed_variety_values'] ?? [])->sum() <= 0)<div class="module-chart-empty">No seed variety data for this view.</div>@endif</div>
    </article>
  </section>

  <details class="module-more">
    <summary>Open detailed program analytics</summary>
    <div class="module-more-content">
      <div class="module-analytics-grid">
        @foreach([
          ['riceLocationChart','Top farm locations','Kilograms by farm location'],
          ['riceAreaChart','Farm area by municipality','Total recorded hectares'],
          ['riceGenderChart','Recipient gender','Distribution record count'],
          ['riceAgeChart','Recipient age groups','Distribution record count'],
          ['riceCropChart','Crop establishment','Direct and transplanted methods'],
          ['riceYieldChart','Leading planted varieties','Total production bags'],
          ['riceClassChart','Seed class','Distribution record count'],
          ['riceEligibilityChart','Eligibility groups','Tagged recipient records']
        ] as [$id,$title,$subtitle])
          <article class="module-chart"><div class="module-chart-head"><h3>{{ $title }}</h3><p>{{ $subtitle }}</p></div><div class="module-chart-body"><canvas id="{{ $id }}"></canvas></div></article>
        @endforeach
      </div>
    </div>
  </details>

  <section class="module-panel">
    <div class="module-table-tools"><div><strong>Distribution register</strong><span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · open details for monitoring fields</span></div></div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead><tr><th>Recipient</th><th>FFRS / RSBSA</th><th>Farm location</th><th>Seed issued</th><th class="module-numeric">Release</th><th>Claim details</th><th>Eligibility</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach($records as $record)
              @php
                $name = trim($record->last_name . ', ' . $record->first_name . ' ' . ($record->middle_name ?? '') . ' ' . ($record->ext_name ?? ''));
                $initials = mb_strtoupper(mb_substr($record->first_name ?? '', 0, 1) . mb_substr($record->last_name ?? '', 0, 1));
                $eligibility = collect(['ARB' => $record->is_arb, '4Ps' => $record->is_4ps, 'IP' => $record->is_ip, 'PWD' => $record->is_pwd, 'SC' => $record->is_sc, 'OFW' => $record->is_ofw])->filter()->keys();
              @endphp
              <tr>
                <td><div class="module-person"><span class="module-avatar">{{ $initials ?: 'FR' }}</span><span class="module-person-copy"><strong>{{ $name ?: 'Unnamed recipient' }}</strong><small>{{ $record->gender ?: 'Gender not recorded' }}</small></span></div></td>
                <td class="module-mono"><strong>{{ $record->ffrs ?: 'Not assigned' }}</strong></td>
                <td><strong>{{ $record->farm_municipality ?: 'Municipality not recorded' }}</strong><small>{{ $record->farm_location ?: 'Farm location not recorded' }}</small></td>
                <td><strong>{{ $record->seed_variety_claimed ?: 'Not recorded' }}</strong><small>{{ $record->seed_class ?: 'Class not recorded' }} · {{ $record->crop_establishment ?: 'Method not recorded' }}</small></td>
                <td class="module-numeric"><strong>{{ number_format((float) $record->kgs_received, 2) }} kg</strong><small>{{ $fmtDate($record->date_received) }}</small></td>
                <td><strong>{{ $record->claimed_area_ha !== null ? number_format((float) $record->claimed_area_ha, 2).' ha' : '—' }}</strong><small>{{ $record->claimed_seeds_kg !== null ? number_format((float) $record->claimed_seeds_kg, 2).' kg claimed' : 'Claimed seeds not recorded' }}</small></td>
                <td><div class="module-badges">@forelse($eligibility as $tag)<span class="module-badge module-badge-green">{{ $tag }}</span>@empty<span class="module-badge">None</span>@endforelse</div></td>
                <td><div class="module-row-actions"><button class="module-button module-button-small" type="button" data-row-detail="rice-detail-{{ $record->id }}" aria-expanded="false">Details</button>@if($canManageOperations)<a class="module-button module-button-small" href="{{ route('rice-seed-distributions.edit', $record) }}">Edit</a><form method="POST" action="{{ route('rice-seed-distributions.destroy', $record) }}" onsubmit="return confirm('Delete this rice distribution record?')">@csrf @method('DELETE')<button class="module-button module-button-danger module-button-small" type="submit">Delete</button></form>@endif</div></td>
              </tr>
              <tr class="module-detail-row" id="rice-detail-{{ $record->id }}" hidden>
                <td colspan="8"><dl class="module-detail-grid">
                  <div><dt>Contact</dt><dd>{{ $record->contact_number ?: '—' }}</dd></div><div><dt>Date of birth</dt><dd>{{ $fmtDate($record->date_of_birth) }}</dd></div><div><dt>Farm area</dt><dd>{{ $record->farm_area_ha !== null ? number_format((float) $record->farm_area_ha, 2).' ha' : '—' }}</dd></div><div><dt>Ecosystem</dt><dd>{{ $record->ecosystem ?: '—' }}</dd></div><div><dt>Ecosystem source</dt><dd>{{ $record->ecosystem_source ?: '—' }}</dd></div>
                  <div><dt>Lot series</dt><dd>{{ $record->lot_series ?: '—' }}</dd></div><div><dt>Sowing schedule</dt><dd>{{ $record->date_of_sowing_label ?: '—' }}</dd></div><div><dt>Average bag weight</dt><dd>{{ $record->avg_weight_per_bag_kg !== null ? $record->avg_weight_per_bag_kg.' kg' : '—' }}</dd></div><div><dt>Production</dt><dd>{{ $record->total_production_bags !== null ? number_format($record->total_production_bags).' bags' : '—' }}</dd></div><div><dt>Harvested area</dt><dd>{{ $record->avg_area_harvested_ha !== null ? number_format((float) $record->avg_area_harvested_ha, 2).' ha' : '—' }}</dd></div>
                  <div><dt>Variety planted</dt><dd>{{ $record->seed_variety_planted ?: '—' }}</dd></div><div><dt>Province</dt><dd>{{ $record->farm_province ?: '—' }}</dd></div>
                </dl></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M12 21V9M8 13c-3 0-5-2-5-5 3 0 5 2 5 5M16 11c3 0 5-2 5-5-3 0-5 2-5 5"></path></svg></span><strong>No distribution records found</strong><span>{{ $hasFilters ? 'Clear or adjust the current filters to find other releases.' : 'No seed releases have been recorded yet.' }}</span>@if(!$hasFilters && $canManageOperations)<a class="module-button module-button-primary" href="{{ route('rice-seed-distributions.create') }}">Record release</a>@endif</div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'distribution record'])
  </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

  if (typeof Chart === 'undefined') return;
  const charts = @json($charts);
  const grid = 'rgba(23,33,27,.07)';
  const ticks = { color:'#68756d', font:{ size:9, weight:'600' } };
  const baseOptions = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'#17211b', padding:9, cornerRadius:6 } }, scales:{ x:{ grid:{ display:false }, ticks }, y:{ beginAtZero:true, grid:{ color:grid }, ticks } } };
  const create = (id, type, labels, data, options = {}, color = '#17643a') => {
    const canvas = document.getElementById(id); if (!canvas) return;
    new Chart(canvas, { type, data:{ labels:labels || [], datasets:[{ data:data || [], backgroundColor:type === 'line' ? 'rgba(23,100,58,.09)' : color, borderColor:color, borderWidth:type === 'line' ? 2 : 0, pointRadius:3, tension:.28, fill:type === 'line' }] }, options:{ ...baseOptions, ...options } });
  };
  create('riceMonthlyChart','line',charts.monthly_labels,charts.monthly_values);
  create('riceVarietyChart','bar',charts.seed_variety_labels,charts.seed_variety_values,{ ...baseOptions, indexAxis:'y' },'#3f8659');
  create('riceLocationChart','bar',charts.toploc_labels,charts.toploc_values,{ ...baseOptions, indexAxis:'y' },'#b47a19');
  create('riceAreaChart','bar',charts.area_mun_labels,charts.area_mun_values,{ ...baseOptions, indexAxis:'y' },'#3575b5');
  create('riceGenderChart','doughnut',charts.gender_labels,charts.gender_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}}} },['#3575b5','#d5a034','#85928a']);
  create('riceAgeChart','bar',charts.age_labels,charts.age_values,{},'#588d6a');
  create('riceCropChart','doughnut',charts.crop_est_labels,charts.crop_est_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}}} },['#17643a','#d5a034']);
  create('riceYieldChart','bar',charts.yield_variety_labels,charts.yield_variety_values,{ ...baseOptions, indexAxis:'y' },'#6b75aa');
  create('riceClassChart','doughnut',charts.seed_class_labels,charts.seed_class_values,{ responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}}} },['#17643a','#d5a034','#8d9690']);
  create('riceEligibilityChart','bar',charts.elig_labels,charts.elig_values,{},'#4d8762');
})();
</script>
@endpush
