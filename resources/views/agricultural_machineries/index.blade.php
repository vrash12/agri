@extends('layouts.app')

@section('title', 'Machinery Inventory')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .machinery-attention-list{display:grid;gap:8px;padding:13px}.machinery-attention-item{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:12px;padding:10px 11px;border:1px solid #e4eae6;border-radius:8px;background:#fbfcfb}.machinery-attention-item strong,.machinery-attention-item small{display:block}.machinery-attention-item strong{color:var(--module-ink);font-size:10px}.machinery-attention-item small{margin-top:3px;color:var(--module-muted);font-size:9px}.machinery-attention-date{text-align:right}.machinery-attention-date span{display:block;color:var(--module-red);font-size:9px;font-weight:850}.machinery-attention-date small{white-space:nowrap}.machinery-assignment-summary{display:flex;gap:6px;flex-wrap:wrap;padding:0 15px 13px}.machinery-code{display:inline-flex;padding:4px 6px;border:1px solid #dfe7e2;border-radius:5px;color:#254334;background:#f7faf8;font-size:9px;font-weight:850}.machinery-condition-dot{width:6px;height:6px;margin-right:5px;border-radius:50%;background:currentColor}.machinery-detail-toggle[aria-expanded="true"]{color:var(--module-green);border-color:#9db6a7;background:#f5faf6}@media(max-width:700px){.machinery-attention-item{grid-template-columns:1fr}.machinery-attention-date{text-align:left}}
  </style>
@endpush

@php
  $filters = $filters ?? [];
  $hasFilters = collect($filters)->filter(fn ($value, $key) => filled($value) && !($key === 'sort' && $value === 'asset_code') && !($key === 'per_page' && (int) $value === 15))->isNotEmpty();
  $canManageOperations = auth()->user()->canManageOperationalData();
  $conditionBadge = fn ($status) => match($status) {
      'excellent', 'good' => 'module-badge-green',
      'fair' => 'module-badge-amber',
      'needs_repair', 'unserviceable' => 'module-badge-amber',
      default => '',
  };
  $availabilityBadge = fn ($status) => match($status) {
      'available' => 'module-badge-green',
      'in_use' => 'module-badge-blue',
      'maintenance' => 'module-badge-amber',
      default => '',
  };
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Municipal asset operations</div>
      <h1>Machinery inventory</h1>
      <p>Track agricultural equipment, assigned farmers and cooperatives, availability, condition, value, and upcoming maintenance in one operational dashboard.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('machinery-inventory.export', request()->query()) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 19h16"></path></svg>
        Export CSV
      </a>
      @if($canManageOperations)
        <a class="module-button module-button-primary" href="{{ route('machinery-inventory.create', ['municipality_id' => $filters['municipality_id'] ?? null]) }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
          Register machinery
        </a>
      @else
        <span class="module-badge module-badge-green">Read-only oversight</span>
      @endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <section class="module-kpis" aria-label="Machinery inventory summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Registered assets</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 15h16v4H4zM7 15V9h7l3 6M8 19v2M17 19v2"></path><circle cx="8" cy="18" r="1"></circle><circle cx="17" cy="18" r="1"></circle></svg></span></div>
      <strong>{{ number_format((int) ($summary->total ?? 0)) }}</strong>
      <small>Assets matching the current office and filters</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Available now</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path><circle cx="12" cy="12" r="9"></circle></svg></span></div>
      <strong>{{ number_format((int) ($summary->available ?? 0)) }}</strong>
      <small>{{ number_format((int) ($summary->in_use ?? 0)) }} currently marked in use</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Needs attention</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"></path><circle cx="12" cy="12" r="9"></circle></svg></span></div>
      <strong>{{ number_format((int) $maintenanceAttention) }}</strong>
      <small>Repair, maintenance, or service due within 30 days</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Recorded asset value</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M6 3h9a4 4 0 0 1 0 8H6M6 7h10M9 3v18M6 15h8"></path></svg></span></div>
      <strong><small>₱</small>{{ number_format((float) ($summary->total_value ?? 0), 0) }}</strong>
      <small>Based on entered acquisition costs</small>
    </article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head">
      <div><h2>Find and assess equipment</h2><p>Combine asset, assignment, condition, availability, and maintenance filters.</p></div>
      @if($hasFilters)<span class="module-panel-tag">Filtered view</span>@endif
    </div>
    <form class="module-filter" method="GET" action="{{ route('machinery-inventory.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search">
          <label for="machinerySearch">Search inventory</label>
          <div class="module-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input class="module-input" id="machinerySearch" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Asset code, machine, holder, serial, or location"></div>
        </div>
        @if($canChooseMunicipality ?? false)
          <div class="module-field"><label for="machineryMunicipality">Municipality</label><select class="module-input" id="machineryMunicipality" name="municipality_id"><option value="">All municipalities</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected((string) ($filters['municipality_id'] ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
        @endif
        <div class="module-field"><label for="machineryCategory">Machinery type</label><select class="module-input" id="machineryCategory" name="category"><option value="">All machinery types</option>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="module-field"><label for="machineryCondition">Condition</label><select class="module-input" id="machineryCondition" name="condition_status"><option value="">All conditions</option>@foreach($conditions as $key => $label)<option value="{{ $key }}" @selected(($filters['condition_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="module-field"><label for="machineryAvailability">Availability</label><select class="module-input" id="machineryAvailability" name="availability_status"><option value="">All availability</option>@foreach($availabilityStatuses as $key => $label)<option value="{{ $key }}" @selected(($filters['availability_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="module-field"><label for="machineryHolder">Assigned to</label><select class="module-input" id="machineryHolder" name="holder_type"><option value="">Farmers and cooperatives</option><option value="farmer" @selected(($filters['holder_type'] ?? '') === 'farmer')>Individual farmer</option><option value="cooperative" @selected(($filters['holder_type'] ?? '') === 'cooperative')>Farmers cooperative</option><option value="unassigned" @selected(($filters['holder_type'] ?? '') === 'unassigned')>Unassigned</option></select></div>
        <div class="module-field"><label for="machineryMaintenance">Maintenance</label><select class="module-input" id="machineryMaintenance" name="maintenance"><option value="">Any maintenance state</option><option value="overdue" @selected(($filters['maintenance'] ?? '') === 'overdue')>Overdue</option><option value="due" @selected(($filters['maintenance'] ?? '') === 'due')>Due within 30 days</option><option value="attention" @selected(($filters['maintenance'] ?? '') === 'attention')>All needing attention</option></select></div>
        <div class="module-field"><label for="machinerySort">Sort by</label><select class="module-input" id="machinerySort" name="sort"><option value="asset_code" @selected(($filters['sort'] ?? 'asset_code') === 'asset_code')>Asset code</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Machinery name</option><option value="maintenance" @selected(($filters['sort'] ?? '') === 'maintenance')>Next maintenance</option><option value="value" @selected(($filters['sort'] ?? '') === 'value')>Highest value</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Recently registered</option></select></div>
        <div class="module-field"><label for="machineryPerPage">Rows per page</label><select class="module-input" id="machineryPerPage" name="per_page">@foreach([15,25,50,100] as $number)<option value="{{ $number }}" @selected((int) ($filters['per_page'] ?? 15) === $number)>{{ $number }} rows</option>@endforeach</select></div>
      </div>
      <div class="module-filter-actions"><span>@if($hasFilters)<span class="module-active-filter">Filters are active and apply to the dashboard and export</span>@else Showing all equipment available to your account @endif</span><div class="module-filter-buttons">@if($hasFilters)<a class="module-button" href="{{ route('machinery-inventory.index') }}">Clear filters</a>@endif<button class="module-button module-button-primary" type="submit">Apply filters</button></div></div>
    </form>
  </section>

  <section class="module-analytics-grid" aria-label="Machinery analytics">
    <article class="module-chart"><div class="module-chart-head"><h3>Inventory by machinery type</h3><p>Composition of the currently filtered asset list</p></div><div class="module-chart-body"><canvas id="machineryCategoryChart"></canvas>@if($categoryChart->sum('total') === 0)<div class="module-chart-empty">No machinery data for this view.</div>@endif</div></article>
    <article class="module-chart"><div class="module-chart-head"><h3>Equipment condition</h3><p>Service readiness across recorded machinery</p></div><div class="module-chart-body"><canvas id="machineryConditionChart"></canvas>@if($conditionChart->sum('total') === 0)<div class="module-chart-empty">No condition data for this view.</div>@endif</div></article>
  </section>

  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Maintenance attention</h2><p>Equipment in repair, under maintenance, overdue, or due within 30 days.</p></div><span class="module-panel-tag">{{ number_format((int) $maintenanceAttention) }} flagged</span></div>
    @if($maintenanceQueue->isNotEmpty())
      <div class="machinery-attention-list">
        @foreach($maintenanceQueue as $item)
          <div class="machinery-attention-item"><div><strong>{{ $item->asset_code }} · {{ $item->name }}</strong><small>{{ $item->holder_label }} · {{ $item->condition_label }} · {{ $item->availability_label }}</small></div><div class="machinery-attention-date"><span>{{ $item->next_maintenance_date && $item->next_maintenance_date->isPast() ? 'Overdue' : 'Attention required' }}</span><small>{{ $item->next_maintenance_date?->format('M d, Y') ?? 'No service date entered' }}</small></div></div>
        @endforeach
      </div>
    @else
      <div class="module-empty" style="min-height:120px"><strong>No immediate maintenance flags</strong><span>Equipment in this view has no recorded repair or 30-day maintenance warning.</span></div>
    @endif
    <div class="machinery-assignment-summary"><span class="module-badge module-badge-blue">{{ number_format((int) ($summary->farmer_assigned ?? 0)) }} farmer-assigned</span><span class="module-badge module-badge-green">{{ number_format((int) ($summary->cooperative_assigned ?? 0)) }} cooperative-assigned</span>@if((int) ($summary->unassigned ?? 0) > 0)<span class="module-badge module-badge-amber">{{ number_format((int) $summary->unassigned) }} need reassignment</span>@endif</div>
  </section>

  <section class="module-panel">
    <div class="module-table-tools"><div><strong>Asset register</strong><span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · use Details for complete acquisition and maintenance information</span></div></div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead><tr><th>Asset</th><th>Assigned holder</th><th>Condition</th><th>Availability</th><th>Location</th><th>Next maintenance</th><th class="module-numeric">Value</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach($records as $record)
              <tr>
                <td><div class="module-person"><span class="module-avatar">{{ mb_strtoupper(mb_substr($record->name, 0, 2)) }}</span><span class="module-person-copy"><strong>{{ $record->name }}</strong><small><span class="machinery-code module-mono">{{ $record->asset_code }}</span> · {{ $record->category_label }}</small></span></div></td>
                <td><strong>{{ $record->holder_label }}</strong><small>{{ $record->holder_type === 'farmer' ? 'Individual farmer'.($record->farmer?->ffrs ? ' · '.$record->farmer->ffrs : '') : ($record->holder_type === 'cooperative' ? 'Farmers cooperative' : 'Assignment required') }}</small></td>
                <td><span class="module-badge {{ $conditionBadge($record->condition_status) }}"><span class="machinery-condition-dot"></span>{{ $record->condition_label }}</span></td>
                <td><span class="module-badge {{ $availabilityBadge($record->availability_status) }}">{{ $record->availability_label }}</span></td>
                <td><strong>{{ $record->location ?: 'Not recorded' }}</strong><small>{{ $record->municipality?->name ?? 'Municipality unavailable' }}</small></td>
                <td>@if($record->next_maintenance_date)<strong>{{ $record->next_maintenance_date->format('M d, Y') }}</strong><small>{{ $record->next_maintenance_date->isPast() ? $record->next_maintenance_date->diffForHumans().' overdue' : $record->next_maintenance_date->diffForHumans() }}</small>@else<span>Not scheduled</span>@endif</td>
                <td class="module-numeric"><strong>{{ $record->acquisition_cost !== null ? '₱'.number_format((float) $record->acquisition_cost, 2) : '—' }}</strong></td>
                <td><div class="module-row-actions"><button class="module-button module-button-small machinery-detail-toggle" type="button" data-detail-target="machinery-details-{{ $record->id }}" aria-expanded="false">Details</button>@if($canManageOperations)<a class="module-button module-button-primary module-button-small" href="{{ route('machinery-inventory.edit', $record) }}">Edit</a>@endif<details class="module-action-menu"><summary aria-label="More actions">•••</summary><div class="module-action-menu-list">@if($canManageOperations)<a href="{{ route('machinery-inventory.edit', $record) }}">Update record</a><form method="POST" action="{{ route('machinery-inventory.destroy', $record) }}" onsubmit="return confirm('Remove {{ addslashes($record->asset_code) }} from the machinery inventory?')">@csrf @method('DELETE')<button class="danger" type="submit">Delete machinery</button></form>@else<span style="display:block;padding:8px;color:var(--module-muted);font-size:9px">Read-only record</span>@endif</div></details></div></td>
              </tr>
              <tr class="module-detail-row" id="machinery-details-{{ $record->id }}" hidden><td colspan="8"><dl class="module-detail-grid"><div><dt>Brand / model</dt><dd>{{ collect([$record->brand, $record->model])->filter()->implode(' · ') ?: 'Not recorded' }}</dd></div><div><dt>Serial number</dt><dd class="module-mono">{{ $record->serial_number ?: 'Not recorded' }}</dd></div><div><dt>Acquisition</dt><dd>{{ $record->acquisition_source_label }}{{ $record->acquisition_date ? ' · '.$record->acquisition_date->format('M d, Y') : '' }}</dd></div><div><dt>Year acquired</dt><dd>{{ $record->year_acquired ?: 'Not recorded' }}</dd></div><div><dt>Service hours</dt><dd>{{ $record->service_hours !== null ? number_format((float) $record->service_hours, 1).' hours' : 'Not recorded' }}</dd></div><div><dt>Last maintenance</dt><dd>{{ $record->last_maintenance_date?->format('M d, Y') ?? 'Not recorded' }}</dd></div><div><dt>Assignment type</dt><dd>{{ ucfirst($record->holder_type) }}</dd></div><div><dt>Municipality</dt><dd>{{ $record->municipality?->name ?? 'Unavailable' }}</dd></div><div><dt>Notes</dt><dd>{{ $record->notes ?: 'No operational notes' }}</dd></div><div><dt>Registered</dt><dd>{{ $record->created_at?->format('M d, Y') ?? 'Not recorded' }}</dd></div></dl></td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M4 15h16v4H4zM7 15V9h7l3 6M8 19v2M17 19v2"></path></svg></span><strong>No machinery found</strong><span>{{ $hasFilters ? 'Try clearing or changing the current inventory filters.' : 'Register the first agricultural machine for this office.' }}</span>@if(!$hasFilters && $canManageOperations)<a class="module-button module-button-primary" href="{{ route('machinery-inventory.create') }}">Register machinery</a>@endif</div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'machinery record'])
  </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
  (() => {
    const category = @json($categoryChart);
    const condition = @json($conditionChart);
    const baseOptions = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ displayColors:false } }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 }, grid:{ color:'rgba(97,116,104,.10)' } }, x:{ grid:{ display:false }, ticks:{ maxRotation:0, autoSkip:true, font:{ size:9 } } } } };
    const categoryCanvas = document.getElementById('machineryCategoryChart');
    if (categoryCanvas && category.length) new Chart(categoryCanvas, { type:'bar', data:{ labels:category.map(item => item.label), datasets:[{ data:category.map(item => item.total), backgroundColor:'#2f7d4c', borderRadius:5, maxBarThickness:42 }] }, options:baseOptions });
    const conditionCanvas = document.getElementById('machineryConditionChart');
    if (conditionCanvas && condition.length) new Chart(conditionCanvas, { type:'doughnut', data:{ labels:condition.map(item => item.label), datasets:[{ data:condition.map(item => item.total), backgroundColor:condition.map(item => ({ excellent:'#267a47', good:'#55a66d', fair:'#d6a438', needs_repair:'#dc7b32', unserviceable:'#b54747' }[item.key] || '#8a9890')), borderWidth:0 }] }, options:{ responsive:true, maintainAspectRatio:false, cutout:'67%', plugins:{ legend:{ position:'bottom', labels:{ boxWidth:9, boxHeight:9, usePointStyle:true, font:{ size:9 } } } } } });
    document.querySelectorAll('.machinery-detail-toggle').forEach(button => button.addEventListener('click', () => { const row=document.getElementById(button.dataset.detailTarget); if(!row)return; const willOpen=row.hasAttribute('hidden'); row.toggleAttribute('hidden', !willOpen); button.setAttribute('aria-expanded', willOpen ? 'true':'false'); button.textContent=willOpen ? 'Hide':'Details'; }));
    document.addEventListener('click', event => document.querySelectorAll('.module-action-menu[open]').forEach(menu => { if(!menu.contains(event.target)) menu.removeAttribute('open'); }));
  })();
</script>
@endpush
