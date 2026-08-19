{{-- resources/views/farmers/maps.blade.php --}}

@php
  $googleMapsApiKey = $googleMapsApiKey ?? config('services.google_maps.key') ?? env('GOOGLE_MAPS_API_KEY') ?? '';
  $googleMapsMapId  = $googleMapsMapId ?? config('services.google_maps.map_id') ?? env('GOOGLE_MAPS_MAP_ID') ?? '';
  $farmersMapData   = $farmersMapData ?? [];
@endphp

@push('styles')
<style>
  /* Adjusted map height: shorter than before */
  #farmersMapModule .farmers-map-main{
    display:grid;
    grid-template-columns:minmax(0, 1fr) 500px;
    align-items:stretch;
    gap:0;
  }

  #farmersMapModule .farmers-map-stage{
    position:relative;
    min-height:720px !important;
    height:720px !important;
    overflow:hidden;
    border-radius:24px;
  }

  #farmersMapModule .farmers-map,
  #farmersMapModule #farmersMap{
    width:100%;
    height:100% !important;
    min-height:720px !important;
    display:block;
  }

  #farmersMapModule .map-panel{
    height:720px;
    overflow-y:auto;
  }

  @media (max-width: 1200px){
    #farmersMapModule .farmers-map-main{
      grid-template-columns:1fr;
    }

    #farmersMapModule .farmers-map-stage{
      min-height:600px !important;
      height:600px !important;
    }

    #farmersMapModule .farmers-map,
    #farmersMapModule #farmersMap{
      min-height:600px !important;
      height:600px !important;
    }

    #farmersMapModule .map-panel{
      height:auto;
      overflow:visible;
    }
  }

  /* =========================================================
   ENHANCED FARMER DETAILS PANEL
   ========================================================= */

#farmersMapModule .farmer-details-panel{
  --fd-text:#0f172a;
  --fd-muted:#64748b;
  --fd-border:#e2e8f0;
  --fd-green:#16a34a;
  --fd-green-dark:#166534;
  --fd-green-soft:#ecfdf5;
  --fd-blue:#2563eb;
  --fd-blue-soft:#eff6ff;
  --fd-amber:#d97706;
  --fd-amber-soft:#fffbeb;

  height:720px;
  overflow-y:auto;
  padding:0;
  background:#f8fafc;
  border-left:1px solid var(--fd-border);
  scrollbar-width:thin;
  scrollbar-color:#cbd5e1 transparent;
}

#farmersMapModule .farmer-details-panel::-webkit-scrollbar{
  width:8px;
}

#farmersMapModule .farmer-details-panel::-webkit-scrollbar-thumb{
  background:#cbd5e1;
  border-radius:999px;
}

#farmersMapModule .farmer-details-inner{
  min-height:100%;
  background:#f8fafc;
}

/* Sticky header */

#farmersMapModule .farmer-details-header{
  position:sticky;
  top:0;
  z-index:12;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  padding:16px 18px;
  border-bottom:1px solid var(--fd-border);
  background:rgba(255,255,255,.94);
  backdrop-filter:blur(14px);
}

#farmersMapModule .farmer-details-heading{
  display:flex;
  align-items:center;
  gap:10px;
}

#farmersMapModule .farmer-details-heading-icon{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  flex:0 0 auto;
  border-radius:13px;
  color:var(--fd-green-dark);
  background:var(--fd-green-soft);
}

#farmersMapModule .farmer-details-heading-icon svg{
  width:19px;
  height:19px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
}

#farmersMapModule .farmer-details-heading h3{
  margin:0;
  color:var(--fd-text);
  font-size:16px;
  font-weight:950;
}

#farmersMapModule .farmer-details-heading p{
  margin:3px 0 0;
  color:var(--fd-muted);
  font-size:11px;
}

#farmersMapModule .farmer-close-btn{
  min-width:42px;
  height:38px;
  padding:0 12px !important;
  border-radius:12px !important;
}

/* General body */

#farmersMapModule .farmer-details-body{
  display:flex;
  flex-direction:column;
  gap:14px;
  padding:14px;
}

#farmersMapModule .fd-card{
  overflow:hidden;
  border:1px solid var(--fd-border);
  border-radius:18px;
  background:#fff;
  box-shadow:0 8px 22px rgba(15,23,42,.045);
}

#farmersMapModule .fd-card-body{
  padding:14px;
}

#farmersMapModule .fd-section-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-bottom:12px;
}

#farmersMapModule .fd-section-title-wrap{
  display:flex;
  align-items:center;
  gap:9px;
}

#farmersMapModule .fd-section-icon{
  width:32px;
  height:32px;
  display:grid;
  place-items:center;
  flex:0 0 auto;
  border-radius:11px;
  color:var(--fd-green-dark);
  background:var(--fd-green-soft);
}

#farmersMapModule .fd-section-icon.blue{
  color:var(--fd-blue);
  background:var(--fd-blue-soft);
}

#farmersMapModule .fd-section-icon.amber{
  color:var(--fd-amber);
  background:var(--fd-amber-soft);
}

#farmersMapModule .fd-section-icon svg{
  width:16px;
  height:16px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
}

#farmersMapModule .fd-section-title{
  margin:0;
  color:var(--fd-text);
  font-size:13px;
  font-weight:950;
}

#farmersMapModule .fd-section-subtitle{
  margin:3px 0 0;
  color:var(--fd-muted);
  font-size:10px;
  line-height:1.4;
}

/* Farmer identity */

#farmersMapModule .fd-profile-card{
  position:relative;
  overflow:hidden;
  padding:16px;
  background:
    radial-gradient(
      circle at top right,
      rgba(250,204,21,.15),
      transparent 35%
    ),
    radial-gradient(
      circle at bottom left,
      rgba(34,197,94,.13),
      transparent 42%
    ),
    linear-gradient(145deg,#ffffff,#f6fff8);
}

