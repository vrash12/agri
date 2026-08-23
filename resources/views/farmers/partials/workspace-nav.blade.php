@php
  $workspaceUser = auth()->user();
  $workspaceHasProvinceScope = $workspaceUser->canAccessAllMunicipalities();
  $workspaceMunicipality = $workspaceMunicipality ?? ($selectedMunicipality ?? null);
  $workspaceMunicipalityId = $workspaceMunicipality?->id
      ?? (filter_var(request('municipality_id'), FILTER_VALIDATE_INT) ?: null)
      ?? $workspaceUser->municipality_id;
  $workspaceMunicipalityName = $workspaceMunicipality?->name
      ?? optional($workspaceUser->municipality)->name;

  $workspaceWeatherParameters = $workspaceHasProvinceScope && $workspaceMunicipalityId
      ? ['municipality_id' => $workspaceMunicipalityId]
      : [];
  $workspaceFarmerParameters = $workspaceHasProvinceScope && $workspaceMunicipalityId
      ? ['municipality_id' => $workspaceMunicipalityId]
      : [];
  $workspaceOnFarmerIndex = request()->routeIs('farmers.index');
  $workspaceRegistryUrl = $workspaceOnFarmerIndex
      ? '#farmerDirectory'
      : route('farmers.index', $workspaceFarmerParameters).'#farmerDirectory';
  $workspaceMapUrl = $workspaceOnFarmerIndex
      ? '#farmersMapModule'
      : route('farmers.index', $workspaceFarmerParameters).'#farmersMapModule';

  if ($workspaceHasProvinceScope) {
      $workspaceScopeTitle = $workspaceUser->isSuperAdmin()
          ? 'Province-wide oversight'
          : 'Province-wide operations';
      $workspaceScopeDetail = $workspaceMunicipalityName
          ? $workspaceMunicipalityName.' selected'
          : 'All municipalities available';
  } else {
      $workspaceScopeTitle = 'Municipal workspace';
      $workspaceScopeDetail = ($workspaceMunicipalityName ?: 'Assigned municipality').' only';
  }
@endphp

