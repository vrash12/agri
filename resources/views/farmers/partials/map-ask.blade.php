{{-- "Ask the map" and the field-visit priority layer. Behaviour lives in
     public/js/parcel-map-assistant.js; this partial holds no server data. --}}
<section class="parcel-tool-group map-ask-panel" id="mapAskPanel" aria-labelledby="mapAskTitle">
  <div class="map-ask-head">
    <h3 class="parcel-tool-label" id="mapAskTitle">Ask the map</h3>
    <small>Answers come from saved records in this map view only. Nothing is changed.</small>
  </div>
  <form class="map-ask-form" id="mapAskForm" novalidate>
    <label class="map-ask-sr" for="mapAskInput">Question about parcels or farmers</label>
    <input class="parcel-search-input" id="mapAskInput" name="question" type="text" maxlength="200" autocomplete="off"
      placeholder="e.g. corn parcels with no harvest this wet season" aria-describedby="mapAskHelp">
    <button type="submit" class="btn btn-primary btn-sm" id="mapAskSubmit">Ask</button>
  </form>
  <small id="mapAskHelp">Try: rice parcels 2026 dry season · parcels without a boundary · farmers without a parcel in a barangay · parcels needing a field visit.</small>
  <div class="map-ask-answer" id="mapAskAnswer" role="status" aria-live="polite" hidden></div>

  <div class="map-highlight-controls">
    <label class="map-toggle" title="Outline parcels that most need a visit, from saved records only">
      <input type="checkbox" id="mapPriorityToggle" disabled><span>Field-visit priority</span>
    </label>
    <label class="map-toggle"><input type="checkbox" id="mapHighlightOnly" disabled><span>Show only highlighted</span></label>
    <button type="button" class="btn btn-soft btn-sm" id="mapHighlightClear" hidden>Clear highlight</button>
    <button type="button" class="btn btn-soft btn-sm" id="mapHighlightRetry" hidden>Retry highlight</button>
  </div>
  <p class="map-highlight-status" id="mapHighlightStatus" role="status" aria-live="polite">Highlights become available when the parcel map loads.</p>
  <div class="map-highlight-legend" id="mapHighlightLegend" aria-label="Highlight legend" hidden></div>
</section>
<style>
  #farmersMapModule .map-ask-sr { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
  #farmersMapModule .map-ask-panel { grid-column:1 / -1; border-right:0; border-top:1px solid var(--parcel-border); display:flex; flex-direction:column; gap:8px; min-width:0; }
  #farmersMapModule .map-ask-head { display:flex; flex-wrap:wrap; align-items:baseline; gap:4px 12px; }
  #farmersMapModule .map-ask-head h3 { margin:0; }
  #farmersMapModule .map-ask-head small, #farmersMapModule #mapAskHelp { color:var(--parcel-muted); font-size:12px; line-height:1.45; }
  #farmersMapModule .map-ask-form { display:flex; gap:8px; min-width:0; }
  #farmersMapModule .map-ask-form input { flex:1 1 auto; min-width:0; min-height:44px; font-size:16px; }
  #farmersMapModule .map-ask-form button { min-height:44px; min-width:72px; }
  #farmersMapModule .map-ask-answer { display:flex; flex-direction:column; gap:6px; padding:10px 12px; border:1px solid var(--parcel-border); border-left:4px solid var(--parcel-green); border-radius:8px; background:#f6fbf7; font-size:13px; line-height:1.5; overflow-wrap:anywhere; }
  #farmersMapModule .map-ask-answer[hidden] { display:none; }
  #farmersMapModule .map-ask-answer.is-clarify { border-left-color:#d4a017; background:#fffbeb; }
  #farmersMapModule .map-ask-answer.is-error { border-left-color:#b42318; background:#fef3f2; }
  #farmersMapModule .map-ask-answer small { color:var(--parcel-muted); }
  #farmersMapModule .map-ask-examples { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
  #farmersMapModule .map-ask-examples .btn { min-height:40px; white-space:normal; text-align:left; }
  #farmersMapModule .map-ask-link { font-weight:600; color:var(--parcel-green); }
  #farmersMapModule .map-highlight-controls { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  #farmersMapModule .map-highlight-controls [hidden] { display:none; }
  #farmersMapModule .map-highlight-status { margin:0; font-size:13px; color:var(--parcel-muted); }
  #farmersMapModule .map-highlight-status.is-error { color:#b42318; }
  #farmersMapModule .map-highlight-legend { display:flex; flex-wrap:wrap; gap:6px 14px; align-items:center; font-size:13px; }
  #farmersMapModule .map-highlight-legend[hidden] { display:none; }
  #farmersMapModule .map-highlight-legend > span:not(.parcel-flag-chip) { display:inline-flex; gap:6px; align-items:center; }
  #farmersMapModule .map-highlight-legend i { width:16px; height:12px; border:3px solid; border-radius:3px; background:#fff; }
  #farmersMapModule .map-highlight-legend small { flex-basis:100%; color:var(--parcel-muted); }
  #farmersMapModule .parcel-flag-chips { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
  #farmersMapModule .parcel-flag-chip { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; line-height:1.5; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; }
  #farmersMapModule .parcel-flag-chip.is-satellite-decline { background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
  #farmersMapModule .parcel-flag-chip.is-no-harvest { background:#fefce8; color:#854d0e; border-color:#fde68a; }
  #farmersMapModule .parcel-flag-chip.is-question { background:#fefce8; color:#713f12; border-color:#facc15; }
  #farmersMapModule .map-plot-flags[hidden] { display:none; }
  @media (max-width:520px) {
    #farmersMapModule .map-ask-form { flex-direction:column; }
    #farmersMapModule .map-ask-form button { width:100%; }
    #farmersMapModule .map-highlight-controls > * { flex:1 1 100%; }
  }
</style>
