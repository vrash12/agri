@php
  $workspaceUser = auth()->user();
  $workspaceMunicipality = $workspaceMunicipality ?? $selectedMunicipality ?? null;
  $workspaceCanChoose = $workspaceUser->canAccessAllMunicipalities();
  $scopeFilters = request()->only(['q', 'gender', 'mapping', 'quality', 'per_page']);
  $scopeParameters = $workspaceParameters ?? ($workspaceMunicipality ? ['municipality_id' => $workspaceMunicipality->id] : []);
  $workspaceLabel = $workspaceName ?? $workspaceMunicipality?->name ?? $workspaceUser->scopeLabel();
  $workspaceProvinceLabel = ($workspaceProvince ?? null)?->name ?? $workspaceMunicipality?->province;
  $workspaceHasFilters = $workspaceCanChoose && isset($workspaceRegions);
  $workspaceProvinceLabels = ($workspaceProvinces ?? collect())->pluck('name', 'id');
@endphp

@once
  @push('styles')
    <style>
      .farmer-workspace-control{display:flex;flex-direction:column;gap:16px;padding:16px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface)}
      .workspace-heading{display:flex;align-items:end;justify-content:space-between;gap:16px}.workspace-heading h2{margin:0;font-size:16px;font-weight:500}.workspace-heading p{margin:6px 0 0;color:var(--ui-text-muted);font-size:14px}
      .workspace-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px}.workspace-filter{min-width:0}.workspace-filter label{display:block;margin-bottom:6px;font-size:14px;font-weight:500}.workspace-select-row{display:flex;align-items:center;gap:8px}
      .workspace-select{width:100%;min-width:0;min-height:44px;padding:8px;border:1px solid var(--ui-control-border);border-radius:var(--ui-radius-control);background:var(--ui-surface);color:var(--ui-text);font:inherit;font-size:16px}
      .workspace-select-row .module-button{flex:0 0 auto;min-width:96px;justify-content:center;text-align:center}
      .workspace-summary{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:14px;border-top:1px solid var(--ui-border);font-size:13px}.workspace-summary strong{color:var(--ui-text)}.workspace-summary span{color:var(--ui-text-muted);text-align:right}
      .workspace-view-switch{display:flex;gap:8px;width:100%}.workspace-view-link{display:inline-flex;align-items:center;min-height:44px;padding:8px 14px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-control);color:var(--ui-primary);text-decoration:none}.workspace-view-link.is-active{background:var(--ui-accent-soft);border-color:var(--ui-primary)}
      .workspace-select:focus-visible,.workspace-view-link:focus-visible{outline:3px solid var(--ui-focus);outline-offset:3px}
      @media(max-width:720px){.workspace-filters{grid-template-columns:1fr}.workspace-heading,.workspace-summary{align-items:flex-start;flex-direction:column}.workspace-summary span{text-align:left}.workspace-select-row{flex-wrap:wrap}.workspace-select-row button{width:100%}}
    </style>
  @endpush
  @push('scripts')
    <script src="{{ asset('js/farmer-workspace.js') }}?v={{ @filemtime(public_path('js/farmer-workspace.js')) ?: 1 }}" defer></script>
  @endpush
@endonce

