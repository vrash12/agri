@php
  $workspaceUser = auth()->user();
  $workspaceCanChoose = $workspaceUser->canAccessAllMunicipalities();
  $workspaceMunicipality = $workspaceMunicipality ?? null;
  $workspaceFarmerCount = (int) ($workspaceFarmerCount ?? 0);
  $workspaceMappedFarmerCount = (int) ($workspaceMappedFarmerCount ?? 0);
  $workspacePlotCount = (int) ($workspacePlotCount ?? 0);
  $workspaceName = $workspaceMunicipality?->name ?? 'All Tarlac municipalities';
  $workspaceProvinceView = $workspaceCanChoose && !$workspaceMunicipality;
@endphp

@once
  @push('styles')
    <style>
      .farmer-workspace-control{position:sticky;top:8px;z-index:45;display:grid;grid-template-columns:minmax(180px,.72fr) minmax(310px,1.2fr) minmax(310px,1fr);align-items:stretch;gap:10px;min-width:0;padding:10px;border:1px solid #d3e1d7;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 8px 26px rgba(20,52,31,.09);backdrop-filter:blur(14px)}
      .farmer-workspace-control:before{content:"";position:absolute;left:18px;right:18px;top:-1px;height:3px;border-radius:0 0 999px 999px;background:linear-gradient(90deg,#1d874a,#70bd69 55%,#d6ad3b)}
      .workspace-identity{display:flex;align-items:center;gap:10px;min-width:0;padding:7px 8px}.workspace-identity-icon{position:relative;width:38px;height:38px;display:grid;place-items:center;flex:0 0 auto;border:1px solid #cce4d4;border-radius:11px;color:#116a37;background:linear-gradient(145deg,#f1fbf4,#def3e5)}.workspace-identity-icon:after{content:"";position:absolute;right:-2px;bottom:-2px;width:9px;height:9px;border:2px solid #fff;border-radius:50%;background:#45aa64}.workspace-identity-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.workspace-identity strong,.workspace-identity small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.workspace-identity strong{color:#17251d;font-size:11px;font-weight:900}.workspace-identity small{margin-top:2px;color:#708077;font-size:8px}
      .workspace-scope-form,.workspace-locked-scope{min-width:0;padding:8px 10px;border:1px solid #dce6df;border-radius:11px;background:#f8fbf9}.workspace-scope-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px}.workspace-scope-head label,.workspace-scope-head span{color:#31513c;font-size:8px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.workspace-scope-head small{color:#78857d;font-size:7px}.workspace-select-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px}.workspace-select{width:100%;height:36px;padding:0 30px 0 10px;border:1px solid #c5d7ca;border-radius:8px;color:#173b25;background:#fff;font:inherit;font-size:10px;font-weight:850;cursor:pointer}.workspace-apply{height:36px;padding:0 11px;border:0;border-radius:8px;color:#fff;background:#16743e;font:inherit;font-size:9px;font-weight:900;cursor:pointer}.workspace-scope-help{display:block;margin-top:5px;color:#718078;font-size:7px;line-height:1.35}.workspace-locked-scope{display:flex;align-items:center;gap:9px}.workspace-lock-icon{width:30px;height:30px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px;color:#17643a;background:#e6f4ea}.workspace-lock-icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.workspace-locked-copy{min-width:0}.workspace-locked-copy span,.workspace-locked-copy strong,.workspace-locked-copy small{display:block}.workspace-locked-copy span{color:#6d7b72;font-size:7px;font-weight:900;letter-spacing:.045em;text-transform:uppercase}.workspace-locked-copy strong{margin-top:2px;color:#173b25;font-size:11px;font-weight:900}.workspace-locked-copy small{margin-top:2px;color:#718078;font-size:7px}
      .workspace-view-switch{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5px;min-width:0;padding:4px;border:1px solid #e1e8e3;border-radius:11px;background:#f3f7f4}.workspace-view-link{position:relative;display:flex;align-items:center;gap:8px;min-width:0;padding:7px 9px;border:1px solid transparent;border-radius:8px;color:#536259;text-decoration:none}.workspace-view-icon{width:30px;height:30px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px;color:#65756b;background:#fff;box-shadow:0 1px 3px rgba(30,54,37,.06)}.workspace-view-icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.workspace-view-copy{display:block;min-width:0}.workspace-view-link strong,.workspace-view-link small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.workspace-view-link strong{font-size:9px;font-weight:900}.workspace-view-link small{margin-top:2px;color:#7d8a82;font-size:7px}.workspace-view-link:hover{border-color:#d3dfd6;color:#17643a;background:#fff}.workspace-view-link.is-active{border-color:#b8d8c2;color:#0d6634;background:#fff;box-shadow:0 2px 7px rgba(28,83,45,.08)}.workspace-view-link.is-active:after{content:"";position:absolute;left:12px;right:12px;bottom:-5px;height:3px;border-radius:999px;background:#35a35d}.workspace-view-link.is-active .workspace-view-icon{color:#fff;background:linear-gradient(145deg,#168044,#36a45e)}
      .workspace-scope-summary{grid-column:1/-1;display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:9px;color:#496056;background:#eef6f0;font-size:8px}.workspace-scope-summary b{color:#17643a}.workspace-scope-summary i{width:7px;height:7px;flex:0 0 auto;border-radius:50%;background:#36a45e}.workspace-scope-summary.is-province{color:#65572e;background:#fff8df}.workspace-scope-summary.is-province i{background:#d5a728}.workspace-scope-summary.is-province b{color:#765e12}
      @media(max-width:1120px){.farmer-workspace-control{grid-template-columns:minmax(170px,.65fr) minmax(300px,1.2fr)}.workspace-view-switch{grid-column:1/-1}.workspace-scope-summary{grid-column:1/-1}}
      @media(max-width:720px){.farmer-workspace-control{position:relative;top:auto;grid-template-columns:1fr}.workspace-view-switch{grid-column:auto}.workspace-scope-summary{grid-column:auto;align-items:flex-start}.workspace-identity{padding-inline:4px}}
      @media(max-width:430px){.workspace-select-row{grid-template-columns:1fr}.workspace-apply{width:100%}.workspace-view-link{padding-inline:7px}.workspace-view-link small{display:none}}
    </style>
  @endpush

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const control = document.querySelector('[data-farmer-workspace-control]');
        if (!control) return;

        const links = Array.from(control.querySelectorAll('[data-workspace-target]'));
        const mapTarget = document.getElementById('farmersMapModule');
        const scopeForm = control.querySelector('[data-workspace-scope-form]');
        const scopeSelect = control.querySelector('[data-workspace-municipality]');

        function activate(target) {
          links.forEach(function (link) {
            const active = link.dataset.workspaceTarget === target;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
          });
        }

        function syncHash() {
          activate(window.location.hash === '#farmersMapModule' ? 'map' : 'registry');
        }

        syncHash();
        window.addEventListener('hashchange', syncHash);

        if (mapTarget && 'IntersectionObserver' in window) {
          new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) activate('map');
              else if (window.scrollY < mapTarget.offsetTop) activate('registry');
            });
          }, {rootMargin: '-20% 0px -56% 0px', threshold: 0}).observe(mapTarget);
        }

        scopeForm?.addEventListener('submit', function () {
          const activeMap = control.querySelector('[data-workspace-target="map"]')
            ?.classList.contains('is-active');
          const action = new URL(scopeForm.action, window.location.href);
          action.hash = activeMap ? 'farmersMapModule' : 'farmerDirectory';
          scopeForm.action = action.toString();
        });

        scopeSelect?.addEventListener('change', function () {
          scopeForm?.requestSubmit();
        });
      });
    </script>
  @endpush
