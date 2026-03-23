{{-- resources/views/farmers/maps.blade.php --}}

@php
  $googleMapsApiKey = $googleMapsApiKey ?? config('services.google_maps.key') ?? env('GOOGLE_MAPS_API_KEY') ?? '';
  $googleMapsMapId  = $googleMapsMapId ?? config('services.google_maps.map_id') ?? env('GOOGLE_MAPS_MAP_ID') ?? '';
  $farmersMapData   = $farmersMapData ?? [];
@endphp

<div class="farmers-map-wrap" id="farmersMapModule">
  
  {{-- TOP HEADER BAR --}}
  <div class="farmers-map-head">
    <div class="farmers-map-head-left">
      <div class="map-title-row">
        <h2 style="margin:0; font-weight: 800; color: #0f172a;">Farm Map</h2>
        <span class="map-badge map-badge-solid">3D Beta</span>
        <span class="map-badge map-badge-blue" id="plotModeBadge" style="display:none;">Plotting Mode Active</span>
      </div>

      <div class="p map-subtitle" style="margin:6px 0 0; color: #64748b;">
        Click a marker or table row to view farmer details and manage their land plots.
      </div>

      <div class="map-status-row" style="margin-top: 12px; display: flex; align-items: center; gap: 8px;">
        <span class="pill pill-gray" id="mapStatus">Loading map…</span>
        <div class="map-progress" aria-hidden="true" style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 99px; overflow: hidden;">
          <div class="map-progress-bar" id="mapProgressBar" style="width:0%; height: 100%; background: #3b82f6; transition: width 0.3s ease;"></div>
        </div>
        <span class="map-status-small" id="mapStatusSmall" style="font-size: 12px; color: #64748b;">Starting…</span>
        <span class="pill pill-blue" id="mapGeocodedPill">0 Geocoded</span>
      </div>
    </div>

    <div class="farmers-map-head-right">
      <div class="map-controls">
        {{-- View Controls --}}
        <div class="map-control-group">
          <div class="map-control-label">View & Filters</div>
          <button type="button" class="btn btn-soft btn-sm" id="recenterMapBtn" title="Fit map to visible farmers">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
            Fit to Results
          </button>
          <button type="button" class="btn btn-soft btn-sm" id="resetMapBtn" title="Reset camera">
            Reset
          </button>
          <label class="map-toggle">
            <input type="checkbox" id="toggleMarkers" checked>
            <span>Markers</span>
          </label>
          <label class="map-toggle">
            <input type="checkbox" id="togglePlots" checked>
            <span>Plots</span>
          </label>
        </div>

        {{-- Plotting Controls --}}
        <div class="map-control-group" style="border-left: 1px solid #e2e8f0; padding-left: 16px;">
          <div class="map-control-label">Plot Management</div>
          
          {{-- Notice I added a hypothetical 'btn-primary' for the main action --}}
          <button type="button" class="btn btn-primary btn-sm" id="plotModeBtn" title="Select a farmer, then start plotting">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Plot Land
          </button>

          <button type="button" class="btn btn-soft btn-sm" id="plotClearBtn" style="display:none;" title="Clear draft rectangle">
            Clear Draft
          </button>
          <button type="button" class="btn btn-primary btn-sm" id="plotSaveBtn" style="display:none;" title="Save plot (Enter)">
            Save Plot
          </button>
          <button type="button" class="btn btn-danger btn-sm" id="plotCancelBtn" style="display:none;" title="Cancel plotting (Esc)">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- MAIN MAP STAGE --}}
  <div class="farmers-map-main">
    <div class="farmers-map-stage">
      <div id="farmersMap" class="farmers-map"></div>
      <div id="plotCursor" class="plot-cursor" aria-hidden="true"></div>
      
      <div class="map-hint" id="mapHint">
        <div class="map-hint-title">Select a farmer to begin</div>
        <div class="map-hint-text">Click a marker on the map to view their farm records and manage land plots.</div>
      </div>

      {{-- Floating Workflow Card (Visible during plotting) --}}
      <div class="map-workflow-card shadow-lg" id="plotWorkflowCard">
        <div class="map-workflow-head">
          <div class="map-workflow-title">Plot Draft</div>
          <span class="pill pill-blue" id="plotWorkflowStep">Step 1</span>
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
            <div class="map-workflow-label">Est. Area</div>
            <div class="map-workflow-value text-blue-600" id="workflowArea">0.00 ha</div>
          </div>
        </div>
        <div class="map-workflow-actions">
          <button type="button" class="btn btn-soft btn-sm" id="plotNewRectBtn">New Starter Box</button>
          <button type="button" class="btn btn-soft btn-sm" id="plotCenterDraftBtn">Center View</button>
        </div>
      </div>

      <div class="map-toast" id="mapToast" role="status" aria-live="polite"></div>
    </div>

    {{-- SIDE PANEL --}}
    <aside class="map-panel" id="mapPanel" aria-label="Selected farmer details">
      
      {{-- Empty State (Optional: Handle via JS if you want to hide the panel entirely when null) --}}
      <div class="map-panel-head" style="justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
        <div class="map-panel-title" style="font-size: 18px; font-weight: 800;">Farmer Details</div>
        <button type="button" class="btn btn-soft btn-sm" id="clearSelectionBtn" title="Deselect farmer">
          Close
        </button>
      </div>

      <div class="map-panel-body">
        
        {{-- Section 1: Identity Card --}}
        <div class="panel-card bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
          <h3 class="font-bold text-lg text-slate-800" id="selName">—</h3>
          <div class="text-sm text-slate-500 mt-1 flex items-center gap-2">
            <span class="font-mono bg-white px-2 py-0.5 rounded border border-gray-200" id="selFfrs">FFRS: —</span>
          </div>
          <div class="text-sm text-slate-600 mt-2 flex items-start gap-2">
            <svg class="mt-0.5 text-slate-400" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            <span id="selLocation">—</span>
          </div>

          <div class="flex gap-2 mt-4">
            <a href="#" class="btn btn-white btn-sm flex-1 text-center" id="viewRecordsBtn" style="justify-content: center;">
              View Profile
            </a>
            <button type="button" class="btn btn-white btn-sm" id="focusSelectedBtn" title="Center on map">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            </button>
          </div>
        </div>

        {{-- Section 2: Farm Stats --}}
        <div class="map-metrics mb-6">
          <div class="map-metric bg-white border border-gray-200 rounded p-2">
            <div class="map-metric-label text-xs text-slate-500 uppercase tracking-wide">Records</div>
            <div class="map-metric-value text-lg font-bold text-slate-800" id="selRecords">0</div>
          </div>
          <div class="map-metric bg-white border border-gray-200 rounded p-2">
            <div class="map-metric-label text-xs text-slate-500 uppercase tracking-wide">Total Yield</div>
            <div class="map-metric-value text-lg font-bold text-slate-800"><span id="selKgs">0.00</span><span class="text-sm font-normal text-slate-500 ml-1">kg</span></div>
          </div>
        </div>

        <div class="map-divider" style="height: 1px; background: #e2e8f0; margin: 20px 0;"></div>

        {{-- Section 3: Plot Management --}}
        <div class="flex items-center justify-between mb-3">
          <div class="map-panel-title" style="margin:0; font-size: 16px; font-weight: 700;">Land Plots</div>
          <div id="plotMeta" class="flex gap-1">
            <span class="pill pill-blue text-xs font-bold" id="plotCountPill">0 plots</span>
          </div>
        </div>

        <div class="text-sm text-slate-600 mb-4 bg-blue-50 text-blue-800 p-2 rounded border border-blue-100 flex justify-between items-center">
          <span>Total Farm Area</span>
          <strong id="plotAreaTotalPill">0.00 ha</strong>
        </div>

        {{-- Draft info dynamically shown during plot mode --}}
        <div class="map-plot-draft bg-amber-50 border border-amber-200 p-3 rounded-lg mb-4" id="plotDraftInfo" style="display:none;">
          <div class="font-bold text-amber-900 mb-1">Drafting Plot</div>
          <div class="text-sm text-amber-800">
            Est. Area: <strong id="plotDraftArea">0.00</strong> ha<br>
            Points: <strong id="plotDraftPoints">0</strong>
          </div>
          <div class="text-xs text-amber-700 mt-2">
            Click a corner to select it, then click the map to move it. Press Enter to save.
          </div>
        </div>

        {{-- Plot Setup Inputs --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-4">
          <div class="map-input-row mb-3">
            <label class="map-input-label text-xs font-bold text-slate-600 uppercase mb-1 block" for="plotNameInput">Plot Name</label>
            <input id="plotNameInput" class="map-input w-full p-2 border border-gray-300 rounded" type="text" placeholder="e.g., North Field">
          </div>

          <div class="map-input-row">
            <label class="map-input-label text-xs font-bold text-slate-600 uppercase mb-1 block" for="plotColorInput">Plot Color</label>
            <div class="map-color-row flex gap-2 mb-2">
              <input id="plotColorInput" class="map-color w-10 h-10 p-0 border-0 rounded cursor-pointer" type="color" value="#3b82f6">
              <input id="plotColorHex" class="map-color-hex flex-1 p-2 border border-gray-300 rounded font-mono text-sm" type="text" value="#3b82f6" maxlength="16" spellcheck="false">
            </div>
            <div class="map-color-presets flex gap-1 mt-2" id="plotColorPresets">
              {{-- Presets --}}
              <button type="button" class="w-6 h-6 rounded-full border-2 border-transparent hover:scale-110 transition-transform cursor-pointer" data-color="#3b82f6" style="background:#3b82f6;" title="Blue"></button>
              <button type="button" class="w-6 h-6 rounded-full border-2 border-transparent hover:scale-110 transition-transform cursor-pointer" data-color="#22c55e" style="background:#22c55e;" title="Green"></button>
              <button type="button" class="w-6 h-6 rounded-full border-2 border-transparent hover:scale-110 transition-transform cursor-pointer" data-color="#f97316" style="background:#f97316;" title="Orange"></button>
              <button type="button" class="w-6 h-6 rounded-full border-2 border-transparent hover:scale-110 transition-transform cursor-pointer" data-color="#e11d48" style="background:#e11d48;" title="Red"></button>
              <button type="button" class="btn btn-soft btn-sm text-xs px-2 py-0 ml-auto" id="plotColorRandomBtn">Random</button>
            </div>
          </div>
        </div>

        {{-- List of saved plots --}}
        <div class="map-plot-list flex flex-col gap-2" id="plotList">
          <div class="map-empty text-center p-6 bg-gray-50 border border-dashed border-gray-300 rounded text-slate-500 text-sm">
            No plots yet.<br>Click <b>Plot Land</b> above to start.
          </div>
        </div>

      </div>
    </aside>
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