#farmersMapModule .fd-profile-card::after{
  content:"";
  position:absolute;
  width:120px;
  height:120px;
  top:-68px;
  right:-48px;
  border-radius:999px;
  background:rgba(34,197,94,.08);
}

#farmersMapModule .fd-profile-main{
  position:relative;
  z-index:1;
  display:grid;
  grid-template-columns:56px minmax(0,1fr);
  gap:13px;
  align-items:start;
}

#farmersMapModule .fd-avatar{
  width:56px;
  height:56px;
  display:grid;
  place-items:center;
  border:1px solid rgba(34,197,94,.24);
  border-radius:18px;
  color:#fff;
  background:linear-gradient(135deg,#22c55e,#166534);
  box-shadow:0 12px 22px rgba(22,163,74,.20);
  font-size:18px;
  font-weight:950;
  overflow:hidden;
  background-position:center;
  background-size:cover;
}

#farmersMapModule .fd-profile-label{
  display:flex;
  align-items:center;
  gap:7px;
  margin-bottom:5px;
  color:var(--fd-green-dark);
  font-size:9px;
  font-weight:950;
  letter-spacing:.55px;
  text-transform:uppercase;
}

#farmersMapModule .fd-profile-label-dot{
  width:7px;
  height:7px;
  border-radius:999px;
  background:#22c55e;
  box-shadow:0 0 0 4px rgba(34,197,94,.13);
}

#farmersMapModule .fd-profile-name{
  margin:0;
  color:var(--fd-text);
  font-size:18px;
  font-weight:950;
  line-height:1.22;
  word-break:break-word;
}

#farmersMapModule .fd-profile-ffrs{
  display:inline-flex;
  align-items:center;
  max-width:100%;
  margin-top:8px;
  padding:5px 9px;
  border:1px solid rgba(15,23,42,.10);
  border-radius:999px;
  color:#334155;
  background:rgba(255,255,255,.85);
  font-family:
    ui-monospace,
    SFMono-Regular,
    Menlo,
    Monaco,
    Consolas,
    monospace;
  font-size:10px;
  font-weight:850;
}

#farmersMapModule .fd-location{
  position:relative;
  z-index:1;
  display:grid;
  grid-template-columns:34px minmax(0,1fr);
  gap:10px;
  align-items:center;
  margin-top:14px;
  padding:10px 11px;
  border:1px solid rgba(37,99,235,.15);
  border-radius:14px;
  background:rgba(239,246,255,.80);
}

#farmersMapModule .fd-location-icon{
  width:34px;
  height:34px;
  display:grid;
  place-items:center;
  border-radius:11px;
  color:var(--fd-blue);
  background:#fff;
}

#farmersMapModule .fd-location-icon svg{
  width:17px;
  height:17px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
}

#farmersMapModule .fd-location-label{
  color:var(--fd-muted);
  font-size:9px;
  font-weight:950;
  letter-spacing:.35px;
  text-transform:uppercase;
}

#farmersMapModule .fd-location-value{
  margin-top:2px;
  color:var(--fd-text);
  font-size:11px;
  font-weight:850;
  line-height:1.4;
}

/* Information grid */

#farmersMapModule .fd-info-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:9px;
}

#farmersMapModule .fd-info-item{
  min-width:0;
  padding:10px 11px;
  border:1px solid var(--fd-border);
  border-radius:13px;
  background:#f8fafc;
}

#farmersMapModule .fd-info-item.is-wide{
  grid-column:1 / -1;
}

#farmersMapModule .fd-info-label{
  color:var(--fd-muted);
  font-size:9px;
  font-weight:950;
  letter-spacing:.35px;
  text-transform:uppercase;
}

#farmersMapModule .fd-info-value{
  margin-top:5px;
  overflow:hidden;
  color:var(--fd-text);
  font-size:11px;
  font-weight:900;
  line-height:1.4;
  text-overflow:ellipsis;
  word-break:break-word;
}

/* Actions */

#farmersMapModule .fd-profile-actions{
  display:grid;
  grid-template-columns:1fr auto;
  gap:8px;
  margin-top:12px;
}

#farmersMapModule .fd-view-btn{
  min-height:40px;
  color:#fff !important;
  border-color:#16a34a !important;
  background:linear-gradient(135deg,#22c55e,#15803d) !important;
  box-shadow:0 10px 20px rgba(22,163,74,.18) !important;
}

#farmersMapModule .fd-focus-btn{
  min-width:44px;
  min-height:40px;
  padding:0 12px !important;
}

#farmersMapModule .fd-action-icon{
  width:16px;
  height:16px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
}

/* Metrics */

#farmersMapModule .fd-metrics{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:9px;
}

#farmersMapModule .fd-metric{
  position:relative;
  overflow:hidden;
  min-width:0;
  padding:12px;
  border:1px solid var(--fd-border);
  border-radius:15px;
  background:#fff;
}

#farmersMapModule .fd-metric::after{
  content:"";
  position:absolute;
  width:62px;
  height:62px;
  top:-30px;
  right:-28px;
  border-radius:999px;
  background:var(--metric-bg);
}

#farmersMapModule .fd-metric.green{
  --metric-colour:#15803d;
  --metric-bg:rgba(34,197,94,.13);
}

#farmersMapModule .fd-metric.blue{
  --metric-colour:#1d4ed8;
  --metric-bg:rgba(37,99,235,.12);
}

#farmersMapModule .fd-metric-label{
  position:relative;
  z-index:1;
  color:var(--fd-muted);
  font-size:9px;
  font-weight:950;
  letter-spacing:.35px;
  text-transform:uppercase;
}

#farmersMapModule .fd-metric-value{
  position:relative;
  z-index:1;
  margin-top:7px;
  color:var(--metric-colour);
  font-size:19px;
  font-weight:950;
}

/* Plot summary */

#farmersMapModule .fd-plot-summary{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:9px;
}

#farmersMapModule .fd-plot-summary-item{
  padding:11px;
  border:1px solid var(--fd-border);
  border-radius:14px;
  background:#f8fafc;
}

