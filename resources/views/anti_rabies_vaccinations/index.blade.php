@extends('layouts.app')

@section('title', 'Anti-Rabies Vaccination')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@php
  $hasFilters = filled($q ?? '') || filled($barangay ?? '') || filled($petType ?? '')
      || filled($year ?? '') || filled($selectedMunicipalityId ?? '')
      || (int) ($perPage ?? 20) !== 20;
  $dogCount = (int) ($petTypeChartData[0] ?? 0);
  $catCount = (int) ($petTypeChartData[1] ?? 0);
  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return 'Not recorded';
      try { return \Illuminate\Support\Carbon::parse($value)->format($format); }
      catch (\Throwable $e) { return 'Not recorded'; }
  };
  $canManageOperations = auth()->user()->canManageOperationalData();
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Animal health services</div>
      <h1>Anti-rabies vaccination</h1>
      <p>Register animal-health services, reuse existing owner and pet information, and monitor vaccination activity by area.</p>
    </div>
    <div class="module-actions">
      @if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('anti-rabies-vaccinations.create', ['municipality_id' => $selectedMunicipalityId ?? null]) }}"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>Record vaccination</a>@else<span class="module-badge module-badge-green">Read-only oversight</span>@endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif

  <section class="module-kpis" aria-label="Vaccination summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Vaccinations</span><span class="module-kpi-icon module-kpi-icon-red"><svg viewBox="0 0 24 24"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8l2-4Z"></path><path d="M9 11h6M12 8v6"></path></svg></span></div>
      <strong>{{ number_format((int) ($totalVaccinations ?? 0)) }}</strong>
      <small>Records matching the current filters</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">This month</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v14H4zM8 3v6M16 3v6M4 10h16"></path></svg></span></div>
      <strong>{{ number_format((int) ($currentMonthVaccinations ?? 0)) }}</strong>
      <small>{{ now()->format('F Y') }} service activity</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Owners served</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span></div>
      <strong>{{ number_format((int) ($uniqueOwners ?? 0)) }}</strong>
      <small>Distinct owner names</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Animals registered</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="2"></circle><circle cx="16" cy="8" r="2"></circle><circle cx="5" cy="13" r="2"></circle><circle cx="19" cy="13" r="2"></circle><path d="M8 19c0-3 2-5 4-5s4 2 4 5c-2 2-6 2-8 0Z"></path></svg></span></div>
      <strong>{{ number_format((int) ($uniquePets ?? 0)) }}</strong>
      <small>{{ number_format($dogCount) }} dogs · {{ number_format($catCount) }} cats · latest {{ $fmtDate($latestVaccinationDate, 'M d') }}</small>
    </article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Find vaccination records</h2><p>Search owner or pet information and narrow service records by location, animal type, and year.</p></div>@if($hasFilters)<span class="module-panel-tag">Filtered view</span>@endif</div>
    <form class="module-filter" method="GET" action="{{ route('anti-rabies-vaccinations.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search"><label for="vaccinationSearch">Search</label><div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input class="module-input" id="vaccinationSearch" type="search" name="q" value="{{ $q ?? '' }}" placeholder="Owner, pet, or breed"></div></div>
        @if($canChooseMunicipality ?? false)
          <div class="module-field"><label for="vaccinationMunicipality">Municipality</label><select class="module-input" id="vaccinationMunicipality" name="municipality_id"><option value="">All municipalities</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) ($selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
        @endif
        <div class="module-field"><label for="vaccinationPetType">Animal type</label><select class="module-input" id="vaccinationPetType" name="pet_type"><option value="">Dogs and cats</option>@foreach(($petTypeOptions ?? ['Dog','Cat']) as $type)<option value="{{ $type }}" @selected(($petType ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationBarangay">Barangay</label><select class="module-input" id="vaccinationBarangay" name="barangay"><option value="">All barangays</option>@foreach(($barangayOptions ?? []) as $option)<option value="{{ $option }}" @selected(($barangay ?? '') === $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationYear">Year</label><select class="module-input" id="vaccinationYear" name="year"><option value="">All years</option>@foreach(($yearOptions ?? []) as $option)<option value="{{ $option }}" @selected((string) ($year ?? '') === (string) $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationPerPage">Rows per page</label><select class="module-input" id="vaccinationPerPage" name="per_page">@foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected((int) $perPage === $n)>{{ $n }} rows</option>@endforeach</select></div>
      </div>
      <div class="module-filter-actions"><span>@if($hasFilters)<span class="module-active-filter">Summary and charts reflect these filters</span>@else Showing all vaccination records in your access scope @endif</span><div class="module-filter-buttons">@if($hasFilters)<a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
  </section>

  <section class="module-analytics-grid" aria-label="Vaccination analytics">
    <article class="module-chart"><div class="module-chart-head"><h3>Monthly vaccination trend</h3><p>Service activity during {{ $chartYear ?? now()->year }}</p></div><div class="module-chart-body"><canvas id="vaccinationMonthlyChart"></canvas>@if(collect($monthlyChartData ?? [])->sum() <= 0)<div class="module-chart-empty">No vaccination activity for this year.</div>@endif</div></article>
    <article class="module-chart"><div class="module-chart-head"><h3>Dogs and cats served</h3><p>Vaccination record distribution by animal type</p></div><div class="module-chart-body"><canvas id="vaccinationPetChart"></canvas>@if(collect($petTypeChartData ?? [])->sum() <= 0)<div class="module-chart-empty">No animal-type data for this view.</div>@endif</div></article>
  </section>

  <details class="module-more">
    <summary>Open detailed animal-health analytics</summary>
    <div class="module-more-content"><div class="module-analytics-grid">
      <article class="module-chart"><div class="module-chart-head"><h3>Vaccinations by year</h3><p>Long-term service activity</p></div><div class="module-chart-body"><canvas id="vaccinationYearChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Leading barangays</h3><p>Top ten service locations</p></div><div class="module-chart-body"><canvas id="vaccinationBarangayChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Common breeds</h3><p>Top ten recorded breeds</p></div><div class="module-chart-body"><canvas id="vaccinationBreedChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Owner age groups</h3><p>Based on recorded birthdays</p></div><div class="module-chart-body"><canvas id="vaccinationAgeChart"></canvas></div></article>
    </div></div>
  </details>

  <section class="module-panel">
    <div class="module-table-tools"><div><strong>Vaccination register</strong><span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · newest services first</span></div></div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead><tr><th>Animal</th><th>Owner</th><th>Barangay</th><th>Breed and color</th><th>Vaccination date</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach($records as $record)
              @php $initials = mb_strtoupper(mb_substr($record->pet_name ?? $record->pet_type ?? 'P', 0, 2)); @endphp
              <tr>
                <td><div class="module-person"><span class="module-avatar">{{ $initials }}</span><span class="module-person-copy"><strong>{{ $record->pet_name ?: 'Unnamed animal' }}</strong><small><span class="module-badge {{ $record->pet_type === 'Dog' ? 'module-badge-green' : 'module-badge-blue' }}">{{ $record->pet_type ?: 'Type unavailable' }}</span></small></span></div></td>
                <td><strong>{{ $record->owner_name }}</strong><small>Birthday: {{ $fmtDate($record->birthday) }}</small></td>
                <td><strong>{{ $record->barangay ?: 'Not recorded' }}</strong></td>
                <td><strong>{{ $record->pet_breed ?: 'Breed not recorded' }}</strong><small>{{ $record->pet_color ?: 'Color not recorded' }}</small></td>
                <td><strong>{{ $fmtDate($record->vaccination_date) }}</strong><small>{{ $record->vaccination_date ? $record->vaccination_date->diffForHumans() : 'Date unavailable' }}</small></td>
                <td>@if($canManageOperations)<div class="module-row-actions"><a class="module-button module-button-small" href="{{ route('anti-rabies-vaccinations.edit', $record) }}">Edit</a><form method="POST" action="{{ route('anti-rabies-vaccinations.destroy', $record) }}" onsubmit="return confirm('Delete this vaccination record?')">@csrf @method('DELETE')<button class="module-button module-button-danger module-button-small" type="submit">Delete</button></form></div>@else<span class="module-badge module-badge-green">Read only</span>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8l2-4Z"></path><path d="M9 11h6M12 8v6"></path></svg></span><strong>No vaccination records found</strong><span>{{ $hasFilters ? 'Clear or adjust the current filters to find other services.' : 'No anti-rabies services have been recorded yet.' }}</span>@if(!$hasFilters && $canManageOperations)<a class="module-button module-button-primary" href="{{ route('anti-rabies-vaccinations.create') }}">Record vaccination</a>@endif</div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'vaccination record'])
  </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  if (typeof Chart === 'undefined') return;
  const grid = 'rgba(23,33,27,.07)';
  const ticks = { color:'#68756d', font:{ size:9, weight:'600' } };
  const cartesian = { responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'#17211b',padding:9,cornerRadius:6}},scales:{x:{grid:{display:false},ticks},y:{beginAtZero:true,grid:{color:grid},ticks}} };
  const make = (id,type,labels,data,color='#17643a',options=cartesian) => { const canvas=document.getElementById(id); if(!canvas)return; new Chart(canvas,{type,data:{labels,datasets:[{data,backgroundColor:type==='line'?'rgba(23,100,58,.09)':color,borderColor:color,borderWidth:type==='line'?2:0,pointRadius:3,tension:.28,fill:type==='line'}]},options}); };
  make('vaccinationMonthlyChart','line',@json($monthlyChartLabels ?? []),@json($monthlyChartData ?? []));
  make('vaccinationPetChart','doughnut',@json($petTypeChartLabels ?? []),@json($petTypeChartData ?? []),['#3978b5','#d3a03a'],{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}}}});
  make('vaccinationYearChart','bar',@json($yearChartLabels ?? []),@json($yearChartData ?? []),'#4c8b63');
  make('vaccinationBarangayChart','bar',@json($barangayChartLabels ?? []),@json($barangayChartData ?? []),'#3978b5',{...cartesian,indexAxis:'y'});
  make('vaccinationBreedChart','bar',@json($breedChartLabels ?? []),@json($breedChartData ?? []),'#b67b22',{...cartesian,indexAxis:'y'});
  make('vaccinationAgeChart','bar',@json($ageChartLabels ?? []),@json($ageChartData ?? []),'#7a6da8');
})();
</script>
@endpush
