@extends('layouts.app')

@section('title', 'Farmer Distribution History')

@section('content')
@include('partials.operations-ui-styles')
@php
  $farmerName = trim(collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' '));
  $farmAddress = collect([$farmer->farm_location, $farmer->farm_municipality, $farmer->farm_province])->filter()->implode(' · ');
  $favoriteLabel = filled($favoriteVariety) && $favoriteVariety !== 'N/A' ? $favoriteVariety : 'No item recorded';
  $firstLabel = $firstReceived ? \Illuminate\Support\Carbon::parse($firstReceived)->format('M d, Y') : '—';
  $lastLabel = $lastReceived ? \Illuminate\Support\Carbon::parse($lastReceived)->format('M d, Y') : '—';
  $filterCount = collect([request('q'), request('input_category'), request('received_from'), request('received_to')])->filter(fn ($value) => filled($value))->count();
  $canManageOperations = auth()->user()->canManageOperationalData();
@endphp

<div class="module-page farmer-history-page">
  @if (session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if (session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  @include('farmers.partials.workspace-nav', ['workspaceMunicipality' => $farmer->municipality])

  <header class="module-header">
    <div class="farmer-history-heading">
      <span class="farmer-history-avatar">
        @if ($farmer->profile_photo_path)
          <img src="{{ route('farmers.photo', $farmer) }}" alt="{{ $farmerName ?: 'Farmer' }} profile photo">
        @else
          {{ strtoupper(substr($farmer->first_name ?: 'F', 0, 1).substr($farmer->last_name ?: 'R', 0, 1)) }}
        @endif
      </span>
      <div>
        <div class="module-eyebrow">Beneficiary assistance history</div>
        <h1>{{ $farmerName ?: 'Farmer profile' }}</h1>
        <p>{{ $farmer->agri_gov_id }} · {{ $farmAddress ?: 'Farm location not yet recorded' }}{{ $farmer->ffrs ? ' · FFRS '.$farmer->ffrs : '' }}</p>
      </div>
    </div>
    <div class="module-actions">
      @can('update', $farmer)<a class="module-button" href="{{ route('farmers.portal-account.show', $farmer) }}">Farmer portal access</a>@endcan
      <a class="module-button" href="{{ route('farmers.id-card', $farmer) }}">View digital ID</a>
      <a class="module-button" href="{{ route('machinery-inventory.index', ['holder_type' => 'farmer', 'q' => $farmer->agri_gov_id]) }}">{{ number_format((int) ($machineryCount ?? 0)) }} machinery {{ Str::plural('asset', (int) ($machineryCount ?? 0)) }}</a>
      @if($canManageOperations)<a class="module-button" href="{{ route('farmers.edit', $farmer) }}">Edit profile</a>@endif
      <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $farmer->municipality_id]) }}">Back to registry</a>
      @if($canManageOperations)
      <a class="module-button module-button-primary" href="{{ route('rice-seed-distributions.create', ['farmer_id' => $farmer->id]) }}">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Add distribution
      </a>
      <a class="module-button" href="{{ route('rice-seed-distributions.create', ['farmer_id' => $farmer->id, 'assistance_sector' => 'fisheries']) }}">Add fisheries</a>
      @endif
    </div>
  </header>

  <section class="module-panel farmer-registry-source" id="registrySourceInformation">
    <div class="module-panel-head">
      <div>
        <h2>Registry source information</h2>
        <p>Every field retained from this farmer's imported Excel rows. Repeated rows represent separate parcel, commodity, livestock, ownership, or agency records.</p>
      </div>
      <span class="module-panel-tag">{{ number_format($registryRowCount) }} source {{ Str::plural('row', $registryRowCount) }}</span>
    </div>

    @if($registryRows && $registryRows->count())
      <div class="farmer-registry-summary">
        <span><strong>{{ number_format($registryRowCount) }}</strong> imported source rows</span>
        <span><strong>{{ number_format($registryParcelCount) }}</strong> distinct parcel references</span>
        <span>Original workbook values are shown without replacing the active farmer identity.</span>
      </div>

      <div class="farmer-registry-rows">
        @foreach($registryRows as $sourceRow)
          <details class="farmer-registry-row" @if($loop->first) open @endif>
            <summary>
              <span>
                <strong>{{ $sourceRow->source_sheet }} · row {{ number_format($sourceRow->source_row) }}</strong>
                <small>FFRS {{ $sourceRow->source_ffrs ?: 'not recorded' }} · Parcel {{ $sourceRow->parcel_no ?: 'not recorded' }}</small>
              </span>
              <span class="module-badge {{ $sourceRow->record_status === 'outside_lgu' ? 'module-badge-amber' : 'module-badge-green' }}">
                {{ $sourceRow->record_status === 'outside_lgu' ? 'Outside LGU address' : 'Parcel listing' }}
              </span>
            </summary>
            <dl class="farmer-registry-grid">
              @foreach(($sourceRow->payload ?? []) as $label => $value)
                @php
                  $displayValue = $value;
                  if ($displayValue === null || $displayValue === '') {
                      $displayValue = 'Not recorded';
                  } elseif (is_bool($displayValue)) {
                      $displayValue = $displayValue ? 'Yes' : 'No';
                  } elseif (is_array($displayValue)) {
                      $displayValue = implode(', ', $displayValue);
                  }
                @endphp
                <div>
                  <dt>{{ $label }}</dt>
                  <dd>{{ $displayValue }}</dd>
                </div>
              @endforeach
            </dl>
          </details>
        @endforeach
      </div>

      @include('partials.pagination', ['paginator' => $registryRows, 'label' => 'registry source row', 'fragment' => 'registrySourceInformation'])
    @else
      <div class="module-empty">
        <strong>No Excel source rows are linked to this farmer.</strong>
        <span>Manually created profiles and records from older imports may not have a retained workbook row.</span>
      </div>
    @endif
  </section>

  <section class="module-kpis">
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Distribution records</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M7 3h10v18H7zM10 7h4M10 11h4M10 15h4"/></svg></span></div><strong>{{ number_format($totalRecords) }}</strong><small>Matching this history view</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Weight-based inputs</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M12 22V8M7 13c-3-1-4-4-4-7 3 0 6 1 7 4M17 13c3-1 4-4 4-7-3 0-6 1-7 4"/></svg></span></div><strong>{{ number_format($totalKgs, 2) }}<small> kg</small></strong><small>Only releases measured in kilograms</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Most received item</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><path d="M4 19c5-1 8-4 9-9 4 1 6 4 7 8-5 2-11 2-16 1Z"/><path d="M4 19c4-2 7-4 9-9"/></svg></span></div><strong class="farmer-kpi-text">{{ $favoriteLabel }}</strong><small>Based on the current filtered records</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Latest receipt</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span></div><strong class="farmer-kpi-text">{{ $lastLabel }}</strong><small>First recorded receipt: {{ $firstLabel }}</small></article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Search distribution history</h2><p>Filter this beneficiary's agriculture and fisheries records by category, item, lot/batch, notes, or receipt date.</p></div><span class="module-panel-tag">{{ $filterCount ? $filterCount.' active' : 'Complete history' }}</span></div>
    <form class="module-filter" method="GET">
      <div class="module-filter-grid">
        <div class="module-field module-field-search"><label for="history_q">Search history</label><div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input class="module-input" type="search" id="history_q" name="q" value="{{ request('q') }}" placeholder="Seed, fertilizer, fingerlings, gear, batch, or notes"></div></div>
        <div class="module-field"><label for="history_category">Input category</label><select class="module-input" id="history_category" name="input_category"><option value="">All categories</option>@foreach(($inputCategoryOptions ?? []) as $value => $label)<option value="{{ $value }}" @selected(request('input_category') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="module-field"><label for="received_from">Received from</label><input class="module-input" type="date" id="received_from" name="received_from" value="{{ request('received_from') }}"></div>
        <div class="module-field"><label for="received_to">Received to</label><input class="module-input" type="date" id="received_to" name="received_to" value="{{ request('received_to') }}"></div>
        <div class="module-field"><label for="history_per_page">Rows per page</label><select class="module-input" id="history_per_page" name="per_page">@foreach([10,25,50,100] as $amount)<option value="{{ $amount }}" @selected((int) $perPage === $amount)>{{ $amount }} rows</option>@endforeach</select></div>
      </div>
      <div class="module-filter-actions"><span>{{ number_format($records->total()) }} matching record{{ $records->total() === 1 ? '' : 's' }}</span><div class="module-filter-buttons">@if($filterCount)<a class="module-button" href="{{ route('farmers.records', $farmer) }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
  </section>

  @if ($totalRecords > 0)
    <section class="module-analytics-grid">
      <article class="module-chart"><div class="module-chart-head"><h3>Weight-based inputs over time</h3><p>Kilograms received on each recorded date.</p></div><div class="module-chart-body"><canvas id="historyTimelineChart"></canvas></div></article>
      <article class="module-chart"><div class="module-chart-head"><h3>Weight by item</h3><p>Total kilograms grouped by seed or farm-input name.</p></div><div class="module-chart-body"><canvas id="historyVarietyChart"></canvas></div></article>
    </section>
  @endif

  <section class="module-panel">
    <div class="module-table-tools"><div><strong>Distribution ledger</strong><span>All agriculture and fisheries assistance linked to {{ $farmerName ?: 'this beneficiary' }}.</span></div><span>{{ number_format($totalKgs, 2) }} kg in weight-based results</span></div>
    <div class="module-table-scroll">
      <table class="module-table farmer-history-table">
        <thead><tr><th>Date received</th><th>Input issued</th><th class="module-numeric">Received</th><th class="module-numeric">Claimed area</th><th class="module-numeric">Claimed seeds</th><th>Lot / batch</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
          @forelse ($records as $record)
            <tr>
              <td><strong>{{ $record->date_received ? \Illuminate\Support\Carbon::parse($record->date_received)->format('M d, Y') : '—' }}</strong><small>{{ $record->date_received ? \Illuminate\Support\Carbon::parse($record->date_received)->diffForHumans() : 'Date not recorded' }}</small></td>
              <td><strong>{{ $record->seed_variety_claimed ?: 'Unspecified' }}</strong><small><span class="module-badge {{ $record->isSeedInput() ? 'module-badge-green' : ($record->input_category === 'fertilizer' ? 'module-badge-amber' : 'module-badge-blue') }}">{{ $record->inputCategoryLabel() }}</span> · {{ $record->assistanceSectorLabel() }}</small></td>
              <td class="module-numeric"><strong>{{ number_format((float) $record->kgs_received, 2) }} {{ $record->quantityUnitLabel() }}</strong></td>
              <td class="module-numeric">{{ $record->claimed_area_ha !== null ? number_format((float) $record->claimed_area_ha, 2).' ha' : '—' }}</td>
              <td class="module-numeric">{{ $record->claimed_seeds_kg !== null ? number_format((float) $record->claimed_seeds_kg, 2).' kg' : '—' }}</td>
              <td><strong class="module-mono">{{ $record->lot_series ?: '—' }}</strong><small>{{ $record->input_notes ?: ($record->date_of_sowing_label ?: 'No additional notes') }}</small></td>
              <td>@if($canManageOperations)<div class="module-row-actions"><a class="module-button module-button-small" href="{{ route('rice-seed-distributions.edit', $record) }}">Edit</a><form method="POST" action="{{ route('rice-seed-distributions.destroy', $record) }}" onsubmit="return confirm('Delete this distribution record?');">@csrf @method('DELETE')<button class="module-button module-button-small module-button-danger" type="submit">Delete</button></form></div>@else<span class="module-badge module-badge-green">Read only</span>@endif</td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M12 22V8M7 13c-3-1-4-4-4-7 3 0 6 1 7 4M17 13c3-1 4-4 4-7-3 0-6 1-7 4"/></svg></span><strong>No distribution records found</strong><span>{{ $filterCount ? 'No records match the current filters.' : 'No releases have been recorded for this farmer.' }}</span>@if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('rice-seed-distributions.create', ['farmer_id' => $farmer->id]) }}">Add distribution</a>@endif</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.pagination', ['paginator' => $records, 'label' => 'distribution record'])
  </section>
</div>
@endsection

@push('styles')
<style>
  .farmer-history-heading{display:flex;align-items:center;gap:13px}.farmer-history-avatar{width:46px;height:46px;display:grid;place-items:center;overflow:hidden;flex:0 0 auto;border-radius:10px;color:#fff;background:#285a3b;font-size:12px;font-weight:900}.farmer-history-avatar img{width:100%;height:100%;display:block;object-fit:cover}.farmer-kpi-text{overflow:hidden;font-size:18px!important;line-height:1.15!important;text-overflow:ellipsis;white-space:nowrap}.farmer-history-table{min-width:980px}
  .farmer-registry-summary{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--module-border);color:var(--module-muted);font-size:13px}.farmer-registry-summary span{padding:6px 9px;border-radius:7px;background:var(--module-surface-subtle,#f5f8f5)}.farmer-registry-summary strong{color:var(--module-ink)}.farmer-registry-rows{display:grid;gap:10px;padding:14px 16px}.farmer-registry-row{overflow:hidden;border:1px solid var(--module-border);border-radius:9px;background:#fff}.farmer-registry-row>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:48px;padding:10px 12px;cursor:pointer;list-style:none;background:#f8faf8}.farmer-registry-row>summary::-webkit-details-marker{display:none}.farmer-registry-row>summary span:first-child{display:grid;gap:2px}.farmer-registry-row>summary strong{color:var(--module-ink);font-size:14px}.farmer-registry-row>summary small{color:var(--module-muted);font-size:12px}.farmer-registry-row>summary:focus-visible{outline:3px solid var(--ui-focus,#236344);outline-offset:-3px}.farmer-registry-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0;padding:12px;border-top:1px solid var(--module-border);gap:1px;background:var(--module-border)}.farmer-registry-grid>div{min-width:0;padding:9px 10px;background:#fff}.farmer-registry-grid dt{margin-bottom:3px;color:var(--module-muted);font-size:12px;font-weight:500}.farmer-registry-grid dd{margin:0;overflow-wrap:anywhere;color:var(--module-ink);font-size:13px;line-height:1.45}
  @media(max-width:960px){.farmer-registry-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:620px){.farmer-history-heading{align-items:flex-start}.farmer-history-avatar{display:none}}
  @media(max-width:620px){.farmer-registry-row>summary{align-items:flex-start;flex-direction:column}.farmer-registry-grid{grid-template-columns:1fr}.farmer-registry-summary{align-items:stretch;flex-direction:column}}
</style>
@endpush

@push('scripts')
@if ($totalRecords > 0)
{!! \App\Support\Cdn::script('chart_js') !!}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const timeline = @json($kgsOverTime ?? []);
    const varieties = @json($varietyChartData ?? []);
    const common = {responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#edf1ee'},ticks:{font:{size:9}}},x:{grid:{display:false},ticks:{font:{size:9}}}}};
    new Chart(document.getElementById('historyTimelineChart'), {type:'line',data:{labels:Object.keys(timeline),datasets:[{data:Object.values(timeline),borderColor:'#17643a',backgroundColor:'rgba(23,100,58,.1)',fill:true,tension:.28,pointRadius:3}]},options:common});
    new Chart(document.getElementById('historyVarietyChart'), {type:'bar',data:{labels:Object.keys(varieties),datasets:[{data:Object.values(varieties),backgroundColor:'#4f8765',borderRadius:4,maxBarThickness:38}]},options:common});
  });
</script>
@endif
@endpush