#farmersMapModule .fd-plot-summary-label{
  color:var(--fd-muted);
  font-size:9px;
  font-weight:950;
  text-transform:uppercase;
}

#farmersMapModule .fd-plot-summary-value{
  margin-top:5px;
  color:var(--fd-text);
  font-size:13px;
  font-weight:950;
}

/* Draft box */

#farmersMapModule .fd-draft-box{
  margin-top:10px;
  padding:11px 12px;
  border:1px dashed rgba(217,119,6,.40);
  border-radius:14px;
  color:#92400e;
  background:#fffbeb;
}

#farmersMapModule .fd-draft-title{
  font-size:11px;
  font-weight:950;
}

#farmersMapModule .fd-draft-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:8px;
  margin-top:8px;
}

#farmersMapModule .fd-draft-stat{
  padding:8px;
  border-radius:10px;
  background:rgba(255,255,255,.66);
}

#farmersMapModule .fd-draft-stat span{
  display:block;
  color:#b45309;
  font-size:9px;
  font-weight:900;
  text-transform:uppercase;
}

#farmersMapModule .fd-draft-stat strong{
  display:block;
  margin-top:3px;
  font-size:12px;
}

/* Inputs */

#farmersMapModule .fd-field + .fd-field{
  margin-top:12px;
}

#farmersMapModule .fd-label{
  display:block;
  margin:0 0 6px;
  color:var(--fd-text);
  font-size:10px;
  font-weight:950;
}

#farmersMapModule .fd-input{
  width:100%;
  min-height:42px;
  padding:10px 12px;
  border:1px solid var(--fd-border);
  border-radius:12px;
  outline:none;
  color:var(--fd-text);
  background:#fff;
  font-size:11px;
  font-weight:800;
}

#farmersMapModule .fd-input:focus{
  border-color:rgba(34,197,94,.65);
  box-shadow:0 0 0 4px rgba(34,197,94,.11);
}

#farmersMapModule .fd-help-text{
  margin-top:5px;
  color:var(--fd-muted);
  font-size:9px;
  line-height:1.4;
}

/* Colour picker */

#farmersMapModule .fd-colour-row{
  display:grid;
  grid-template-columns:46px minmax(0,1fr);
  gap:8px;
}

#farmersMapModule .fd-colour-input{
  width:46px;
  height:42px;
  padding:4px;
  border:1px solid var(--fd-border);
  border-radius:12px;
  background:#fff;
  cursor:pointer;
}

#farmersMapModule .fd-colour-hex{
  width:100%;
  min-height:42px;
  padding:10px 12px;
  border:1px solid var(--fd-border);
  border-radius:12px;
  outline:none;
  font-family:
    ui-monospace,
    SFMono-Regular,
    Menlo,
    Monaco,
    Consolas,
    monospace;
  font-size:11px;
  font-weight:900;
}

#farmersMapModule .fd-colour-presets{
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:8px;
  margin-top:9px;
}

#farmersMapModule .fd-colour-chip{
  width:27px;
  height:27px;
  padding:0;
  border:3px solid #fff;
  border-radius:999px;
  background:var(--colour);
  box-shadow:0 0 0 1px rgba(15,23,42,.12);
  cursor:pointer;
  transition:transform .14s ease,box-shadow .14s ease;
}

#farmersMapModule .fd-colour-chip:hover{
  transform:translateY(-2px) scale(1.04);
}

#farmersMapModule .fd-random-btn{
  margin-left:auto;
}

/* Advanced details */

#farmersMapModule .fd-advanced{
  margin-top:12px;
  overflow:hidden;
  border:1px solid var(--fd-border);
  border-radius:14px;
  background:#fff;
}

#farmersMapModule .fd-advanced summary{
  display:flex;
  align-items:center;
  gap:8px;
  padding:11px 12px;
  color:var(--fd-text);
  font-size:10px;
  font-weight:950;
  cursor:pointer;
  list-style:none;
}

#farmersMapModule .fd-advanced summary::-webkit-details-marker{
  display:none;
}

#farmersMapModule .fd-advanced summary::after{
  content:"+";
  margin-left:auto;
  color:var(--fd-muted);
  font-size:16px;
}

#farmersMapModule .fd-advanced[open] summary::after{
  content:"−";
}

#farmersMapModule .fd-advanced-body{
  padding:0 12px 12px;
}

/* Plot list */

#farmersMapModule .fd-plot-list{
  display:flex;
  flex-direction:column;
  gap:8px;
  margin-top:12px;
}

#farmersMapModule .fd-plot-list .map-plot-item{
  border-radius:14px;
}

#farmersMapModule .fd-empty{
  display:grid;
  place-items:center;
  min-height:110px;
  padding:17px;
  border:1px dashed #cbd5e1;
  border-radius:15px;
  color:var(--fd-muted);
  background:linear-gradient(145deg,#fff,#f8fafc);
  text-align:center;
  font-size:10px;
  font-weight:800;
  line-height:1.5;
}

#farmersMapModule .fd-empty-icon{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  margin:0 auto 8px;
  border-radius:13px;
  color:var(--fd-green-dark);
  background:var(--fd-green-soft);
}

#farmersMapModule .fd-empty-icon svg{
  width:19px;
  height:19px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
}

@media (max-width:1200px){
  #farmersMapModule .farmer-details-panel{
    height:auto;
    overflow:visible;
    border-left:0;
  }
}

@media (max-width:620px){
  #farmersMapModule .fd-info-grid,
  #farmersMapModule .fd-metrics,
  #farmersMapModule .fd-plot-summary{
    grid-template-columns:1fr;
  }

  #farmersMapModule .fd-info-item.is-wide{
    grid-column:auto;
  }

  #farmersMapModule .fd-profile-actions{
    grid-template-columns:1fr;
  }

  #farmersMapModule .fd-focus-btn{
    width:100%;
  }
}
</style>
@endpush

