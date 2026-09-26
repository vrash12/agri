@php($modal = $modal ?? false)
<div class="module-page satellite-page" id="satellitePage">
  <header class="satellite-heading">
    <div><div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · Satellite field check</div>
      <h1>{{ $plot->name ?: 'Parcel #'.$plot->id }}</h1>
      <p>See where vegetation looks greener, then compare it with conditions on the farm.</p>
    </div>
    <span class="satellite-area">{{ number_format($parcel['area_ha'] ?? (float) $plot->area_ha, 2) }} ha <small>Approximate mapped area</small></span>
    @if(!$modal)
      <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id]) }}#farmersMapModule">Back to parcel map</a>
    @endif
  </header>
  @if(!$configured)
    <div class="module-alert" role="status"><strong>Satellite access needs setup.</strong> Ask your administrator to enable satellite pictures. You can still view the saved parcel boundary.</div>
  @endif
  @if($geometryError)<div class="module-alert module-alert-error" role="alert">{{ $geometryError }}</div>@endif
  <section class="module-panel satellite-search" aria-labelledby="satelliteSearchHeading">
    <div class="module-panel-head"><div><h2 id="satelliteSearchHeading"><span class="satellite-step" aria-hidden="true">1</span> Find a satellite picture</h2><p>Choose up to 31 days. We will open the latest usable picture in that period.</p></div></div>
    <form id="satelliteForm" class="module-form-body" action="{{ route('farm-plots.satellite.analyse', $plot) }}" method="POST">
      @csrf
      <div class="satellite-filters">
        <div class="module-form-field"><label for="satelliteFrom">Start date</label><input class="module-input" id="satelliteFrom" name="from" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $from }}" required aria-describedby="satelliteDateHelp"></div>
        <div class="module-form-field"><label for="satelliteTo">End date</label><input class="module-input" id="satelliteTo" name="to" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $to }}" required aria-describedby="satelliteDateHelp"></div>
        <button class="module-button module-button-primary" id="satelliteLoad" @disabled(!$configured || !$parcel)>Find pictures <span aria-hidden="true">→</span></button>
      </div>
      <p class="satellite-note" id="satelliteDateHelp">Satellite dates use UTC. Pictures may be unavailable on cloudy days.</p>
      <details class="satellite-options" id="satelliteSearchOptions">
        <summary>More search options</summary>
        <div class="satellite-option-content"><label for="satelliteCloud">Which satellite scenes should we search?</label><select class="module-input" id="satelliteCloud" name="max_cloud" aria-describedby="satelliteCloudHelp"><option value="20">Mostly clear scenes</option><option value="60" selected>Balanced search (recommended)</option><option value="100">All scenes — try if no pictures are found</option></select>
          <p class="satellite-note" id="satelliteCloudHelp">This filters cloud cover across the wider satellite scene (20%, 60% or 100%). Clouds and shadows inside your parcel are still excluded. It is not the percentage of your farm that is clear.</p>
        </div>
      </details>
    </form>
    <noscript><p class="module-alert">Enable JavaScript to request observations and view the map.</p></noscript>
  </section>
  <p id="satelliteStatus" class="satellite-status" role="status" aria-live="polite">Ready when you are. Choose dates above, then select Find pictures.</p>
  <div class="satellite-workspace">
    <section class="module-panel satellite-map-panel" aria-label="Parcel and satellite layers">
      <div class="module-panel-head"><div><h2><span class="satellite-step" aria-hidden="true">2</span> Look at your parcel</h2><p id="satelliteImageCaption" role="status" aria-live="polite">The outline shows your saved parcel. Find pictures to see its vegetation.</p></div></div>
      <div class="satellite-map-controls">
        <div><label for="satelliteDate">Picture date</label><select class="module-input" id="satelliteDate" disabled><option>Find pictures first</option></select></div>
        <div><label for="satelliteLayer">Show me</label><select class="module-input" id="satelliteLayer" disabled><option value="ndvi">Vegetation colours</option><option value="true-color">Natural-colour picture</option></select></div>
        <button class="module-button" type="button" id="satelliteImageRetry" hidden>Retry image</button>
      </div>
      <div class="satellite-map" id="satelliteMap" aria-label="Map of the selected parcel"><p id="satelliteMapStatus">Preparing parcel map…</p></div>
      <div class="satellite-preview" id="satellitePreview" hidden><img id="satellitePreviewImage" alt="Cloud-masked Sentinel-2 image clipped to the selected parcel"></div>
      <div class="satellite-legend" id="satelliteLegend" hidden>
        <strong>How to read the colours</strong>
        <span class="satellite-ramp" aria-hidden="true"></span>
        <div class="satellite-legend-labels"><span>Water / little vegetation</span><span>More green vegetation</span></div>
        <p class="satellite-note">Brown or yellow can be soil, new planting or harvested land. Darker green usually means more green vegetation. Blank areas have no clear reading.</p>
      </div>
      <details class="satellite-options satellite-display-options"><summary>Adjust picture visibility</summary><div class="satellite-option-content"><label for="satelliteOpacity">Picture strength over the map</label><input id="satelliteOpacity" type="range" min="0" max="100" value="85" disabled><p class="satellite-note">Lower the strength to see more of the reference map underneath.</p></div></details>
      <p class="satellite-source">Imagery: Copernicus Sentinel-2 via CDSE. The road map is a separate reference.</p>
    </section>
    <section class="module-panel satellite-summary" aria-labelledby="satelliteSummaryHeading">
      <div class="module-panel-head"><div><h2 id="satelliteSummaryHeading"><span class="satellite-step" aria-hidden="true">3</span> Understand the result</h2></div></div>
      <div class="module-form-body">
        <div class="satellite-result" id="satelliteResult" data-state="empty" role="status" aria-live="polite" aria-atomic="true">
          <p class="satellite-result-label" id="satelliteResultTitle">Your result will appear here</p>
          <p id="satelliteQuality">Find a picture to see the vegetation reading for this parcel.</p>
        </div>
        <div id="satelliteScore" hidden>
          <dl class="satellite-score"><div><dt>Average vegetation greenness</dt><dd id="satelliteMean">—</dd></div></dl>
          <meter id="satelliteScoreMeter" min="-1" max="1" value="0" aria-label="Average vegetation greenness, from minus one to plus one"></meter>
          <div class="satellite-scale-labels"><span>−1 · Less green</span><span>+1 · More green</span></div>
          <p class="satellite-note">This score is called NDVI. It is not a crop-health grade. Water and bare soil can give low readings.</p>
        </div>
        <div class="satellite-next-step"><h3>What to do next</h3><p id="satelliteNextStep">Start with the suggested dates. If clouds hide your parcel, try another period.</p></div>
        <details class="satellite-options"><summary>See the measurements</summary><dl class="satellite-metrics"><div><dt>Picture date (UTC)</dt><dd id="satelliteDay">—</dd></div><div><dt>Lowest / highest score</dt><dd id="satelliteRange">—</dd></div><div><dt>Clear image cells (pixels)</dt><dd id="satellitePixels">—</dd></div></dl><p class="satellite-note">Each cell is about 10 metres across. These counts do not tell us what percentage of the parcel is clear.</p></details>
        <p class="satellite-caution">Use this to plan a field check. It cannot diagnose disease, predict harvest, prove ownership or decide assistance eligibility.</p>
      </div>
    </section>
  </div>
  <details class="module-panel satellite-history" id="satelliteHistoryPanel">
    <summary><span>Compare dates</span><span class="satellite-note" id="satelliteHistorySummary">Find pictures to see past readings</span></summary>
    <p class="satellite-note">Each dot is a day with a usable reading. Missing days are not zero readings. Compare dates at a similar crop stage.</p>
    <div class="satellite-chart" id="satelliteChart" aria-label="Average greenness observations; complete values in the table below"></div>
    <div class="module-table-scroll"><table class="module-table"><thead><tr><th scope="col">Date (UTC)</th><th scope="col">Average greenness</th><th scope="col">Lowest score</th><th scope="col">Highest score</th><th scope="col">Clear image cells</th><th scope="col">Result</th></tr></thead><tbody id="satelliteHistory"><tr><td colspan="6">Find pictures to see the readings.</td></tr></tbody></table></div>
  </details>
  <details class="module-panel satellite-method"><summary>About these satellite pictures</summary>
    <p>NDVI is a vegetation greenness score from −1 to +1. Higher values usually mean more green vegetation. Crop stage, water, soil and recent harvest affect the reading. A low value alone does not mean the crop is damaged.</p>
    <p>We use Sentinel-2 images with clouds, shadows and missing data filtered out. Clear areas may cover only part of your parcel. Several images from one UTC day may be combined. Small or narrow parcels may contain only a few usable image cells.</p>
    <p>This is a satellite reading, not a live view or a replacement for a field visit. Check crop records and conditions on the ground before acting.</p>
  </details>
</div>
<script type="application/json" id="satelliteConfig">{!! json_encode([
  'geometry' => $parcel['geometry'] ?? null, 'bounds' => $parcel['map_bounds'] ?? null,
  'googleKey' => (string) config('services.google_maps.key', ''),
  'imageUrl' => route('farm-plots.satellite.image', $plot),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/parcel-satellite.js') }}?v={{ @filemtime(public_path('js/parcel-satellite.js')) ?: 1 }}" defer></script>
