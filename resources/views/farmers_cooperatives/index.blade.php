@extends('layouts.app')

@section('title', 'Farmers Cooperatives')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .cooperative-directory .module-filter-grid { grid-template-columns:minmax(0,2fr) repeat(2,minmax(0,1fr)); gap:16px; }
    .cooperative-directory .module-field { grid-column:auto; }
    .cooperative-display-options { margin-top:16px; }
    .cooperative-display-options > summary { display:flex; align-items:center; gap:8px; width:fit-content; min-height:44px; color:var(--ui-primary); font-weight:500; }
    .cooperative-display-options > summary::after { content:'+'; }
    .cooperative-display-options[open] > summary::after { content:'−'; }
    .cooperative-display-options .module-filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); max-width:560px; padding-top:8px; }
    .cooperative-directory .module-filter-summary { padding:12px 16px; border-top:1px solid var(--ui-border); }
    .cooperative-directory .module-table { min-width:760px; }
    .cooperative-directory .module-table caption { padding:12px 16px; color:var(--ui-text-muted); font-size:13px; text-align:left; border-bottom:1px solid var(--ui-border); }
    .cooperative-directory .module-person { min-width:160px; align-items:flex-start; }
    .cooperative-directory .module-person-copy strong,.cooperative-directory .module-person-copy small { white-space:normal; overflow-wrap:anywhere; }
    .cooperative-directory .module-table td { overflow-wrap:anywhere; }
    .cooperative-profile-notes { margin-top:8px; }
    .cooperative-profile-notes summary { min-height:44px; display:flex; align-items:center; color:var(--ui-primary); text-decoration:underline; text-underline-offset:3px; }
    .cooperative-profile-notes p { margin:4px 0; }
    .cooperative-directory .module-row-actions { flex-wrap:wrap; justify-content:flex-start; min-width:150px; }
    .cooperative-directory .module-row-actions .module-button { font-size:13px; min-height:44px; }
    .cooperative-more { width:100%; }
    .cooperative-more > summary { min-height:44px; width:fit-content; padding:8px 0; color:var(--ui-primary); font-weight:500; }
    .cooperative-more-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .cooperative-more-actions form { margin:0; }
    .cooperative-mobile-label { display:none; }
    .cooperative-directory .module-empty strong { font-size:18px; }
    .cooperative-directory .module-empty > span { font-size:14px; }
    @media(max-width:1100px) {
      .cooperative-directory .module-filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
      .cooperative-directory .module-field-search { grid-column:1/-1; }
    }
    @media(max-width:1100px) {
      .cooperative-directory .module-filter-grid,.cooperative-display-options .module-filter-grid { grid-template-columns:1fr; }
      .cooperative-directory .module-table { display:block; min-width:0; }
      .cooperative-directory .module-table caption { display:block; }
      .cooperative-directory .module-table thead { position:absolute; width:1px; height:1px; padding:0; overflow:hidden; clip-path:inset(50%); white-space:nowrap; }
      .cooperative-directory .module-table tbody { display:block; }
      .cooperative-directory .module-table tr { display:block; padding:16px; border-bottom:1px solid var(--ui-border); }
      .cooperative-directory .module-table tr:last-child { border-bottom:0; }
      .cooperative-directory .module-table td { display:block; padding:8px 0; border:0; }
      .cooperative-directory .module-table td:first-child { padding-top:0; }
      .cooperative-mobile-label { display:block; margin-bottom:4px; color:var(--ui-text-muted); font-size:12px; font-weight:500; }
      .cooperative-directory .module-row-actions { padding-top:8px; border-top:1px solid var(--ui-border); }
      .cooperative-directory .module-row-actions > .module-button { flex:1 1 auto; }
    }
  </style>
@endpush

