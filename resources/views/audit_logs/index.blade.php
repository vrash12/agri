@extends('layouts.app')

@section('title', 'Audit Trail')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .audit-header{background:linear-gradient(112deg,#fff 0%,#f8fbf8 60%,#edf5f8 100%)}
    .audit-security-note{display:flex;align-items:flex-start;gap:9px;padding:11px 13px;border:1px solid #d9e5dd;border-radius:9px;color:#526159;background:#f8faf8;font-size:9px;line-height:1.45}.audit-security-note svg{width:17px;height:17px;flex:0 0 auto;color:var(--module-green);fill:none;stroke:currentColor;stroke-width:1.8}
    .audit-overview{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(260px,.8fr);gap:12px}.audit-event-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;padding:13px}.audit-event-link{display:flex;align-items:center;justify-content:space-between;gap:7px;padding:10px;border:1px solid #e4eae6;border-radius:8px;color:#45534a;background:#fbfcfb;text-decoration:none;font-size:9px;font-weight:800}.audit-event-link:hover{color:var(--module-green);border-color:#afc3b6;background:#f8fbf9}.audit-event-link strong{font-size:14px;color:var(--module-ink)}
    .audit-module-list{display:flex;flex-direction:column;padding:7px 14px 12px}.audit-module-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid #edf1ee;color:#536159;font-size:9px}.audit-module-row:last-child{border-bottom:0}.audit-module-row strong{color:var(--module-ink)}
    .audit-time{min-width:116px}.audit-time strong,.audit-time small{display:block}.audit-event{display:flex;align-items:center;gap:8px;min-width:120px}.audit-event-dot{width:8px;height:8px;flex:0 0 auto;border-radius:50%;background:#849088;box-shadow:0 0 0 4px #eef1ef}.audit-event-dot-green{background:#26834a;box-shadow:0 0 0 4px #e8f4eb}.audit-event-dot-blue{background:#2b68a7;box-shadow:0 0 0 4px #eaf2fa}.audit-event-dot-amber{background:#b27814;box-shadow:0 0 0 4px #faf1dc}.audit-event-dot-red{background:#bc3e3e;box-shadow:0 0 0 4px #faeaea}.audit-module-copy{min-width:260px}.audit-module-copy strong,.audit-module-copy small{display:block}.audit-module-copy small{max-width:420px;white-space:normal;line-height:1.45}.audit-request{max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.module-badge-red{color:var(--module-red);background:var(--module-red-soft)}.module-badge-neutral{color:#526057;background:#edf1ee}.audit-read-button{white-space:nowrap}
    @media(max-width:1050px){.audit-overview{grid-template-columns:1fr}.audit-event-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:560px){.audit-event-grid{grid-template-columns:1fr}}
  </style>
@endpush

@php
  $filters = $filters ?? [];
  $activeFilterCount = collect($filters)
      ->except(['per_page'])
      ->filter(fn ($value) => filled($value))
      ->count();
  $roleLabels = [
      'super_admin' => 'Super Admin',
      'provincial_staff' => 'Provincial Staff',
      'municipal_head' => 'Head Agriculturist',
      'municipal_staff' => 'Municipal Staff',
  ];
  $eventOrder = ['login_failed', 'login_blocked', 'deleted', 'updated', 'created', 'login', 'logout', 'membership_updated', 'exported'];
  $exportQuery = collect(request()->query())->except(['page'])->all();
@endphp

@section('content')
<div class="module-page">
  <header class="module-header audit-header">
    <div>
      <div class="module-eyebrow">Super Admin oversight</div>
      <h1>Audit trail</h1>
      <p>Review account activity and an immutable history of operational changes across every municipality.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('dashboard') }}">Dashboard</a>
      <a class="module-button module-button-primary" href="{{ route('audit-logs.export', $exportQuery) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5"></path><path d="M5 21h14"></path></svg>
        Export filtered CSV
      </a>
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif

  <section class="module-kpis" aria-label="Audit summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Matching activities</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg></span></div>
      <strong>{{ number_format((int) $stats['total']) }}</strong>
      <small>{{ $activeFilterCount ? 'Reflects the active filter set' : 'All retained security and change events' }}</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Activity today</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span></div>
      <strong>{{ number_format((int) $stats['today']) }}</strong>
      <small>{{ now()->format('F d, Y') }} in system time</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Last seven days</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M4 5h16v16H4zM8 3v4M16 3v4M4 10h16"></path></svg></span></div>
      <strong>{{ number_format((int) $stats['seven_days']) }}</strong>
      <small>Recent activity matching this view</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Attention events</span><span class="module-kpi-icon module-kpi-icon-red"><svg viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"></path><path d="M10.3 3.8 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"></path></svg></span></div>
      <strong>{{ number_format((int) $stats['alerts']) }}</strong>
      <small>Failed, blocked, or deleted activity</small>
    </article>
  </section>

  <div class="audit-security-note">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg>
    <span><strong>Protected record.</strong> Only active Super Admin accounts can open or export this page. Passwords, session tokens, farmer QR tokens, and stored photo paths are never included in before/after values.</span>
  </div>

  <section class="module-panel">
    <div class="module-panel-head">
      <div><h2>Search the history</h2><p>Combine filters to investigate an account, municipality, date range, module, or record.</p></div>
      @if($activeFilterCount)<span class="module-panel-tag">{{ $activeFilterCount }} active {{ Str::plural('filter', $activeFilterCount) }}</span>@endif
    </div>
    <form class="module-filter" method="GET" action="{{ route('audit-logs.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search">
          <label for="auditSearch">Search activity</label>
          <div class="module-search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            <input class="module-input" id="auditSearch" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Actor, email, IP, description, or record ID">
          </div>
        </div>
        <div class="module-field">
          <label for="auditEvent">Event</label>
          <select class="module-input" id="auditEvent" name="event">
            <option value="">All events</option>
            @foreach($eventLabels as $value => $label)<option value="{{ $value }}" @selected($filters['event'] === $value)>{{ $label }}</option>@endforeach
          </select>
        </div>
        <div class="module-field">
          <label for="auditModule">Module</label>
          <select class="module-input" id="auditModule" name="module">
            <option value="">All modules</option>
            @foreach($modules as $module)<option value="{{ $module }}" @selected($filters['module'] === $module)>{{ $module }}</option>@endforeach
          </select>
        </div>
        <div class="module-field">
          <label for="auditActor">Actor</label>
          <select class="module-input" id="auditActor" name="user_id">
            <option value="">All accounts</option>
            @foreach($users as $actor)<option value="{{ $actor->id }}" @selected($filters['user_id'] === (string) $actor->id)>{{ $actor->name }} · {{ $actor->email }}</option>@endforeach
          </select>
        </div>
        <div class="module-field">
          <label for="auditMunicipality">Municipality</label>
          <select class="module-input" id="auditMunicipality" name="municipality_id">
            <option value="">All municipalities</option>
            @foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected($filters['municipality_id'] === (string) $municipality->id)>{{ $municipality->name }}</option>@endforeach
          </select>
        </div>
        <div class="module-field">
          <label for="auditDateFrom">From</label>
          <input class="module-input" id="auditDateFrom" type="date" name="date_from" value="{{ $filters['date_from'] }}">
        </div>
        <div class="module-field">
          <label for="auditDateTo">To</label>
          <input class="module-input" id="auditDateTo" type="date" name="date_to" value="{{ $filters['date_to'] }}">
        </div>
        <div class="module-field">
          <label for="auditPerPage">Rows per page</label>
          <select class="module-input" id="auditPerPage" name="per_page">
            @foreach([15,30,50,100] as $size)<option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }} rows</option>@endforeach
          </select>
        </div>
      </div>
      <div class="module-filter-actions">
        <span>@if($activeFilterCount)<span class="module-active-filter">The summary and export reflect these filters</span>@else Displaying the complete province-wide trail @endif</span>
        <div class="module-filter-buttons">
          @if($activeFilterCount)<a class="module-button" href="{{ route('audit-logs.index') }}">Clear filters</a>@endif
          <button class="module-button module-button-primary" type="submit">Apply filters</button>
        </div>
      </div>
    </form>
  </section>

  @if($stats['total'] > 0)
    <div class="audit-overview">
      <section class="module-panel">
        <div class="module-panel-head"><div><h2>Event composition</h2><p>Choose an event to narrow the activity table.</p></div></div>
        <div class="audit-event-grid">
          @foreach($eventOrder as $event)
            @if(($eventCounts[$event] ?? 0) > 0)
              <a class="audit-event-link" href="{{ route('audit-logs.index', array_merge(collect(request()->query())->except(['page', 'event'])->all(), ['event' => $event])) }}">
                <span>{{ $eventLabels[$event] ?? Str::headline($event) }}</span><strong>{{ number_format((int) $eventCounts[$event]) }}</strong>
              </a>
            @endif
          @endforeach
        </div>
      </section>
      <section class="module-panel">
        <div class="module-panel-head"><div><h2>Most active modules</h2><p>Based on the current filters.</p></div></div>
        <div class="audit-module-list">
          @foreach($moduleCounts as $moduleCount)
            <div class="audit-module-row"><span>{{ $moduleCount->module }}</span><strong>{{ number_format((int) $moduleCount->total) }}</strong></div>
          @endforeach
        </div>
      </section>
    </div>
  @endif

  <section class="module-panel">
    <div class="module-table-tools">
      <div><strong>Activity ledger</strong><span>{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }} · newest first</span></div>
      @if($records->isNotEmpty())<span class="module-badge module-badge-green">Read-only history</span>@endif
    </div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead><tr><th>Date & time</th><th>Event</th><th>Module & activity</th><th>Actor</th><th>Municipality</th><th>Source</th><th><span class="sr-only">View</span></th></tr></thead>
          <tbody>
            @foreach($records as $record)
              @php
                $initials = collect(preg_split('/\s+/', trim($record->actor_name ?: 'System')))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                $roleLabel = $roleLabels[$record->actor_role] ?? ($record->actor_role ? Str::headline($record->actor_role) : 'Unattributed event');
              @endphp
              <tr>
                <td class="audit-time"><strong>{{ $record->created_at?->format('M d, Y') }}</strong><small>{{ $record->created_at?->format('h:i:s A') }} · {{ $record->created_at?->diffForHumans() }}</small></td>
                <td><div class="audit-event"><span class="audit-event-dot audit-event-dot-{{ $record->event_tone }}"></span><span class="module-badge module-badge-{{ $record->event_tone === 'neutral' ? 'neutral' : $record->event_tone }}">{{ $record->event_label }}</span></div></td>
                <td class="audit-module-copy"><strong>{{ $record->module }}</strong><small>{{ $record->description }}</small></td>
                <td><div class="module-person"><span class="module-avatar">{{ $initials ?: 'SY' }}</span><span class="module-person-copy"><strong>{{ $record->actor_name ?: 'System / unknown' }}</strong><small>{{ $record->actor_email ?: $roleLabel }}</small></span></div></td>
                <td><strong>{{ $record->municipality?->name ?? 'Province-wide' }}</strong><small>{{ $roleLabel }}</small></td>
                <td><span class="module-mono">{{ $record->ip_address ?: 'Not captured' }}</span><small class="audit-request" title="{{ trim(($record->request_method ?? '').' '.($record->request_url ?? '')) }}">{{ $record->request_method ?: 'Background' }} {{ $record->request_url ? Str::after($record->request_url, url('/')) : 'event' }}</small></td>
                <td><a class="module-button module-button-small audit-read-button" href="{{ route('audit-logs.show', $record) }}">Inspect</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty">
        <span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg></span>
        <strong>No audit activity matches</strong>
        <span>{{ $activeFilterCount ? 'Clear or adjust the current filters to broaden the investigation.' : 'New account and record activity will appear here automatically.' }}</span>
        @if($activeFilterCount)<a class="module-button module-button-primary" href="{{ route('audit-logs.index') }}">Clear filters</a>@endif
      </div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'audit event'])
  </section>
</div>
@endsection
