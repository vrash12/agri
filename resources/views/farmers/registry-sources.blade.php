@extends('layouts.app')

@section('title', $registrySourceTitle.' · Farmers')

@section('content')
@include('partials.operations-ui-styles')
<div class="module-page registry-sources-page">
  @include('farmers.partials.workspace-nav')

  <header class="module-header">
    <div>
      <div class="module-eyebrow">{{ $workspaceName }} · Farmer registry</div>
      <h1>{{ $registrySourceTitle }}</h1>
      <p>
        @if($registrySourceStatus === 'delisted')
          Original delisted workbook entries, retained for office reference. These entries do not create or reactivate farmer profiles.
        @else
          Original workbook entries listed under Outside LGU. The recorded outside address does not transfer ownership away from the maintaining LGU.
        @endif
      </p>
    </div>
    <a class="module-button" href="{{ route('farmers.index', $workspaceParameters) }}#farmerDirectory">Back to farmer registry</a>
  </header>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert"><strong>Check the filters.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  @if(!$sourceAvailable)
    <div class="module-alert" role="status">Workbook records are unavailable until the registry source table is installed. Existing farmer profiles remain available.</div>
  @else
    <section class="module-panel" aria-labelledby="sourceDirectoryHeading">
      <div class="module-panel-head">
        <div><h2 id="sourceDirectoryHeading">{{ $registrySourceTitle }} records</h2><p>{{ number_format($sourceCounts[$registrySourceStatus]) }} retained source rows in {{ $workspaceName }}. Repeated parcel or commodity rows are counted separately.</p></div>
        <span class="module-panel-tag">Office reference</span>
      </div>
      <form class="registry-source-filters" method="GET" action="{{ route('farmers.registry-sources.index', ['status' => $registrySourceStatus]) }}">
        @foreach($workspaceParameters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <div><label for="sourceSearch">Search name, FFRS, RSBSA or parcel reference</label><input class="module-input" type="search" id="sourceSearch" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Enter a name or registry number"></div>
        <div><label for="sourcePerPage">Rows per page</label><select class="module-input" id="sourcePerPage" name="per_page">@foreach([10, 25, 50, 100] as $size)<option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>@endforeach</select></div>
        <button class="module-button module-button-primary" type="submit">Search records</button>
        @if(filled(request('q')))<a class="module-button" href="{{ route('farmers.registry-sources.index', ['status' => $registrySourceStatus] + $workspaceParameters) }}">Clear search</a>@endif
      </form>

      @if($sourceRows->count())
        <p class="registry-source-results" role="status">Showing {{ number_format($sourceRows->firstItem()) }}–{{ number_format($sourceRows->lastItem()) }} of {{ number_format($sourceRows->total()) }} {{ filled(request('q')) ? 'matching' : '' }} source rows.</p>
        <div class="registry-source-list">
          @foreach($sourceRows as $entry)
            <article class="registry-source-entry">
              <div class="registry-source-entry-head">
                <div><h3>{{ $entry['name'] }}</h3><p>{{ $entry['municipality'] }}@if($entry['province']) · {{ $entry['province'] }}@endif · {{ $entry['sheet'] }} · row {{ number_format($entry['row']) }}</p></div>
                <div class="registry-source-badges">
                  @if($registrySourceStatus === 'delisted')
                    @forelse($entry['reasons'] as $reason)<span class="module-badge module-badge-amber">{{ $reason }}</span>@empty<span class="module-badge module-badge-amber">Reason not recorded</span>@endforelse
                  @else<span class="module-badge">Outside LGU</span>@endif
                </div>
              </div>
              <dl class="registry-source-summary">
                <div><dt>Original RSBSA</dt><dd>{{ $entry['rsbsa'] ?: 'Not recorded' }}</dd></div>
                <div><dt>Original FFRS</dt><dd>{{ $entry['ffrs'] ?: 'Not recorded' }}</dd></div>
                <div><dt>Recorded residence</dt><dd>{{ $entry['address'] }}</dd></div>
              </dl>
              <details class="registry-source-details">
                <summary>View all original fields</summary>
                <dl class="registry-source-fields">
                  @foreach($entry['fields'] as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value === null || $value === '' ? 'Not recorded' : (is_bool($value) ? ($value ? 'Yes' : 'No') : (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))) }}</dd></div>
                  @endforeach
                </dl>
              </details>
              @if($entry['farmer_id'])<a class="module-button registry-source-profile-link" href="{{ route('farmers.records', $entry['farmer_id']) }}">View linked farmer profile</a>@endif
            </article>
          @endforeach
        </div>
        @include('partials.pagination', ['paginator' => $sourceRows, 'label' => 'source row'])
      @else
        @if($sourceRows->total() > 0)
          <div class="module-empty"><strong>This page has no source rows.</strong><span>The matching records are on an earlier page.</span><a class="module-button" href="{{ $sourceRows->url(1) }}">Return to first page</a></div>
        @else
          <div class="module-empty"><strong>{{ filled(request('q')) ? 'No matching source rows.' : 'No '.$registrySourceTitle.' source rows retained for this area.' }}</strong><span>{{ filled(request('q')) ? 'Try another name or registry number, or clear the search.' : 'Only retained workbook sheets appear here. This does not change the active farmer registry.' }}</span></div>
        @endif
      @endif
    </section>
  @endif
</div>
@endsection

@push('styles')
<style>
.registry-source-filters{display:flex;align-items:end;flex-wrap:wrap;gap:12px;padding:16px;border-bottom:1px solid var(--ui-border)}.registry-source-filters>div:first-child{flex:1 1 320px}.registry-source-filters label{display:block;margin-bottom:6px;font-size:14px;font-weight:500}.registry-source-results{margin:0;padding:12px 16px;color:var(--ui-text-muted);font-size:14px}.registry-source-list{padding:0 16px 16px;display:grid;gap:12px}.registry-source-entry{padding:16px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface)}.registry-source-entry-head{display:flex;justify-content:space-between;align-items:start;gap:16px}.registry-source-entry h3{margin:0;font-size:16px;font-weight:600;overflow-wrap:anywhere}.registry-source-entry-head p{margin:5px 0 0;color:var(--ui-text-muted);font-size:13px}.registry-source-badges{display:flex;gap:6px;flex-wrap:wrap}.registry-source-summary,.registry-source-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:16px 0 0}.registry-source-summary dt,.registry-source-fields dt{color:var(--ui-text-muted);font-size:12px}.registry-source-summary dd,.registry-source-fields dd{margin:4px 0 0;font-size:14px;overflow-wrap:anywhere;white-space:pre-wrap}.registry-source-details{margin-top:16px;border-top:1px solid var(--ui-border)}.registry-source-details summary{padding:12px 0;min-height:44px;cursor:pointer;color:var(--ui-primary);font-size:14px;font-weight:500}.registry-source-details summary:focus-visible{outline:3px solid var(--ui-focus);outline-offset:3px}.registry-source-fields{margin:0;padding:12px;background:var(--ui-surface-subtle)}.registry-source-profile-link{margin-top:12px}.registry-sources-page .module-header p{max-width:80ch}@media(max-width:720px){.registry-source-entry-head{flex-direction:column;gap:10px}.registry-source-summary,.registry-source-fields{grid-template-columns:1fr}.registry-source-filters>.module-button{width:100%;justify-content:center}.registry-source-filters>div{width:100%}}
</style>
@endpush