@once
  @push('styles')
    <style>
      .farmer-workspace-nav{position:sticky;top:8px;z-index:45;display:grid;grid-template-columns:minmax(175px,.72fr) minmax(430px,1.8fr) minmax(185px,.7fr);align-items:center;gap:10px;min-width:0;padding:9px;border:1px solid #d6e3da;border-radius:14px;background:rgba(255,255,255,.94);box-shadow:0 7px 24px rgba(20,52,31,.07);backdrop-filter:blur(14px)}
      .farmer-workspace-nav:before{content:"";position:absolute;left:15px;right:15px;top:-1px;height:2px;border-radius:0 0 999px 999px;background:linear-gradient(90deg,#1e8b4d 0%,#75bd6c 52%,#d9af39 100%)}
      .farmer-workspace-brand{display:flex;align-items:center;gap:9px;min-width:0;padding:4px 8px}.farmer-workspace-brand-icon{position:relative;width:36px;height:36px;display:grid;place-items:center;flex:0 0 auto;border:1px solid #cce4d4;border-radius:11px;color:#116a37;background:linear-gradient(145deg,#f1fbf4,#def3e5)}.farmer-workspace-brand-icon:after{content:"";position:absolute;right:-2px;bottom:-2px;width:9px;height:9px;border:2px solid #fff;border-radius:50%;background:#45aa64}.farmer-workspace-brand-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.farmer-workspace-brand strong,.farmer-workspace-brand small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.farmer-workspace-brand strong{color:#17251d;font-size:11px;font-weight:900;letter-spacing:-.01em}.farmer-workspace-brand small{margin-top:2px;color:#708077;font-size:8px}
      .farmer-workspace-links{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;min-width:0;padding:4px;border:1px solid #e2e9e4;border-radius:11px;background:#f5f8f6}.farmer-workspace-link{position:relative;display:flex;align-items:center;gap:8px;min-width:0;min-height:47px;padding:6px 9px;border:1px solid transparent;border-radius:8px;color:#536259;text-decoration:none}.farmer-workspace-link-icon{width:29px;height:29px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px;color:#65756b;background:#fff;box-shadow:0 1px 3px rgba(30,54,37,.06)}.farmer-workspace-link svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.farmer-workspace-link-copy{display:block;min-width:0}.farmer-workspace-link strong,.farmer-workspace-link small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.farmer-workspace-link strong{font-size:9px;font-weight:900}.farmer-workspace-link small{margin-top:2px;color:#849088;font-size:7px;font-weight:650}.farmer-workspace-link:hover{border-color:#d3dfd6;color:#17643a;background:#fff}.farmer-workspace-link:hover .farmer-workspace-link-icon{color:#17643a;background:#eaf6ed}.farmer-workspace-link.is-active{border-color:#b9d9c3;color:#0d6634;background:#fff;box-shadow:0 2px 7px rgba(28,83,45,.08)}.farmer-workspace-link.is-active:after{content:"";position:absolute;left:12px;right:12px;bottom:-5px;height:3px;border-radius:999px;background:#35a35d}.farmer-workspace-link.is-active .farmer-workspace-link-icon{color:#fff;background:linear-gradient(145deg,#168044,#36a45e);box-shadow:0 3px 8px rgba(24,128,68,.2)}.farmer-workspace-link[data-workspace-target="weather"].is-active{color:#225f85;border-color:#bdd6e5}.farmer-workspace-link[data-workspace-target="weather"].is-active:after{background:#3b86b4}.farmer-workspace-link[data-workspace-target="weather"].is-active .farmer-workspace-link-icon{background:linear-gradient(145deg,#2f739e,#55a2d1);box-shadow:0 3px 8px rgba(47,115,158,.2)}
      .farmer-workspace-scope{appearance:none;width:100%;display:flex;align-items:center;gap:8px;min-width:0;padding:8px 9px;border:1px solid #e0e7e2;border-radius:10px;color:inherit;background:#fbfcfb;text-align:left;font:inherit}.farmer-workspace-scope[data-workspace-scope-button]{cursor:pointer}.farmer-workspace-scope[data-workspace-scope-button]:hover{border-color:#b9d5c2;background:#f5faf6}.farmer-workspace-scope-icon{width:29px;height:29px;display:grid;place-items:center;flex:0 0 auto;border-radius:8px;color:#17643a;background:#e7f4eb}.farmer-workspace-scope-icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.farmer-workspace-scope-copy{display:block;min-width:0;flex:1}.farmer-workspace-scope strong,.farmer-workspace-scope small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.farmer-workspace-scope strong{color:#2b4434;font-size:7px;font-weight:900;text-transform:uppercase;letter-spacing:.045em}.farmer-workspace-scope small{max-width:170px;margin-top:2px;color:#718078;font-size:8px}.farmer-workspace-scope-chevron{width:7px;height:7px;flex:0 0 auto;margin-right:3px;border-right:1.5px solid #75827a;border-bottom:1.5px solid #75827a;transform:rotate(45deg)}
      @media(max-width:1050px){.farmer-workspace-nav{grid-template-columns:minmax(170px,.65fr) minmax(390px,1.7fr)}.farmer-workspace-scope{grid-column:1/-1}.farmer-workspace-scope small{max-width:none}}
      @media(max-width:760px){.farmer-workspace-nav{position:relative;top:auto;grid-template-columns:1fr;padding:9px}.farmer-workspace-brand{padding-inline:5px}.farmer-workspace-scope{grid-column:auto;grid-row:2}.farmer-workspace-links{grid-row:3;overflow-x:auto;scroll-snap-type:x proximity}.farmer-workspace-link{min-width:145px;scroll-snap-align:start}.farmer-workspace-link.is-active:after{bottom:-4px}}
      @media(max-width:480px){.farmer-workspace-links{display:flex}.farmer-workspace-link{flex:0 0 145px}.farmer-workspace-brand small{display:none}.farmer-workspace-scope small{max-width:none}}
    </style>
  @endpush

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-farmer-workspace-nav]').forEach(function (nav) {
          const links = Array.from(nav.querySelectorAll('[data-workspace-target]'));
          const registryLink = nav.querySelector('[data-workspace-target="registry"]');
          const mapLink = nav.querySelector('[data-workspace-target="map"]');
          const mapTarget = document.getElementById('farmersMapModule');

          function activate(target) {
            links.forEach(function (link) {
              const active = link.dataset.workspaceTarget === target;
              link.classList.toggle('is-active', active);
              if (active) link.setAttribute('aria-current', 'page');
              else link.removeAttribute('aria-current');
            });
          }

          if (mapTarget && registryLink && mapLink) {
            const syncHash = function () {
              activate(window.location.hash === '#farmersMapModule' ? 'map' : 'registry');
            };
            syncHash();
            window.addEventListener('hashchange', syncHash);

            if ('IntersectionObserver' in window) {
              const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                  if (entry.isIntersecting) activate('map');
                  else if (window.scrollY < mapTarget.offsetTop) activate('registry');
                });
              }, {rootMargin: '-18% 0px -57% 0px', threshold: 0});
              observer.observe(mapTarget);
            }
          }

          nav.querySelector('[data-workspace-scope-button]')?.addEventListener('click', function () {
            const selector = document.querySelector('#weatherMunicipality, #farmer_municipality, #municipality_id, #import_municipality_id');
            if (!selector) {
              window.location.href = @json(route('farmers.index', $workspaceFarmerParameters).'#farmerDirectory');
              return;
            }
            const details = selector.closest('details');
            if (details) details.open = true;
            selector.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.setTimeout(function () { selector.focus({preventScroll: true}); }, 350);
          });
        });
      });
    </script>
  @endpush
