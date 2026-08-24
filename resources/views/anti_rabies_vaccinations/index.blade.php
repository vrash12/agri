@extends('layouts.app')

@section('title', 'Animal Health Services')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>.animal-health-kpis{grid-template-columns:repeat(5,minmax(0,1fr))}.animal-service-badge-vaccination{color:#17643a;background:#e7f4eb}.animal-service-badge-deworming{color:#8b641c;background:#fbf2dc}.animal-service-badge-vitamins{color:#2d6594;background:#e8f2fb}.animal-service-badge-treatment{color:#8e4440;background:#fbeceb}@media(max-width:1000px){.animal-health-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:650px){.animal-health-kpis{grid-template-columns:1fr}}</style>
@endpush

@php
  $hasFilters = filled($q ?? '') || filled($barangay ?? '') || filled($petType ?? '') || filled($serviceType ?? '')
      || filled($year ?? '') || filled($selectedMunicipalityId ?? '')
      || (int) ($perPage ?? 20) !== 20;
  $fmtDate = function ($value, $format = 'M d, Y') {
      if (blank($value)) return 'Not recorded';
      try { return \Illuminate\Support\Carbon::parse($value)->format($format); }
      catch (\Throwable $e) { return 'Not recorded'; }
  };
  $canManageOperations = auth()->user()->can(
      'create',
      \App\Models\AntiRabiesVaccination::class
  );
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Animal health services</div>
      <h1>Animal health services</h1>
      <p>Record vaccination, deworming, vitamins, and treatment for pets, livestock, poultry, and other farm animals.</p>
    </div>
    <div class="module-actions">
      @if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('anti-rabies-vaccinations.create', ['municipality_id' => $selectedMunicipalityId ?? null, 'service_type' => $serviceType ?: null]) }}"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>Record service</a>@else<span class="module-badge module-badge-green">Read-only oversight</span>@endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <section class="module-kpis animal-health-kpis" aria-label="Animal health summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Services recorded</span><span class="module-kpi-icon module-kpi-icon-red"><svg viewBox="0 0 24 24"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8l2-4Z"></path><path d="M9 11h6M12 8v6"></path></svg></span></div>
      <strong>{{ number_format((int) ($totalServices ?? 0)) }}</strong><small>Records matching the current filters</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Animals served</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="2"></circle><circle cx="16" cy="8" r="2"></circle><circle cx="5" cy="13" r="2"></circle><circle cx="19" cy="13" r="2"></circle><path d="M8 19c0-3 2-5 4-5s4 2 4 5c-2 2-6 2-8 0Z"></path></svg></span></div>
      <strong>{{ number_format((int) ($animalsServed ?? 0)) }}</strong><small>Individual animals across all records</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">This month</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v14H4zM8 3v6M16 3v6M4 10h16"></path></svg></span></div>
      <strong>{{ number_format((int) ($currentMonthServices ?? 0)) }}</strong>
      <small>{{ \App\Support\LocalTime::now()->format('F Y') }} service activity</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Owners served</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span></div>
      <strong>{{ number_format((int) ($uniqueOwners ?? 0)) }}</strong>
      <small>Distinct owner names</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Animal profiles / groups</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="2"></circle><circle cx="16" cy="8" r="2"></circle><circle cx="5" cy="13" r="2"></circle><circle cx="19" cy="13" r="2"></circle><path d="M8 19c0-3 2-5 4-5s4 2 4 5c-2 2-6 2-8 0Z"></path></svg></span></div>
      <strong>{{ number_format((int) ($uniquePets ?? 0)) }}</strong>
      <small>Latest service {{ $fmtDate($latestServiceDate, 'M d') }}</small>
    </article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Find animal-health records</h2><p>Search owners, animals, products, or diagnoses and narrow by service, species, location, and year.</p></div>@if($hasFilters)<span class="module-panel-tag">Filtered view</span>@endif</div>
    <form class="module-filter" method="GET" action="{{ route('anti-rabies-vaccinations.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search"><label for="vaccinationSearch">Search</label><div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input class="module-input" id="vaccinationSearch" type="search" name="q" value="{{ $q ?? '' }}" placeholder="Owner, animal, product, diagnosis, or notes"></div></div>
        @if($canChooseMunicipality ?? false)
          <div class="module-field"><label for="vaccinationMunicipality">Municipality</label><select class="module-input" id="vaccinationMunicipality" name="municipality_id"><option value="">All municipalities</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) ($selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
        @endif
        <div class="module-field"><label for="animalServiceType">Service type</label><select class="module-input" id="animalServiceType" name="service_type"><option value="">All services</option>@foreach(($serviceTypeOptions ?? []) as $type => $label)<option value="{{ $type }}" @selected(($serviceType ?? '') === $type)>{{ $label }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationPetType">Animal species</label><select class="module-input" id="vaccinationPetType" name="pet_type"><option value="">All animal species</option>@foreach(($petTypeOptions ?? []) as $type => $label)<option value="{{ $type }}" @selected(($petType ?? '') === $type)>{{ $label }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationBarangay">Barangay</label><select class="module-input" id="vaccinationBarangay" name="barangay"><option value="">All barangays</option>@foreach(($barangayOptions ?? []) as $option)<option value="{{ $option }}" @selected(($barangay ?? '') === $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationYear">Year</label><select class="module-input" id="vaccinationYear" name="year"><option value="">All years</option>@foreach(($yearOptions ?? []) as $option)<option value="{{ $option }}" @selected((string) ($year ?? '') === (string) $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-field"><label for="vaccinationPerPage">Rows per page</label><select class="module-input" id="vaccinationPerPage" name="per_page">@foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected((int) $perPage === $n)>{{ $n }} rows</option>@endforeach</select></div>
      </div>
      <div class="module-filter-actions"><span>@if($hasFilters)<span class="module-active-filter">Summary and charts reflect these filters</span>@else Showing all animal-health records in your access scope @endif</span><div class="module-filter-buttons">@if($hasFilters)<a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
  </section>

  <section class="module-analytics-grid" aria-label="Animal health analytics">
    <article class="module-chart"><div class="module-chart-head"><h3>Monthly service trend</h3><p>Animal-health activity during {{ $chartYear ?? \App\Support\LocalTime::now()->year }}</p></div><div class="module-chart-body"><canvas id="vaccinationMonthlyChart"></canvas>@if(collect($monthlyChartData ?? [])->sum() <= 0)<div class="module-chart-empty">No animal-health activity for this year.</div>@endif</div></article>
    <article class="module-chart"><div class="module-chart-head"><h3>Service mix</h3><p>Vaccination, deworming, vitamins, and treatment</p></div><div class="module-chart-body"><canvas id="animalServiceTypeChart"></canvas></div></article>
    <article class="module-chart"><div class="module-chart-head"><h3>Animals served by species</h3><p>Uses the number of animals recorded per service</p></div><div class="module-chart-body"><canvas id="vaccinationPetChart"></canvas>@if(collect($petTypeChartData ?? [])->sum() <= 0)<div class="module-chart-empty">No animal-species data for this view.</div>@endif</div></article>
  </section>

  <details class="module-more">
    <summary>Open detailed animal-health analytics</summary>
    <div class="module-more-content"><div class="module-analytics-grid">
      <article class="module-chart"><div class="module-chart-head"><h3>Services by year</h3><p>Long-term animal-health activity</p></div><div class="module-chart-body"><canvas id="vaccinationYearChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Leading barangays</h3><p>Top ten service locations</p></div><div class="module-chart-body"><canvas id="vaccinationBarangayChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Common breeds</h3><p>Top ten recorded breeds</p></div><div class="module-chart-body"><canvas id="vaccinationBreedChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Owner age groups</h3><p>Based on recorded birthdays</p></div><div class="module-chart-body"><canvas id="vaccinationAgeChart"></canvas></div></article>
    </div></div>
  </details>

  <section class="module-panel">
    <div class="module-table-tools"><div><strong>Animal-health register</strong><span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · newest services first</span></div></div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead><tr><th>Service</th><th>Animal coverage</th><th>Owner / raiser</th><th>Details</th><th>Service date</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach($records as $record)
              @php $initials = mb_strtoupper(mb_substr($record->pet_name ?: $record->pet_type ?: 'A', 0, 2)); $serviceTypeValue = $record->service_type ?: 'vaccination'; @endphp
              <tr>
                <td><strong>{{ $record->service_name ?: 'Anti-rabies vaccination' }}</strong><small><span class="module-badge animal-service-badge-{{ $serviceTypeValue }}">{{ $record->serviceTypeLabel() }}</span></small></td>
                <td><div class="module-person"><span class="module-avatar">{{ $initials }}</span><span class="module-person-copy"><strong>{{ number_format($record->animalsServed()) }} {{ $record->animalTypeLabel() }}</strong><small>{{ $record->pet_name ?: 'Group / name not specified' }}{{ $record->pet_breed ? ' · '.$record->pet_breed : '' }}</small></span></div></td>
                <td><strong>{{ $record->owner_name }}</strong><small>{{ $record->barangay ?: 'Barangay not recorded' }}</small></td>
                <td><strong>{{ $record->dosage ?: 'Dosage not recorded' }}</strong><small>{{ $record->administration_route ?: 'Route not recorded' }}{{ $record->diagnosis ? ' · '.$record->diagnosis : '' }}</small></td>
                <td><strong>{{ $fmtDate($record->vaccination_date) }}</strong><small>{{ $record->next_service_date ? 'Follow-up '.$fmtDate($record->next_service_date, 'M d, Y') : 'No follow-up scheduled' }}</small></td>
                <td>@if($canManageOperations)<div class="module-row-actions"><a class="module-button module-button-small" href="{{ route('anti-rabies-vaccinations.edit', $record) }}">Edit</a><form method="POST" action="{{ route('anti-rabies-vaccinations.destroy', $record) }}" onsubmit="return confirm('Delete this animal-health record?')">@csrf @method('DELETE')<button class="module-button module-button-danger module-button-small" type="submit">Delete</button></form></div>@else<span class="module-badge module-badge-green">Read only</span>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8l2-4Z"></path><path d="M9 11h6M12 8v6"></path></svg></span><strong>No animal-health records found</strong><span>{{ $hasFilters ? 'Clear or adjust the current filters to find other services.' : 'No vaccination, deworming, vitamins, or treatment records have been added yet.' }}</span>@if(!$hasFilters && $canManageOperations)<a class="module-button module-button-primary" href="{{ route('anti-rabies-vaccinations.create') }}">Record service</a>@endif</div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'animal-health record'])
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
  make('animalServiceTypeChart','doughnut',@json($serviceTypeChartLabels ?? []),@json($serviceTypeChartData ?? []),['#247246','#b67b22','#3978b5','#a64d49'],{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}}}});
  make('vaccinationPetChart','doughnut',@json($petTypeChartLabels ?? []),@json($petTypeChartData ?? []),['#3978b5','#d3a03a','#4c8b63','#7a6da8','#b45d54','#4b91aa','#9b7245','#749353','#b67b22','#5b79a6','#8e6c9e','#7d8b83','#a85f75'],{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:9}}}}});
  make('vaccinationYearChart','bar',@json($yearChartLabels ?? []),@json($yearChartData ?? []),'#4c8b63');
  make('vaccinationBarangayChart','bar',@json($barangayChartLabels ?? []),@json($barangayChartData ?? []),'#3978b5',{...cartesian,indexAxis:'y'});
  make('vaccinationBreedChart','bar',@json($breedChartLabels ?? []),@json($breedChartData ?? []),'#b67b22',{...cartesian,indexAxis:'y'});
  make('vaccinationAgeChart','bar',@json($ageChartLabels ?? []),@json($ageChartData ?? []),'#7a6da8');
})();
</script>
@endpush
