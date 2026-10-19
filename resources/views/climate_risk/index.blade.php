@extends('layouts.app')
@section('title', 'El Niño Readiness')
@push('styles')
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/climate-risk.css') }}?v={{ filemtime(public_path('css/climate-risk.css')) }}">
@endpush
@section('content')
<div class="module-page climate-risk" id="climateRiskDashboard">
  <header class="module-header">
    <div><div class="module-eyebrow">{{ auth()->user()->scopeLabel() }} · Climate readiness</div><h1>El Niño readiness</h1><p>See where dry conditions need attention, review the evidence, and plan a field check.</p></div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('dashboard') }}">Office dashboard</a>
      @if($installed)
        @can('create', \App\Models\ClimateRiskAssessment::class)
          <a class="module-button module-button-primary" href="{{ route('climate-risk.create', ['municipality_id' => $selected?->id]) }}">Record an assessment</a>
        @endcan
      @endif
    </div>
  </header>
  @if(!$installed)
    <div class="module-alert" role="status">The climate registry needs its database update. Your administrator can install the documented migration to enable dated assessments.</div>
  @else
    <form method="GET" class="module-panel climate-filters" aria-label="Dashboard area filters">
      <div><label for="riskMunicipality">Municipality / city</label>
        <select class="module-input" id="riskMunicipality" name="municipality_id">
          @if(auth()->user()->canAccessAllMunicipalities())<option value="">All authorized municipalities</option>@endif
          @foreach($choices as $choice)<option value="{{ $choice->id }}" @selected($selected?->id === $choice->id)>{{ $choice->name }} · {{ $choice->province }}</option>@endforeach
        </select>
      </div>
      <div><label for="riskLevelFilter">Office risk level</label><select class="module-input" id="riskLevelFilter" name="risk"><option value="">All levels</option>
        @foreach(\App\Models\ClimateRiskAssessment::LEVELS + ['unassessed' => 'Not assessed'] as $key => $label)<option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>@endforeach
      </select></div>
      <button type="submit" class="module-button module-button-primary">Show area</button>
    </form>
    @if($selected)
      @include('climate_risk._monitoring')
    @else
      <p class="climate-explanation">Choose a municipality above to see automatic rainfall and heat checks.</p>
    @endif
    <h2 class="climate-section-heading">Verified office assessments</h2>
    <div class="climate-metrics" aria-label="Current municipality status counts">
      @foreach(\App\Models\ClimateRiskAssessment::LEVELS + ['unassessed' => 'Not assessed'] as $key => $label)
        <a class="module-panel climate-metric" href="{{ route('climate-risk.index', ['risk' => $key]) }}"><span class="climate-status climate-{{ $key }}">{{ $label }}</span><strong>{{ number_format($totals->get($key, 0)) }}</strong><small>municipalities in your office scope</small></a>
      @endforeach
    </div>
    <p class="climate-explanation">Colors show the highest <strong>current office assessment</strong> in each municipality. Barangay assessments apply only to the named barangay. Gray means a current assessment is needed.</p>
    <section class="module-panel" aria-labelledby="riskMapTitle">
      <div class="module-panel-head"><div><h2 id="riskMapTitle">{{ $selected?->name ?? 'Municipality' }} risk map</h2><p>Open an area to review dates and sources. Parcel outlines mark land reported affected by your office.</p></div><button type="button" class="module-button" id="loadClimateMap">Open risk map</button></div>
      <div id="climateMap" class="climate-map" aria-label="Municipality and barangay risk map" hidden></div>
      <p class="climate-map-status" id="climateMapStatus" role="status">The area register below is available before the map loads.</p>
      <div class="climate-legend" aria-label="Map legend">
        @foreach(\App\Models\ClimateRiskAssessment::LEVELS + ['unassessed' => 'Not assessed'] as $key => $label)<span class="climate-status climate-{{ $key }}">{{ $label }}</span>@endforeach
        <span>White outline: reported parcel</span>
      </div>
    </section>
    @if($selected)
      <section class="module-panel" aria-labelledby="areaTitle">
        <div class="module-panel-head"><div><h2 id="areaTitle">{{ $selected->name }} · Dated assessments</h2><p>Rainfall and drought categories as recorded from a source or local field review.</p></div></div>
        <div class="module-table-wrap"><table class="module-table"><thead><tr><th>Area</th><th>Office level</th><th>Drought / rainfall outlook</th><th>Observed / review by</th><th>Evidence</th><th>Action</th></tr></thead><tbody>
          @forelse($assessments as $assessment)
          <tr><td><strong>{{ $assessment->barangay ?: 'Municipality-wide' }}</strong></td><td><span class="climate-status climate-{{ $assessment->isCurrent() ? $assessment->risk_level : 'unassessed' }}">{{ $assessment->isCurrent() ? \App\Models\ClimateRiskAssessment::LEVELS[$assessment->risk_level] : 'Needs review' }}</span></td>
            <td>{{ \App\Models\ClimateRiskAssessment::DROUGHT[$assessment->drought_status] }}<small>{{ \App\Models\ClimateRiskAssessment::RAINFALL[$assessment->rainfall_outlook] }} rainfall</small></td>
            <td>{{ $assessment->observed_on->format('M j, Y') }}<small>Review by {{ $assessment->valid_until->format('M j, Y') }}</small></td>
            <td>{{ $assessment->source_type === 'pagasa' ? 'PAGASA reference' : 'Office field review' }}<small>{{ $assessment->source_label }}</small>@if($assessment->source_url)<a href="{{ $assessment->source_url }}" target="_blank" rel="noopener noreferrer">View source</a>@endif
              @if($assessment->notes)<details><summary>Assessment notes</summary><p class="climate-notes">{{ $assessment->notes }}</p></details>@endif
            </td><td>@can('update', $assessment)<a class="module-button" href="{{ route('climate-risk.edit', $assessment) }}">Update</a>@else Read-only @endcan</td>
          </tr>
          @empty<tr><td colspan="6">No assessment has been recorded for this municipality. Start with a dated source or field review.</td></tr>@endforelse
        </tbody></table></div>{{ $assessments->links() }}
      </section>
      <section class="module-panel" aria-labelledby="affectedTitle">
        <div class="module-panel-head"><div><h2 id="affectedTitle">Reported affected parcels</h2><p>{{ number_format($plots->total()) }} distinct parcels linked to current assessments. Listed parcels are explicitly reported; a municipality risk color does not assign impact to every parcel.</p></div></div>
        <div class="module-table-wrap"><table class="module-table"><thead><tr><th>Parcel</th><th>Latest NDVI</th><th>Observation date</th><th>Actions</th></tr></thead><tbody>
          @forelse($plots as $plot)
          @php($summary = $plotSummaries->get($plot->id))
          <tr><td>{{ $summary['name'] }}</td><td>{{ $summary['ndvi'] ? number_format($summary['ndvi']['mean'], 3) : 'No saved reading' }}@if($summary['ndvi'])<small>{{ $summary['ndvi']['valid_pixels'] }} usable pixels · Sentinel-2</small>@endif</td><td>{{ $summary['ndvi']['observed_on'] ?? '—' }}@if($summary['ndvi']['is_stale'] ?? false)<small>Older than 14 days · recheck conditions</small>@endif</td><td><div class="module-actions"><a class="module-button" href="{{ $summary['map_url'] }}">Parcel map</a><a class="module-button" href="{{ $summary['satellite_url'] }}">Satellite field check</a></div></td></tr>
          @empty<tr><td colspan="4">No parcels are reported affected in current assessments.</td></tr>@endforelse
        </tbody></table></div>{{ $plots->links() }}
      </section>
    @endif
    <section class="module-panel" aria-labelledby="municipalityRegisterTitle">
      <div class="module-panel-head"><div><h2 id="municipalityRegisterTitle">Municipality register</h2><p>{{ number_format($paginator->total()) }} municipalities in this filter. Open one for barangays, rainfall and reported parcels.</p></div></div>
      <div class="module-table-wrap"><table class="module-table"><thead><tr><th>Municipality</th><th>Office level</th><th>Latest observation</th><th>Review by</th><th>Reported parcels</th><th>Action</th></tr></thead><tbody>
        @forelse($paginator as $row)<tr><td><strong>{{ $row['name'] }}</strong><small>{{ $row['province'] }}</small></td><td><span class="climate-status climate-{{ $row['level'] }}">{{ $row['label'] }}</span></td><td>{{ $row['observed_on'] ?? 'No current assessment' }}@if(!$row['observed_on'] && $row['last_observed_on'])<small>Last record: {{ $row['last_observed_on'] }}</small>@endif</td><td>{{ $row['review_on'] ?? '—' }}</td><td>{{ number_format($row['affected_count']) }}</td><td><a class="module-button" href="{{ route('climate-risk.index', ['municipality_id' => $row['id']]) }}">Open area</a></td></tr>
        @empty<tr><td colspan="6">No municipalities match this filter.</td></tr>@endforelse
      </tbody></table></div>{{ $paginator->links() }}
    </section>
  @endif
  <section class="module-panel climate-sources" aria-label="Official climate sources"><strong>Official climate references</strong><div class="module-actions"><a href="https://www.pagasa.dost.gov.ph/climate/el-nino-la-nina/advisories" target="_blank" rel="noopener noreferrer">PAGASA ENSO advisories</a><a href="https://www.pagasa.dost.gov.ph/climate/climate-prediction/seasonal-forecast" target="_blank" rel="noopener noreferrer">Seasonal rainfall outlook</a><a href="https://pubfiles.pagasa.dost.gov.ph/pagasaweb/files/climate/dryspelldrought/" target="_blank" rel="noopener noreferrer">Dry spell / drought assessments</a></div><p>This is an office planning dashboard. The four office levels express local response priority; check the source bulletin for official geographic coverage and categories.</p></section>
</div>
@endsection
@push('scripts')
<script>
window.__climateRiskConfig = @json($mapConfig);
</script>
<script src="{{ asset('js/climate-risk.js') }}?v={{ filemtime(public_path('js/climate-risk.js')) }}"></script>
@endpush
