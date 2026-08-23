@php
  $mapWeatherMunicipalities = collect($municipalities ?? []);
  $mapWeatherDefaultMunicipalityId = (int) request('municipality_id')
      ?: (int) auth()->user()->municipality_id
      ?: (int) optional($mapWeatherMunicipalities->first())->id;
  $mapWeatherCanChooseMunicipality = (bool) ($canChooseMunicipality ?? false);
@endphp

<div class="map-weather-scrim" id="mapWeatherScrim" aria-hidden="true"></div>
<aside class="map-weather-drawer" id="mapWeatherDrawer" aria-labelledby="mapWeatherTitle" aria-hidden="true">
  <header class="map-weather-header">
    <div class="map-weather-heading">
      <span class="map-weather-heading-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M7 18h10a5 5 0 0 0 0-10 7 7 0 0 0-13 3 4 4 0 0 0 3 7Z"></path><path d="m8 21-1 2m5-2-1 2m5-2-1 2"></path></svg>
      </span>
      <span><small>Field decision support</small><strong id="mapWeatherTitle">Weather & advisories</strong></span>
    </div>
    <button class="map-weather-close" id="mapWeatherCloseBtn" type="button" aria-label="Close weather panel">
      <svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"></path></svg>
    </button>
  </header>

  <div class="map-weather-scope">
    <label for="mapWeatherMunicipality">Forecast municipality</label>
    @if($mapWeatherCanChooseMunicipality)
      <select id="mapWeatherMunicipality">
        @foreach($mapWeatherMunicipalities as $municipality)
          <option value="{{ $municipality->id }}" @selected((int) $municipality->id === $mapWeatherDefaultMunicipalityId)>{{ $municipality->name }}</option>
        @endforeach
      </select>
      <small id="mapWeatherScopeNote">Choose a municipality or select a farmer on the map.</small>
    @else
      <div class="map-weather-locked-scope">
        <span>{{ optional(auth()->user()->municipality)->name ?: 'Assigned municipality' }}</span>
        <small>Municipal forecast scope</small>
      </div>
      <input id="mapWeatherMunicipality" type="hidden" value="{{ $mapWeatherDefaultMunicipalityId }}">
    @endif
  </div>

  <div class="map-weather-body" id="mapWeatherBody" aria-live="polite">
    <div class="map-weather-empty" id="mapWeatherInitial">
      <span class="map-weather-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M7 17h10a5 5 0 0 0 0-10 7 7 0 0 0-13 3 4 4 0 0 0 3 7Z"></path><path d="M12 20v2M8 20l-1 2m9-2 1 2"></path></svg>
      </span>
      <strong>Weather loads when you open this panel</strong>
      <p>Use the forecast before plotting visits, fertilizer application, spraying, harvesting, or machinery dispatch.</p>
    </div>
  </div>

  <footer class="map-weather-footer">
    <span id="mapWeatherUpdated">Forecast not loaded</span>
    <button type="button" id="mapWeatherRefreshBtn">
      <svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.34-5.66L20 9M20 4v5h-5"></path></svg>
      Refresh
    </button>
  </footer>
</aside>