<div class="farmers-map-wrap {{ ($canManageOperations ?? auth()->user()->canManageOperationalData()) ? '' : 'is-readonly' }}" id="farmersMapModule">
  <header class="parcel-workspace-header">
    <div>
      <div class="parcel-title-row">
        <span class="parcel-kicker">Land management</span>
        <span class="parcel-mode-badge" id="plotModeBadge" style="display:none;">Boundary drawing active</span>
      </div>
      <h2>Parcel mapping workspace</h2>
      <p>@if($canManageOperations ?? auth()->user()->canManageOperationalData())Select a farmer, review existing parcels, then draw or import a verified farm boundary.@elseSelect a farmer to review existing parcels and export printable plot information.@endif</p>
    </div>

    <div class="parcel-load-status" aria-live="polite">
      <div class="parcel-load-copy">
        <strong id="mapStatus">Loading map…</strong>
        <span id="mapStatusSmall">Preparing farmer locations</span>
      </div>
      <div class="map-progress" aria-hidden="true">
        <div class="map-progress-bar" id="mapProgressBar" style="width:0%;"></div>
      </div>
      <span class="parcel-geocoded" id="mapGeocodedPill">0 geocoded</span>
    </div>
  </header>

  <div class="parcel-command-bar">
    <div class="parcel-farmer-picker">
      <label for="mapFarmerSearch">Find farmer on this page</label>
      <div class="parcel-search-row">
        <input
          class="parcel-search-input"
          id="mapFarmerSearch"
          type="search"
          list="mapFarmerOptions"
          placeholder="Name, FFRS, or farm location"
          autocomplete="off"
        >
        <datalist id="mapFarmerOptions">
          @foreach($farmersMapData as $farmerOption)
            @php
              $farmerOptionName = trim(collect([
                  $farmerOption['first_name'] ?? null,
                  $farmerOption['middle_name'] ?? null,
                  $farmerOption['last_name'] ?? null,
                  $farmerOption['ext_name'] ?? null,
              ])->filter()->join(' '));
            @endphp
            <option value="{{ $farmerOptionName }} — {{ $farmerOption['ffrs'] ?: 'No FFRS' }}"></option>
          @endforeach
        </datalist>
        <button type="button" class="btn btn-soft btn-sm" id="mapFarmerLocateBtn" disabled>Locate</button>
      </div>
      <small id="mapPickerHelp">{{ number_format(count($farmersMapData)) }} farmers available from the current table page.</small>
    </div>

    <div class="parcel-tool-group">
      <span class="parcel-tool-label">Map view</span>
      <div class="parcel-tool-actions">
        <button type="button" class="btn btn-soft btn-sm" id="recenterMapBtn" title="Fit the camera to visible farmers">Fit results</button>
        <button type="button" class="btn btn-soft btn-sm" id="resetMapBtn" title="Return to the province view">Reset view</button>
        <label class="map-toggle"><input type="checkbox" id="toggleMarkers" checked><span>Farmers</span></label>
        <label class="map-toggle"><input type="checkbox" id="togglePlots" checked><span>Parcels</span></label>
      </div>
    </div>

    <div class="parcel-tool-group parcel-tool-group-primary">
      <span class="parcel-tool-label">Selected farmer actions</span>
      <div class="parcel-tool-actions">
        <button type="button" class="btn btn-primary btn-sm parcel-requires-selection operational-write-control" id="plotModeBtn" title="Select a farmer before drawing" disabled>
          Draw boundary
        </button>
        <button type="button" class="btn btn-soft btn-sm parcel-requires-selection operational-write-control" id="importKmzBtn" title="Import KML or KMZ for the selected farmer" disabled>Import KML/KMZ</button>
        <input class="operational-write-control" type="file" id="kmzFileInput" accept=".kmz,.kml,.xml" hidden>
        <button type="button" class="btn btn-soft btn-sm parcel-requires-selection" id="downloadAllPlotsBtn" title="Download parcel maps for the selected farmer" disabled>Export parcels</button>
        <button type="button" class="btn btn-soft btn-sm operational-write-control" id="plotClearBtn" style="display:none;" title="Remove every point from this draft">Clear points</button>
        <button type="button" class="btn btn-soft btn-sm operational-write-control" id="plotDeleteCornerBtn" style="display:none;" title="Delete the selected boundary point">Delete point</button>
        <button type="button" class="btn btn-primary btn-sm operational-write-control" id="plotSaveBtn" style="display:none;" title="Save plot (Enter)">Save boundary</button>
        <button type="button" class="btn btn-danger btn-sm operational-write-control" id="plotCancelBtn" style="display:none;" title="Cancel plotting (Esc)">Cancel</button>
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
        <div class="map-hint-text">Use the farmer finder, click a map marker, or select a row in the directory above.</div>
      </div>

      <div class="parcel-map-legend" aria-label="Map legend">
        <span><i class="parcel-legend-dot"></i> Farmer</span>
        <span><i class="parcel-legend-line"></i> Saved parcel</span>
        <span><i class="parcel-legend-line parcel-legend-draft"></i> Draft</span>
      </div>

      <div class="map-toast" id="mapToast" role="status" aria-live="polite"></div>
    </div>

{{-- ENHANCED FARMER DETAILS SIDE PANEL --}}
<aside
  class="map-panel farmer-details-panel"
  id="mapPanel"
  aria-label="Selected farmer details"
