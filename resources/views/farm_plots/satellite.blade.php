@extends('layouts.app')
@section('title', 'Satellite observations')
@push('styles')
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/parcel-satellite.css') }}?v={{ @filemtime(public_path('css/parcel-satellite.css')) ?: 1 }}">
@endpush
@section('content')
<div class="module-page satellite-page" id="satellitePage">
  <header class="module-header">
    <div><div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · Parcel monitoring</div>
      <h1>Satellite observations</h1>
      <p>{{ $plot->name ?: 'Parcel #'.$plot->id }} · {{ number_format($parcel['area_ha'] ?? (float) $plot->area_ha, 2) }} ha approximate mapped area</p>
    </div>
    <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id]) }}#farmersMapModule">Back to parcel map</a>
  </header>
  @if(!$configured)
    <div class="module-alert" role="status"><strong>Satellite access needs setup.</strong> Your administrator must configure Copernicus access before observations can load. Parcel boundaries remain available.</div>
  @endif
  @if($geometryError)<div class="module-alert module-alert-error" role="alert">{{ $geometryError }}</div>@endif
  <section class="module-panel">
    <div class="module-panel-head"><div><h2>Choose an observation period</h2><p>Sentinel-2 surface reflectance · Up to 31 days at a time · Requests use the office's Copernicus allowance.</p></div></div>
    <form id="satelliteForm" class="module-form-body satellite-filters" action="{{ route('farm-plots.satellite.analyse', $plot) }}" method="POST">
      @csrf
      <div class="module-form-field"><label for="satelliteFrom">From (UTC)</label><input class="module-input" id="satelliteFrom" name="from" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $from }}" required></div>
      <div class="module-form-field"><label for="satelliteTo">To (UTC)</label><input class="module-input" id="satelliteTo" name="to" type="date" min="2017-03-28" max="{{ now()->utc()->toDateString() }}" value="{{ $to }}" required></div>
      <div class="module-form-field"><label for="satelliteCloud">Maximum scene cloud cover</label><select class="module-input" id="satelliteCloud" name="max_cloud"><option value="20">20%</option><option value="60" selected>60%</option><option value="100">100% · Include all scenes</option></select></div>
      <button class="module-button module-button-primary" id="satelliteLoad" @disabled(!$configured || !$parcel)>Load observations</button>
    </form>
    <p class="satellite-note">The cloud limit applies to whole satellite scenes. Clouds, shadows, snow, uncertain pixels and no-data areas are masked again inside your parcel. Clear observations may be unavailable.</p>
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
      <p class="satellite-note">Color represents vegetation greenness, not a diagnosis. Transparent areas have no usable observation. Imagery: Copernicus Sentinel-2, processed through CDSE. The road map underneath is a separate reference layer.</p>
    </section>
    <section class="module-panel satellite-summary" aria-labelledby="satelliteSummaryHeading">
      <div class="module-panel-head"><div><h2 id="satelliteSummaryHeading">Within this parcel</h2><p>Zonal statistics from usable satellite pixels.</p></div></div>
      <div class="module-form-body">
        <dl class="satellite-metrics"><div><dt>Mean NDVI</dt><dd id="satelliteMean">—</dd></div><div><dt>Minimum / maximum</dt><dd id="satelliteRange">—</dd></div><div><dt>Usable pixels</dt><dd id="satellitePixels">—</dd></div><div><dt>Observation day</dt><dd id="satelliteDay">—</dd></div></dl>
        <p id="satelliteQuality" class="satellite-note">No vegetation measurement has been loaded.</p>
        <p class="satellite-note">Red and near-infrared bands have 10 m native resolution. This pilot uses an approximately 10 m output grid. Small or narrow parcels and edge pixels need particular care.</p>
        <p class="satellite-note">Daily results can combine overlapping scenes from that UTC day. Water, harvest, planting stage and soil affect NDVI; compare with crop records and field observations.</p>
      </div>
    </section>
  </div>
  <section class="module-panel" aria-labelledby="satelliteHistoryHeading">
    <div class="module-panel-head"><div><h2 id="satelliteHistoryHeading">Vegetation over time</h2><p>Daily observations in the selected period. Missing observations are not zero.</p></div></div>
    <div class="satellite-chart" id="satelliteChart" aria-label="Mean NDVI observations; complete values in the table below"></div>
    <div class="module-table-scroll"><table class="module-table"><thead><tr><th scope="col">Day (UTC)</th><th scope="col">Mean NDVI</th><th scope="col">Minimum</th><th scope="col">Maximum</th><th scope="col">Usable pixels</th><th scope="col">Availability</th></tr></thead><tbody id="satelliteHistory"><tr><td colspan="6">No observations loaded.</td></tr></tbody></table></div>
  </section>
  <details class="module-panel satellite-method"><summary>How to interpret this analysis</summary>
    <p>This view combines <strong>overlay analysis</strong> (imagery with a saved parcel boundary) and <strong>zonal statistics</strong> (NDVI values inside that boundary). It can guide a field visit, but does not determine crop type, disease, yield or assistance eligibility.</p>
    <p>A buffer can support proximity planning when service locations are recorded. Statistical hot spots need defined variables, enough observations and a tested method; interpolation needs measured soil or weather samples; network analysis needs a connected road network and travel costs. Those methods are not computed by this satellite view.</p>
  </details>
</div>
<script type="application/json" id="satelliteConfig">{!! json_encode([
  'geometry' => $parcel['geometry'] ?? null, 'bounds' => $parcel['map_bounds'] ?? null,
  'googleKey' => (string) config('services.google_maps.key', ''),
  'imageUrl' => route('farm-plots.satellite.image', $plot),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/parcel-satellite.js') }}?v={{ @filemtime(public_path('js/parcel-satellite.js')) ?: 1 }}" defer></script>
@endsection
