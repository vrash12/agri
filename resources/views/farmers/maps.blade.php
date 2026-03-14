{{-- resources/views/farmers/maps.blade.php --}}

@php
  $googleMapsApiKey = $googleMapsApiKey
    ?? config('services.google_maps.key')
    ?? env('GOOGLE_MAPS_API_KEY')
    ?? '';

  $googleMapsMapId  = $googleMapsMapId
    ?? config('services.google_maps.map_id')
    ?? env('GOOGLE_MAPS_MAP_ID')
    ?? '';

  $farmersMapData = $farmersMapData ?? [];
@endphp

<div class="farmers-map-wrap" id="farmersMapModule">
  <div class="farmers-map-head">
    <div class="farmers-map-head-left">
      <div class="map-title-row">
        <div class="h2" style="margin:0;">Farm Map</div>
        <span class="map-badge map-badge-solid">3D</span>
        <span class="map-badge map-badge-blue" id="plotModeBadge" style="display:none;">Plot mode</span>
      </div>

      <div class="p map-subtitle" style="margin:6px 0 0;">
        Markers come from <strong>Location of Farm</strong> (geocoded). Click a row or marker to select a farmer and manage plots.
      </div>

      <div class="map-status-row">
        <span class="pill pill-gray" id="mapStatus">Loading map…</span>

        <div class="map-progress" aria-hidden="true" title="Geocoding progress">
          <div class="map-progress-bar" id="mapProgressBar" style="width:0%;"></div>
        </div>

        <span class="map-status-small" id="mapStatusSmall">Starting…</span>
        <span class="pill pill-gray" id="mapGeocodedPill">0 geocoded</span>
        <span class="pill pill-gray" id="mapSelectedPill">No selection</span>
      </div>
    </div>

    <div class="farmers-map-head-right">
      <div class="map-controls">
        <div class="map-control-group">
          <div class="map-control-label">View</div>

          <button type="button" class="btn btn-soft btn-sm" id="recenterMapBtn" title="Fit map to visible (filtered) rows">
            Fit to results
          </button>

          <button type="button" class="btn btn-soft btn-sm" id="resetMapBtn" title="Reset camera and close popover">
            Reset
          </button>

          <label class="map-toggle" title="Show/hide markers">
            <input type="checkbox" id="toggleMarkers" checked>
            <span>Markers</span>
          </label>

          <label class="map-toggle" title="Show/hide saved plots">
            <input type="checkbox" id="togglePlots" checked>
            <span>Plots</span>
          </label>
        </div>

        <div class="map-control-group">
          <div class="map-control-label">Land plot</div>

          <button type="button" class="btn btn-soft btn-sm" id="plotModeBtn" title="Select a farmer, then start plotting">
            Plot Land
          </button>

          <button type="button" class="btn btn-soft btn-sm" id="plotClearBtn" style="display:none;" title="Clear draft rectangle">
            Clear
          </button>

          <button type="button" class="btn btn-sm" id="plotSaveBtn" style="display:none;" title="Save plot (Enter)">
            Save
          </button>

          <button type="button" class="btn btn-soft btn-sm" id="plotCancelBtn" style="display:none;" title="Cancel plotting (Esc)">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="farmers-map-main">
    <div class="farmers-map-stage">
      <div id="farmersMap" class="farmers-map"></div>

      <div id="plotCursor" class="plot-cursor" aria-hidden="true"></div>

      <div class="map-crosshair" aria-hidden="true">
        <div class="map-crosshair-dot"></div>
      </div>

      <div class="map-hint" id="mapHint">
        <div class="map-hint-title">Select a farmer</div>
        <div class="map-hint-text">
          Click a farmer row in the table or a marker on the map to view details and plots.
        </div>
      </div>

      <div class="map-selection-chip" id="mapSelectionChip" style="display:none;">
        <span class="map-selection-dot"></span>
        <span id="mapSelectionChipText">Selected farmer</span>
      </div>

      <div class="map-workflow-card" id="plotWorkflowCard">
        <div class="map-workflow-head">
          <div class="map-workflow-title">Plot workflow</div>
          <span class="pill pill-gray" id="plotWorkflowStep">Step 1</span>
        </div>

        <div class="map-workflow-text" id="plotWorkflowText">
          Select a farmer first, then click <b>Plot Land</b>.
        </div>

        <div class="map-workflow-grid">
          <div class="map-workflow-metric">
            <div class="map-workflow-label">Farmer</div>
            <div class="map-workflow-value" id="workflowFarmer">—</div>
          </div>
          <div class="map-workflow-metric">
            <div class="map-workflow-label">Corners</div>
            <div class="map-workflow-value" id="workflowCorners">0</div>
          </div>
          <div class="map-workflow-metric">
            <div class="map-workflow-label">Draft Area</div>
            <div class="map-workflow-value" id="workflowArea">0.00 ha</div>
          </div>
        </div>

        <div class="map-workflow-actions">
          <button type="button" class="btn btn-soft btn-sm" id="plotNewRectBtn" title="Create a fresh rectangle around the selected farmer">
            New rectangle
          </button>
          <button type="button" class="btn btn-soft btn-sm" id="plotCenterDraftBtn" title="Center the map on the current draft">
            Center draft
          </button>
        </div>
      </div>

      <div class="map-legend-card">
        <div class="map-legend-row">
          <span class="map-legend-swatch is-draft"></span>
          <span>Draft plot</span>
        </div>
        <div class="map-legend-row">
          <span class="map-legend-swatch is-saved"></span>
          <span>Saved plot</span>
        </div>
      </div>

      <div class="map-toast" id="mapToast" role="status" aria-live="polite" aria-atomic="true"></div>
    </div>

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

            <button type="button" class="btn btn-soft btn-sm" id="downloadSelectedPlotBtn" title="Download printable plot image">
              Download Image
            </button>

            <button type="button" class="btn btn-soft btn-sm" id="printSelectedPlotBtn" title="Print selected plot sheet">
              Print Sheet
            </button>
          </div>

          <div class="map-divider"></div>

          <div class="map-panel-title" style="margin:0 0 10px;">Land Plots / Lots</div>

          <div class="map-plot-meta" id="plotMeta">
            <span class="pill pill-gray" id="plotCountPill">0 plots</span>
            <span class="pill pill-gray" id="plotAreaTotalPill">0.00 ha total</span>

            <span class="map-plot-draft" id="plotDraftInfo" style="display:none;">
              Draft Area: ~<b id="plotDraftArea">0.00</b> ha
              <br>
              <span id="plotAdjustHint" style="font-size:11px; font-weight:normal; color:var(--muted);">
                Click a corner dot, then click the map to move it. Use arrow keys for fine nudging.
              </span>
            </span>
          </div>

          <div class="map-input-row">
            <label class="map-input-label" for="plotNameInput">Plot name</label>
            <input id="plotNameInput" class="map-input" type="text" placeholder="e.g., North Field">
          </div>

          <div class="map-input-row">
            <label class="map-input-label" for="plotColorInput">Plot color</label>
            <div class="map-color-row">
              <input id="plotColorInput" class="map-color" type="color" value="#3b82f6">
              <input id="plotColorHex" class="map-color-hex" type="text" value="#3b82f6" maxlength="16" spellcheck="false">
              <button type="button" class="btn btn-soft btn-sm" id="plotColorRandomBtn" title="Random color">Random</button>
            </div>

            <div class="map-color-presets" id="plotColorPresets">
              <button type="button" class="map-color-chip is-active" data-color="#3b82f6" style="--chip:#3b82f6;" title="#3b82f6"></button>
              <button type="button" class="map-color-chip" data-color="#22c55e" style="--chip:#22c55e;" title="#22c55e"></button>
              <button type="button" class="map-color-chip" data-color="#f97316" style="--chip:#f97316;" title="#f97316"></button>
              <button type="button" class="map-color-chip" data-color="#e11d48" style="--chip:#e11d48;" title="#e11d48"></button>
              <button type="button" class="map-color-chip" data-color="#8b5cf6" style="--chip:#8b5cf6;" title="#8b5cf6"></button>
              <button type="button" class="map-color-chip" data-color="#14b8a6" style="--chip:#14b8a6;" title="#14b8a6"></button>
            </div>
          </div>

          <div class="map-plot-list" id="plotList">
            <div class="map-empty">No plots yet. Select a farmer, then click <b>Plot Land</b>.</div>
          </div>

          <details class="map-help">
            <summary>Plotting help</summary>
            <div class="map-help-body">
              <ul class="map-help-list">
                <li>Select a farmer first, then click <b>Plot Land</b>.</li>
                <li>A starter rectangle appears automatically around the selected marker.</li>
                <li>Click a corner dot, then click the map to move it.</li>
                <li>Use <b>Arrow keys</b> for fine nudging and <b>Shift + Arrow</b> for larger steps.</li>
                <li><b>Enter</b> saves and <b>Esc</b> cancels.</li>
              </ul>
            </div>
          </details>
        </div>
      </div>
    </aside>
  </div>

  <div class="farmers-map-foot">
    <span class="badge badge-gray">Tip:</span>
    <span class="p" style="margin:0;">
      Click a row to fly to its marker. Click a marker to highlight the row. Use the right panel to manage plots.
    </span>
  </div>
</div>

<script>
  window.__farmersMapData = window.__farmersMapData || @json($farmersMapData);
  window.__gmapsApiKey = window.__gmapsApiKey || @json($googleMapsApiKey);
  window.__gmapsMapId = window.__gmapsMapId || @json($googleMapsMapId);
  window.__farmersRecordsBaseUrl = window.__farmersRecordsBaseUrl || "/farmers";
</script>

@include('farmers.partials.maps-styles')
@include('farmers.partials.maps-scripts')