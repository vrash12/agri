@extends('layouts.app')

@section('title', 'Farmers Cooperatives')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@php
  $hasFilters = filled($q ?? '')
      || filled($selectedMunicipalityId ?? '')
      || filled($status ?? '')
      || ($sort ?? 'name') !== 'name'
      || (int) ($perPage ?? 10) !== 10;
  $canManageOperations = auth()->user()->canManageOperationalData();
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Organization management</div>
      <h1>Farmers cooperatives</h1>
      <p>Maintain cooperative profiles, organize membership, and prepare member workbooks for field coordination.</p>
    </div>
    <div class="module-actions">
      @if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('farmers-cooperatives.create') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
        New cooperative
      </a>@else<span class="module-badge module-badge-green">Read-only oversight</span>@endif
    </div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error">{{ session('error') }}</div>@endif

  <section class="module-kpis" aria-label="Cooperative summary">
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

  <section class="module-panel">
    <div class="module-panel-head">
      <div><h2>Find a cooperative</h2><p>Search office records and narrow the membership state.</p></div>
      @if($hasFilters)<span class="module-panel-tag">Filtered view</span>@endif
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
      <div class="module-filter-actions">
        <span>@if($hasFilters)<span class="module-active-filter">Filters are active</span>@else Showing records available to your account @endif</span>
        <div class="module-filter-buttons">
          @if($hasFilters)<a class="module-button" href="{{ route('farmers-cooperatives.index') }}">Clear filters</a>@endif
          <button class="module-button module-button-primary" type="submit">Apply filters</button>
        </div>
      </div>
    </form>
  </section>

  <section class="module-panel">
    <div class="module-table-tools">
      <div><strong>Cooperative directory</strong><span>{{ number_format($records->total()) }} {{ Str::plural('result', $records->total()) }}</span></div>
    </div>
    @if($records->isNotEmpty())
      <div class="module-table-scroll">
        <table class="module-table">
          <thead><tr><th>Cooperative</th><th>Chairperson</th><th>Contact</th><th>Address</th><th>Members</th><th>Machinery</th><th>Description</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach($records as $record)
              @php
                $initials = collect(preg_split('/\s+/', trim($record->name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
              @endphp
              <tr>
                <td><div class="module-person"><span class="module-avatar">{{ $initials ?: 'CO' }}</span><span class="module-person-copy"><strong>{{ $record->name }}</strong><small>{{ $record->municipality?->name ?? 'Municipality unavailable' }}</small></span></div></td>
                <td><strong>{{ $record->chairperson ?: 'Not recorded' }}</strong></td>
                <td>{{ $record->contact_number ?: '—' }}</td>
                <td><span title="{{ $record->address }}">{{ Str::limit($record->address ?: 'Not recorded', 42) }}</span></td>
                <td><span class="module-badge {{ (int) $record->farmers_count > 0 ? 'module-badge-green' : 'module-badge-amber' }}">{{ number_format((int) $record->farmers_count) }} {{ Str::plural('member', (int) $record->farmers_count) }}</span></td>
                <td><a class="module-badge {{ (int) $record->machineries_count > 0 ? 'module-badge-blue' : '' }}" style="text-decoration:none" href="{{ route('machinery-inventory.index', ['holder_type' => 'cooperative', 'q' => $record->name]) }}">{{ number_format((int) $record->machineries_count) }} {{ Str::plural('asset', (int) $record->machineries_count) }}</a></td>
                <td>{{ Str::limit($record->description ?: 'No description', 58) }}</td>
                <td>
                  <div class="module-row-actions">
                    @if($canManageOperations)<a class="module-button module-button-primary module-button-small" href="{{ route('farmers-cooperatives.assign-farmers', $record) }}">Manage members</a>@endif
                    <details class="module-action-menu">
                      <summary aria-label="More actions">•••</summary>
                      <div class="module-action-menu-list">
                        <a href="{{ route('farmers-cooperatives.export-excel', $record) }}">Export member list</a>
                        @if($canManageOperations)<a href="{{ route('farmers-cooperatives.edit', $record) }}">Edit profile</a>
                        <form method="POST" action="{{ route('farmers-cooperatives.destroy', $record) }}" onsubmit="return confirm('Delete this cooperative?')">@csrf @method('DELETE')<button class="danger" type="submit">Delete cooperative</button></form>@endif
                      </div>
                    </details>
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
        <strong>No cooperatives found</strong><span>{{ $hasFilters ? 'Try clearing the current search or membership filters.' : 'Create the first cooperative profile for this office.' }}</span>
        @if(!$hasFilters && $canManageOperations)<a class="module-button module-button-primary" href="{{ route('farmers-cooperatives.create') }}">New cooperative</a>@endif
      </div>
    @endif
    @include('partials.pagination', ['paginator' => $records, 'label' => 'cooperative'])
  </section>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('click', event => {
    document.querySelectorAll('.module-action-menu[open]').forEach(menu => {
      if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
  });
</script>
@endpush
