@php
  $workspaceUser = auth()->user();
  $workspaceMunicipality = $workspaceMunicipality ?? $selectedMunicipality ?? null;
  $workspaceCanChoose = $workspaceUser->canAccessAllMunicipalities();
  $workspaceReady = $workspaceMunicipality !== null;
  $scopeFilters = request()->only(['q', 'gender', 'mapping', 'quality', 'per_page']);
@endphp

@once
  @push('styles')
    <style>
      .farmer-workspace-control{padding:20px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface);color:var(--ui-text)}
      .workspace-heading,.workspace-current,.workspace-view-switch{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
      .workspace-heading h2{margin:0;font-size:18px;font-weight:500}.workspace-heading p{margin:6px 0 0;color:var(--ui-text-muted);font-size:14px;line-height:1.5}
      .workspace-current{margin-top:16px;padding-top:14px;border-top:1px solid var(--ui-border);font-size:14px}.workspace-current span{color:var(--ui-text-muted)}
      .workspace-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:18px}
      .workspace-step{min-width:0}.workspace-step label,.workspace-step-label{display:block;margin-bottom:8px;font-size:14px;font-weight:500}
      .workspace-step-number{display:inline-grid;place-items:center;width:24px;height:24px;margin-right:6px;border-radius:50%;background:var(--ui-accent-soft);color:var(--ui-primary)}
      .workspace-select-row{display:flex;gap:8px;align-items:center}.workspace-select{min-width:0;width:100%;min-height:44px;padding:8px;border:1px solid var(--ui-control-border);border-radius:var(--ui-radius-control);background:var(--ui-surface);color:var(--ui-text);font:inherit;font-size:16px}
      .workspace-step-locked{display:block;padding:11px 0;font-size:16px}.workspace-view-switch{justify-content:flex-start;margin-top:16px}
      .workspace-view-link{padding:10px 16px;min-height:44px;display:inline-flex;align-items:center;border:1px solid var(--ui-border);border-radius:var(--ui-radius-control);text-decoration:none;color:var(--ui-text);font-size:14px}
      .workspace-view-link.is-active{color:var(--ui-primary);background:var(--ui-accent-soft);border-color:var(--ui-primary)}
      .workspace-select:focus-visible,.workspace-view-link:focus-visible{outline:3px solid var(--ui-focus);outline-offset:3px}
      .workspace-selection-hint{margin:16px 0 0;color:var(--ui-text-muted);font-size:14px;line-height:1.5}
      @media(max-width:540px){.farmer-workspace-control{padding:16px}.workspace-steps{grid-template-columns:1fr}.workspace-select-row{flex-wrap:wrap}.workspace-select-row button{width:100%}}
    </style>
  @endpush
  @push('scripts')
    <script src="{{ asset('js/farmer-workspace.js') }}?v={{ @filemtime(public_path('js/farmer-workspace.js')) ?: 1 }}" defer></script>
  @endpush
@endonce

