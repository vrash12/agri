@extends('layouts.app')

@section('title', 'Farmers')

@section('content')
@include('partials.operations-ui-styles')
@php
  $farmersMapData = collect($farmers->items())->map(function ($farmer) {
    return [
      'id' => $farmer->id,
      'registry_id' => $farmer->registry_id,
      'profile_photo_url' => $farmer->profile_photo_path ? route('farmers.photo', $farmer) : null,
      'last_name' => $farmer->last_name,
      'first_name' => $farmer->first_name,
      'middle_name' => $farmer->middle_name,
      'ext_name' => $farmer->ext_name,
      'owner_name' => $farmer->owner_name,
      'ffrs' => $farmer->ffrs,
      'date_of_birth' => $farmer->date_of_birth,
      'gender' => $farmer->gender,
      'location' => $farmer->farm_location,
      'farm_location' => $farmer->farm_location,
      'farm_municipality' => $farmer->farm_municipality,
      'farm_province' => $farmer->farm_province,
      'farm_area_ha' => $farmer->farm_area_ha,
      'records_count' => (int) ($farmer->records_count ?? 0),
      'total_kgs' => (float) ($farmer->total_kgs ?? 0),
      'last_received' => $farmer->last_received,
    ];
  })->values();

  $activeFilterCount = collect([
    request('q'), request('municipality_id'), request('gender'),
    request('mapping'), request('quality'),
  ])->filter(fn ($value) => filled($value))->count();
  $advancedFilterCount = collect([
    request('municipality_id'), request('gender'), request('mapping'),
    request('quality'),
  ])->filter(fn ($value) => filled($value))->count();
  $selectedMunicipality = collect($municipalities ?? [])->firstWhere(
      'id',
      (int) request('municipality_id')
  );
  $activeFilters = collect();
  if (filled(request('q'))) {
      $activeFilters->push(['key' => 'q', 'label' => 'Search', 'value' => '“'.request('q').'”']);
  }
  if (filled(request('municipality_id'))) {
      $activeFilters->push(['key' => 'municipality_id', 'label' => 'Municipality', 'value' => $selectedMunicipality?->name ?? 'Selected']);
  }
  if (filled(request('gender'))) {
      $activeFilters->push(['key' => 'gender', 'label' => 'Gender', 'value' => request('gender')]);
  }
  if (filled(request('mapping'))) {
      $activeFilters->push(['key' => 'mapping', 'label' => 'Parcel status', 'value' => request('mapping') === 'mapped' ? 'Mapped' : 'Needs mapping']);
  }
  if (filled(request('quality'))) {
      $activeFilters->push(['key' => 'quality', 'label' => 'Follow-up', 'value' => request('quality') === 'missing_ffrs' ? 'Missing FFRS' : 'Missing location']);
  }
  $directoryUrl = fn (array $parameters = []) => route('farmers.index', $parameters).'#farmerDirectory';
  $canManageOperations = auth()->user()->canManageOperationalData();
@endphp