@endonce

<nav class="farmer-workspace-nav" aria-label="Farmers workspace navigation" data-farmer-workspace-nav>
  <div class="farmer-workspace-brand">
    <span class="farmer-workspace-brand-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"></path><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
    </span>
    <span><strong>Farmers workspace</strong><small>Registry, parcels, and field advisories</small></span>
  </div>

  <div class="farmer-workspace-links" aria-label="Workspace sections">
    <a class="farmer-workspace-link {{ request()->routeIs('farmers.*', 'farm-plots.*') ? 'is-active' : '' }}"
       href="{{ $workspaceRegistryUrl }}"
       data-workspace-target="registry"
       @if(request()->routeIs('farmers.*', 'farm-plots.*')) aria-current="page" @endif>
      <span class="farmer-workspace-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"></path><path d="M8 9h8M8 13h5"></path></svg></span>
      <span class="farmer-workspace-link-copy"><strong>Farmer registry</strong><small>Profiles and records</small></span>
    </a>
    <a class="farmer-workspace-link"
       href="{{ $workspaceMapUrl }}"
       data-workspace-target="map">
      <span class="farmer-workspace-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path><path d="M9 3v15M15 6v15"></path></svg></span>
      <span class="farmer-workspace-link-copy"><strong>Parcel map</strong><small>Boundaries and exports</small></span>
    </a>
    <a class="farmer-workspace-link {{ request()->routeIs('weather.*') ? 'is-active' : '' }}"
       href="{{ route('weather.index', $workspaceWeatherParameters) }}"
       data-workspace-target="weather"
       @if(request()->routeIs('weather.*')) aria-current="page" @endif>
      <span class="farmer-workspace-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 18h10a5 5 0 0 0 0-10 7 7 0 0 0-13 3 4 4 0 0 0 3 7Z"></path><path d="M8 21h8"></path></svg></span>
      <span class="farmer-workspace-link-copy"><strong>Weather & advisories</strong><small>Forecast and alerts</small></span>
    </a>
  </div>

  @if($workspaceHasProvinceScope)
    <button class="farmer-workspace-scope" type="button" title="Change municipality scope" data-workspace-scope-button>
      <span class="farmer-workspace-scope-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h2m2 0h2m-6 4h2m2 0h2"></path></svg></span>
      <span class="farmer-workspace-scope-copy"><strong>{{ $workspaceScopeTitle }}</strong><small>{{ $workspaceScopeDetail }} · Change scope</small></span>
      <span class="farmer-workspace-scope-chevron" aria-hidden="true"></span>
    </button>
  @else
    <div class="farmer-workspace-scope" title="{{ $workspaceScopeTitle }} — {{ $workspaceScopeDetail }}">
      <span class="farmer-workspace-scope-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 21V5h10v16M14 9h6v12M8 9h2m-2 4h2m-2 4h2"></path></svg></span>
      <span class="farmer-workspace-scope-copy"><strong>{{ $workspaceScopeTitle }}</strong><small>{{ $workspaceScopeDetail }}</small></span>
    </div>
  @endif
</nav>