@endonce

<nav class="farmer-workspace-control" aria-label="Farmers workspace controls" data-farmer-workspace-control>
  <div class="workspace-identity">
    <span class="workspace-identity-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"></path><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
    </span>
    <span><strong>Farmers workspace</strong><small>One municipality · one registry · one parcel map</small></span>
  </div>

  @if($workspaceCanChoose)
    <form class="workspace-scope-form" method="GET" action="{{ route('farmers.index') }}#farmerDirectory" data-workspace-scope-form>
      <div class="workspace-scope-head"><label for="workspaceMunicipality">Municipality workspace</label><small>Controls registry + map</small></div>
      <div class="workspace-select-row">
        <select class="workspace-select" id="workspaceMunicipality" name="municipality_id" data-workspace-municipality>
          <option value="">All municipalities — province overview</option>
          @foreach($municipalities as $municipality)
            <option value="{{ $municipality->id }}" @selected((int) $workspaceMunicipality?->id === (int) $municipality->id)>{{ $municipality->name }}</option>
          @endforeach
        </select>
        <button class="workspace-apply" type="submit">Load</button>
      </div>
      <small class="workspace-scope-help">Changing this reloads all farmers, counts, map markers, parcel boundaries, and weather for that municipality.</small>
    </form>
  @else
    <div class="workspace-locked-scope" title="Your account is limited to its assigned municipality">
      <span class="workspace-lock-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 10V8a6 6 0 0 1 12 0v2M5 10h14v11H5z"></path></svg></span>
      <span class="workspace-locked-copy"><span>Municipality workspace</span><strong>{{ $workspaceName }}</strong><small>Locked to your assigned municipality</small></span>
    </div>
  @endif

  <div class="workspace-view-switch" aria-label="Choose farmers view">
    <a class="workspace-view-link is-active" href="#farmerDirectory" data-workspace-target="registry" aria-current="page">
      <span class="workspace-view-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"></path><path d="M8 9h8M8 13h5"></path></svg></span>
      <span class="workspace-view-copy"><strong>Farmer registry</strong><small>{{ number_format($workspaceFarmerCount) }} farmers in scope</small></span>
    </a>
    <a class="workspace-view-link" href="#farmersMapModule" data-workspace-target="map">
      <span class="workspace-view-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path><path d="M9 3v15M15 6v15"></path></svg></span>
      <span class="workspace-view-copy"><strong>Parcel map</strong><small>{{ number_format($workspacePlotCount) }} boundaries · {{ number_format($workspaceMappedFarmerCount) }} mapped</small></span>
    </a>
  </div>

  <div class="workspace-scope-summary {{ $workspaceProvinceView ? 'is-province' : '' }}" role="status">
    <i aria-hidden="true"></i>
    @if($workspaceProvinceView)
      <span><b>Province overview:</b> the registry and map currently include all accessible municipalities. Choose a municipality above for a focused local workspace.</span>
    @else
      <span><b>Showing {{ $workspaceName }}:</b> {{ number_format($workspaceFarmerCount) }} farmers, {{ number_format($workspaceMappedFarmerCount) }} with mapped land, and {{ number_format($workspacePlotCount) }} saved parcel {{ Str::plural('boundary', $workspacePlotCount) }}.</span>
    @endif
  </div>
</nav>