@once
  @push('styles')
    <style>
      #farmersMapModule .map-weather-scrim{position:absolute;inset:0;z-index:34;pointer-events:none;background:rgba(14,30,20,0);transition:background .2s ease}#farmersMapModule .map-weather-scrim.is-open{pointer-events:auto;background:rgba(14,30,20,.2)}
      #farmersMapModule .map-weather-drawer{position:absolute;top:10px;right:10px;bottom:10px;z-index:36;width:min(390px,calc(100% - 20px));display:flex;overflow:hidden;visibility:hidden;flex-direction:column;border:1px solid rgba(201,216,206,.95);border-radius:13px;background:#f7faf8;box-shadow:0 16px 38px rgba(13,37,22,.24);opacity:0;transform:translateX(calc(100% + 22px));transition:transform .22s ease,opacity .18s ease,visibility .22s}#farmersMapModule .map-weather-drawer.is-open{visibility:visible;opacity:1;transform:translateX(0)}
      #farmersMapModule .map-weather-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 14px;color:#fff;background:linear-gradient(125deg,#17643a 0%,#267b52 50%,#317da6 100%)}#farmersMapModule .map-weather-heading{display:flex;align-items:center;gap:9px;min-width:0}#farmersMapModule .map-weather-heading-icon{width:34px;height:34px;display:grid;place-items:center;flex:0 0 auto;border-radius:10px;background:rgba(255,255,255,.14)}#farmersMapModule .map-weather-heading-icon svg,#farmersMapModule .map-weather-close svg,#farmersMapModule .map-weather-footer svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}#farmersMapModule .map-weather-heading small,#farmersMapModule .map-weather-heading strong{display:block}#farmersMapModule .map-weather-heading small{color:rgba(255,255,255,.72);font-size:7px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}#farmersMapModule .map-weather-heading strong{margin-top:2px;font-size:13px;font-weight:900}#farmersMapModule .map-weather-close{width:31px;height:31px;display:grid;place-items:center;flex:0 0 auto;border:1px solid rgba(255,255,255,.2);border-radius:8px;color:#fff;background:rgba(255,255,255,.08);cursor:pointer}#farmersMapModule .map-weather-close:hover{background:rgba(255,255,255,.18)}
      #farmersMapModule .map-weather-scope{padding:10px 12px;border-bottom:1px solid #dfe7e1;background:#fff}#farmersMapModule .map-weather-scope label{display:block;margin-bottom:5px;color:#56665c;font-size:8px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}#farmersMapModule .map-weather-scope select{width:100%;height:36px;padding:0 9px;border:1px solid #cedbd2;border-radius:8px;color:#1f3025;background:#fff;font:inherit;font-size:10px;font-weight:750}#farmersMapModule .map-weather-scope>small{display:block;margin-top:5px;color:#77847c;font-size:8px}#farmersMapModule .map-weather-locked-scope{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 9px;border:1px solid #d9e5dc;border-radius:8px;background:#f5faf6}#farmersMapModule .map-weather-locked-scope span{color:#1d4d2e;font-size:10px;font-weight:850}#farmersMapModule .map-weather-locked-scope small{color:#77847c;font-size:8px}
      #farmersMapModule .map-weather-body{min-height:0;flex:1;overflow-y:auto;padding:11px}#farmersMapModule .map-weather-empty{min-height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:24px;text-align:center}#farmersMapModule .map-weather-empty-icon{width:52px;height:52px;display:grid;place-items:center;margin-bottom:11px;border-radius:15px;color:#317da6;background:#e6f2f9}#farmersMapModule .map-weather-empty-icon svg{width:27px;height:27px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}#farmersMapModule .map-weather-empty strong{color:#1b2920;font-size:12px}#farmersMapModule .map-weather-empty p{max-width:285px;margin:6px 0 0;color:#6c7971;font-size:9px;line-height:1.55}
      #farmersMapModule .map-weather-loading{display:grid;gap:8px}#farmersMapModule .map-weather-skeleton{height:66px;border-radius:9px;background:linear-gradient(90deg,#edf2ef 25%,#f7faf8 40%,#edf2ef 65%);background-size:240% 100%;animation:map-weather-pulse 1.25s infinite}@keyframes map-weather-pulse{to{background-position:-240% 0}}
      #farmersMapModule .map-weather-now{display:grid;grid-template-columns:1.1fr .9fr;gap:9px;padding:12px;border-radius:11px;color:#fff;background:linear-gradient(135deg,#17643a,#2b7d55 55%,#347fa8)}#farmersMapModule .map-weather-now-location{display:block;color:rgba(255,255,255,.72);font-size:8px;font-weight:850}#farmersMapModule .map-weather-now-temp{display:block;margin:5px 0 2px;font-size:32px;line-height:1;font-weight:900;letter-spacing:-.05em}#farmersMapModule .map-weather-now-condition{display:block;font-size:10px;font-weight:800}#farmersMapModule .map-weather-now-meta{display:grid;align-content:center;gap:6px}#farmersMapModule .map-weather-now-meta span{display:flex;justify-content:space-between;gap:8px;padding-bottom:5px;border-bottom:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.76);font-size:8px}#farmersMapModule .map-weather-now-meta b{color:#fff;font-weight:900}
      #farmersMapModule .map-weather-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-top:8px}#farmersMapModule .map-weather-metric{padding:9px;border:1px solid #dce6df;border-radius:9px;background:#fff}#farmersMapModule .map-weather-metric span{display:block;color:#738077;font-size:7px;font-weight:900;text-transform:uppercase}#farmersMapModule .map-weather-metric strong{display:block;margin-top:5px;color:#213128;font-size:13px;font-weight:900}
      #farmersMapModule .map-weather-section{margin-top:8px;overflow:hidden;border:1px solid #dce6df;border-radius:10px;background:#fff}#farmersMapModule .map-weather-section-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 10px;border-bottom:1px solid #e5ebe7}#farmersMapModule .map-weather-section-head strong{color:#24342a;font-size:9px;font-weight:900}#farmersMapModule .map-weather-section-head span{color:#7a877f;font-size:7px}#farmersMapModule .map-weather-advisories{display:grid;gap:6px;padding:8px}#farmersMapModule .map-weather-advisory{padding:8px 9px;border-left:3px solid #4e91ba;border-radius:6px;background:#f5f9fc}#farmersMapModule .map-weather-advisory[data-severity="high"]{border-left-color:#c94a4a;background:#fff4f4}#farmersMapModule .map-weather-advisory[data-severity="moderate"]{border-left-color:#d69825;background:#fff9ed}#farmersMapModule .map-weather-advisory strong{display:block;color:#26372d;font-size:9px}#farmersMapModule .map-weather-advisory p{margin:3px 0 0;color:#69766e;font-size:8px;line-height:1.45}
      #farmersMapModule .map-weather-days{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:8px}#farmersMapModule .map-weather-day{padding:8px;border:1px solid #e1e8e3;border-radius:8px;background:#fafcfb;text-align:center}#farmersMapModule .map-weather-day span{display:block;color:#76837b;font-size:7px;font-weight:850}#farmersMapModule .map-weather-day b{display:block;margin:5px 0 3px;color:#24342a;font-size:10px}#farmersMapModule .map-weather-day small{color:#617168;font-size:8px}
      #farmersMapModule .map-weather-official{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:8px;padding:9px 10px;border:1px solid #cfe0eb;border-radius:9px;color:#245b7c;background:#eef7fc;text-decoration:none}#farmersMapModule .map-weather-official strong{display:block;font-size:9px}#farmersMapModule .map-weather-official small{display:block;margin-top:2px;color:#638096;font-size:7px}
      #farmersMapModule .map-weather-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 11px;border-top:1px solid #dfe7e1;background:#fff}#farmersMapModule .map-weather-footer>span{overflow:hidden;color:#76837b;font-size:7px;text-overflow:ellipsis;white-space:nowrap}#farmersMapModule .map-weather-footer button{display:flex;align-items:center;gap:5px;padding:6px 8px;border:1px solid #cfdcd3;border-radius:7px;color:#17643a;background:#f6faf7;font:inherit;font-size:8px;font-weight:850;cursor:pointer}#farmersMapModule .map-weather-footer button:disabled{cursor:wait;opacity:.55}
      #farmersMapModule .map-weather-trigger{display:inline-flex;align-items:center;gap:6px;border-color:#9fc6da!important;color:#205f84!important;background:#edf7fc!important}#farmersMapModule .map-weather-trigger:hover{border-color:#5798bc!important;background:#e0f1fa!important}#farmersMapModule .map-weather-trigger svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}#farmersMapModule .map-weather-trigger.is-active{color:#fff!important;border-color:#2f789f!important;background:#2f789f!important}
      @media(max-width:720px){#farmersMapModule .map-weather-drawer{top:6px;right:6px;bottom:6px;width:calc(100% - 12px)}#farmersMapModule .map-weather-now{grid-template-columns:1fr}#farmersMapModule .map-weather-metrics{grid-template-columns:repeat(3,minmax(85px,1fr));overflow-x:auto}}
    </style>
  @endpush

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const trigger = document.getElementById('mapWeatherBtn');
        const drawer = document.getElementById('mapWeatherDrawer');
        const scrim = document.getElementById('mapWeatherScrim');
        const closeButton = document.getElementById('mapWeatherCloseBtn');
        const refreshButton = document.getElementById('mapWeatherRefreshBtn');
        const municipalityInput = document.getElementById('mapWeatherMunicipality');
        const scopeNote = document.getElementById('mapWeatherScopeNote');
        const body = document.getElementById('mapWeatherBody');
        const updated = document.getElementById('mapWeatherUpdated');
        const triggerLabel = document.getElementById('mapWeatherButtonLabel');
        const config = {
          summaryUrl: @json(route('farmers.weather-summary')),
          refreshUrl: @json(route('farmers.weather-refresh')),
          csrfToken: @json(csrf_token()),
          defaultMunicipalityId: @json($mapWeatherDefaultMunicipalityId),
          autoOpen: @json(request()->boolean('show_weather'))
        };
        let loadedMunicipalityId = null;
        let loading = false;

        if (!trigger || !drawer || !body || !municipalityInput) return;

        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
        const number = (value, digits = 0) => value === null || value === undefined || value === '' || Number.isNaN(Number(value)) ? '—' : Number(value).toFixed(digits);
        const weatherEmoji = icon => icon === 'sun' ? '☀️' : (icon === 'storm' ? '⛈️' : (icon === 'rain' ? '🌧️' : '☁️'));

        function setOpen(open) {
          drawer.classList.toggle('is-open', open);
          scrim?.classList.toggle('is-open', open);
          trigger.classList.toggle('is-active', open);
          drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
          trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) loadWeather(false);
        }

        function showLoading() {
          body.innerHTML = '<div class="map-weather-loading"><div class="map-weather-skeleton"></div><div class="map-weather-skeleton"></div><div class="map-weather-skeleton"></div><div class="map-weather-skeleton"></div></div>';
        }

        function showError(message) {
          body.innerHTML = '<div class="map-weather-empty"><span class="map-weather-empty-icon">!</span><strong>Forecast unavailable</strong><p>' + escapeHtml(message || 'Please try again and check PAGASA for official warnings.') + '</p></div>';
          updated.textContent = 'Could not update forecast';
        }

        function renderWeather(payload) {
          const forecast = payload.forecast || {};
          const municipality = payload.selected_municipality || {};
          if (!forecast.available) {
            showError(forecast.status_message);
            return;
          }
          const current = forecast.current || {};
          const summary = forecast.summary || {};
          const advisories = Array.isArray(forecast.advisories) ? forecast.advisories : [];
          const days = Array.isArray(forecast.daily) ? forecast.daily.slice(0, 3) : [];
          const official = payload.official_links || {};
          const advisoryHtml = advisories.length
            ? advisories.slice(0, 4).map(item => '<article class="map-weather-advisory" data-severity="' + escapeHtml(item.severity || 'normal') + '"><strong>' + escapeHtml(item.title || item.category || 'Field advisory') + '</strong><p>' + escapeHtml(item.message || item.description || '') + '</p></article>').join('')
            : '<article class="map-weather-advisory"><strong>No elevated forecast signal</strong><p>Continue monitoring field conditions and official PAGASA bulletins.</p></article>';
          const dayHtml = days.map(day => '<article class="map-weather-day"><span>' + escapeHtml(day.day_label || day.date || '') + '</span><b>' + weatherEmoji(day.condition_icon) + ' ' + escapeHtml(day.condition || '') + '</b><small>' + number(day.temperature_max) + '° / ' + number(day.temperature_min) + '° · ' + number(day.precipitation_probability) + '% rain</small></article>').join('');

          body.innerHTML = '<section class="map-weather-now"><div><span class="map-weather-now-location">' + escapeHtml(municipality.name || 'Municipality') + ', ' + escapeHtml(municipality.province || 'Tarlac') + '</span><strong class="map-weather-now-temp">' + number(current.temperature, 1) + '°C</strong><span class="map-weather-now-condition">' + weatherEmoji(current.condition_icon) + ' ' + escapeHtml(current.condition || 'Current conditions') + '</span></div><div class="map-weather-now-meta"><span>Feels like <b>' + number(current.apparent_temperature, 1) + '°</b></span><span>Humidity <b>' + number(current.humidity) + '%</b></span><span>Wind <b>' + number(current.wind_speed, 1) + ' km/h</b></span></div></section>'
            + '<div class="map-weather-metrics"><article class="map-weather-metric"><span>Rain chance</span><strong>' + number(summary.maximum_rain_probability) + '%</strong></article><article class="map-weather-metric"><span>7-day rain</span><strong>' + number(summary.seven_day_rain, 1) + ' mm</strong></article><article class="map-weather-metric"><span>Peak gust</span><strong>' + number(summary.maximum_wind_gust, 1) + ' km/h</strong></article></div>'
            + '<section class="map-weather-section"><div class="map-weather-section-head"><strong>Agricultural advisories</strong><span>Forecast-based guidance</span></div><div class="map-weather-advisories">' + advisoryHtml + '</div></section>'
            + '<section class="map-weather-section"><div class="map-weather-section-head"><strong>Three-day outlook</strong><span>Planning window</span></div><div class="map-weather-days">' + dayHtml + '</div></section>'
            + '<a class="map-weather-official" href="' + escapeHtml(official.weather || 'https://www.pagasa.dost.gov.ph/weather') + '" target="_blank" rel="noopener"><span><strong>Verify with PAGASA</strong><small>Use official bulletins for safety-critical decisions</small></span><span>↗</span></a>';
          const fetchedAt = forecast.fetched_at ? new Date(forecast.fetched_at) : null;
          updated.textContent = fetchedAt && !Number.isNaN(fetchedAt.getTime()) ? 'Updated ' + fetchedAt.toLocaleString() : 'Forecast loaded';
          triggerLabel.textContent = number(current.temperature) + '° · ' + (current.condition || 'Weather');
          loadedMunicipalityId = String(municipality.id || municipalityInput.value || '');
        }

        async function loadWeather(force) {
          const municipalityId = String(municipalityInput.value || config.defaultMunicipalityId || '');
          if (!municipalityId || loading || (!force && loadedMunicipalityId === municipalityId)) return;
          loading = true;
          refreshButton.disabled = true;
          showLoading();
          try {
            const options = {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}};
            let url = config.summaryUrl + '?municipality_id=' + encodeURIComponent(municipalityId);
            if (force) {
              url = config.refreshUrl;
              options.method = 'POST';
              options.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
              options.headers['X-CSRF-TOKEN'] = config.csrfToken;
              options.body = new URLSearchParams({municipality_id: municipalityId}).toString();
            }
            const response = await fetch(url, options);
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Forecast request failed.');
            renderWeather(payload);
          } catch (error) {
            showError(error?.message);
          } finally {
            loading = false;
            refreshButton.disabled = false;
          }
        }

        trigger.addEventListener('click', () => setOpen(!drawer.classList.contains('is-open')));
        closeButton?.addEventListener('click', () => setOpen(false));
        scrim?.addEventListener('click', () => setOpen(false));
        refreshButton?.addEventListener('click', () => loadWeather(true));
        municipalityInput.addEventListener('change', () => {
          loadedMunicipalityId = null;
          if (scopeNote) scopeNote.textContent = 'Forecast scope changed manually.';
          loadWeather(false);
        });
        document.addEventListener('keydown', event => {
          if (event.key === 'Escape' && drawer.classList.contains('is-open')) setOpen(false);
        });
        window.addEventListener('farmers:selection-changed', event => {
          const farmer = event.detail?.farmer;
          const municipalityId = farmer?.municipality_id;
          if (!municipalityId) return;
          const matchingOption = Array.from(municipalityInput.options || []).find(option => String(option.value) === String(municipalityId));
          if (matchingOption) municipalityInput.value = String(municipalityId);
          else if (municipalityInput.type === 'hidden') municipalityInput.value = String(municipalityId);
          if (scopeNote) scopeNote.textContent = 'Following the selected farmer’s municipality.';
          if (String(loadedMunicipalityId) !== String(municipalityId)) {
            loadedMunicipalityId = null;
            if (drawer.classList.contains('is-open')) loadWeather(false);
          }
        });

        if (config.autoOpen) setOpen(true);
      });
    </script>
  @endpush
@endonce