>
  <div class="farmer-details-inner">

    {{-- Sticky header --}}
    <header class="farmer-details-header">
      <div class="farmer-details-heading">
        <div class="farmer-details-heading-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M4 21a8 8 0 0 1 16 0"></path>
          </svg>
        </div>

        <div>
          <h3>Farmer Details</h3>
          <p>Profile, distribution, and land information</p>
        </div>
      </div>

      <button
        type="button"
        class="btn btn-soft btn-sm farmer-close-btn parcel-requires-selection"
        id="clearSelectionBtn"
        title="Deselect farmer"
        disabled
      >
        Close
      </button>
    </header>

    <div class="farmer-details-body">

      {{-- Farmer profile --}}
      <section class="fd-card fd-profile-card">
        <div class="fd-profile-main">
          <div
            class="fd-avatar"
            id="selAvatar"
            aria-hidden="true"
          >
            —
          </div>

          <div>
            <div class="fd-profile-label">
              <span class="fd-profile-label-dot"></span>
              Farmer selection
            </div>

            <h3
              class="fd-profile-name"
              id="selName"
              aria-live="polite"
            >
              No farmer selected
            </h3>

            <span
              class="fd-profile-ffrs"
              id="selFfrs"
            >
              —
            </span>
          </div>
        </div>

        <div class="fd-location">
          <div class="fd-location-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path>
              <circle cx="12" cy="10" r="2.5"></circle>
            </svg>
          </div>

          <div>
            <div class="fd-location-label">Farm location</div>
            <div
              class="fd-location-value"
              id="selLocation"
            >
              Select a map marker or farmer row
            </div>
          </div>
        </div>

        <div class="fd-profile-actions">
          <a
            href="#"
            class="btn btn-sm fd-view-btn parcel-requires-selection-link"
            id="viewRecordsBtn"
            style="text-decoration:none;"
            aria-disabled="true"
            tabindex="-1"
          >
            <svg
              class="fd-action-icon"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path d="M4 5h16v14H4z"></path>
              <path d="M8 9h8"></path>
              <path d="M8 13h5"></path>
            </svg>

            View Full Profile
          </a>

          <button
            type="button"
            class="btn btn-soft btn-sm fd-focus-btn parcel-requires-selection"
            id="focusSelectedBtn"
            title="Centre map on selected farmer"
            disabled
          >
            <svg
              class="fd-action-icon"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M12 2v3"></path>
              <path d="M12 19v3"></path>
              <path d="M2 12h3"></path>
              <path d="M19 12h3"></path>
            </svg>

            Focus
          </button>
        </div>
      </section>

      {{-- Owner and farm details --}}
      <section class="fd-card">
        <div class="fd-card-body">
          <div class="fd-section-head">
            <div class="fd-section-title-wrap">
              <div class="fd-section-icon">
                <svg viewBox="0 0 24 24">
                  <circle cx="12" cy="8" r="4"></circle>
                  <path d="M4 21a8 8 0 0 1 16 0"></path>
                </svg>
              </div>

              <div>
                <h4 class="fd-section-title">Owner and Farm Information</h4>
                <p class="fd-section-subtitle">
                  Registered ownership and location details
                </p>
              </div>
            </div>
          </div>

          <div class="fd-info-grid">
            <div class="fd-info-item is-wide">
              <div class="fd-info-label">Owner</div>
              <div class="fd-info-value" id="selOwnerName">—</div>
            </div>

            <div class="fd-info-item">
              <div class="fd-info-label">FFRS</div>
              <div class="fd-info-value" id="selOwnerFfrs">—</div>
            </div>

            <div class="fd-info-item">
              <div class="fd-info-label">Farm Area</div>
              <div class="fd-info-value" id="selOwnerFarmArea">—</div>
            </div>

            <div class="fd-info-item">
              <div class="fd-info-label">Barangay</div>
              <div class="fd-info-value" id="selOwnerBarangay">—</div>
            </div>

            <div class="fd-info-item">
              <div class="fd-info-label">Municipality</div>
              <div class="fd-info-value" id="selOwnerMunicipality">—</div>
            </div>

            <div class="fd-info-item is-wide">
              <div class="fd-info-label">Province</div>
              <div class="fd-info-value" id="selOwnerProvince">—</div>
            </div>
          </div>
        </div>
      </section>

      {{-- Distribution metrics --}}
      <section class="fd-card">
        <div class="fd-card-body">
          <div class="fd-section-head">
            <div class="fd-section-title-wrap">
              <div class="fd-section-icon blue">
                <svg viewBox="0 0 24 24">
                  <path d="M4 19V9"></path>
                  <path d="M10 19V5"></path>
                  <path d="M16 19v-7"></path>
                  <path d="M22 19H2"></path>
                </svg>
              </div>

              <div>
                <h4 class="fd-section-title">Distribution Summary</h4>
                <p class="fd-section-subtitle">
                  Rice seed records linked to this farmer
                </p>
              </div>
            </div>
          </div>

          <div class="fd-metrics">
            <article class="fd-metric green">
              <div class="fd-metric-label">Distribution Records</div>
              <div class="fd-metric-value" id="selRecords">0</div>
            </article>

            <article class="fd-metric blue">
              <div class="fd-metric-label">Total Seed Received</div>
              <div class="fd-metric-value">
                <span id="selKgs">0.00</span>
                <span style="font-size:10px;">kg</span>
              </div>
            </article>
          </div>
        </div>
      </section>

      {{-- Land plot management --}}
      <section class="fd-card">
        <div class="fd-card-body">
          <div class="fd-section-head">
            <div class="fd-section-title-wrap">
              <div class="fd-section-icon amber">
                <svg viewBox="0 0 24 24">
                  <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path>
                  <path d="M9 3v15"></path>
                  <path d="M15 6v15"></path>
                </svg>
              </div>

              <div>
                <h4 class="fd-section-title">Land Plots</h4>
                <p class="fd-section-subtitle">
                  Saved boundaries and mapped farm area
                </p>
              </div>
            </div>
          </div>

          <div class="fd-plot-summary" id="plotMeta">
            <div class="fd-plot-summary-item">
              <div class="fd-plot-summary-label">Saved Plots</div>
              <div
                class="fd-plot-summary-value"
                id="plotCountPill"
              >
                0 plots
              </div>
            </div>

            <div class="fd-plot-summary-item">
              <div class="fd-plot-summary-label">Total Mapped Area</div>
              <div
                class="fd-plot-summary-value"
                id="plotAreaTotalPill"
              >
                0.00 ha total
              </div>
            </div>
          </div>

          {{-- Draft status --}}
          <div
            class="map-plot-draft fd-draft-box operational-write-control"
            id="plotDraftInfo"
            style="display:none;"
          >
            <div class="fd-draft-title">
              Plotting in progress
            </div>

            <div class="fd-draft-grid">
              <div class="fd-draft-stat">
                <span>Estimated area</span>
                <strong>
                  <span id="plotDraftArea">0.00</span> ha
                </strong>
              </div>

              <div class="fd-draft-stat">
                <span>Boundary points</span>
                <strong id="plotDraftPoints">0</strong>
              </div>
            </div>

            <div class="fd-help-text" style="color:#92400e;">
              Click a corner to select it. Click the map to reposition it.
              Press Enter to save.
            </div>
          </div>

          {{-- Plot name --}}
          <div class="fd-field operational-write-control" style="margin-top:12px;">
            <label
              class="fd-label"
              for="plotNameInput"
            >
              Plot name
            </label>

            <input
              id="plotNameInput"
              class="map-input fd-input"
              type="text"
              maxlength="120"
              placeholder="Example: North Field"
              autocomplete="off"
            >

            <div class="fd-help-text">
              Use a clear name that staff can easily recognise.
            </div>
          </div>

          {{-- Advanced plot appearance --}}
          <details class="fd-advanced operational-write-control">
            <summary>
              Plot colour and appearance
            </summary>

            <div class="fd-advanced-body">
              <div class="fd-field">
                <label
                  class="fd-label"
                  for="plotColorInput"
                >
                  Boundary colour
                </label>

                <div class="fd-colour-row">
                  <input
                    id="plotColorInput"
                    class="map-color fd-colour-input"
                    type="color"
                    value="#3b82f6"
                    title="Choose plot colour"
                  >

                  <input
                    id="plotColorHex"
                    class="map-color-hex fd-colour-hex"
                    type="text"
                    value="#3b82f6"
                    maxlength="16"
                    spellcheck="false"
                  >
                </div>

                <div
                  class="map-color-presets fd-colour-presets"
                  id="plotColorPresets"
                >
                  <button
                    type="button"
                    class="fd-colour-chip"
                    data-color="#3b82f6"
                    style="--colour:#3b82f6;"
                    title="Blue"
                  ></button>

                  <button
                    type="button"
                    class="fd-colour-chip"
                    data-color="#22c55e"
                    style="--colour:#22c55e;"
                    title="Green"
                  ></button>

                  <button
                    type="button"
                    class="fd-colour-chip"
                    data-color="#f97316"
                    style="--colour:#f97316;"
                    title="Orange"
                  ></button>

                  <button
                    type="button"
                    class="fd-colour-chip"
                    data-color="#e11d48"
                    style="--colour:#e11d48;"
                    title="Red"
                  ></button>

                  <button
                    type="button"
                    class="btn btn-soft btn-sm fd-random-btn"
                    id="plotColorRandomBtn"
                  >
                    Random colour
                  </button>
                </div>
              </div>
            </div>
          </details>

          {{-- Saved plot list --}}
          <div
            class="map-plot-list fd-plot-list"
            id="plotList"
          >
            <div class="map-empty fd-empty">
              <div>
                <div class="fd-empty-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24">
                    <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path>
                    <path d="M9 3v15"></path>
                    <path d="M15 6v15"></path>
                  </svg>
                </div>

                <strong>No saved plots yet</strong><br>
                Select a farmer and click <b>Draw boundary</b> above to begin.
              </div>
            </div>
          </div>
        </div>
      </section>

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
  window.__allFarmPlotsUrl = "{{ route('farm-plots.all') }}";
  window.__farmerMapCardUrlTemplate = "{{ url('/farmers/__ID__/map-card') }}";
  window.__farmerGeocodeUrl = "{{ route('geocode') }}";
  window.__canManageOperationalData = @json($canManageOperations ?? auth()->user()->canManageOperationalData());
