<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <title>{{ $farmer->registry_id }} · Interactive land map</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    :root{--ink:#14231a;--muted:#64736a;--green:#0f6b38;--green-2:#17864a;--soft:#edf7f0;--line:#dbe6de;--gold:#efbb22;--white:#fff;--shadow:0 18px 45px rgba(11,45,25,.12)}
    *{box-sizing:border-box}
    html,body{margin:0;min-height:100%;font-family:Roboto,system-ui,sans-serif;color:var(--ink);background:#f4f8f4}
    body{background:radial-gradient(900px 420px at 8% 0,rgba(239,187,34,.17),transparent 60%),radial-gradient(800px 440px at 100% 12%,rgba(23,134,74,.13),transparent 58%),#f5f8f5}
    button{font:inherit}
    .topline{height:5px;background:linear-gradient(90deg,#16864a 0 72%,#efbb22 72%)}
    .shell{width:min(1440px,100%);margin:auto;padding:18px clamp(14px,3vw,38px) 38px}
    .masthead{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:13px 16px;border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.9);box-shadow:0 8px 24px rgba(22,71,39,.06);backdrop-filter:blur(10px)}
    .brand{display:flex;align-items:center;min-width:0;gap:12px}.brand img{width:48px;height:48px;padding:4px;object-fit:contain;border:1px solid var(--line);border-radius:14px;background:#fff}.brand strong,.brand span{display:block}.brand strong{font-size:14px}.brand span{margin-top:3px;color:var(--muted);font-size:11px}
    .verified{display:flex;align-items:center;gap:8px;padding:8px 11px;border-radius:999px;color:#0a6534;background:#e6f6eb;font-size:10px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}.verified i{display:grid;place-items:center;width:18px;height:18px;border-radius:50%;color:#fff;background:#16a05a;font-style:normal}
    .hero{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);gap:16px;margin-top:16px}
    .hero-main,.registry-card{border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:var(--shadow)}
    .hero-main{position:relative;overflow:hidden;padding:clamp(22px,4vw,44px)}.hero-main:after{content:"";position:absolute;right:-90px;bottom:-130px;width:330px;height:280px;border-radius:50%;background:linear-gradient(135deg,rgba(22,134,74,.16),rgba(239,187,34,.15));pointer-events:none}
    .eyebrow{display:flex;align-items:center;gap:8px;color:var(--green);font-size:10px;font-weight:900;letter-spacing:.11em;text-transform:uppercase}.eyebrow:before{content:"";width:22px;height:3px;border-radius:999px;background:var(--gold)}
    h1{position:relative;z-index:1;margin:12px 0 7px;font-size:clamp(27px,4vw,48px);line-height:1.03;letter-spacing:-.045em}.subtitle{position:relative;z-index:1;margin:0;max-width:720px;color:var(--muted);font-size:clamp(12px,1.5vw,15px);line-height:1.55}
    .hero-stats{position:relative;z-index:1;display:flex;flex-wrap:wrap;gap:8px;margin-top:22px}.hero-stat{min-width:126px;padding:10px 12px;border:1px solid #dce9df;border-radius:12px;background:#f8fbf8}.hero-stat span,.hero-stat strong{display:block}.hero-stat span{color:var(--muted);font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.hero-stat strong{margin-top:5px;font-size:16px}
    .registry-card{display:flex;flex-direction:column;padding:18px}.registry-card-top{display:flex;align-items:center;gap:12px}.registry-mark{display:grid;place-items:center;width:48px;height:48px;flex:0 0 auto;border-radius:15px;color:#fff;background:linear-gradient(135deg,var(--green-2),var(--green));font-size:16px;font-weight:900}.registry-card h2{margin:0;font-size:16px}.registry-id{margin-top:4px;color:var(--green);font:800 11px ui-monospace,monospace}.registry-lines{display:grid;gap:12px;margin-top:18px}.registry-line{padding-top:11px;border-top:1px solid #e5ece7}.registry-line span,.registry-line strong{display:block}.registry-line span{color:var(--muted);font-size:8px;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.registry-line strong{margin-top:4px;font-size:12px}.privacy-note{margin-top:auto;padding:11px;border-radius:11px;color:#53645a;background:var(--soft);font-size:9px;line-height:1.5}
    .workspace{display:grid;grid-template-columns:310px minmax(0,1fr);min-height:620px;margin-top:16px;overflow:hidden;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:var(--shadow)}
    .parcel-panel{display:flex;min-height:0;flex-direction:column;border-right:1px solid var(--line);background:#fbfdfb}.panel-head{padding:19px 18px 15px;border-bottom:1px solid var(--line)}.panel-head small,.panel-head strong{display:block}.panel-head small{color:var(--green);font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.panel-head strong{margin-top:5px;font-size:18px}.panel-head p{margin:5px 0 0;color:var(--muted);font-size:10px;line-height:1.45}
    .parcel-list{display:grid;align-content:start;gap:8px;min-height:0;padding:12px;overflow:auto}.parcel-button{width:100%;display:grid;grid-template-columns:36px 1fr auto;align-items:center;gap:10px;padding:10px;border:1px solid var(--line);border-radius:12px;color:var(--ink);background:#fff;text-align:left;cursor:pointer;transition:.15s ease}.parcel-button:hover,.parcel-button:focus-visible,.parcel-button.is-active{border-color:#7eb692;background:#eef8f1;outline:none;box-shadow:0 7px 18px rgba(18,92,50,.09);transform:translateY(-1px)}.parcel-swatch{display:grid;place-items:center;width:36px;height:36px;border-radius:11px;color:#fff;background:var(--plot-color);font-size:10px;font-weight:900}.parcel-copy{min-width:0}.parcel-copy strong,.parcel-copy span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.parcel-copy strong{font-size:11px}.parcel-copy span{margin-top:3px;color:var(--muted);font-size:9px}.parcel-arrow{color:#799085;font-size:20px}
    .empty-parcels{margin:10px;padding:20px 13px;border:1px dashed #c8d9ce;border-radius:13px;text-align:center;background:#fff}.empty-parcels strong,.empty-parcels span{display:block}.empty-parcels strong{font-size:12px}.empty-parcels span{margin-top:6px;color:var(--muted);font-size:10px;line-height:1.5}
    .map-card{position:relative;min-height:620px;background:#e9eee9}.map-toolbar{position:absolute;z-index:500;top:13px;left:50%;display:flex;gap:7px;transform:translateX(-50%)}.map-action{display:flex;align-items:center;gap:6px;min-height:36px;padding:0 11px;border:1px solid rgba(24,65,39,.16);border-radius:10px;color:#173e27;background:rgba(255,255,255,.96);box-shadow:0 7px 20px rgba(4,24,11,.13);font-size:9px;font-weight:900;cursor:pointer}.map-action:hover{background:#f0f8f2}.map-action svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    #landMap{width:100%;height:620px}.leaflet-container{font-family:Roboto,system-ui,sans-serif}.leaflet-control-layers,.leaflet-bar{border:0!important;box-shadow:0 8px 22px rgba(4,30,13,.16)!important}.leaflet-control-layers{border-radius:12px!important;font-size:10px}.leaflet-popup-content-wrapper{border-radius:13px;box-shadow:0 12px 32px rgba(4,31,13,.18)}.map-popup strong,.map-popup span{display:block}.map-popup strong{font-size:12px}.map-popup span{margin-top:4px;color:#66766c;font-size:10px}.map-legend{position:absolute;z-index:500;right:12px;bottom:22px;max-width:190px;padding:9px 11px;border:1px solid rgba(24,65,39,.13);border-radius:11px;background:rgba(255,255,255,.95);box-shadow:0 7px 20px rgba(4,24,11,.12);font-size:9px}.map-legend strong{display:block}.map-legend span{display:block;margin-top:3px;color:var(--muted);line-height:1.35}.map-error{position:absolute;z-index:600;inset:50% auto auto 50%;display:none;width:min(360px,calc(100% - 30px));padding:18px;transform:translate(-50%,-50%);border:1px solid #e5c8a1;border-radius:14px;background:#fffaf2;text-align:center}.map-error strong,.map-error span{display:block}.map-error span{margin-top:6px;color:#755f43;font-size:10px;line-height:1.45}
    .footer{display:flex;justify-content:space-between;gap:15px;padding:17px 3px 0;color:#718078;font-size:9px}.footer strong{color:#385443}
    @media(max-width:900px){.hero{grid-template-columns:1fr}.workspace{grid-template-columns:1fr}.parcel-panel{max-height:none;border-right:0;border-bottom:1px solid var(--line)}.parcel-list{grid-template-columns:repeat(2,minmax(0,1fr));max-height:230px}.map-card,#landMap{min-height:58vh;height:58vh}.privacy-note{margin-top:15px}}
    @media(max-width:560px){.shell{padding:10px 10px 25px}.masthead{padding:10px 11px;border-radius:14px}.brand img{width:40px;height:40px}.brand strong{font-size:11px}.brand span{font-size:9px}.verified{padding:7px;font-size:0}.verified i{font-size:10px}.hero-main,.registry-card,.workspace{border-radius:15px}.hero-main{padding:22px 18px}.hero-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.hero-stat{min-width:0}.parcel-list{grid-template-columns:1fr;max-height:210px}.map-toolbar{top:10px;left:10px;right:10px;justify-content:center;transform:none}.map-action{padding:0 9px}.map-action span{display:none}.map-legend{right:9px;bottom:18px}.footer{flex-direction:column}}
  </style>
</head>
<body>
  @php
    $fullName = trim(collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' '));
    $municipality = optional($farmer->municipality)->name ?: $farmer->farm_municipality ?: 'Municipality not recorded';
    $province = optional($farmer->municipality)->province ?: $farmer->farm_province ?: 'Tarlac';
    $initials = strtoupper(substr($farmer->first_name ?: 'F', 0, 1).substr($farmer->last_name ?: 'R', 0, 1));
    $mappedArea = $plots->sum(fn ($plot) => (float) ($plot['area_ha'] ?? 0));
  @endphp
  <div class="topline"></div>
  <main class="shell">
    <header class="masthead">
      <div class="brand">
        <img src="{{ asset('images/da.jpg') }}" alt="Department of Agriculture logo">
        <div><strong>Agriculture Information System</strong><span>Public parcel verification · {{ $province }}</span></div>
      </div>
      <div class="verified"><i>✓</i><span>Registry link verified</span></div>
    </header>

    <section class="hero">
      <article class="hero-main">
        <div class="eyebrow">Farmer land profile</div>
        <h1>{{ $fullName ?: 'Registered farmer' }}</h1>
        <p class="subtitle">This QR-linked page displays the read-only parcel boundaries currently saved in the agriculture registry. Pan, zoom, switch map layers, and select a parcel to inspect it.</p>
        <div class="hero-stats">
          <div class="hero-stat"><span>Mapped parcels</span><strong>{{ $plots->count() }}</strong></div>
          <div class="hero-stat"><span>Mapped area</span><strong>{{ number_format($mappedArea, 2) }} ha</strong></div>
          <div class="hero-stat"><span>Declared area</span><strong>{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'Not recorded' }}</strong></div>
          <div class="hero-stat"><span>Municipality</span><strong>{{ $municipality }}</strong></div>
        </div>
      </article>

      <aside class="registry-card">
        <div class="registry-card-top"><div class="registry-mark">{{ $initials }}</div><div><h2>Registry verification</h2><div class="registry-id">{{ $farmer->registry_id }}</div></div></div>
        <div class="registry-lines">
          <div class="registry-line"><span>Farm location</span><strong>{{ $farmer->farm_location ?: 'Not recorded' }}</strong></div>
          <div class="registry-line"><span>Municipality / province</span><strong>{{ $municipality }}, {{ $province }}</strong></div>
          <div class="registry-line"><span>Map status</span><strong>{{ $plots->isEmpty() ? 'No parcel boundary recorded' : $plots->count().' active parcel '.($plots->count() === 1 ? 'boundary' : 'boundaries') }}</strong></div>
        </div>
        <div class="privacy-note">For verification only. Contact details, dates of birth, account information, and internal agriculture records are not displayed.</div>
      </aside>
    </section>

    <section class="workspace" aria-label="Interactive parcel map">
      <aside class="parcel-panel">
        <div class="panel-head"><small>Saved boundaries</small><strong>Land parcels</strong><p>Select a parcel to focus its boundary on the map.</p></div>
        @if($plots->isNotEmpty())
          <div class="parcel-list" id="parcelList">
            @foreach($plots as $index => $plot)
              <button class="parcel-button" type="button" data-plot-index="{{ $index }}" style="--plot-color:{{ preg_match('/^#[0-9a-fA-F]{6}$/', $plot['color']) ? $plot['color'] : '#16834b' }}">
                <span class="parcel-swatch">P{{ $index + 1 }}</span>
                <span class="parcel-copy"><strong>{{ $plot['name'] }}</strong><span>{{ $plot['area_ha'] !== null ? number_format($plot['area_ha'], 2).' hectares' : 'Area not recorded' }}</span></span>
                <span class="parcel-arrow">›</span>
              </button>
            @endforeach
          </div>
        @else
          <div class="empty-parcels"><strong>No mapped parcel yet</strong><span>The farmer is registered, but no polygon boundary has been saved for this profile.</span></div>
        @endif
      </aside>

      <div class="map-card" id="mapCard">
        <div class="map-toolbar">
          <button class="map-action" type="button" id="resetMap"><svg viewBox="0 0 24 24"><path d="M3 11a9 9 0 1 0 3-6.7"></path><path d="M3 4v7h7"></path></svg><span>All parcels</span></button>
          <button class="map-action" type="button" id="locateMe"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><circle cx="12" cy="12" r="8"></circle><path d="M12 2v2M12 20v2M2 12h2M20 12h2"></path></svg><span>My location</span></button>
          <button class="map-action" type="button" id="fullscreenMap"><svg viewBox="0 0 24 24"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"></path></svg><span>Fullscreen</span></button>
        </div>
        <div id="landMap" role="application" aria-label="Interactive map showing the farmer's plotted land"></div>
        <div class="map-legend"><strong>Interactive parcel map</strong><span>Pinch or scroll to zoom. Drag to move. Tap a boundary for details.</span></div>
        <div class="map-error" id="mapError"><strong>Map could not load</strong><span>Check your internet connection and refresh this page. The parcel list remains available.</span></div>
      </div>
    </section>

    <footer class="footer"><span><strong>Read-only public view.</strong> Parcel changes can only be made by authorized agriculture personnel.</span><span>Generated from the live registry record.</span></footer>
  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      const plots = @json($plots);
      const fallbackCenter = [15.4755, 120.5963];
      const mapError = document.getElementById('mapError');
      if (typeof window.L === 'undefined') {
        if (mapError) mapError.style.display = 'block';
        return;
      }

      const map = L.map('landMap', { zoomControl: false, preferCanvas: true }).setView(fallbackCenter, 10);
      L.control.zoom({ position: 'bottomleft' }).addTo(map);

      const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 20,
        attribution: 'Tiles &copy; Esri'
      });
      L.control.layers({ 'Street map': street, 'Satellite': satellite }, null, { position: 'topright' }).addTo(map);
      L.control.scale({ imperial: false, position: 'bottomright' }).addTo(map);

      const group = L.featureGroup().addTo(map);
      const layers = [];
      const buttons = Array.from(document.querySelectorAll('[data-plot-index]'));

      function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        })[character]);
      }
      function normalizePoint(point) {
        if (Array.isArray(point) && point.length >= 2) return [Number(point[0]), Number(point[1])];
        if (point && typeof point === 'object') return [Number(point.lat ?? point.latitude), Number(point.lng ?? point.lon ?? point.longitude)];
        return null;
      }
      function selectPlot(index, openPopup) {
        const layer = layers[index];
        if (!layer) return;
        buttons.forEach((button, buttonIndex) => button.classList.toggle('is-active', buttonIndex === index));
        map.fitBounds(layer.getBounds(), { padding: [45, 45], maxZoom: 19 });
        if (openPopup !== false) layer.openPopup();
      }

      plots.forEach((plot, index) => {
        const ring = (Array.isArray(plot.polygon) ? plot.polygon : [])
          .map(normalizePoint)
          .filter(point => point && Number.isFinite(point[0]) && Number.isFinite(point[1]));
        if (ring.length < 3) return;
        const color = /^#[0-9a-f]{6}$/i.test(plot.color || '') ? plot.color : '#16834b';
        const polygon = L.polygon(ring, { color, fillColor: color, weight: 3, opacity: .95, fillOpacity: .24 });
        const area = plot.area_ha === null ? 'Area not recorded' : Number(plot.area_ha).toFixed(2) + ' hectares';
        polygon.bindPopup('<div class="map-popup"><strong>' + escapeHtml(plot.name) + '</strong><span>' + escapeHtml(area) + ' · ' + ring.length + ' corner points</span></div>');
        polygon.on('click', () => buttons.forEach((button, buttonIndex) => button.classList.toggle('is-active', buttonIndex === index)));
        polygon.addTo(group);
        layers[index] = polygon;
      });

      function showAllPlots() {
        buttons.forEach(button => button.classList.remove('is-active'));
        if (group.getLayers().length) map.fitBounds(group.getBounds(), { padding: [35, 35], maxZoom: 18 });
        else map.setView(fallbackCenter, 10);
      }
      buttons.forEach(button => button.addEventListener('click', () => selectPlot(Number(button.dataset.plotIndex))));
      document.getElementById('resetMap')?.addEventListener('click', showAllPlots);
      document.getElementById('locateMe')?.addEventListener('click', () => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(position => {
          const point = [position.coords.latitude, position.coords.longitude];
          L.circleMarker(point, { radius: 8, color: '#155eef', fillColor: '#fff', fillOpacity: 1, weight: 4 }).addTo(map).bindPopup('Your current location').openPopup();
          map.setView(point, Math.max(map.getZoom(), 15));
        });
      });
      document.getElementById('fullscreenMap')?.addEventListener('click', () => document.getElementById('mapCard')?.requestFullscreen?.());
      document.addEventListener('fullscreenchange', () => setTimeout(() => map.invalidateSize(), 120));
      showAllPlots();
      setTimeout(() => map.invalidateSize(), 100);
    })();
  </script>
</body>
</html>
