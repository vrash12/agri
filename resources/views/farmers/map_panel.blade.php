{{-- resources/views/farmers/map_panel.blade.php --}}

<aside class="map-panel" id="mapPanel" aria-label="Selected farmer details">
  <div class="map-panel-card">
    <div class="map-panel-head">
      <div class="map-panel-title">Selected Farmer</div>
      <button type="button" class="btn btn-soft btn-sm" id="clearSelectionBtn" title="Clear selection">
        Clear
      </button>
    </div>

    <div class="map-panel-body">
      <div class="map-kv">
        <div class="map-k">Name</div>
        <div class="map-v" id="selName">—</div>
      </div>
      <div class="map-kv">
        <div class="map-k">FFRS</div>
        <div class="map-v td-mono" id="selFfrs">—</div>
      </div>
      <div class="map-kv">
        <div class="map-k">Location</div>
        <div class="map-v" id="selLocation">—</div>
      </div>

      <div class="map-metrics">
        <div class="map-metric">
          <div class="map-metric-label">Records</div>
          <div class="map-metric-value" id="selRecords">0</div>
        </div>
        <div class="map-metric">
          <div class="map-metric-label">Total Kgs</div>
          <div class="map-metric-value" id="selKgs">0.00</div>
        </div>
        <div class="map-metric">
          <div class="map-metric-label">Last Received</div>
          <div class="map-metric-value" id="selLast">—</div>
        </div>
      </div>

      <div class="map-panel-actions">
        <a href="#" class="btn btn-soft btn-sm" id="viewRecordsBtn" style="text-decoration:none;">
          View Records
        </a>

        <button type="button" class="btn btn-soft btn-sm" id="focusSelectedBtn" title="Fly to the selected marker">
          Focus
        </button>
      </div>

      <div class="map-divider"></div>

      <div class="map-panel-title" style="margin:0 0 10px;">Land Plots / Lots</div>

      <div class="map-plot-meta" id="plotMeta">
        <span class="pill pill-gray" id="plotCountPill">0 plots</span>
        <span class="pill pill-gray" id="plotAreaTotalPill">0.00 ha total</span>

        <span class="map-plot-draft" id="plotDraftInfo" style="display:none;">
          Draft: <b id="plotDraftPoints">0</b> pts • ~<b id="plotDraftArea">0.00</b> ha
        </span>
      </div>

      <div class="map-panel-title" style="margin:8px 0 8px;">Selected Lot Details</div>

      <div class="map-kv">
        <div class="map-k">Lot</div>
        <div class="map-v" id="selPlotName">—</div>
      </div>
      <div class="map-kv">
        <div class="map-k">Area (ha)</div>
        <div class="map-v" id="selPlotArea">0.00</div>
      </div>
      <div class="map-kv">
        <div class="map-k">Points</div>
        <div class="map-v" id="selPlotPts">0</div>
      </div>
      <div class="map-kv" style="border-bottom:none;">
        <div class="map-k">Created</div>
        <div class="map-v" id="selPlotDate">—</div>
      </div>

      <div class="map-divider"></div>

      <div class="map-input-row">
        <label class="map-input-label" for="plotNameInput">Plot name</label>
        <input id="plotNameInput" class="map-input" type="text" placeholder="e.g., North Field">
      </div>

      <div class="map-plot-list" id="plotList">
        <div class="map-empty">No plots yet. Select a farmer, then click <b>Plot Land</b>.</div>
      </div>

      <details class="map-help">
        <summary>Help & shortcuts</summary>
        <div class="map-help-body">
          <ul class="map-help-list">
            <li><b>Fit to results</b> fits the camera to visible (filtered) farmers.</li>
            <li><b>Plot Land</b> → click the map to add corners (min 3).</li>
            <li><b>Esc</b> cancels plot, <b>Backspace</b> undo last point, <b>Enter</b> save.</li>
            <li>If some markers are missing, the address may be incomplete.</li>
          </ul>
        </div>
      </details>
    </div>
  </div>
</aside>