</script>
@include('farmers.partials.maps-styles')
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
@include('farmers.partials.maps-scripts')

@push('styles')
<style>
  #farmersMapModule.is-readonly .operational-write-control{display:none!important}
  #farmersMapModule {
    --parcel-ink: #17211b;
    --parcel-muted: #66736b;
    --parcel-border: #dfe6e1;
    --parcel-green: #17643a;
    overflow: hidden;
    border: 1px solid var(--parcel-border);
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(20, 40, 27, .035);
  }

  #farmersMapModule .parcel-workspace-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 18px 20px 16px;
    border-bottom: 1px solid var(--parcel-border);
    background: #ffffff;
  }

  #farmersMapModule .parcel-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 22px;
  }

  #farmersMapModule .parcel-kicker {
    color: var(--parcel-green);
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .06em;
    text-transform: uppercase;
  }

  #farmersMapModule .parcel-mode-badge {
    padding: 4px 7px;
    border-radius: 5px;
    color: #8a5707;
    background: #faf1dc;
    font-size: 9px;
    font-weight: 850;
    letter-spacing: .03em;
    text-transform: uppercase;
  }

  #farmersMapModule .parcel-workspace-header h2 {
    margin: 5px 0 4px;
    color: var(--parcel-ink);
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -.025em;
  }

  #farmersMapModule .parcel-workspace-header p {
    margin: 0;
    color: var(--parcel-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  #farmersMapModule .parcel-load-status {
    width: min(310px, 100%);
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 7px 10px;
    align-items: center;
  }

  #farmersMapModule .parcel-load-copy {
    min-width: 0;
  }

  #farmersMapModule .parcel-load-copy strong,
  #farmersMapModule .parcel-load-copy span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  #farmersMapModule .parcel-load-copy strong {
    color: var(--parcel-ink) !important;
    background: none !important;
    border: 0 !important;
    box-shadow: none !important;
    font-size: 11px;
    font-weight: 800;
  }

  #farmersMapModule .parcel-load-copy span {
    margin-top: 2px;
    color: var(--parcel-muted) !important;
    text-shadow: none !important;
    font-size: 10px !important;
    font-weight: 650 !important;
  }

  #farmersMapModule .parcel-geocoded {
    grid-column: 2;
    grid-row: 1 / 3;
    padding: 6px 8px;
    border: 1px solid var(--parcel-border) !important;
    border-radius: 6px;
    color: #405047 !important;
    background: #f5f7f5 !important;
    box-shadow: none !important;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
  }

  #farmersMapModule .parcel-load-status .map-progress {
    width: 100%;
    height: 4px;
    overflow: hidden;
    border: 0;
    border-radius: 999px;
    background: #e8ede9;
  }

  #farmersMapModule .parcel-load-status .map-progress-bar {
    height: 100%;
    border-radius: inherit;
    background: var(--parcel-green);
    transition: width .2s ease;
  }

  #farmersMapModule .parcel-command-bar {
    display: grid;
    grid-template-columns: minmax(260px, 1.15fr) minmax(250px, auto) minmax(360px, 1.4fr);
    align-items: stretch;
    border-bottom: 1px solid var(--parcel-border);
    background: #f7f9f7;
  }

  #farmersMapModule .parcel-farmer-picker,
  #farmersMapModule .parcel-tool-group {
    min-width: 0;
    padding: 12px 14px;
    border-right: 1px solid var(--parcel-border);
  }

  #farmersMapModule .parcel-tool-group:last-child {
    border-right: 0;
  }

  #farmersMapModule .parcel-farmer-picker label,
  #farmersMapModule .parcel-tool-label {
    display: block;
    margin-bottom: 6px;
    color: #4f5e55;
    font-size: 9px;
    font-weight: 850;
    letter-spacing: .055em;
    text-transform: uppercase;
  }

  #farmersMapModule .parcel-search-row,
  #farmersMapModule .parcel-tool-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }

  #farmersMapModule .parcel-search-row {
    flex-wrap: nowrap;
  }

  #farmersMapModule .parcel-search-input {
    width: 100%;
    min-width: 0;
    height: 34px;
    padding: 7px 10px;
    border: 1px solid #cfd8d2;
    border-radius: 7px;
    outline: none;
    color: var(--parcel-ink);
    background: #ffffff;
    font-size: 11px;
    font-weight: 650;
  }

  #farmersMapModule .parcel-search-input:focus {
    border-color: #669277;
    box-shadow: 0 0 0 3px rgba(23, 100, 58, .09);
  }

  #farmersMapModule .parcel-farmer-picker small {
    display: block;
    margin-top: 5px;
    color: var(--parcel-muted);
    font-size: 9px;
    line-height: 1.35;
  }

  #farmersMapModule .parcel-command-bar .btn {
    min-height: 34px;
    padding: 7px 10px;
    border-radius: 7px;
    box-shadow: none;
    font-size: 10px;
    font-weight: 800;
  }

  #farmersMapModule .parcel-command-bar .btn-primary {
    border-color: var(--parcel-green) !important;
    background: var(--parcel-green) !important;
    color: #ffffff !important;
  }

  #farmersMapModule .parcel-command-bar .btn:disabled {
    cursor: not-allowed;
    opacity: .48;
    filter: grayscale(.15);
  }

  #farmersMapModule .parcel-requires-selection:disabled {
    cursor: not-allowed;
    pointer-events: none;
    opacity: .48;
    filter: grayscale(.15);
  }

  #farmersMapModule .map-toggle {
    min-height: 34px;
    padding: 6px 9px;
    border-color: #cfd8d2;
    border-radius: 7px;
    background: #ffffff;
    font-size: 10px;
    font-weight: 750;
  }

  #farmersMapModule .map-toggle input {
    accent-color: var(--parcel-green);
  }

  #farmersMapModule .farmers-map-main {
    grid-template-columns: minmax(0, 1fr) 405px;
    min-height: 640px;
  }

  #farmersMapModule .farmers-map-stage,
  #farmersMapModule .farmers-map {
    min-height: 640px;
    height: 640px;
  }

  #farmersMapModule .farmers-map-stage {
    border-right: 1px solid var(--parcel-border);
  }

  #farmersMapModule .farmer-details-panel {
    height: 640px;
    min-height: 0;
    padding: 0;
    overflow-y: auto;
    background: #f7f9f7;
  }

  #farmersMapModule .farmer-details-header {
    top: 0;
    z-index: 5;
    border-bottom-color: var(--parcel-border);
    background: rgba(255, 255, 255, .97);
    backdrop-filter: blur(8px);
  }

  #farmersMapModule .farmer-details-body {
    gap: 10px;
    padding: 10px;
  }

  #farmersMapModule .fd-card {
    border-color: var(--parcel-border);
    border-radius: 9px;
    box-shadow: none;
  }

  #farmersMapModule .fd-card-body,
  #farmersMapModule .fd-profile-card {
    padding: 13px;
  }

  #farmersMapModule:not(.has-farmer-selection) .fd-profile-card {
    background: #ffffff;
  }

  #farmersMapModule:not(.has-farmer-selection) .fd-avatar {
    color: #66736b;
    background: #edf1ee;
  }

  #farmersMapModule .parcel-requires-selection-link[aria-disabled="true"] {
    pointer-events: none;
    opacity: .48;
    filter: grayscale(.15);
  }

  #farmersMapModule .map-hint {
    top: 14px;
    left: 14px;
    width: 285px;
    max-width: calc(100% - 28px);
    padding: 11px 13px;
    border-radius: 8px;
    background: rgba(24, 34, 28, .92);
    box-shadow: 0 5px 18px rgba(14, 24, 17, .18);
  }

  #farmersMapModule .map-hint-title {
    font-size: 12px;
    font-weight: 800;
  }

  #farmersMapModule .map-hint-text {
    font-size: 10px;
    font-weight: 600;
  }

  #farmersMapModule .parcel-map-legend {
    position: absolute;
    right: 14px;
    bottom: 14px;
    z-index: 25;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    border: 1px solid rgba(23, 33, 27, .14);
    border-radius: 7px;
    color: #405047;
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 4px 14px rgba(23, 33, 27, .09);
    backdrop-filter: blur(6px);
    font-size: 9px;
    font-weight: 750;
  }

  #farmersMapModule .parcel-map-legend span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  #farmersMapModule .parcel-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, .16);
  }

  #farmersMapModule .parcel-legend-line {
    width: 16px;
    height: 3px;
    border-radius: 999px;
    background: #22c55e;
  }

  #farmersMapModule .parcel-legend-draft {
    background: #2563eb;
  }

  @media (max-width: 1250px) {
    #farmersMapModule .parcel-command-bar {
      grid-template-columns: 1fr 1fr;
    }

    #farmersMapModule .parcel-farmer-picker {
      grid-column: 1 / -1;
      border-bottom: 1px solid var(--parcel-border);
    }
  }

  @media (max-width: 1080px) {
    #farmersMapModule .farmers-map-main {
      grid-template-columns: 1fr;
    }

    #farmersMapModule .farmers-map-stage,
    #farmersMapModule .farmers-map {
      min-height: 500px;
      height: 500px;
    }

    #farmersMapModule .farmer-details-panel {
      height: auto;
      max-height: none;
    }
  }

  @media (max-width: 720px) {
    #farmersMapModule .parcel-workspace-header {
      flex-direction: column;
      padding: 15px;
    }

    #farmersMapModule .parcel-load-status {
      width: 100%;
    }

    #farmersMapModule .parcel-command-bar {
      grid-template-columns: 1fr;
    }

    #farmersMapModule .parcel-farmer-picker,
    #farmersMapModule .parcel-tool-group {
      grid-column: auto;
      border-right: 0;
      border-bottom: 1px solid var(--parcel-border);
    }

    #farmersMapModule .parcel-tool-actions .btn,
    #farmersMapModule .parcel-tool-actions .map-toggle {
      flex: 1 1 auto;
    }

    #farmersMapModule .farmers-map-stage,
    #farmersMapModule .farmers-map {
      min-height: 410px;
      height: 410px;
    }

    #farmersMapModule .parcel-map-legend {
      display: none;
    }
  }