@php
  $hasFilters = filled($q ?? '') || in_array($status ?? '', ['with_members', 'empty'], true);
  $displayOptionsActive = ($sort ?? 'name') !== 'name' || (int) ($perPage ?? 10) !== 10;
  $canManageOperations = auth()->user()->canManageOperationalData();
  $scopeQuery = ($canChooseMunicipality ?? false) && filled($selectedMunicipalityId ?? '')
      ? ['municipality_id' => $selectedMunicipalityId] : [];
  $directoryQuery = array_filter(array_merge($scopeQuery, [
      'q' => $q ?? '', 'status' => $status ?? '', 'sort' => $sort ?? 'name', 'per_page' => $perPage ?? 10,
  ]), fn ($value) => filled($value));
  $clearFiltersUrl = route('farmers-cooperatives.index', $scopeQuery);
  $scopeName = ($canChooseMunicipality ?? false)
      ? (collect($municipalities ?? [])->firstWhere('id', $selectedMunicipalityId)?->name ?? auth()->user()->scopeLabel())
      : auth()->user()->scopeLabel();
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Organization management</div>
      <h1>Farmers cooperatives</h1>
      <p>Find a cooperative, manage its members, or export a member list.</p>
      <p class="module-scope-note">Office scope: <strong>{{ $scopeName }}</strong></p>
    </div>
    <div class="module-actions">
      @if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('farmers-cooperatives.create', $scopeQuery) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
        New cooperative
      </a>@else<span class="module-badge module-badge-green">Read-only oversight</span>@endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <section class="module-kpis module-kpis-compact" aria-label="Cooperative summary for the current office and filters">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Cooperatives</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"></circle><circle cx="16" cy="8" r="3"></circle><path d="M2 20a6 6 0 0 1 12 0M10 20a6 6 0 0 1 12 0"></path></svg></span></div>
      <strong>{{ number_format((int) ($totalCooperatives ?? 0)) }}</strong>
      <small>Matching the current office and filters</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Assigned members</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20a6 6 0 0 1 12 0M16 11a4 4 0 0 1 4 4v5"></path></svg></span></div>
      <strong>{{ number_format((int) ($totalMembers ?? 0)) }}</strong>
      <small>Membership assignments across cooperatives</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Active membership lists</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg></span></div>
      <strong>{{ number_format((int) ($cooperativesWithMembers ?? 0)) }}</strong>
      <small>Cooperatives with at least one assigned farmer</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Needs membership</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"></path><circle cx="12" cy="12" r="9"></circle></svg></span></div>
      <strong>{{ number_format((int) ($emptyCooperatives ?? 0)) }}</strong>
      <small>Profiles without an assigned farmer</small>
    </article>
  </section>

  <section class="module-panel cooperative-directory" aria-labelledby="cooperativeDirectoryTitle">
    <div class="module-panel-head">
      <div><h2 id="cooperativeDirectoryTitle">Cooperative directory</h2><p>{{ number_format($records->total()) }} {{ Str::plural('cooperative', $records->total()) }}{{ $hasFilters ? ' matching your filters' : ' in this office scope' }}</p></div>
    </div>
    <form class="module-filter" method="GET" action="{{ route('farmers-cooperatives.index') }}">
      <div class="module-filter-grid">
        <div class="module-field module-field-search">
          <label for="cooperativeSearch">Search</label>
          <div class="module-search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            <input class="module-input" id="cooperativeSearch" type="search" name="q" value="{{ $q }}" placeholder="Name, chairperson, address, or contact">
          </div>
        </div>
        @if($canChooseMunicipality ?? false)
          <div class="module-field">
            <label for="cooperativeMunicipality">Municipality</label>
            <select class="module-input" id="cooperativeMunicipality" name="municipality_id">
              <option value="">All municipalities</option>
              @foreach(($municipalities ?? []) as $municipality)
                <option value="{{ $municipality->id }}" @selected((string) ($selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="module-field">
          <label for="cooperativeStatus">Membership</label>
          <select class="module-input" id="cooperativeStatus" name="status">
            <option value="">All profiles</option>
            <option value="with_members" @selected(($status ?? '') === 'with_members')>With assigned members</option>
            <option value="empty" @selected(($status ?? '') === 'empty')>Needs membership</option>
          </select>
        </div>
      </div>
      <details class="cooperative-display-options" @if($displayOptionsActive) open @endif>
        <summary>Display options</summary>
        <div class="module-filter-grid">
        <div class="module-field">
          <label for="cooperativeSort">Sort by</label>
          <select class="module-input" id="cooperativeSort" name="sort">
            <option value="name" @selected(($sort ?? 'name') === 'name')>Cooperative name</option>
            <option value="members" @selected(($sort ?? '') === 'members')>Most members</option>
            <option value="newest" @selected(($sort ?? '') === 'newest')>Recently created</option>
          </select>
        </div>
        <div class="module-field">
          <label for="cooperativePerPage">Rows per page</label>
          <select class="module-input" id="cooperativePerPage" name="per_page">
            @foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected((int) $perPage === $n)>{{ $n }} rows</option>@endforeach
          </select>
        </div>
        </div>
      </details>
      <div class="module-filter-actions">
        <span>Search by name, chairperson, address, or contact number.</span>
        <div class="module-filter-buttons">
          @if($hasFilters)<a class="module-button" href="{{ $clearFiltersUrl }}">Clear filters</a>@endif
          <button class="module-button module-button-primary" type="submit">Apply filters</button>
        </div>
      </div>
    </form>
    @if($hasFilters)
      <div class="module-filter-summary" aria-label="Applied filters">
        <span>Filtered by:</span>
        @if(filled($q ?? ''))
          <a class="module-filter-chip" href="{{ route('farmers-cooperatives.index', \Illuminate\Support\Arr::except($directoryQuery, ['q'])) }}" aria-label="Remove search filter: {{ $q }}"><span>Search: {{ $q }}</span><span aria-hidden="true">×</span></a>
        @endif
        @if(in_array($status ?? '', ['with_members', 'empty'], true))
          <a class="module-filter-chip" href="{{ route('farmers-cooperatives.index', \Illuminate\Support\Arr::except($directoryQuery, ['status'])) }}" aria-label="Remove membership filter"><span>{{ $status === 'empty' ? 'Needs membership' : 'With assigned members' }}</span><span aria-hidden="true">×</span></a>
        @endif
      </div>
    @endif
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table" role="table">
          <caption>Profiles and membership · {{ match ($sort ?? 'name') { 'members' => 'Most members first', 'newest' => 'Newest profiles first', default => 'Name A–Z' } }}</caption>
          <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Cooperative</th><th scope="col" role="columnheader">Chairperson / contact</th><th scope="col" role="columnheader">Address</th><th scope="col" role="columnheader">Members</th><th scope="col" role="columnheader">Machinery</th><th scope="col" role="columnheader">Actions</th></tr></thead>
          <tbody role="rowgroup">
            @foreach($records as $record)
              @php
                $initials = collect(preg_split('/\s+/', trim($record->name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
              @endphp
              <tr role="row">
                <td role="cell"><div class="module-person"><span class="module-avatar" aria-hidden="true">{{ $initials ?: 'CO' }}</span><span class="module-person-copy"><strong>{{ $record->name }}</strong><small>{{ $record->municipality?->name ?? 'Municipality unavailable' }}</small></span></div>@if(filled($record->description))<details class="cooperative-profile-notes"><summary>Profile notes</summary><p>{{ $record->description }}</p></details>@endif</td>
                <td role="cell"><span class="cooperative-mobile-label" aria-hidden="true">Chairperson / contact</span><strong>{{ $record->chairperson ?: 'Not recorded' }}</strong><small>{{ $record->contact_number ?: 'No contact recorded' }}</small></td>
                <td role="cell"><span class="cooperative-mobile-label" aria-hidden="true">Address</span>{{ $record->address ?: 'Not recorded' }}</td>
                <td role="cell"><span class="cooperative-mobile-label" aria-hidden="true">Members</span><span class="module-badge {{ (int) $record->farmers_count > 0 ? 'module-badge-green' : 'module-badge-amber' }}">{{ number_format((int) $record->farmers_count) }} {{ Str::plural('member', (int) $record->farmers_count) }}</span></td>
                <td role="cell"><span class="cooperative-mobile-label" aria-hidden="true">Machinery</span><a class="module-button module-button-small" href="{{ route('machinery-inventory.index', ['holder_type' => 'cooperative', 'q' => $record->name, 'municipality_id' => $record->municipality_id]) }}" aria-label="View machinery for {{ $record->name }}">{{ number_format((int) $record->machineries_count) }} {{ Str::plural('asset', (int) $record->machineries_count) }}</a></td>
                <td role="cell">
                  <div class="module-row-actions">
                    @if($canManageOperations)
                    <a class="module-button module-button-small" href="{{ route('farmers-cooperatives.assign-farmers', $record) }}" aria-label="Manage members of {{ $record->name }}">Manage members</a>
                    <a class="module-button module-button-small" href="{{ route('farmers-cooperatives.edit', $record) }}" aria-label="Edit profile of {{ $record->name }}">Edit profile</a>
                    <details class="cooperative-more">
                      <summary aria-label="More actions for {{ $record->name }}">More actions</summary>
                      <div class="cooperative-more-actions">
                        <a class="module-button module-button-small" href="{{ route('farmers-cooperatives.export-excel', $record) }}">Export member list</a>
                        <form method="POST" action="{{ route('farmers-cooperatives.destroy', $record) }}" data-cooperative-name="{{ $record->name }}" onsubmit="return confirm('Delete ' + this.dataset.cooperativeName + '? This removes the cooperative profile and its membership links. Farmer records are kept.')">@csrf @method('DELETE')<button class="module-button module-button-small module-button-danger" type="submit">Delete cooperative</button></form>
                      </div>
                    </details>
                    @else
                    <a class="module-button module-button-small" href="{{ route('farmers-cooperatives.export-excel', $record) }}" aria-label="Export member list for {{ $record->name }}">Export member list</a>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="module-empty">
        <span class="module-empty-icon"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"></circle><circle cx="16" cy="8" r="3"></circle><path d="M2 20a6 6 0 0 1 12 0M10 20a6 6 0 0 1 12 0"></path></svg></span>
        <strong>{{ $hasFilters ? 'No cooperatives match these filters' : 'No cooperatives recorded yet' }}</strong>
        <span>{{ $hasFilters ? 'Clear the search and membership filters to see other profiles in this office scope.' : ($canManageOperations ? 'Create a cooperative profile, then add its registered farmers.' : 'Cooperative profiles will appear here when staff record them.') }}</span>
        @if($hasFilters)<a class="module-button" href="{{ $clearFiltersUrl }}">Clear filters</a>
        @elseif($canManageOperations)<a class="module-button module-button-primary" href="{{ route('farmers-cooperatives.create', $scopeQuery) }}">New cooperative</a>@endif
      </div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'cooperative'])
  </section>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('click', event => {
    document.querySelectorAll('.cooperative-more[open]').forEach(menu => {
      if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.cooperative-more[open]').forEach(menu => {
      if (menu.contains(document.activeElement)) menu.querySelector('summary').focus();
      menu.removeAttribute('open');
    });
  });
</script>
@endpush