<div class="module-page farmers-registry-page">
  @if (session('success'))
    <div class="module-alert">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="module-alert module-alert-error">{{ session('error') }}</div>
  @endif

  <header class="module-header">
    <div>
      <div class="module-eyebrow">Registry and land management</div>
      <h1>Farmer registry</h1>
      <p>Manage verified farmer profiles, monitor assistance history, and maintain parcel boundaries from one operational workspace.</p>
    </div>
    <div class="module-actions">
      @if($canManageOperations)
        <a class="module-button" href="#farmerImport">
          <svg viewBox="0 0 24 24"><path d="M12 3v12m0-12-4 4m4-4 4 4M5 15v4h14v-4"/></svg>
          Import Excel
        </a>
      @endif
      <a class="module-button" href="#farmersMapModule">
        <svg viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/></svg>
        Mapping workspace
      </a>
      @if($canManageOperations)
        <a class="module-button module-button-primary" href="{{ route('farmers.create') }}">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          Add farmer
        </a>
      @else
        <span class="module-badge module-badge-green">Read-only oversight</span>
      @endif
    </div>
  </header>

  @if($activeFilterCount)
    <div class="farmer-scope-banner" role="status">
      <span class="farmer-scope-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16l-6 7v6l-4 2v-8L4 5Z"></path></svg></span>
      <div><strong>Filtered registry view</strong><span>The summary cards, directory, insights, and map currently reflect {{ $activeFilterCount }} active {{ Str::plural('filter', $activeFilterCount) }}.</span></div>
      <a href="{{ $directoryUrl(request()->except('page')) }}">Review filters</a>
      <a href="{{ $directoryUrl() }}" data-clear-all>Clear all</a>
    </div>
  @endif

  <section class="module-kpis" aria-label="Farmer registry summary">
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Matching profiles</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M22 21v-2a4 4 0 0 0-3-3.87"/></svg></span></div>
      <strong>{{ number_format($totalFarmers) }}</strong>
      <small>{{ number_format($locationCount) }} recorded farm locations</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Mapping coverage</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/></svg></span></div>
      <strong>{{ number_format($mappingCoverage, 1) }}<small>%</small></strong>
      <small>{{ number_format($mappedFarmers) }} farmers · {{ number_format($totalPlots) }} saved parcels</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Seed assistance</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="M12 22V8M7 13c-3-1-4-4-4-7 3 0 6 1 7 4M17 13c3-1 4-4 4-7-3 0-6 1-7 4"/></svg></span></div>
      <strong>{{ number_format($totalKgs, 2) }}<small> kg</small></strong>
      <small>Distributed to the current result set</small>
    </article>
    <article class="module-kpi">
      <div class="module-kpi-top"><span class="module-kpi-label">Registry follow-up</span><span class="module-kpi-icon {{ $missingFfrs > 0 ? 'module-kpi-icon-red' : '' }}"><svg viewBox="0 0 24 24"><path d="M9 11h6M9 15h4M7 3h10l3 3v15H4V3h3Z"/><path d="M14 3v4h6"/></svg></span></div>
      <strong>{{ number_format($missingFfrs) }}</strong>
      <small>Profiles missing an FFRS number</small>
    </article>
  </section>

  <section class="module-panel" id="farmerDirectory">
    <div class="module-panel-head farmer-directory-head">
      <div><h2>Farmer directory</h2><p>Search the registry first, then open a farmer on the map to review or plot their land.</p></div>
      <span class="module-panel-tag">{{ number_format($farmers->total()) }} {{ Str::plural('farmer', $farmers->total()) }}</span>
    </div>

    <form class="farmer-directory-filter" method="GET" action="{{ route('farmers.index') }}#farmerDirectory">
      <div class="farmer-search-toolbar">
        <div class="farmer-primary-search">
          <label for="farmer_q">Search farmers</label>
          <div class="module-search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input class="module-input" type="search" id="farmer_q" name="q" value="{{ request('q') }}" placeholder="Name, Farmer ID, FFRS, RSBSA, owner, or location">
          </div>
        </div>
        <button class="module-button module-button-primary farmer-search-button" type="submit">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          Search
        </button>
        @if($activeFilterCount)
          <a class="module-button farmer-clear-button" href="{{ $directoryUrl() }}">Clear all</a>
        @endif
      </div>

      <details class="farmer-filter-drawer" @if($advancedFilterCount) open @endif>
        <summary>
          <span class="farmer-filter-summary-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16l-6 7v6l-4 2v-8L4 5Z"></path></svg></span>
          <span><strong>More filters</strong><small>Municipality, gender, parcel status, and records needing follow-up</small></span>
          @if($advancedFilterCount)<b>{{ $advancedFilterCount }} active</b>@endif
          <i aria-hidden="true"></i>
        </summary>
        <div class="farmer-filter-drawer-body">
          <div class="module-filter-grid">
            @if ($canChooseMunicipality)
              <div class="module-field">
                <label for="farmer_municipality">Municipality</label>
                <select class="module-input" id="farmer_municipality" name="municipality_id">
                  <option value="">All municipalities</option>
                  @foreach ($municipalities as $municipality)
                    <option value="{{ $municipality->id }}" @selected((string) request('municipality_id') === (string) $municipality->id)>{{ $municipality->name }}</option>
                  @endforeach
                </select>
              </div>
            @endif
            <div class="module-field">
              <label for="farmer_gender">Gender</label>
              <select class="module-input" id="farmer_gender" name="gender">
                <option value="">All genders</option>
                @foreach (['Male', 'Female', 'Other', 'Unspecified'] as $gender)
                  <option value="{{ $gender }}" @selected(request('gender') === $gender)>{{ $gender }}</option>
                @endforeach
              </select>
            </div>
            <div class="module-field">
              <label for="farmer_mapping">Parcel status</label>
              <select class="module-input" id="farmer_mapping" name="mapping">
                <option value="">All mapping states</option>
                <option value="mapped" @selected(request('mapping') === 'mapped')>With mapped parcel</option>
                <option value="unmapped" @selected(request('mapping') === 'unmapped')>Needs mapping</option>
              </select>
            </div>
            <div class="module-field">
              <label for="farmer_quality">Data follow-up</label>
              <select class="module-input" id="farmer_quality" name="quality">
                <option value="">All profile quality</option>
                <option value="missing_ffrs" @selected(request('quality') === 'missing_ffrs')>Missing FFRS</option>
                <option value="missing_location" @selected(request('quality') === 'missing_location')>Missing farm location</option>
              </select>
            </div>
            <div class="module-field">
              <label for="farmer_per_page">Rows per page</label>
              <select class="module-input" id="farmer_per_page" name="per_page" data-auto-submit>
                @foreach ([10, 25, 50, 100] as $amount)
                  <option value="{{ $amount }}" @selected((int) $perPage === $amount)>{{ $amount }} rows</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="farmer-filter-actions">
            <span>These filters update the summary, directory, map, and insights together.</span>
            <div>
              @if($advancedFilterCount)<a class="module-button" href="{{ $directoryUrl(request()->except(['municipality_id', 'gender', 'mapping', 'quality', 'page'])) }}">Reset more filters</a>@endif
              <button class="module-button module-button-primary" type="submit">Show matching farmers</button>
            </div>
          </div>
        </div>
      </details>
    </form>

    @if($activeFilters->isNotEmpty())
      <div class="farmer-active-filters" aria-label="Applied filters">
        <span>Applied filters</span>
        @foreach($activeFilters as $filter)
          <a href="{{ $directoryUrl(request()->except([$filter['key'], 'page'])) }}" title="Remove {{ $filter['label'] }} filter">
            <small>{{ $filter['label'] }}</small>{{ $filter['value'] }} <b aria-hidden="true">×</b>
          </a>
        @endforeach
      </div>
    @endif

    <div class="module-table-tools farmer-results-tools">
      <div><strong>{{ $activeFilterCount ? 'Matching farmers' : 'All accessible farmers' }}</strong><span>Showing {{ $farmers->firstItem() ?? 0 }}–{{ $farmers->lastItem() ?? 0 }} of {{ number_format($farmers->total()) }} · Select a row to open that farmer in the mapping workspace.</span></div>
      <span class="farmer-map-hint"><i></i>{{ number_format($mappedFarmers) }} mapped in these results</span>
    </div>
    <div class="module-table-scroll">
      <table class="module-table farmer-directory-table" id="farmersTable">
        <thead>
          <tr>
            <th>Farmer</th>
            <th>Registry IDs</th>
            <th>Farm location</th>
            <th>Farm profile</th>
            <th>Mapping</th>
            <th>Assistance history</th>
            <th>Latest activity</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($farmers as $farmer)
            @php
              $name = trim(collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' '));
              $initials = strtoupper(substr($farmer->first_name ?: 'F', 0, 1).substr($farmer->last_name ?: 'R', 0, 1));
              $plotCount = (int) ($farmer->plot_count ?? 0);
              $mappedArea = (float) ($farmer->mapped_area_ha ?? 0);
              $recordCount = (int) ($farmer->records_count ?? 0);
              $lastReceived = $farmer->last_received ? \Illuminate\Support\Carbon::parse($farmer->last_received)->format('M d, Y') : null;
            @endphp
            <tr id="farmer-row-{{ $farmer->id }}" data-farmer-id="{{ $farmer->id }}" data-location="{{ e((string) $farmer->farm_location) }}" tabindex="0">
              <td data-label="Farmer">
                <div class="module-person">
                  <span class="module-avatar farmer-directory-avatar">
                    @if ($farmer->profile_photo_path)
                      <img src="{{ route('farmers.photo', $farmer) }}" alt="{{ $name ?: 'Farmer' }} profile photo" loading="lazy">
                    @else
                      {{ $initials }}
                    @endif
                  </span>
                  <span class="module-person-copy"><strong>{{ $name ?: 'Unnamed farmer' }}</strong><small>{{ $farmer->gender ?: 'Gender not recorded' }}{{ $farmer->contact_number ? ' · '.$farmer->contact_number : '' }}</small></span>
                </div>
              </td>
              <td data-label="Registry IDs"><strong class="module-mono">{{ $farmer->registry_id }}</strong><small class="module-mono">FFRS: {{ $farmer->ffrs ?: '—' }}</small><small class="module-mono">RSBSA: {{ $farmer->rsbsa_no ?: '—' }}</small></td>
              <td data-label="Farm location"><strong>{{ $farmer->farm_location ?: 'Location needed' }}</strong><small>{{ $farmer->farm_municipality ?: 'Municipality not recorded' }}</small></td>
              <td data-label="Farm profile"><strong>{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'Area needed' }}</strong><small>{{ $farmer->ecosystem ?: 'Ecosystem not recorded' }}</small></td>
              <td data-label="Mapping">
                @if ($plotCount > 0)
                  <span class="module-badge module-badge-green">{{ $plotCount }} parcel{{ $plotCount === 1 ? '' : 's' }}</span>
                  <small>{{ number_format($mappedArea, 2) }} ha mapped</small>
                @else
                  <span class="module-badge module-badge-amber">Needs mapping</span>
                  <small>No saved boundary</small>
                @endif
              </td>
              <td data-label="Assistance history"><strong>{{ number_format((float) ($farmer->total_kgs ?? 0), 2) }} kg</strong><small>Weight-based · {{ number_format($recordCount) }} distribution record{{ $recordCount === 1 ? '' : 's' }}</small></td>
              <td data-label="Latest activity"><strong>{{ $lastReceived ?: 'No distribution yet' }}</strong><small>{{ $farmer->updated_at ? 'Profile updated '.$farmer->updated_at->diffForHumans() : '—' }}</small></td>
              <td data-label="Actions">
                <div class="module-row-actions">
                  <a class="module-button module-button-small" href="{{ route('farmers.records', $farmer) }}">History</a>
                  <details class="module-action-menu">
                    <summary aria-label="More actions">⋯</summary>
                    <div class="module-action-menu-list">
                      <button type="button" class="js-map-farmer" data-farmer-id="{{ $farmer->id }}">Open on map</button>
                      <a href="{{ route('farmers.id-card', $farmer) }}">Digital ID</a>
                      @if($canManageOperations)
                        <a href="{{ route('farmers.edit', $farmer) }}">Edit profile</a>
                        <a href="{{ route('rice-seed-distributions.create', ['farmer_id' => $farmer->id]) }}">Add distribution</a>
                        <form method="POST" action="{{ route('farmers.destroy', $farmer) }}" onsubmit="return confirm('Delete this farmer profile? This cannot be undone.');">
                          @csrf @method('DELETE')
                          <button class="danger" type="submit">Delete profile</button>
                        </form>
                      @endif
                    </div>
                  </details>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><div class="module-empty"><span class="module-empty-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8"/></svg></span><strong>No farmer profiles match</strong><span>Clear the current filters to review other profiles.</span>@if($canManageOperations)<a class="module-button module-button-primary" href="{{ route('farmers.create') }}">Add farmer</a>@endif</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.pagination', ['paginator' => $farmers, 'label' => 'farmer', 'fragment' => 'farmerDirectory'])
  </section>

  <section aria-label="Parcel mapping workspace">
    @include('farmers.maps', ['farmersMapData' => $farmersMapData])
  </section>

  <details class="module-more">
    <summary>Registry insights <span>Gender and high-volume farm locations</span></summary>
    <div class="module-more-content">
      <div class="module-analytics-grid">
        <article class="module-chart"><div class="module-chart-head"><h3>Profile gender distribution</h3><p>Composition of the filtered farmer set.</p></div><div class="module-chart-body"><canvas id="genderChart"></canvas>@if (collect($genderStats)->isEmpty())<div class="module-chart-empty">No gender data to chart.</div>@endif</div></article>
        <article class="module-chart"><div class="module-chart-head"><h3>Top farm locations</h3><p>Locations with the most matching farmer profiles.</p></div><div class="module-chart-body"><canvas id="locationChart"></canvas>@if (collect($locationStats)->isEmpty())<div class="module-chart-empty">No location data to chart.</div>@endif</div></article>
      </div>
    </div>
  </details>

  @if($canManageOperations)
  <details class="module-more" id="farmerImport" @if($errors->has('file') || $errors->has('municipality_id')) open @endif>
    <summary>Import farmer registry from Excel <span>Ramos RSBSA / FFRS workbook</span></summary>
    <div class="module-more-content">
      <form class="farmer-import-form" method="POST" action="{{ route('farmers.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="module-dropzone">
          <span class="module-dropzone-icon"><svg viewBox="0 0 24 24"><path d="M12 3v12m0-12-4 4m4-4 4 4M5 15v4h14v-4"/></svg></span>
          <div><strong>Select the official workbook</strong><small>.xlsx or .xls · PARCEL LISTING and OUTSIDE LGU sheets are processed</small><input type="file" name="file" accept=".xlsx,.xls" required></div>
        </div>
        @if ($canChooseMunicipality)
          <div class="module-field">
            <label for="import_municipality_id">Import into municipality</label>
            <select class="module-input" id="import_municipality_id" name="municipality_id" required>
              <option value="">Select municipality</option>
              @foreach ($municipalities as $municipality)
                <option value="{{ $municipality->id }}" @selected((string) old('municipality_id') === (string) $municipality->id)>{{ $municipality->name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <button class="module-button module-button-primary" type="submit">Import workbook</button>
      </form>
      @error('file')<div class="farmer-import-error">{{ $message }}</div>@enderror
      @error('municipality_id')<div class="farmer-import-error">{{ $message }}</div>@enderror
    </div>
  </details>
  @endif
</div>

<script>
  window.__genderStats = @json($genderStats ?? []);
  window.__locationStats = @json($locationStats ?? []);
  window.__farmersMapData = window.__farmersMapData || @json($farmersMapData);
</script>
@endsection

@push('styles')
<style>
  .farmers-registry-page{scroll-behavior:smooth}
  #farmerDirectory,#farmersMapModule{scroll-margin-top:16px}
  .farmer-scope-banner{display:flex;align-items:center;gap:10px;padding:11px 13px;border:1px solid #cfdfd4;border-radius:10px;color:#526159;background:#f7fbf8;font-size:9px;line-height:1.4}.farmer-scope-banner .farmer-scope-icon{width:28px;height:28px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px;color:var(--module-green);background:var(--module-green-soft)}.farmer-scope-banner svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.farmer-scope-banner>div{display:flex;min-width:0;flex:1;flex-direction:column}.farmer-scope-banner strong{color:var(--module-ink);font-size:10px}.farmer-scope-banner a{padding:5px 8px;border-radius:6px;color:var(--module-green);font-weight:850;text-decoration:none;white-space:nowrap}.farmer-scope-banner a:hover{background:var(--module-green-soft)}.farmer-scope-banner a[data-clear-all]{color:var(--module-red)}.farmer-scope-banner a[data-clear-all]:hover{background:var(--module-red-soft)}
  .farmer-directory-head{align-items:center}.farmer-directory-filter{padding:14px 16px;border-bottom:1px solid var(--module-border);background:#fff}.farmer-search-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:8px;align-items:end}.farmer-primary-search label{display:block;margin:0 0 6px;color:#45534a;font-size:10px;font-weight:850}.farmer-primary-search .module-input{height:42px;font-size:11px}.farmer-search-button,.farmer-clear-button{height:42px;padding-inline:15px}
  .farmer-filter-drawer{margin-top:10px;border:1px solid #e0e7e2;border-radius:9px;background:#fafcfa}.farmer-filter-drawer>summary{display:flex;align-items:center;gap:9px;padding:10px 12px;list-style:none;cursor:pointer;user-select:none}.farmer-filter-drawer>summary::-webkit-details-marker{display:none}.farmer-filter-summary-icon{width:27px;height:27px;display:grid;place-items:center;flex:0 0 auto;border-radius:7px;color:var(--module-green);background:var(--module-green-soft)}.farmer-filter-summary-icon svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.farmer-filter-drawer>summary>span:nth-child(2){display:flex;min-width:0;flex:1;flex-direction:column}.farmer-filter-drawer>summary strong{color:var(--module-ink);font-size:10px}.farmer-filter-drawer>summary small{margin-top:2px;color:var(--module-muted);font-size:8px}.farmer-filter-drawer>summary b{padding:4px 7px;border-radius:999px;color:var(--module-green);background:var(--module-green-soft);font-size:8px;white-space:nowrap}.farmer-filter-drawer>summary i{width:8px;height:8px;margin:0 4px;border-right:2px solid #758078;border-bottom:2px solid #758078;transform:rotate(45deg);transition:transform .15s}.farmer-filter-drawer[open]>summary i{transform:rotate(225deg)}.farmer-filter-drawer-body{padding:12px;border-top:1px solid #e4eae6;background:#fff}.farmer-filter-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:11px;padding-top:11px;border-top:1px solid #edf1ee}.farmer-filter-actions>span{color:var(--module-muted);font-size:9px}.farmer-filter-actions>div{display:flex;gap:7px;flex-wrap:wrap}
  .farmer-active-filters{display:flex;align-items:center;gap:6px;padding:10px 16px;border-bottom:1px solid var(--module-border);background:#f8faf8;overflow-x:auto}.farmer-active-filters>span{margin-right:2px;color:var(--module-muted);font-size:8px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}.farmer-active-filters a{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border:1px solid #d8e3db;border-radius:999px;color:#34483b;background:#fff;font-size:9px;font-weight:800;text-decoration:none;white-space:nowrap}.farmer-active-filters a:hover{color:var(--module-red);border-color:#e7bbbb}.farmer-active-filters small{color:var(--module-muted);font-size:8px;font-weight:750}.farmer-active-filters b{font-size:12px;line-height:1}
  .farmer-results-tools{background:#fbfcfb}.farmer-map-hint{display:flex!important;align-items:center;gap:6px;color:var(--module-muted)!important;font-size:9px!important;white-space:nowrap}.farmer-map-hint i{width:7px;height:7px;border-radius:50%;background:#43a765}
  .farmer-directory-table{min-width:1120px}.farmer-directory-table tbody tr[data-farmer-id]{cursor:pointer}.farmer-directory-table tbody tr.row-highlight td{background:#eef7f1}.farmer-directory-table tbody tr.row-highlight td:first-child{box-shadow:inset 3px 0 0 var(--module-green)}.farmer-directory-avatar{overflow:hidden}.farmer-directory-avatar img{width:100%;height:100%;display:block;object-fit:cover}
  .farmer-import-form{display:grid;grid-template-columns:minmax(0,1fr) 230px auto;gap:10px;align-items:end;padding-top:12px}.farmer-import-form .module-dropzone{padding:12px}.farmer-import-form .module-dropzone>div{display:grid;gap:3px;width:100%}.farmer-import-form .module-dropzone strong{color:var(--module-ink);font-size:10px}.farmer-import-form .module-dropzone small{color:var(--module-muted);font-size:8px}.farmer-import-form .module-dropzone input{margin-top:5px}.farmer-import-error{margin-top:8px;color:var(--module-red);font-size:10px;font-weight:750}
  .module-more>summary span{margin-left:auto;color:var(--module-muted);font-size:9px;font-weight:650}.module-more>summary:after{margin-left:8px}
  @media(max-width:900px){.farmer-import-form{grid-template-columns:1fr}.farmer-import-form .module-button{width:100%}.farmer-filter-actions{align-items:flex-start;flex-direction:column}.farmer-results-tools{align-items:flex-start;flex-direction:column}}
  @media(max-width:620px){#farmerDirectory,#farmersMapModule{scroll-margin-top:76px}.farmer-scope-banner{align-items:flex-start;flex-wrap:wrap}.farmer-scope-banner>div{flex-basis:calc(100% - 42px)}.farmer-search-toolbar{grid-template-columns:1fr 1fr}.farmer-primary-search{grid-column:1/-1}.farmer-search-button,.farmer-clear-button{width:100%}.farmer-filter-drawer>summary small{display:none}.farmer-filter-actions>div,.farmer-filter-actions .module-button{width:100%}.farmer-directory-head{align-items:flex-start}.farmer-active-filters{padding-inline:12px}.farmer-directory-table,.farmer-directory-table tbody{display:block;width:100%!important;max-width:100%!important;min-width:0!important}.farmer-directory-table thead{display:none}.farmer-directory-table tbody{padding:10px;background:#f5f8f6}.farmer-directory-table tbody tr[data-farmer-id]{display:grid;width:100%!important;max-width:100%!important;grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:10px;overflow:hidden;border:1px solid #dfe7e1;border-radius:10px;background:#fff;box-shadow:0 2px 7px rgba(20,40,27,.025)}.farmer-directory-table tbody tr[data-farmer-id]:last-child{margin-bottom:0}.farmer-directory-table tbody tr[data-farmer-id] td{display:block;width:auto!important;max-width:none!important;min-width:0;padding:9px 11px;border:0;border-bottom:1px solid #edf1ee;background:#fff}.farmer-directory-table tbody tr[data-farmer-id] td:nth-child(odd):not(:first-child):not(:last-child){border-right:1px solid #edf1ee}.farmer-directory-table tbody tr[data-farmer-id] td:first-child,.farmer-directory-table tbody tr[data-farmer-id] td:last-child{grid-column:1/-1}.farmer-directory-table tbody tr[data-farmer-id] td:first-child{padding-block:11px;background:#fbfcfb}.farmer-directory-table tbody tr[data-farmer-id] td:last-child{border-bottom:0}.farmer-directory-table tbody tr[data-farmer-id] td:not(:first-child):before{content:attr(data-label);display:block;margin-bottom:5px;color:var(--module-muted);font-size:7px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.farmer-directory-table .module-row-actions{justify-content:flex-start}.farmer-directory-table .module-action-menu-list{left:0;right:auto}.module-table-scroll:has(.farmer-directory-table){width:100%;max-width:100%;overflow:visible}}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const gender = window.__genderStats || {};
    const locations = window.__locationStats || {};
    const genderCanvas = document.getElementById('genderChart');
    if (genderCanvas && Object.keys(gender).length) {
      new Chart(genderCanvas, {type:'doughnut',data:{labels:Object.keys(gender),datasets:[{data:Object.values(gender),backgroundColor:['#17643a','#d8a438','#6986a4','#9a7ab2'],borderColor:'#fff',borderWidth:3}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'right',labels:{usePointStyle:true,boxWidth:7,font:{size:10}}}}}});
    }
    const locationCanvas = document.getElementById('locationChart');
    if (locationCanvas && Object.keys(locations).length) {
      new Chart(locationCanvas, {type:'bar',data:{labels:Object.keys(locations),datasets:[{data:Object.values(locations),backgroundColor:'#4f8765',borderRadius:4,maxBarThickness:32}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf1ee'}},x:{ticks:{font:{size:9}},grid:{display:false}}}}});
    }

    function focusFarmer(id, openMap) {
      document.querySelectorAll('#farmersTable tbody tr').forEach(row => row.classList.remove('row-highlight'));
      document.getElementById('farmer-row-' + id)?.classList.add('row-highlight');
      if (openMap && typeof window.__openFarmer3d === 'function') window.__openFarmer3d(String(id), {showMarker:false});
      document.getElementById('farmersMapModule')?.scrollIntoView({behavior:'smooth', block:'start'});
    }
    document.getElementById('farmersTable')?.addEventListener('click', function (event) {
      const action = event.target.closest('a,button,form,details,summary,input,select,label');
      if (action) return;
      const row = event.target.closest('tr[data-farmer-id]');
      if (row) focusFarmer(row.dataset.farmerId, true);
    });
    document.querySelectorAll('#farmersTable tr[data-farmer-id]').forEach(row => row.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        focusFarmer(row.dataset.farmerId, true);
      }
    }));
    document.querySelectorAll('.js-map-farmer').forEach(button => button.addEventListener('click', () => focusFarmer(button.dataset.farmerId, true)));
    document.querySelector('[data-auto-submit]')?.addEventListener('change', event => event.target.form?.submit());
  });
</script>
@endpush
