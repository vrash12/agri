<details class="parcel-tool-group parcel-crop-controls" id="parcelCropControls">
  <summary class="parcel-tool-label">Crops by season <span>Recorded parcel crops</span></summary>
  <div class="parcel-crop-fields">
    <label for="parcelCropMode">Map layer<select id="parcelCropMode"><option value="saved">Saved parcel colors</option><option value="crops">Crops by season</option></select></label>
    <label for="parcelCropYear">Year<input id="parcelCropYear" type="number" min="1990" max="{{ \App\Support\LocalTime::now()->year + 1 }}" value="{{ \App\Support\LocalTime::now()->year }}" required></label>
    <label for="parcelCropSeason">Season<select id="parcelCropSeason"><option value="dry">Dry season</option><option value="wet">Wet season</option></select></label>
    <label for="parcelCropFilter">Crop<select id="parcelCropFilter"><option value="all">All crops</option>@foreach(\App\Models\ParcelCropSeason::CROPS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label>
    <button type="button" class="btn btn-soft btn-sm" id="parcelCropApply" disabled>Apply layer</button>
    <button type="button" class="btn btn-soft btn-sm" id="parcelCropRetry" hidden>Retry</button>
  </div>
  <p id="parcelCropStatus" role="status">Waiting for the parcel map to load. Saved parcel colors are shown first.</p>
  <div id="parcelCropLegend" class="parcel-crop-legend" aria-label="Seasonal crop legend"></div>
  <p class="parcel-crop-help">Choose a farmer, then use Seasonal crops beside a parcel to record its crop. Colors describe the selected season; they do not confirm current planting or planted hectares. Gray means Not recorded, not fallow.</p>
</details>
<style>
  #farmersMapModule .parcel-tool-group.parcel-crop-controls { grid-column:1 / -1; min-width:0; border-right:0; border-top:1px solid var(--ui-border); }
  #farmersMapModule #parcelCropRetry[hidden] { display:none; }
  #farmersMapModule .parcel-crop-fields { display:flex; flex-wrap:wrap; gap:12px; align-items:end; margin-top:12px; }
  #farmersMapModule .parcel-crop-fields label { display:flex; flex-direction:column; gap:6px; font-size:14px; flex:1 1 150px; min-width:0; }
  #farmersMapModule .parcel-crop-fields select, #farmersMapModule .parcel-crop-fields input { box-sizing:border-box; width:100%; min-height:44px; font:inherit; font-size:16px; padding:8px; border:1px solid var(--ui-control-border); border-radius:8px; background:var(--ui-surface); color:var(--ui-text); }
  #farmersMapModule .parcel-crop-controls > summary { min-height:44px; padding:12px; cursor:pointer; border:1px solid var(--ui-control-border); border-radius:8px; font-size:14px; }
  #farmersMapModule .parcel-crop-controls > summary span { font-size:12px; font-weight:400; }
  #farmersMapModule .parcel-crop-fields :focus-visible { outline:2px solid var(--ui-focus); outline-offset:2px; }
  #farmersMapModule .parcel-crop-help, #parcelCropStatus { font-size:13px; line-height:1.5; margin:10px 0; }
  #farmersMapModule .parcel-crop-legend { display:flex; flex-wrap:wrap; gap:8px 16px; font-size:13px; }
  #farmersMapModule .parcel-crop-legend span { display:inline-flex; gap:6px; align-items:center; }
  #farmersMapModule .parcel-crop-legend i { width:14px; height:14px; border:1px solid #34443a; border-radius:3px; flex-shrink:0; }
</style>
