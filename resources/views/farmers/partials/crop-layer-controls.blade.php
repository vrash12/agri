<details class="parcel-tool-group parcel-crop-controls" id="parcelCropControls" tabindex="-1">
  <summary class="parcel-tool-label">Crops on the map <span>Choose the year and season for crop records</span></summary>
  <p class="parcel-crop-help">Choose a period, then select <strong>Show this period</strong>. This changes the map, parcel crop summaries and land statistics together.</p>
  <div class="parcel-crop-fields">
    <label for="parcelCropMode">Map layer<select id="parcelCropMode"><option value="saved">All parcels with saved crops</option><option value="crops">Filter parcels by saved crop</option></select></label>
    <label for="parcelCropYear">Year<input id="parcelCropYear" type="number" min="1990" max="{{ \App\Support\LocalTime::now()->year + 1 }}" value="{{ \App\Support\LocalTime::now()->year }}" required></label>
    <label for="parcelCropSeason">Season<select id="parcelCropSeason"><option value="dry">Dry season</option><option value="wet">Wet season</option></select></label>
    <label for="parcelCropFilter">Crop<select id="parcelCropFilter"><option value="all">All crops</option>@foreach(\App\Models\ParcelCropSeason::CROPS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label>
    <button type="button" class="btn btn-primary btn-sm" id="parcelCropApply" disabled>Show this period</button>
    <button type="button" class="btn btn-soft btn-sm" id="parcelCropRetry" hidden>Retry</button>
  </div>
  <p id="parcelCropStatus" role="status">Saved crop icons and drawings load automatically when the parcel map opens.</p>
  <div id="parcelCropLegend" class="parcel-crop-legend" aria-label="Seasonal crop legend"></div>
  <p id="plantedAreaLayerStatus" role="status">Saved crop lines and shaded sections appear across the loaded parcel map, without selecting a farmer.</p>
  <button type="button" class="btn btn-soft btn-sm" id="plantedAreaRetry" hidden>Retry crop drawings</button>
  <details class="parcel-crop-explanation">
    <summary>How to read crop colours and drawings</summary>
    <ul class="parcel-crop-help">
      <li>White outlines mark parcel boundaries. Crop colours and icons describe saved records for this period, not live crop growth.</li>
      <li>Parcels without a crop record have no crop icon. This does not mean the land is unplanted.</li>
      <li>Zoom in to see individual crop drawings. Tap a section to identify its farmer.</li>
      <li>To update a record, open a farmer, expand their parcel and choose <strong>Seasonal crops &amp; drawings</strong>.</li>
      <li>Visual lines do not measure hectares. Polygon areas are approximate mapped areas; a whole-parcel crop colour is not a planted-area measurement.</li>
    </ul>
  </details>
</details>
<style>
  #farmersMapModule .parcel-tool-group.parcel-crop-controls { grid-column:1 / -1; min-width:0; border-right:0; border-top:1px solid var(--ui-border); }
  #farmersMapModule #parcelCropRetry[hidden] { display:none; }
  #farmersMapModule #plantedAreaRetry[hidden] { display:none; }
  #farmersMapModule .parcel-crop-fields { display:flex; flex-wrap:wrap; gap:12px; align-items:end; margin-top:12px; }
  #farmersMapModule .parcel-crop-fields label { display:flex; flex-direction:column; gap:6px; font-size:14px; flex:1 1 150px; min-width:0; }
  #farmersMapModule .parcel-crop-fields select, #farmersMapModule .parcel-crop-fields input { box-sizing:border-box; width:100%; min-height:44px; font:inherit; font-size:16px; padding:8px; border:1px solid var(--ui-control-border); border-radius:8px; background:var(--ui-surface); color:var(--ui-text); }
  #farmersMapModule .parcel-crop-controls > summary { min-height:44px; padding:12px; cursor:pointer; border:1px solid var(--ui-control-border); border-radius:8px; font-size:14px; }
  #farmersMapModule .parcel-crop-controls > summary span { font-size:12px; font-weight:400; }
  #farmersMapModule .parcel-crop-fields :focus-visible { outline:2px solid var(--ui-focus); outline-offset:2px; }
  #farmersMapModule .parcel-crop-help, #parcelCropStatus { font-size:13px; line-height:1.5; margin:10px 0; }
  #farmersMapModule .parcel-crop-explanation > summary { min-height:44px; padding:12px 0; cursor:pointer; font-size:13px; font-weight:600; }
  #farmersMapModule .parcel-crop-explanation li { margin-bottom:8px; }
  #farmersMapModule .parcel-crop-legend { display:flex; flex-wrap:wrap; gap:8px 16px; font-size:13px; }
  #farmersMapModule .parcel-crop-legend span { display:inline-flex; gap:6px; align-items:center; }
  #farmersMapModule .parcel-crop-legend i { width:14px; height:14px; border:1px solid #34443a; border-radius:3px; flex-shrink:0; }
</style>
