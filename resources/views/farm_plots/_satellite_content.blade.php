@php($modal = $modal ?? false)
@php($indexes = $indexes ?? \App\Modules\Climate\Application\SatelliteIndex::definitions())
<div class="module-page satellite-page" id="satellitePage">
  <header class="satellite-heading">
    <div><div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · {{ $modal ? 'Selected parcel' : 'Satellite field check' }}</div>
      <h1>{{ $plot->name ?: 'Parcel #'.$plot->id }}</h1>
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
    <div class="module-panel-head"><div><h2 id="satelliteSearchHeading"><span class="satellite-step" aria-hidden="true">1</span> Choose a check and dates</h2><p>Search up to 31 days. The latest usable picture will open.</p></div></div>
    <form id="satelliteForm" class="module-form-body" action="{{ route('farm-plots.satellite.analyse', $plot) }}" method="POST">
      @csrf
      <div class="satellite-filters">
        <div class="module-form-field satellite-index-field"><label for="satelliteIndex">What would you like to check?</label><select class="module-input" id="satelliteIndex" name="index" aria-describedby="satelliteIndexHelp">@foreach($indexes as $id => $definition)<option value="{{ $id }}">{{ $definition['choice'] }}</option>@endforeach</select></div>
        <div class="module-form-field"><label for="satelliteFrom">Start date</label><input class="module-input" id="satelliteFrom" name="from" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $from }}" required aria-describedby="satelliteDateHelp"></div>
        <div class="module-form-field"><label for="satelliteTo">End date</label><input class="module-input" id="satelliteTo" name="to" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $to }}" required aria-describedby="satelliteDateHelp"></div>
        <button class="module-button module-button-primary" id="satelliteLoad" @disabled(!$configured || !$parcel)>Find pictures <span aria-hidden="true">→</span></button>
      </div>
      <p class="satellite-note satellite-check-help" id="satelliteIndexHelp" aria-live="polite" aria-atomic="true">{{ $indexes['ndvi']['help'] }}</p>
      <p class="satellite-note satellite-date-help" id="satelliteDateHelp">Dates use UTC. Clouds may hide the field.</p>
      <div class="satellite-search-help">
      <details class="satellite-options">
        <summary>Help me choose</summary>
        <ul class="satellite-note satellite-option-content">@foreach($indexes as $id => $definition)<li><strong>{{ $definition['choice'] }}</strong> — {{ $definition['help'] }}</li>@endforeach</ul>
      </details>
      <details class="satellite-options" id="satelliteSearchOptions">
        <summary>Search options</summary>
        <div class="satellite-option-content"><label for="satelliteCloud">Which satellite scenes should we search?</label><select class="module-input" id="satelliteCloud" name="max_cloud" aria-describedby="satelliteCloudHelp"><option value="20">Mostly clear scenes</option><option value="60" selected>Balanced search (recommended)</option><option value="100">All scenes — try if no pictures are found</option></select>
          <p class="satellite-note" id="satelliteCloudHelp">Try All scenes if no pictures are found. This searches a wider set of images; cloudy parts of your parcel are still left out.</p>
        </div>
      </details>
      </div>
    </form>
    <noscript><p class="module-alert">Enable JavaScript to request observations and view the map.</p></noscript>
  </section>
  <p id="satelliteStatus" class="satellite-status" role="status" aria-live="polite" aria-atomic="true"></p>
  <div class="satellite-workspace" id="satelliteWorkspace" data-has-result="false">
    <section class="module-panel satellite-map-panel" aria-label="Parcel and satellite layers">
      <div class="module-panel-head"><div><h2><span class="satellite-step" aria-hidden="true">2</span> View the picture and result</h2><p id="satelliteImageCaption" role="status" aria-live="polite">Your saved parcel boundary. No satellite picture loaded yet.</p></div></div>
      <div class="satellite-map-controls" id="satelliteMapControls" hidden>
        <div><label for="satelliteDate">Picture date</label><select class="module-input" id="satelliteDate" disabled><option>Find pictures first</option></select></div>
        <div><label for="satelliteLayer">Show me</label><select class="module-input" id="satelliteLayer" disabled><option value="ndvi" id="satelliteIndexLayer">NDVI colours</option><option value="true-color">Natural-colour picture</option></select></div>
        <button class="module-button" type="button" id="satelliteImageRetry" hidden>Retry image</button>
      </div>
      <div class="satellite-map" id="satelliteMap" aria-label="Map of the selected parcel"><p id="satelliteMapStatus">Preparing parcel map…</p></div>
      <div class="satellite-preview" id="satellitePreview" hidden><img id="satellitePreviewImage" alt="Cloud-masked Sentinel-2 image clipped to the selected parcel"></div>
      <div class="satellite-legend" id="satelliteLegend" hidden>
        <strong>Colour guide</strong>
        <span class="satellite-ramp" id="satelliteRamp" aria-hidden="true"></span>
        <div class="satellite-legend-labels"><span id="satelliteLegendLow">Less green</span><span id="satelliteLegendHigh">More green</span></div>
        <p class="satellite-note" id="satelliteLegendNote">{{ $indexes['ndvi']['legend_note'] }} Blank parts have no reading.</p>
      </div>
      <details class="satellite-options satellite-display-options" id="satelliteDisplayOptions" hidden><summary>Picture visibility</summary><div class="satellite-option-content"><label for="satelliteOpacity">Picture strength over the map</label><input id="satelliteOpacity" type="range" min="0" max="100" value="85" disabled><p class="satellite-note">Lower this to see more of the map underneath.</p></div></details>
      <p class="satellite-source">Satellite images: Copernicus Sentinel-2. Base map: Google Maps.</p>
    </section>
    <section class="module-panel satellite-summary" id="satelliteSummary" aria-labelledby="satelliteSummaryHeading" hidden>
      <div class="module-panel-head"><div><h2 id="satelliteSummaryHeading">What this means</h2></div></div>
      <div class="module-form-body">
        <div class="satellite-result" id="satelliteResult" data-state="empty">
          <p class="satellite-result-label" id="satelliteResultTitle">Your result will appear here</p>
          <p id="satelliteQuality">Select Find pictures to see the result.</p>
        </div>
        <div id="satelliteScore" hidden>
          <dl class="satellite-score"><div><dt id="satelliteMeanLabel">Average NDVI · Plant greenness</dt><dd id="satelliteMean">—</dd></div></dl>
          <meter id="satelliteScoreMeter" min="-1" max="1" value="0" aria-label="Average plant greenness, from minus one to plus one"></meter>
          <div class="satellite-scale-labels"><span id="satelliteScaleLow">−1 · Lower number</span><span id="satelliteScaleHigh">+1 · Higher number</span></div>
          <p class="satellite-note" id="satelliteScoreHelp">{{ $indexes['ndvi']['reading_help'] }}</p>
        </div>
        <div class="satellite-next-step"><h3>What to do next</h3><p id="satelliteNextStep">Choose dates and select Find pictures.</p></div>
        <details class="satellite-options satellite-result-details"><summary>Measurement details</summary><dl class="satellite-metrics"><div><dt>Picture date (UTC)</dt><dd id="satelliteDay">—</dd></div><div><dt>Lowest / highest number</dt><dd id="satelliteRange">—</dd></div><div><dt>Picture squares used (pixels)</dt><dd id="satellitePixels">—</dd></div></dl><p class="satellite-note" id="satelliteResolution">Each picture square is about 10 metres across. This count is not the percentage of your field that is clear.</p></details>
        <p class="satellite-caution">Use this to plan a field check. The number is not a health grade or proof of drought.</p>
      </div>
    </section>
  </div>
  <details class="module-panel satellite-history" id="satelliteHistoryPanel" hidden>
    <summary><span>Compare dates</span><span class="satellite-note" id="satelliteHistorySummary">Find pictures to see past readings</span></summary>
    <p class="satellite-note">Each dot is a day with a usable reading. Missing days are not zero readings. Compare dates at a similar crop stage.</p>
    <div class="satellite-chart" id="satelliteChart" aria-label="Index observations; complete values in the table below"></div>
    <div class="module-table-scroll"><table class="module-table"><thead><tr><th scope="col">Date (UTC)</th><th scope="col" id="satelliteHistoryMean">Average NDVI</th><th scope="col">Lowest score</th><th scope="col">Highest score</th><th scope="col">Usable image cells</th><th scope="col">Result</th></tr></thead><tbody id="satelliteHistory"><tr><td colspan="6">Find pictures to see the readings.</td></tr></tbody></table></div>
  </details>
  <details class="module-panel satellite-method"><summary>How satellite checks work</summary>
    <p>These are dated pictures, not a live view. We use Sentinel-2 images with clouds, shadows and missing data filtered out. A clear picture may cover only part of the parcel; small or narrow parcels may have very few usable pixels. Missing readings never mean zero.</p>
    <p>NDVI measures plant greenness. NDMI indicates vegetation water content, not soil moisture. SAVI reduces the effect of bare soil; EVI helps compare thick vegetation. Crop stage, standing water and recent harvest can affect every result.</p>
    <p>The colour scale runs from −1 to +1. SAVI and EVI may go beyond it: the numbers are kept and the meter expands, while the map uses the end colours. These numbers are not percentages or risk categories.</p>
    <p id="satelliteFormula">NDVI: {{ $indexes['ndvi']['formula'] }} · Harmonized surface reflectance.</p>
    <p>NDMI uses approximately 20 m pixels; the other checks and natural-colour pictures use approximately 10 m pixels. Several images from one UTC day may be combined. The scene-cloud filter uses 20%, 60% or 100% over the wider image, not the percentage of your field that is clear. NDMI uses Sentinel-2 B08/B11; SAVI uses L = 0.5. Undefined divisions are excluded.</p>
    <p>Confirm conditions in the field before acting. These checks cannot diagnose disease, predict harvest, prove ownership or decide assistance eligibility.</p>
  </details>
</div>
<script type="application/json" id="satelliteConfig">{!! json_encode([
  'geometry' => $parcel['geometry'] ?? null, 'bounds' => $parcel['map_bounds'] ?? null,
  'googleKey' => (string) config('services.google_maps.key', ''),
  'imageUrl' => route('farm-plots.satellite.image', $plot),
  'indexes' => $indexes,
  'contextVersion' => hash('sha256', json_encode([
    $plot->name, $plot->updated_at, $plot->farmer_id, $plot->farmer?->municipality_id,
    auth()->id(), auth()->user()?->role, auth()->user()?->province_id, auth()->user()?->municipality_id,
    $configured, $from, $to, csrf_token(), $indexes, @filemtime(public_path('js/parcel-satellite.js')),
  ], JSON_THROW_ON_ERROR)),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>window.__mapUsage = @json(['url' => route('usage.map-events'), 'token' => csrf_token()]);</script>
<script src="{{ asset('js/reusable-google-map.js') }}?v={{ @filemtime(public_path('js/reusable-google-map.js')) ?: 1 }}" defer></script>
<script src="{{ asset('js/parcel-satellite.js') }}?v={{ @filemtime(public_path('js/parcel-satellite.js')) ?: 1 }}" defer></script>
