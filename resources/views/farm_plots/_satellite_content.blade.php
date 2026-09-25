@php($modal = $modal ?? false)
<div class="module-page satellite-page" id="satellitePage">
  <header class="module-header">
    <div><div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · Parcel monitoring</div>
      <h1>Parcel health view</h1>
      <p>{{ $plot->name ?: 'Parcel #'.$plot->id }} · {{ number_format($parcel['area_ha'] ?? (float) $plot->area_ha, 2) }} ha approximate mapped area</p>
    </div>
    @if(!$modal)
      <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id]) }}#farmersMapModule">Back to parcel map</a>
    @endif
  </header>
  @if(!$configured)
    <div class="module-alert" role="status"><strong>Satellite access needs setup.</strong> Your administrator must configure Copernicus access before observations can load. Parcel boundaries remain available.</div>
  @endif
  @if($geometryError)<div class="module-alert module-alert-error" role="alert">{{ $geometryError }}</div>@endif
  <aside class="satellite-explainer" aria-label="About this parcel health view">
    <strong>What this shows</strong>
    <p>Satellite images are summarized into a vegetation greenness score called NDVI. Higher values often mean more green vegetation. Clouds, shadows, water, bare soil, harvest and planting stage can lower the score.</p>
    <p>As a rough guide, values closer to −1 often occur over water or bare surfaces, while values closer to +1 often occur over denser green vegetation. Compare dates with the crop stage and a field observation.</p>
    <p>Use the result to decide where a field check may help. It is a monitoring signal, not a crop diagnosis, yield estimate or eligibility decision.</p>
  </aside>
  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Choose a date range</h2><p>We will look for clear Sentinel-2 images in this period. You can search up to 31 days at a time.</p></div></div>
    <form id="satelliteForm" class="module-form-body satellite-filters" action="{{ route('farm-plots.satellite.analyse', $plot) }}" method="POST">
      @csrf
      <div class="module-form-field"><label for="satelliteFrom">From (UTC)</label><input class="module-input" id="satelliteFrom" name="from" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $from }}" required></div>
      <div class="module-form-field"><label for="satelliteTo">To (UTC)</label><input class="module-input" id="satelliteTo" name="to" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $to }}" required></div>
      <div class="module-form-field"><label for="satelliteCloud">Maximum scene cloud cover</label><select class="module-input" id="satelliteCloud" name="max_cloud"><option value="20">20%</option><option value="60" selected>60%</option><option value="100">100% · Include all scenes</option></select></div>
      <button class="module-button module-button-primary" id="satelliteLoad" @disabled(!$configured || !$parcel)>Load observations</button>
    </form>
    <p class="satellite-note">The cloud limit filters whole scenes first. Clouds, shadows and missing pixels are filtered again inside this parcel, so some days may have no clear result.</p>
    <noscript><p class="module-alert">Enable JavaScript to request observations and view the map.</p></noscript>
  </section>
  <p id="satelliteStatus" class="satellite-status" role="status" aria-live="polite">Choose a period, then load observations.</p>
  <div class="satellite-workspace">
    <section class="module-panel satellite-map-panel" aria-label="Parcel and satellite layers">
      <div class="module-panel-head"><div><h2>Parcel overlay</h2><p id="satelliteImageCaption">Saved parcel boundary; no satellite observation loaded.</p></div></div>
      <div class="satellite-map-controls">
        <div><label for="satelliteDate">Observation day (UTC)</label><select class="module-input" id="satelliteDate" disabled><option>No observation loaded</option></select></div>
        <div><label for="satelliteLayer">Layer</label><select class="module-input" id="satelliteLayer" disabled><option value="ndvi">NDVI</option><option value="true-color">Sentinel-2 true color</option></select></div>
        <div><label for="satelliteOpacity">Layer opacity</label><input id="satelliteOpacity" type="range" min="0" max="100" value="85" disabled></div>
        <button class="module-button" type="button" id="satelliteImageRetry" hidden>Retry image</button>
      </div>
      <div class="satellite-map" id="satelliteMap" aria-label="Map of the selected parcel"><p id="satelliteMapStatus">Preparing parcel map…</p></div>
      <div class="satellite-preview" id="satellitePreview" hidden><img id="satellitePreviewImage" alt="Cloud-masked Sentinel-2 image clipped to the selected parcel"></div>
      <div class="satellite-legend" id="satelliteLegend"><span>NDVI −1</span><span class="satellite-ramp" aria-hidden="true"></span><span>+1</span></div>
      <p class="satellite-note">The color ramp shows NDVI from −1 to +1. Transparent areas have no clear measurement. Imagery: Copernicus Sentinel-2, processed through CDSE; the road map is a separate reference layer.</p>
    </section>
    <section class="module-panel satellite-summary" aria-labelledby="satelliteSummaryHeading">
      <div class="module-panel-head"><div><h2 id="satelliteSummaryHeading">What the score means here</h2><p>These numbers use only clear pixels inside this saved parcel.</p></div></div>
      <div class="module-form-body">
        <dl class="satellite-metrics"><div><dt>Average greenness score</dt><dd id="satelliteMean">—</dd></div><div><dt>Lowest / highest score</dt><dd id="satelliteRange">—</dd></div><div><dt>Clear pixels in parcel</dt><dd id="satellitePixels">—</dd></div><div><dt>Observation day</dt><dd id="satelliteDay">—</dd></div></dl>
        <p id="satelliteQuality" class="satellite-note">No greenness measurement has been loaded yet.</p>
        <p class="satellite-note">Each image pixel represents about 10 metres on the ground. Small or narrow parcels may have only a few clear pixels, so use their scores carefully.</p>
        <p class="satellite-note">Several images from one UTC day may be combined. Water, harvest, planting stage and soil affect the score; compare it with crop records and a field observation.</p>
      </div>
    </section>
  </div>
  <section class="module-panel" aria-labelledby="satelliteHistoryHeading">
    <div class="module-panel-head"><div><h2 id="satelliteHistoryHeading">How the score changed</h2><p>Daily clear observations in the selected period. A missing day means no clear image was available; it is not a zero.</p></div></div>
    <div class="satellite-chart" id="satelliteChart" aria-label="Average greenness observations; complete values in the table below"></div>
    <div class="module-table-scroll"><table class="module-table"><thead><tr><th scope="col">Day (UTC)</th><th scope="col">Average greenness</th><th scope="col">Lowest score</th><th scope="col">Highest score</th><th scope="col">Clear pixels</th><th scope="col">Result</th></tr></thead><tbody id="satelliteHistory"><tr><td colspan="6">No observations loaded.</td></tr></tbody></table></div>
  </section>
  <details class="module-panel satellite-method"><summary>How to interpret this analysis</summary>
    <p>This view places a satellite image over the saved parcel boundary and summarizes the NDVI values inside it. It can guide a field visit, but does not determine crop type, disease, yield or assistance eligibility.</p>
    <p>This screen does not calculate buffers, hot spots, interpolation or travel networks. Those analyses need additional locations, measurements or road data and should be designed separately.</p>
  </details>
</div>
<script type="application/json" id="satelliteConfig">{!! json_encode([
  'geometry' => $parcel['geometry'] ?? null, 'bounds' => $parcel['map_bounds'] ?? null,
  'googleKey' => (string) config('services.google_maps.key', ''),
  'imageUrl' => route('farm-plots.satellite.image', $plot),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/parcel-satellite.js') }}?v={{ @filemtime(public_path('js/parcel-satellite.js')) ?: 1 }}" defer></script>