<section class="farmer-workspace-control" aria-labelledby="workspaceHeading" data-farmer-workspace-control>
  <div class="workspace-heading">
    <div><h2 id="workspaceHeading">Municipality workspace</h2><p>{{ $workspaceCanChoose ? 'Choose a region, then a province and municipality.' : 'Your registry and parcel map use your assigned municipality.' }}</p></div>
    @if($workspaceCanChoose && $workspaceReady && !isset($workspaceRegions))
      <a class="module-button" href="{{ route('farmers.index') }}">Change workspace</a>
    @endif
  </div>

  @if($workspaceCanChoose && isset($workspaceRegions))
    <div class="workspace-steps">
      @if($workspaceUser->isSystemOwner())
        <form method="GET" action="{{ route('farmers.index') }}" class="workspace-step" data-workspace-scope-form>
          @foreach($scopeFilters as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
          <label for="workspaceRegion"><span class="workspace-step-number">1</span>Region</label>
          <div class="workspace-select-row">
            <select class="workspace-select" id="workspaceRegion" name="region_id" required data-workspace-select>
              <option value="">Select region</option>
              @foreach($workspaceRegions as $region)<option value="{{ $region['id'] }}" @selected(($workspaceRegionId ?? '') === $region['id'])>{{ $region['name'] }}</option>@endforeach
            </select>
            <button class="module-button" type="submit">Next</button>
          </div>
        </form>
      @else
        <div class="workspace-step"><span class="workspace-step-label"><span class="workspace-step-number">1</span>Region</span><strong class="workspace-step-locked">{{ $workspaceRegionName ?? 'Region not assigned' }}</strong></div>
      @endif
      @if(filled($workspaceRegionId ?? null))
        @if($workspaceUser->canChooseProvince())
          <form method="GET" action="{{ route('farmers.index') }}" class="workspace-step" data-workspace-scope-form>
            @foreach($scopeFilters as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
            <input type="hidden" name="region_id" value="{{ $workspaceRegionId }}">
            <label for="workspaceProvince"><span class="workspace-step-number">2</span>Province or independent city</label>
            <div class="workspace-select-row">
              <select class="workspace-select" id="workspaceProvince" name="province_id" required data-workspace-select>
                <option value="">Select province or city</option>
                @foreach($workspaceProvinces as $province)<option value="{{ $province->id }}" @selected((int) ($workspaceProvince?->id ?? 0) === (int) $province->id)>{{ $province->name }}</option>@endforeach
              </select>
              <button class="module-button" type="submit">Next</button>
            </div>
          </form>
        @else
          <div class="workspace-step"><span class="workspace-step-label"><span class="workspace-step-number">2</span>Province</span><strong class="workspace-step-locked">{{ $workspaceProvince?->name }}</strong></div>
        @endif
      @endif
      @if($workspaceProvince ?? null)
        <form method="GET" action="{{ route('farmers.index') }}#farmerDirectory" class="workspace-step" data-workspace-scope-form data-workspace-open>
          @foreach($scopeFilters as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
          <input type="hidden" name="region_id" value="{{ $workspaceRegionId }}">
          <input type="hidden" name="province_id" value="{{ $workspaceProvince->id }}">
          <label for="workspaceMunicipality"><span class="workspace-step-number">3</span>Municipality</label>
          <div class="workspace-select-row">
            <select class="workspace-select" id="workspaceMunicipality" name="municipality_id" required data-workspace-select>
              <option value="">Select municipality</option>
              @foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected((int) $workspaceMunicipality?->id === (int) $municipality->id)>{{ $municipality->name }}</option>@endforeach
            </select>
            <button class="module-button module-button-primary" type="submit">Open</button>
          </div>
        </form>
      @endif
    </div>
  @endif

  @if($workspaceReady)
    <div class="workspace-current"><strong>Showing {{ $workspaceMunicipality->name }}</strong><span>{{ $workspaceMunicipality->province }} · Registry, parcels and weather</span></div>
    @unless($workspaceCanChoose)<p class="workspace-selection-hint">Locked to your assigned municipality</p>@endunless
    <nav class="workspace-view-switch" aria-label="Choose farmers view">
      <a class="workspace-view-link is-active" href="{{ route('farmers.index', ['municipality_id' => $workspaceMunicipality->id]) }}#farmerDirectory" data-workspace-target="registry" aria-current="page">Farmer registry</a>
      <a class="workspace-view-link" href="{{ route('farmers.index', ['municipality_id' => $workspaceMunicipality->id]) }}#farmersMapModule" data-workspace-target="map">Parcel map</a>
    </nav>
  @else
    <p class="workspace-selection-hint" role="status">{{ empty($workspaceRegionId) ? 'Select a region to see its provinces.' : (empty($workspaceProvince) ? 'Select a province or independent city to see its municipalities.' : 'Select a municipality to open its farmer registry and parcel map.') }} Records and map boundaries load after you open a municipality.</p>
    @if(isset($workspaceRegions) && $workspaceRegions->isEmpty())<p class="module-alert">No active municipalities are available in your account scope. Ask your administrator to review the office assignments.</p>@endif
  @endif
</section>