</style>
@endpush

<script>
  (() => {
    const input = document.getElementById('mapFarmerSearch');
    const locateButton = document.getElementById('mapFarmerLocateBtn');
    const pickerHelp = document.getElementById('mapPickerHelp');
    const selectedName = document.getElementById('selName');
    const data = Array.isArray(window.__farmersMapData) ? window.__farmersMapData : [];

    if (!input || !locateButton) return;

    const clean = value => String(value || '').trim().replace(/\s+/g, ' ');
    const entries = data.map(farmer => {
      const name = clean([
        farmer.first_name,
        farmer.middle_name,
        farmer.last_name,
        farmer.ext_name
      ].filter(Boolean).join(' '));
      const ffrs = clean(farmer.ffrs) || 'No FFRS';
      const location = clean(farmer.farm_location || farmer.location);

      return {
        id: String(farmer.id),
        name,
        label: `${name} — ${ffrs}`,
        search: clean(`${name} ${ffrs} ${location}`).toLowerCase()
      };
    });

    function matchesFor(value) {
      const query = clean(value).toLowerCase();
      if (!query) return [];

      const exact = entries.filter(entry => entry.label.toLowerCase() === query);
      if (exact.length) return exact;

      return entries.filter(entry => entry.search.includes(query));
    }

    function syncLocateState() {
      const matches = matchesFor(input.value);
      locateButton.disabled = matches.length !== 1;

      if (!clean(input.value)) {
        pickerHelp.textContent = `${entries.length.toLocaleString()} farmers available from the current table page.`;
      } else if (matches.length === 1) {
        pickerHelp.textContent = `Ready to locate ${matches[0].name}.`;
      } else if (matches.length > 1) {
        pickerHelp.textContent = `${matches.length} matches. Enter more of the name or FFRS.`;
      } else {
        pickerHelp.textContent = 'No match on this page. Use the farmer search above the directory to load another result.';
      }
    }

    function locateFarmer() {
      const matches = matchesFor(input.value);
      if (matches.length !== 1) {
        syncLocateState();
        return;
      }

      if (typeof window.__openFarmer3d !== 'function') {
        if (typeof window.__mapToast === 'function') {
          window.__mapToast('The map is still loading. Try again in a moment.', 'warn');
        }
        return;
      }

      input.value = matches[0].label;
      window.__openFarmer3d(matches[0].id, { showMarker: false });
      syncLocateState();
    }

    function syncSelectionActions() {
      const value = clean(selectedName ? selectedName.textContent : '');
      const hasSelection = value !== '' && value !== '—' && value !== 'No farmer selected';

      document.getElementById('farmersMapModule')?.classList.toggle('has-farmer-selection', hasSelection);

      document.querySelectorAll('#farmersMapModule .parcel-requires-selection').forEach(button => {
        button.disabled = !hasSelection;
        button.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
      });

      document.querySelectorAll('#farmersMapModule .parcel-requires-selection-link').forEach(link => {
        link.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
        link.setAttribute('tabindex', hasSelection ? '0' : '-1');
      });
    }

    input.addEventListener('input', syncLocateState);
    input.addEventListener('change', syncLocateState);
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        locateFarmer();
      }
    });
    locateButton.addEventListener('click', locateFarmer);

    if (selectedName) {
      new MutationObserver(syncSelectionActions).observe(selectedName, {
        childList: true,
        characterData: true,
        subtree: true
      });
    }

    document.addEventListener('keydown', event => {
      if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
      const target = event.target;
      if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;
      event.preventDefault();
      input.focus();
    });

    syncLocateState();
    syncSelectionActions();
  })();
</script>