<section class="farmer-workspace-control" aria-labelledby="workspaceHeading" data-farmer-workspace-control>
  <div class="workspace-heading">
    <div>
      <h2 id="workspaceHeading">{{ $workspaceCanChoose ? 'Choose a municipality' : 'Your municipality' }}</h2>
      <p>{{ $workspaceHasFilters ? ($workspaceUser->isSystemOwner() ? 'Filter by region or municipality. Farmer records and parcels are shown below.' : ($workspaceUser->isRegionalHead() ? 'Filter by province or municipality within your region.' : 'Filter farmer records and parcels by municipality.')) : 'View farmer records and parcels in your assigned area.' }}</p>
    </div>
    @if($workspaceCanChoose && $workspaceMunicipality && !$workspaceHasFilters)
      <a class="module-button" href="{{ route('farmers.index') }}">Change municipality</a>
    @endif
  </div>
  @if($workspaceHasFilters)
  <div class="workspace-filters">
  @if($workspaceUser->isSystemOwner() && isset($workspaceRegions))
    <form method="GET" action="{{ route('farmers.index') }}" class="workspace-filter" data-workspace-scope-form>
      @foreach($scopeFilters as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
      <label for="workspaceRegion">Region</label>
      <div class="workspace-select-row">
        <select class="workspace-select" id="workspaceRegion" name="region_id" data-workspace-select>
          <option value="">All regions</option>
          @foreach($workspaceRegions as $region)<option value="{{ $region['id'] }}" @selected(($workspaceRegionId ?? '') === $region['id'])>{{ $region['name'] }}</option>@endforeach
        </select>
        <button class="module-button" type="submit">Show municipalities</button>
      </div>
    </form>
  @elseif($workspaceUser->isRegionalHead() && isset($workspaceProvinces))
    <form method="GET" action="{{ route('farmers.index') }}" class="workspace-filter" data-workspace-scope-form>
      @foreach($scopeFilters as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
      <label for="workspaceProvince">Province / City</label>
      <div class="workspace-select-row">
        <select class="workspace-select" id="workspaceProvince" name="province_id" data-workspace-select>
          <option value="">All provinces in your region</option>
          @foreach($workspaceProvinces as $province)<option value="{{ $province->id }}" @selected((int) ($workspaceProvince?->id ?? 0) === (int) $province->id)>{{ $province->name }}</option>@endforeach
        </select>
        <button class="module-button" type="submit">Show municipalities</button>
      </div>
    </form>
  @endif
  @if($workspaceCanChoose && isset($workspaceRegions))
    <form method="GET" action="{{ route('farmers.index') }}" class="workspace-filter" data-workspace-scope-form data-workspace-open>
      @foreach(array_merge($scopeFilters, array_diff_key($scopeParameters, ['municipality_id' => true])) as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
      <label for="workspaceMunicipality">Municipality / City</label>
      <div class="workspace-select-row">
        <select class="workspace-select" id="workspaceMunicipality" name="municipality_id" data-workspace-select>
          <option value="">All municipalities in this view</option>
          @foreach(($municipalities ?? collect())->groupBy('province_id') as $provinceId => $choices)
            @php($provinceLabel = $workspaceProvinceLabels->get($provinceId, $choices->first()->province))
            <optgroup label="{{ $provinceLabel }}">
              @foreach($choices as $municipality)<option value="{{ $municipality->id }}" @selected((int) $workspaceMunicipality?->id === (int) $municipality->id)>{{ $municipality->name }} · {{ $provinceLabel }}</option>@endforeach
            </optgroup>
          @endforeach
        </select>
        <button class="module-button module-button-primary" type="submit">View farmers</button>
      </div>
    </form>
  @endif
  </div>
  @endif
  <div class="workspace-summary" role="status">
    <strong>Showing {{ $workspaceMunicipality?->name ?? $workspaceLabel }}</strong>
    <span>{{ $workspaceProvinceLabel ? $workspaceProvinceLabel.' · ' : '' }}Registry, parcels and weather</span>
  </div>
  @if(isset($municipalities) && $municipalities->isEmpty())<p class="module-alert" role="status">No municipalities are available. Ask your administrator to check your assigned area.</p>@endif
  <nav class="workspace-view-switch" aria-label="Choose farmers view">
    <a class="workspace-view-link is-active" href="{{ route('farmers.index', $scopeParameters) }}#farmerDirectory" data-workspace-target="registry" aria-current="page">Farmer registry</a>
    <a class="workspace-view-link" href="{{ route('farmers.index', $scopeParameters) }}#farmersMapModule" data-workspace-target="map">Parcel map</a>
  </nav>
</section>
