@extends('layouts.app')

@section('title', 'Municipality Geofences')

@php
  $canChooseMunicipality = auth()->user()->canAccessAllMunicipalities();
  $assignedMunicipality = $canChooseMunicipality ? null : $municipalities->first();
@endphp

@push('styles')
<style>
  .geo-page{min-width:0;color:#17211b}.geo-hero{position:relative;overflow:hidden;padding:22px;border:1px solid #dce6df;border-radius:18px;background:linear-gradient(120deg,#fff8cf 0,#f6fbf5 48%,#e6f7e9 100%)}
  .geo-hero:after{content:"";position:absolute;right:-70px;top:-95px;width:260px;height:260px;border-radius:50%;background:rgba(34,197,94,.12)}.geo-eyebrow{display:flex;align-items:center;gap:7px;color:#08713d;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:none}.geo-eyebrow i{width:9px;height:9px;border-radius:50%;background:#2eb768;box-shadow:0 0 0 5px rgba(46,183,104,.12)}
  .geo-title-row{position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.geo-title{margin:7px 0 4px;font-size:clamp(27px,3vw,40px);line-height:1;font-weight:700;letter-spacing:-.035em}.geo-subtitle{max-width:790px;margin:0;color:#5b6a61;font-size:12px;line-height:1.55}.geo-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}
  .geo-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:38px;padding:8px 13px;border:1px solid #d7e1da;border-radius:10px;background:#fff;color:#203128;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}.geo-btn:hover{transform:translateY(-1px);border-color:#99bca5;box-shadow:0 6px 16px rgba(18,77,43,.08)}.geo-btn.primary{border-color:#176d3e;background:#176d3e;color:#fff}.geo-btn.warn{border-color:#f0c8a0;color:#9a4d00;background:#fff8ef}.geo-btn.danger{border-color:#efc1bd;color:#a82820;background:#fff6f5}.geo-btn:disabled{opacity:.48;cursor:not-allowed;transform:none}
  .geo-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:13px 0}.geo-stat{min-width:0;padding:13px;border:1px solid #dce5df;border-radius:13px;background:#fff}.geo-stat small{display:block;color:#65736b;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:none}.geo-stat strong{display:block;margin-top:5px;font-size:20px;line-height:1;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.geo-stat span{display:block;margin-top:5px;color:#738078;font-size:12px}
  .geo-workspace{overflow:hidden;border:1px solid #dbe5de;border-radius:16px;background:#fff;box-shadow:0 10px 30px rgba(24,64,39,.05)}.geo-toolbar{display:flex;align-items:end;gap:9px;padding:12px;border-bottom:1px solid #e0e8e2;background:#fbfdfb}.geo-field{min-width:0;flex:1}.geo-field label{display:block;margin:0 0 5px;color:#526158;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:none}.geo-field select,.geo-field input{width:100%;height:38px;padding:0 11px;border:1px solid #d5dfd8;border-radius:9px;background:#fff;color:#1c2b22;font-size:12px;font-weight:700;outline:none}.geo-field select:focus,.geo-field input:focus{border-color:#49a46d;box-shadow:0 0 0 3px rgba(34,197,94,.12)}.geo-search{max-width:330px}.geo-select{max-width:310px}
  .geo-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;min-height:650px}.geo-map-wrap{position:relative;min-width:0;background:#e8eef2}.geo-map{width:100%;height:650px}.geo-map-message{position:absolute;z-index:4;left:16px;top:16px;max-width:330px;padding:11px 13px;border-radius:10px;background:rgba(20,39,29,.92);color:#fff;font-size:12px;line-height:1.5;box-shadow:0 8px 22px rgba(0,0,0,.18)}.geo-map-legend{position:absolute;z-index:3;left:14px;bottom:14px;display:flex;gap:11px;padding:8px 10px;border:1px solid #dfe5e0;border-radius:9px;background:rgba(255,255,255,.94);font-size:12px;font-weight:700}.geo-map-legend span{display:flex;align-items:center;gap:5px}.geo-map-legend i{width:13px;height:7px;border-radius:2px}.geo-map-legend .active{background:#15803d}.geo-map-legend .draft{border:2px dashed #d68b16;background:#fff3d8}.geo-map-legend .parcel{background:#2563eb}
  .geo-panel{min-width:0;border-left:1px solid #dde6df;background:#fbfdfb}.geo-panel-head{padding:16px;border-bottom:1px solid #e0e8e2;background:#fff}.geo-panel-head small{color:#08713d;font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:none}.geo-panel-head h2{margin:4px 0 2px;font-size:19px;font-weight:700}.geo-panel-head p{margin:0;color:#6a776f;font-size:12px;line-height:1.45}.geo-panel-scroll{height:560px;overflow:auto;padding:12px}.geo-empty{padding:24px 15px;border:1px dashed #cfdcd3;border-radius:12px;text-align:center;color:#68756d;font-size:12px;line-height:1.55;background:#fff}.geo-mini-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-bottom:12px}.geo-mini{padding:10px;border:1px solid #dfe7e1;border-radius:10px;background:#fff}.geo-mini span{display:block;color:#68766e;font-size:12px;font-weight:700;text-transform:none}.geo-mini strong{display:block;margin-top:3px;font-size:15px;font-weight:700}.geo-section-title{display:flex;align-items:center;justify-content:space-between;margin:14px 0 7px;font-size:12px;font-weight:700}.geo-boundary-card,.geo-review-card{padding:10px;border:1px solid #dce5df;border-radius:11px;background:#fff;margin-bottom:7px}.geo-boundary-card.active{border-color:#8ac9a2;box-shadow:inset 3px 0 #19824a}.geo-boundary-top{display:flex;align-items:flex-start;justify-content:space-between;gap:7px}.geo-boundary-card strong{font-size:12px}.geo-badge{display:inline-flex;padding:4px 6px;border-radius:999px;background:#edf3ef;color:#56645b;font-size:12px;font-weight:700;text-transform:none}.geo-badge.active{background:#e2f5e9;color:#08713d}.geo-badge.draft{background:#fff1d7;color:#986000}.geo-boundary-meta{margin-top:6px;color:#69776f;font-size:12px}.geo-card-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.geo-card-actions .geo-btn{min-height:29px;padding:5px 8px;font-size:12px}.geo-review-card{cursor:pointer}.geo-review-card:hover{border-color:#9db5a5}.geo-review-top{display:flex;justify-content:space-between;gap:8px}.geo-review-card strong{font-size:12px}.geo-review-card p{margin:5px 0 0;color:#6a776f;font-size:12px}.geo-review-status{font-size:12px;font-weight:700;text-transform:none}.geo-review-status.outside,.geo-review-status.invalid{color:#b42318}.geo-review-status.partial{color:#b15b00}.geo-review-status.near_boundary{color:#9b7100}
  .geo-editor{position:absolute;z-index:5;right:14px;top:14px;width:min(360px,calc(100% - 28px));padding:13px;border:1px solid #cbd8cf;border-radius:13px;background:rgba(255,255,255,.97);box-shadow:0 16px 42px rgba(0,0,0,.18)}.geo-editor[hidden]{display:none}.geo-editor h3{margin:0;font-size:14px;font-weight:700}.geo-editor p{margin:4px 0 10px;color:#65736a;font-size:12px;line-height:1.45}.geo-editor-grid{display:grid;grid-template-columns:1fr 95px;gap:7px}.geo-editor .geo-field{margin-bottom:8px}.geo-editor-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}.geo-check{display:flex;align-items:flex-start;gap:7px;margin:8px 0;color:#536159;font-size:12px;line-height:1.45}.geo-check input{margin-top:1px}.geo-draw-state{display:flex;align-items:center;gap:6px;padding:7px 9px;border-radius:8px;background:#eef7f1;color:#176d3e;font-size:12px;font-weight:700}
  .geo-dialog{width:min(480px,calc(100% - 28px));padding:0;border:0;border-radius:16px;box-shadow:0 25px 70px rgba(0,0,0,.28)}.geo-dialog::backdrop{background:rgba(11,27,18,.52)}.geo-dialog-head{display:flex;align-items:flex-start;justify-content:space-between;padding:16px;border-bottom:1px solid #e0e7e2}.geo-dialog-head h3{margin:0;font-size:17px;font-weight:700}.geo-dialog-head p{margin:4px 0 0;color:#65736b;font-size:12px}.geo-dialog-body{padding:16px}.geo-dialog-grid{display:grid;grid-template-columns:1fr 120px;gap:10px}.geo-dialog-actions{display:flex;justify-content:flex-end;gap:7px;padding:12px 16px;border-top:1px solid #e0e7e2;background:#f9fbf9}.geo-file{height:auto!important;padding:9px!important}.geo-toast{position:fixed;z-index:9999;right:18px;bottom:18px;max-width:390px;padding:11px 14px;border-radius:10px;background:#173a27;color:#fff;font-size:12px;font-weight:700;box-shadow:0 12px 34px rgba(0,0,0,.22)}.geo-toast.bad{background:#9b2c25}.geo-toast[hidden]{display:none}
  @media(max-width:1100px){.geo-stats{grid-template-columns:repeat(3,1fr)}.geo-grid{grid-template-columns:1fr}.geo-panel{border-left:0;border-top:1px solid #dde6df}.geo-panel-scroll{height:auto;max-height:520px}.geo-map,.geo-grid{min-height:560px}.geo-map{height:560px}}
  @media(max-width:700px){.geo-title-row{display:block}.geo-actions{justify-content:flex-start;margin-top:13px}.geo-stats{grid-template-columns:repeat(2,1fr)}.geo-toolbar{align-items:stretch;flex-direction:column}.geo-search,.geo-select{max-width:none}.geo-toolbar .geo-btn{width:100%}.geo-map,.geo-grid{min-height:480px}.geo-map{height:480px}.geo-dialog-grid,.geo-editor-grid{grid-template-columns:1fr}}
  .geo-page{font-family:var(--ui-font);color:var(--ui-text);font-size:14px;line-height:1.5}
  .geo-hero{border-radius:12px;background:var(--ui-surface);box-shadow:none}
  .geo-eyebrow i{display:none}
  .geo-subtitle{font-size:14px}
  .geo-btn,.geo-card-actions .geo-btn{min-height:44px;font-size:14px;font-weight:500;border-radius:8px}
  .geo-btn:hover{transform:none;box-shadow:none}
  .geo-field label{font-size:14px;font-weight:500}
  .geo-field select,.geo-field input{min-height:44px;font-size:16px;font-weight:400;border-color:var(--ui-control-border)}
  .geo-workspace,.geo-dialog{border-radius:12px;box-shadow:none}
  .geo-stats{margin:0;padding:16px}
  .geo-map-tools{align-self:stretch;min-width:190px}
  .geo-map-tools>summary{padding:12px;min-height:44px;font-size:14px;cursor:pointer;border:1px solid var(--ui-control-border);border-radius:8px}
  .geo-map-tools>.geo-actions{margin-top:12px}
  .geo-opacity{padding:12px;margin-top:12px;border:1px solid var(--ui-border);border-radius:8px;background:var(--ui-surface)}
  .geo-opacity-heading{display:flex;justify-content:space-between;gap:12px;font-size:14px;font-weight:700}
  .geo-opacity output{min-width:4ch;text-align:right;color:var(--ui-primary)}
  .geo-opacity input{display:block;width:100%;height:32px;margin:4px 0;accent-color:var(--ui-primary);cursor:pointer}
  .geo-opacity-scale{display:flex;justify-content:space-between;font-size:12px;color:var(--ui-text-muted)}
  .geo-opacity p{margin:8px 0 0;font-size:12px;color:var(--ui-text-muted)}
  .geo-boundary-label{padding:4px 8px;border:1px solid var(--ui-text);border-radius:6px;background:var(--ui-accent-soft);box-shadow:0 1px 4px rgba(0,0,0,.35)}
  .geo-toolbar{flex-wrap:wrap}
  .geo-field{flex-basis:200px}
  .geo-field.geo-search{flex-basis:180px}
  .geo-assigned-scope{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0}
  .geo-assigned-scope span{color:var(--ui-text-muted);font-size:12px}
  .geo-assigned-scope strong{font-size:16px;overflow-wrap:anywhere}
  .geo-panel-scroll .module-more{margin-block:16px}
  .geo-section-title,.geo-boundary-card strong,.geo-review-card strong{font-size:14px}
  .geo-page :is(button,a,input,select,summary,[role="button"]):focus-visible{outline:3px solid var(--ui-focus);outline-offset:3px}
  @media(max-width:700px){.geo-field{flex-basis:auto}.geo-map-tools{width:100%}.geo-stats{grid-template-columns:1fr 1fr}.geo-stat strong{white-space:normal;font-size:18px}}
</style>
@endpush

@section('content')
@include('partials.operations-ui-styles')
<div class="geo-page">
  <section class="geo-hero">
    <div class="geo-title-row">
      <div>
        <div class="geo-eyebrow"><i></i> {{ $canChooseMunicipality ? 'Province boundary administration' : $assignedMunicipality?->name.' workspace' }}</div>
        <h1 class="geo-title">Municipality geofences</h1>
        <p class="geo-subtitle">{{ $canManageBoundaries ? 'Maintain official municipal coverage, inspect mapped parcels, and catch land records that cross or fall outside their assigned municipality.' : 'Review your '.($canChooseMunicipality ? 'municipality workspaces' : 'assigned municipality boundary').' and mapped parcels that need field verification.' }}</p>
      </div>
      @if($canManageBoundaries)
        <div class="geo-actions">
          <button class="geo-btn" type="button" id="openImport">Import KML, KMZ, or GeoJSON</button>
          <button class="geo-btn primary" type="button" id="startBoundary">Draw municipality boundary</button>
        </div>
      @endif
    </div>
  </section>

  <details class="module-more"><summary>Coverage summary <span>{{ $canChooseMunicipality ? 'Municipality, farmer, and parcel totals' : 'Boundary, farmer, and parcel totals for '.$assignedMunicipality?->name }}</span></summary>
  <section class="geo-stats" aria-label="Geofence summary">
    @if($canChooseMunicipality)
      <article class="geo-stat"><small>Municipalities in scope</small><strong>{{ number_format($summary['municipalities']) }}</strong><span>Active municipal offices</span></article>
    @else
      <article class="geo-stat"><small>Assigned municipality</small><strong>{{ $assignedMunicipality?->name }}</strong><span>Your office workspace</span></article>
    @endif
    <article class="geo-stat"><small>Official boundaries</small><strong id="summaryConfigured">{{ number_format($summary['configured']) }}</strong><span>Active geofences</span></article>
    <article class="geo-stat"><small>Boundary coverage</small><strong>{{ number_format($summary['boundary_area_ha'], 0) }} ha</strong><span>{{ $canChooseMunicipality ? 'Combined official area' : 'Active boundary area' }}</span></article>
    <article class="geo-stat"><small>Registered farmers</small><strong id="summaryFarmers">{{ number_format($summary['farmers']) }}</strong><span>Current access scope</span></article>
    <article class="geo-stat"><small>Mapped parcels</small><strong id="summaryParcels">{{ number_format($summary['parcels']) }}</strong><span>Saved farm polygons</span></article>
    <article class="geo-stat"><small>Mapped land</small><strong id="summaryMappedArea">{{ number_format($summary['mapped_area_ha'], 2) }} ha</strong><span>Across visible parcels</span></article>
  </section>
  </details>

  <section class="geo-workspace">
    <div class="geo-toolbar">
      @if($canChooseMunicipality)
      <div class="geo-field geo-select">
        <label for="municipalityFilter">Municipality workspace</label>
        <select id="municipalityFilter">
          <option value="">All municipalities</option>
          @foreach($municipalities as $municipality)
            <option value="{{ $municipality->id }}">{{ $municipality->name }}, {{ $municipality->province }}</option>
          @endforeach
        </select>
      </div>
      <div class="geo-field geo-search">
        <label for="boundarySearch">Find municipality</label>
        <input id="boundarySearch" type="search" placeholder="Type a municipality name" autocomplete="off">
      </div>
      @else
        <div class="geo-assigned-scope" aria-label="Assigned municipality workspace">
          <span>Assigned municipality</span>
          <strong>{{ $assignedMunicipality?->name }}</strong>
          <span>Boundary and parcel checks for your office</span>
        </div>
        <input type="hidden" id="municipalityFilter" value="{{ $assignedMunicipality?->id }}">
      @endif
      <details class="geo-map-tools"><summary>Map tools</summary><div class="geo-actions">
      <button class="geo-btn" type="button" id="fitVisible">Fit visible boundaries</button>
      <button class="geo-btn" type="button" id="resetMap">{{ $canChooseMunicipality ? 'Reset province view' : 'Reset municipality view' }}</button>
      <button class="geo-btn primary" type="button" id="downloadSnapshot" disabled>Download municipality snapshot</button>
      </div>
      <div class="geo-opacity">
        <div class="geo-opacity-heading"><label for="geofenceOpacity">Geofence color opacity</label><output id="geofenceOpacityValue" for="geofenceOpacity">20%</output></div>
        <input id="geofenceOpacity" type="range" min="0" max="100" step="5" value="20" aria-describedby="geofenceOpacityHelp" aria-valuetext="20% color opacity">
        <div class="geo-opacity-scale"><span>0% Clear</span><span>100% Solid</span></div>
        <p id="geofenceOpacityHelp">Adjust the map's color fill. Outlines stay visible; drafts use a lighter fill.</p>
      </div>
      </details>
    </div>

    <div class="geo-grid">
      <div class="geo-map-wrap">
        <div id="geofenceMap" class="geo-map" aria-label="Municipality boundary map"></div>
        <div class="geo-map-message" id="mapMessage">{{ $canChooseMunicipality ? 'Choose a municipality to load its farmers, parcels, and compliance review. All active municipality boundaries remain visible in the province view.' : 'The map opens your assigned municipality boundary and parcel checks automatically.' }}</div>
        <div class="geo-map-legend"><span><i class="active"></i>Official boundary</span><span><i class="draft"></i>Draft</span><span><i class="parcel"></i>Farm parcel</span></div>

        @if($canManageBoundaries)
          <section class="geo-editor" id="boundaryEditor" hidden>
            <h3 id="editorTitle">Draw a municipality boundary</h3>
            <p id="editorHelp">Click around the municipality on the map. Finish with at least three points, then review before saving.</p>
            <div class="geo-field">
              <label for="editorMunicipality">Municipality</label>
              <select id="editorMunicipality">
                <option value="">Select municipality</option>
                @foreach($municipalities as $municipality)
                  <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="geo-editor-grid">
              <div class="geo-field"><label for="editorName">Boundary name</label><input id="editorName" maxlength="150" placeholder="Official municipal boundary"></div>
              <div class="geo-field"><label for="editorColor">Map color</label><input id="editorColor" type="color" value="#15803d"></div>
            </div>
            <div class="geo-field" id="editorStatusField">
              <label for="editorStatus">Save as</label>
              <select id="editorStatus"><option value="draft">Draft for review</option><option value="active">Active official boundary</option></select>
            </div>
            <label class="geo-check"><input type="checkbox" id="replaceConfirmed"> I confirm replacement if this municipality already has an active official boundary.</label>
            <div class="geo-draw-state" id="drawState">0 points placed</div>
            <div class="geo-editor-actions">
              <button class="geo-btn" type="button" id="undoPoint">Undo point</button>
              <button class="geo-btn" type="button" id="clearPoints">Clear</button>
              <button class="geo-btn" type="button" id="cancelEditor">Cancel</button>
              <button class="geo-btn primary" type="button" id="saveBoundary">Save boundary</button>
            </div>
          </section>
        @endif
      </div>

      <aside class="geo-panel">
        <div class="geo-panel-head">
          <small id="panelEyebrow">{{ $canChooseMunicipality ? 'Province-wide view' : 'Assigned municipality' }}</small>
          <h2 id="panelTitle">{{ $canChooseMunicipality ? 'Boundary overview' : $assignedMunicipality?->name }}</h2>
          <p id="panelDescription">{{ $canChooseMunicipality ? 'Select one municipality to inspect its active boundary and parcel placement.' : 'Review your boundary and parcels needing attention.' }}</p>
        </div>
        <div class="geo-panel-scroll" id="panelContent">
          <div class="geo-empty">{{ $canChooseMunicipality ? 'The map is showing all available municipality geofences. Use the municipality selector to load detailed parcel checks.' : 'Your boundary and parcel checks appear here when the map is available.' }}</div>
        </div>
      </aside>
    </div>
  </section>
</div>

@if($canManageBoundaries)
<dialog class="geo-dialog" id="importDialog">
  <form id="importForm" enctype="multipart/form-data">
    <div class="geo-dialog-head"><div><h3>Import municipality boundary</h3><p>Upload an official KML, KMZ, or GeoJSON polygon.</p></div><button class="geo-btn" type="button" id="closeImport">Close</button></div>
    <div class="geo-dialog-body">
      <div class="geo-field"><label for="importMunicipality">Municipality</label><select id="importMunicipality" name="municipality_id" required><option value="">Select municipality</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}">{{ $municipality->name }}</option>@endforeach</select></div>
      <div class="geo-field"><label for="importName">Boundary name</label><input id="importName" name="name" maxlength="150" required placeholder="Official municipal boundary"></div>
      <div class="geo-dialog-grid">
        <div class="geo-field"><label for="importFile">Boundary file</label><input class="geo-file" id="importFile" name="file" type="file" accept=".kml,.kmz,.geojson,.json,.xml" required></div>
        <div class="geo-field"><label for="importColor">Map color</label><input id="importColor" name="color" type="color" value="#15803d"></div>
      </div>
      <div class="geo-field"><label for="importStatus">Save as</label><select id="importStatus" name="status"><option value="draft">Draft for review</option><option value="active">Active official boundary</option></select></div>
      <label class="geo-check"><input type="checkbox" name="replace_confirmed" value="1"> I confirm replacement if an active boundary already exists.</label>
    </div>
    <div class="geo-dialog-actions"><button class="geo-btn" type="button" id="cancelImport">Cancel</button><button class="geo-btn primary" type="submit">Validate and import</button></div>
  </form>
</dialog>
@endif

<div class="geo-toast" id="geoToast" role="status" hidden></div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const settings = {
    key: @json($googleMapsApiKey),
    mapId: @json($googleMapsMapId),
    canManage: @json($canManageBoundaries),
    canChooseMunicipality: @json($canChooseMunicipality),
    assignedMunicipalityId: @json($assignedMunicipality?->id),
    csrf: @json(csrf_token()),
    dataUrl: @json(route('municipality-boundaries.data')),
    storeUrl: @json(route('municipality-boundaries.store')),
    importUrl: @json(route('municipality-boundaries.import')),
    updateTemplate: @json(route('municipality-boundaries.update', ['boundary' => '__ID__'])),
    activateTemplate: @json(route('municipality-boundaries.activate', ['boundary' => '__ID__'])),
    archiveTemplate: @json(route('municipality-boundaries.archive', ['boundary' => '__ID__'])),
    municipalities: @json($municipalities->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values()),
    initialBoundaries: @json($boundaries->values()),
    initialSummary: @json($summary),
  };

  const state = {
    map: null,
    info: null,
    boundaryOverlays: new Map(),
    boundaryFills: new Map(),
    fillOpacity: .2,
    labels: new Map(),
    parcelOverlays: new Map(),
    boundaries: settings.initialBoundaries.slice(),
    selectedMunicipality: '',
    selectedBoundary: null,
    editorMode: null,
    editableOverlay: null,
    draftPoints: [],
    draftOverlay: null,
    mapClick: null,
    currentPayload: null,
    loadRevision: 0,
  };

  const el = id => document.getElementById(id);
  const filter = el('municipalityFilter');
  const panel = el('panelContent');
  const initialBoundary = settings.initialBoundaries[0];
  const defaultViewport = settings.canChooseMunicipality
    ? {center: {lat: 15.4755, lng: 120.5963}, zoom: 10}
    : initialBoundary
      ? {center: {lat: Number(initialBoundary.centroid_lat), lng: Number(initialBoundary.centroid_lng)}, zoom: 12}
      : {center: {lat: 12.8797, lng: 121.774}, zoom: 5};

  function toast(message, bad) {
    const node = el('geoToast');
    node.textContent = message;
    node.classList.toggle('bad', !!bad);
    node.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { node.hidden = true; }, 4800);
  }

  function errorMessage(payload, fallback) {
    if (payload && payload.errors) {
      const first = Object.values(payload.errors).flat()[0];
      if (first) return String(first);
    }
    return payload && payload.message ? payload.message : fallback;
  }

  async function request(url, options) {
    const response = await fetch(url, Object.assign({headers: {'Accept': 'application/json'}}, options || {}));
    let payload = {};
    try { payload = await response.json(); } catch (ignore) {}
    if (!response.ok) throw new Error(errorMessage(payload, 'Request failed (' + response.status + ').'));
    return payload;
  }

  function endpoint(template, id) { return template.replace('__ID__', encodeURIComponent(String(id))); }
  function formatNumber(value, decimals) { return Number(value || 0).toLocaleString(undefined, {maximumFractionDigits: decimals}); }
  function municipalityName(id) { return (settings.municipalities.find(item => String(item.id) === String(id)) || {}).name || 'Municipality'; }

  function geometryPolygons(geometry) {
    if (!geometry || !geometry.coordinates) return [];
    return geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
  }

  function googlePaths(polygon) {
    return polygon.map(ring => ring.map(point => ({lat: Number(point[1]), lng: Number(point[0])})));
  }

  function drawBoundary(boundary) {
    removeBoundary(boundary.id);
    const fills = [];
    const fillScale = boundary.status === 'draft' ? .5 : 1;
    const overlays = geometryPolygons(boundary.geojson).flatMap(polygon => {
      const paths = googlePaths(polygon);
      // A pale casing keeps dark saved colors visible over satellite terrain.
      const outline = new google.maps.Polygon({
        paths,
        strokeColor: '#FFF8D6',
        strokeOpacity: 1,
        strokeWeight: boundary.status === 'draft' ? 6 : 8,
        fillOpacity: 0,
        clickable: false,
        zIndex: boundary.status === 'active' ? 1.9 : .9,
      });
      outline.setMap(state.map);
      const overlay = new google.maps.Polygon({
        paths,
        strokeColor: boundary.color,
        strokeOpacity: 1,
        strokeWeight: boundary.status === 'draft' ? 3 : 4,
        fillColor: boundary.color,
        fillOpacity: state.fillOpacity * fillScale,
        clickable: true,
        zIndex: boundary.status === 'active' ? 2 : 1,
      });
      overlay.setMap(state.map);
      overlay.addListener('click', event => {
        state.info.setContent('<strong>' + escapeHtml(boundary.municipality_name || '') + '</strong><br><span>' + escapeHtml(boundary.name) + ' · ' + escapeHtml(boundary.status) + '<br>' + formatNumber(boundary.area_ha, 2) + ' ha</span>');
        state.info.setPosition(event.latLng);
        state.info.open({map: state.map});
        filter.value = String(boundary.municipality_id);
        loadMunicipality(boundary.municipality_id, boundary.id);
      });
      fills.push(overlay);
      return [outline, overlay];
    });
    state.boundaryOverlays.set(String(boundary.id), overlays);
    state.boundaryFills.set(String(boundary.id), {overlays: fills, scale: fillScale});

    if (boundary.status === 'active') {
      const marker = new google.maps.Marker({
        map: state.map,
        position: {lat: Number(boundary.centroid_lat), lng: Number(boundary.centroid_lng)},
        label: {text: String(boundary.municipality_name || ''), color: '#20362c', fontSize: '12px', fontWeight: '700', className: 'geo-boundary-label'},
        icon: {path: google.maps.SymbolPath.CIRCLE, scale: 0},
        clickable: false,
        zIndex: 4,
      });
      state.labels.set(String(boundary.id), marker);
    }
  }

  function removeBoundary(id) {
    (state.boundaryOverlays.get(String(id)) || []).forEach(item => item.setMap(null));
    state.boundaryOverlays.delete(String(id));
    state.boundaryFills.delete(String(id));
    const label = state.labels.get(String(id));
    if (label) label.setMap(null);
    state.labels.delete(String(id));
  }

  function renderBoundaries() {
    Array.from(state.boundaryOverlays.keys()).forEach(removeBoundary);
    const selected = String(state.selectedMunicipality || '');
    state.boundaries
      .filter(boundary => boundary.status !== 'archived' && (!selected || String(boundary.municipality_id) === selected))
      .forEach(drawBoundary);
  }

  function clearParcels() {
    state.parcelOverlays.forEach(overlay => overlay.setMap(null));
    state.parcelOverlays.clear();
  }

  function drawParcels(parcels) {
    clearParcels();
    parcels.forEach(parcel => {
      const points = (parcel.polygon || []).map(point => ({lat: Number(point.lat), lng: Number(point.lng)}));
      if (points.length < 3) return;
      const overlay = new google.maps.Polygon({paths: points, strokeColor: parcel.color || '#2563eb', strokeWeight: 2, strokeOpacity: .9, fillColor: parcel.color || '#2563eb', fillOpacity: .23, zIndex: 6});
      overlay.setMap(state.map);
      overlay.addListener('mouseover', event => {
        state.info.setContent('<strong>' + escapeHtml(parcel.name) + '</strong><br><span>' + escapeHtml(parcel.farmer.name || 'Unknown farmer') + '<br>' + formatNumber(parcel.area_ha, 4) + ' ha</span>');
        state.info.setPosition(event.latLng);
        state.info.open({map: state.map});
      });
      overlay.addListener('mouseout', () => state.info.close());
      state.parcelOverlays.set(String(parcel.id), overlay);
    });
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function fitVisible() {
    const bounds = new google.maps.LatLngBounds();
    let count = 0;
    state.boundaryOverlays.forEach(overlays => overlays.forEach(overlay => overlay.getPaths().forEach(path => path.forEach(point => { bounds.extend(point); count++; }))));
    state.parcelOverlays.forEach(overlay => overlay.getPath().forEach(point => { bounds.extend(point); count++; }));
    if (count) state.map.fitBounds(bounds, 34);
    else resetDefaultView();
  }

  function resetDefaultView() { state.map.setCenter(defaultViewport.center); state.map.setZoom(defaultViewport.zoom); }

  async function loadMunicipality(id, selectedBoundaryId) {
    if (!settings.canChooseMunicipality) id = settings.assignedMunicipalityId;
    const revision = ++state.loadRevision;
    state.selectedMunicipality = id ? String(id) : '';
    cancelEditor();
    clearParcels();
    state.currentPayload = null;
    el('downloadSnapshot').disabled = true;

    @if($canChooseMunicipality)
    if (!id) {
      renderBoundaries();
      el('panelEyebrow').textContent = 'Province-wide view';
      el('panelTitle').textContent = 'Boundary overview';
      el('panelDescription').textContent = 'Select one municipality to inspect its active boundary and parcel placement.';
      panel.innerHTML = '<div class="geo-empty">The map is showing all available municipality geofences. Use the municipality selector to load detailed parcel checks.</div>';
      el('mapMessage').textContent = 'Province view: active official boundaries and drafts are visible. Select a municipality for parcel compliance.';
      el('summaryConfigured').textContent = formatNumber(settings.initialSummary.configured, 0);
      el('summaryFarmers').textContent = formatNumber(settings.initialSummary.farmers, 0);
      el('summaryParcels').textContent = formatNumber(settings.initialSummary.parcels, 0);
      el('summaryMappedArea').textContent = formatNumber(settings.initialSummary.mapped_area_ha, 2) + ' ha';
      fitVisible();
      return;
    }
    @endif

    el('mapMessage').textContent = 'Loading ' + municipalityName(id) + ' boundary and parcels…';
    panel.innerHTML = '<div class="geo-empty" role="status">Loading boundary and parcel checks…</div>';
    try {
      const payload = await request(settings.dataUrl + '?municipality_id=' + encodeURIComponent(id));
      if (revision !== state.loadRevision) return;
      state.currentPayload = payload;
      el('downloadSnapshot').disabled = !payload.snapshot;
      state.boundaries = state.boundaries.filter(item => String(item.municipality_id) !== String(id)).concat(payload.boundaries);
      state.selectedBoundary = selectedBoundaryId ? payload.boundaries.find(item => String(item.id) === String(selectedBoundaryId)) : payload.boundaries[0] || null;
      renderBoundaries();
      drawParcels(payload.parcels || []);
      renderPanel(payload);
      updateSelectedSummary(payload.stats);
      el('mapMessage').textContent = payload.boundaries.some(item => item.status === 'active')
        ? payload.municipality.name + ': official boundary and ' + payload.stats.parcels + ' parcel(s) loaded.'
        : payload.municipality.name + ' has no active official boundary. Parcels cannot be classified yet.';
      fitVisible();
    } catch (error) {
      if (revision !== state.loadRevision) return;
      toast(error.message, true);
      el('mapMessage').textContent = 'The municipality workspace could not be loaded.';
      panel.innerHTML = '<div class="geo-empty">Boundary and parcel checks could not be loaded. <button type="button" class="geo-btn" data-retry-workspace>Try again</button></div>';
    }
  }

  function updateSelectedSummary(stats) {
    el('summaryFarmers').textContent = formatNumber(stats.farmers, 0);
    el('summaryParcels').textContent = formatNumber(stats.parcels, 0);
    el('summaryMappedArea').textContent = formatNumber(stats.mapped_area_ha, 2) + ' ha';
  }

  function renderPanel(payload) {
    el('panelEyebrow').textContent = 'Municipality workspace';
    el('panelTitle').textContent = payload.municipality.name;
    el('panelDescription').textContent = 'Review the official boundary and parcels needing attention. Older boundaries are available in history.';
    const stats = payload.stats;
    let html = '<div class="geo-mini-stats">' +
      mini('Farmers', stats.farmers) + mini('Mapped farmers', stats.mapped_farmers) + mini('Parcels', stats.parcels) + mini('Mapped hectares', formatNumber(stats.mapped_area_ha, 2)) +
      mini('Outside boundary', stats.outside) + mini('Crossing / near', Number(stats.partial) + Number(stats.near_boundary)) + '</div>';
    function boundaryCard(boundary) {
      let card = '';
      card += '<article class="geo-boundary-card ' + (boundary.status === 'active' ? 'active' : '') + '"><div class="geo-boundary-top"><strong>' + escapeHtml(boundary.name) + '</strong><span class="geo-badge ' + boundary.status + '">' + escapeHtml(boundary.status) + '</span></div><div class="geo-boundary-meta">' + formatNumber(boundary.area_ha, 2) + ' ha · ' + formatNumber(boundary.vertex_count, 0) + ' vertices</div><div class="geo-card-actions">';
      if (boundary.status !== 'archived') card += '<button class="geo-btn" type="button" data-focus-boundary="' + boundary.id + '">Focus</button>';
      if (settings.canManage) {
        if (boundary.status !== 'archived') card += '<button class="geo-btn" type="button" data-edit-boundary="' + boundary.id + '">Edit</button>';
        if (boundary.status !== 'active') card += '<button class="geo-btn primary" type="button" data-activate-boundary="' + boundary.id + '">Activate</button>';
        if (boundary.status !== 'archived') card += '<button class="geo-btn danger" type="button" data-archive-boundary="' + boundary.id + '">Archive</button>';
      }
      card += '</div></article>';
      return card;
    }
    const active = payload.boundaries.filter(boundary => boundary.status === 'active');
    const history = payload.boundaries.filter(boundary => boundary.status !== 'active');
    html += '<div class="geo-section-title">Official boundary</div>';
    html += active.length ? active.map(boundaryCard).join('') : '<div class="geo-empty">No official boundary is active. ' + (settings.canManage ? 'Review a draft or add a boundary.' : 'Contact the Super Administrator to configure the boundary.') + '</div>';
    html += '<details class="module-more"><summary>Drafts and boundary history <span>' + history.length + ' records</span></summary><div class="module-more-content">';
    html += history.length ? history.map(boundaryCard).join('') : '<div class="geo-empty">No drafts or older boundaries.</div>';
    html += '</div></details>';
    html += '<div class="geo-section-title"><span>Needs field review</span><span>' + payload.review.length + '</span></div>';
    if (!payload.review.length) html += '<div class="geo-empty">No outside, crossing, near-boundary, or invalid parcels were found.</div>';
    payload.review.forEach(item => {
      html += '<article class="geo-review-card" tabindex="0" role="button" data-focus-plot="' + item.plot_id + '"><div class="geo-review-top"><strong>' + escapeHtml(item.plot_name) + '</strong><span class="geo-review-status ' + item.status + '">' + escapeHtml(item.status.replace('_', ' ')) + '</span></div><p>' + escapeHtml(item.farmer_name || 'Unknown farmer') + (item.ffrs ? ' · ' + escapeHtml(item.ffrs) : '') + '<br>' + escapeHtml(item.location || 'Location not recorded') + ' · ' + formatNumber(item.area_ha, 4) + ' ha</p></article>';
    });
    panel.innerHTML = html;
    panel.querySelectorAll('[data-focus-plot]').forEach(card => card.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); card.click(); }
    }));
  }

  function mini(label, value) { return '<div class="geo-mini"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>'; }

  function worldPoint(lat, lng, zoom) {
    const safeLat = Math.max(-85.05112878, Math.min(85.05112878, Number(lat)));
    const sine = Math.sin(safeLat * Math.PI / 180);
    const factor = Math.pow(2, Number(zoom));
    return {
      x: ((Number(lng) + 180) / 360) * 256 * factor,
      y: (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * 256 * factor,
    };
  }

  function loadSnapshotImage(url) {
    return fetch(url, {headers:{'Accept':'image/png,image/*'}}).then(async response => {
      if (!response.ok) {
        const message = await response.text();
        throw new Error(message || 'The satellite base image could not be generated.');
      }
      const blob = await response.blob();
      return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(blob);
        image.onload = () => { URL.revokeObjectURL(objectUrl); resolve(image); };
        image.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('The satellite base image could not be read.')); };
        image.src = objectUrl;
      });
    });
  }

  function canvasGeometryPath(geometry, project) {
    const path = new Path2D();
    geometryPolygons(geometry).forEach(polygon => polygon.forEach(ring => {
      ring.forEach((point, index) => {
        const pixel = project(Number(point[1]), Number(point[0]));
        if (index === 0) path.moveTo(pixel.x, pixel.y); else path.lineTo(pixel.x, pixel.y);
      });
      path.closePath();
    }));
    return path;
  }

  function canvasParcelPath(points, project) {
    const path = new Path2D();
    (points || []).forEach((point, index) => {
      const pixel = project(Number(point.lat), Number(point.lng));
      if (index === 0) path.moveTo(pixel.x, pixel.y); else path.lineTo(pixel.x, pixel.y);
    });
    path.closePath();
    return path;
  }

  async function downloadMunicipalitySnapshot() {
    const payload = state.currentPayload;
    if (!payload || !payload.snapshot) {
      toast('Select a municipality with an active official boundary first.', true);
      return;
    }

    const button = el('downloadSnapshot');
    button.disabled = true;
    const originalLabel = button.textContent;
    button.textContent = 'Preparing satellite snapshot…';

    try {
      const image = await loadSnapshotImage(payload.snapshot.base_map_url);
      const canvas = document.createElement('canvas');
      const sourceWidth = Number(image.naturalWidth || payload.snapshot.source_size || 1280);
      const sourceHeight = Number(image.naturalHeight || payload.snapshot.source_size || 1280);
      const mapSize = Math.min(sourceWidth, sourceHeight);
      canvas.width = mapSize;
      canvas.height = mapSize;
      const ctx = canvas.getContext('2d');
      const mapX = 0;
      const mapY = 0;
      const viewportSize = Number(payload.snapshot.viewport_size || 640);
      const sourceScaleX = sourceWidth / viewportSize;
      const sourceScaleY = sourceHeight / viewportSize;
      const zoom = Number(payload.snapshot.zoom);
      const center = worldPoint(payload.snapshot.center_lat, payload.snapshot.center_lng, zoom);
      const project = (lat, lng) => {
        const point = worldPoint(lat, lng, zoom);
        return {
          x: mapX + (((point.x - center.x) * sourceScaleX) + sourceWidth / 2) * (mapSize / sourceWidth),
          y: mapY + (((point.y - center.y) * sourceScaleY) + sourceHeight / 2) * (mapSize / sourceHeight),
        };
      };

      // The export is intentionally map-only. Everything outside the active
      // official geofence remains white, with no report chrome or labels.
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      const activeBoundary = payload.boundaries.find(item => item.status === 'active');
      const boundaryPath = canvasGeometryPath(activeBoundary.geojson, project);
      ctx.save();
      ctx.clip(boundaryPath, 'evenodd');
      ctx.drawImage(image, mapX, mapY, mapSize, mapSize);
      ctx.restore();

      (payload.parcels || []).forEach(parcel => {
        const status = parcel.geofence_status || 'inside';
        if (status === 'invalid' || status === 'unconfigured') return;

        const parcelPath = canvasParcelPath(parcel.polygon, project);
        const parcelColor = parcel.color || '#22c55e';

        if (status === 'outside') {
          ctx.fillStyle = '#ffffff';
          ctx.fill(parcelPath);
          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 5;
          ctx.stroke(parcelPath);
          return;
        }

        if (status === 'partial') {
          ctx.save();
          ctx.clip(boundaryPath, 'evenodd');
          ctx.fillStyle = hexAlpha(parcelColor, .38);
          ctx.fill(parcelPath);
          ctx.restore();
          ctx.strokeStyle = '#ea580c';
          ctx.lineWidth = 5;
          ctx.stroke(parcelPath);
        } else {
          ctx.fillStyle = hexAlpha(parcelColor, .35);
          ctx.fill(parcelPath);
          ctx.strokeStyle = status === 'near_boundary' ? '#ca8a04' : parcelColor;
          ctx.lineWidth = 3;
          ctx.stroke(parcelPath);
        }
      });

      ctx.strokeStyle = activeBoundary.color || '#15803d';
      ctx.lineWidth = 7;
      ctx.stroke(boundaryPath);

      // Preserve Google's complete attribution/logo strip even though the
      // rest of the satellite image is masked outside the municipal boundary.
      const attributionSourceHeight = Math.min(sourceHeight, Math.max(80, Math.ceil(sourceHeight * .08)));
      const attributionHeight = mapSize * (attributionSourceHeight / sourceHeight);
      ctx.drawImage(image, 0, sourceHeight - attributionSourceHeight, sourceWidth, attributionSourceHeight, mapX, mapY + mapSize - attributionHeight, mapSize, attributionHeight);

      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 1));
      if (!blob) throw new Error('The browser could not create the PNG snapshot.');
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = String(payload.municipality.name || 'municipality').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-land-snapshot.png';
      document.body.appendChild(link); link.click(); link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      recordSnapshotExport(payload.snapshot.audit_url);
      toast('Municipality land snapshot downloaded.');
    } catch (error) {
      console.error(error);
      toast(error.message || 'Snapshot generation failed.', true);
    } finally {
      button.textContent = originalLabel;
      button.disabled = !(state.currentPayload && state.currentPayload.snapshot);
    }
  }

  function recordSnapshotExport(url) {
    if (!url) return;
    fetch(url, {
      method: 'POST',
      headers: {'Accept':'application/json','X-CSRF-TOKEN':settings.csrf},
      keepalive: true,
    }).then(response => {
      if (!response.ok) throw new Error('Audit endpoint returned ' + response.status + '.');
    }).catch(error => console.warn('Snapshot audit could not be recorded.', error));
  }

  function hexAlpha(hex, alpha) {
    const value = String(hex || '#22c55e').replace('#','');
    const normalized = value.length === 3 ? value.split('').map(item => item + item).join('') : value.slice(0,6);
    const number = parseInt(normalized, 16);
    if (!Number.isFinite(number)) return 'rgba(34,197,94,' + alpha + ')';
    return 'rgba(' + ((number >> 16) & 255) + ',' + ((number >> 8) & 255) + ',' + (number & 255) + ',' + alpha + ')';
  }

  function focusOverlay(overlays) {
    const bounds = new google.maps.LatLngBounds();
    overlays.forEach(overlay => overlay.getPaths().forEach(path => path.forEach(point => bounds.extend(point))));
    state.map.fitBounds(bounds, 48);
  }

  function startDrawing() {
    cancelEditor();
    state.editorMode = 'create';
    state.draftPoints = [];
    el('boundaryEditor').hidden = false;
    el('editorTitle').textContent = 'Draw a municipality boundary';
    el('editorHelp').textContent = 'Click around the municipality on the map. Place points in order without crossing the boundary line.';
    el('editorMunicipality').disabled = false;
    el('editorMunicipality').value = state.selectedMunicipality || '';
    el('editorName').value = (state.selectedMunicipality ? municipalityName(state.selectedMunicipality) + ' Official Boundary' : '');
    el('editorColor').value = '#15803d';
    el('editorStatusField').hidden = false;
    el('replaceConfirmed').checked = false;
    updateDrawState();
    state.mapClick = state.map.addListener('click', event => {
      state.draftPoints.push(event.latLng);
      refreshDraftOverlay();
    });
    toast('Drawing mode started. Click the map to place boundary points.');
  }

  function editBoundary(boundary) {
    cancelEditor();
    if (!boundary || boundary.status === 'archived') return;
    const polygons = geometryPolygons(boundary.geojson);
    if (polygons.length !== 1 || polygons[0].length !== 1) {
      toast('MultiPolygon or holed boundaries must be replaced through file import.', true);
      return;
    }
    state.editorMode = 'edit';
    state.selectedBoundary = boundary;
    el('boundaryEditor').hidden = false;
    el('editorTitle').textContent = 'Edit municipality boundary';
    el('editorHelp').textContent = 'Drag the boundary points on the map, then save the revised official geometry.';
    el('editorMunicipality').value = String(boundary.municipality_id);
    el('editorMunicipality').disabled = true;
    el('editorName').value = boundary.name;
    el('editorColor').value = String(boundary.color).toLowerCase();
    el('editorStatusField').hidden = true;
    el('replaceConfirmed').checked = false;
    state.editableOverlay = new google.maps.Polygon({paths: googlePaths(polygons[0]), strokeColor: boundary.color, strokeWeight: 4, fillColor: boundary.color, fillOpacity: .24, editable: true, zIndex: 20});
    state.editableOverlay.setMap(state.map);
    updateDrawState();
    focusOverlay([state.editableOverlay]);
  }

  function refreshDraftOverlay() {
    if (state.draftOverlay) state.draftOverlay.setMap(null);
    state.draftOverlay = new google.maps.Polygon({paths: state.draftPoints, strokeColor: el('editorColor').value, strokeWeight: 3, fillColor: el('editorColor').value, fillOpacity: .18, zIndex: 20});
    state.draftOverlay.setMap(state.map);
    updateDrawState();
  }

  function updateDrawState() {
    let count = state.draftPoints.length;
    if (state.editorMode === 'edit' && state.editableOverlay) count = state.editableOverlay.getPath().getLength();
    el('drawState').textContent = count + ' point' + (count === 1 ? '' : 's') + (count < 3 ? ' · at least 3 required' : ' · ready for validation');
  }

  function cancelEditor() {
    if (!settings.canManage) return;
    if (state.mapClick) google.maps.event.removeListener(state.mapClick);
    state.mapClick = null;
    if (state.draftOverlay) state.draftOverlay.setMap(null);
    if (state.editableOverlay) state.editableOverlay.setMap(null);
    state.draftOverlay = null;
    state.editableOverlay = null;
    state.draftPoints = [];
    state.editorMode = null;
    if (el('boundaryEditor')) el('boundaryEditor').hidden = true;
  }

  function pointsToGeoJson(points) {
    const coordinates = points.map(point => [Number(point.lng().toFixed(7)), Number(point.lat().toFixed(7))]);
    if (coordinates.length) coordinates.push(coordinates[0]);
    return {type: 'Polygon', coordinates: [coordinates]};
  }

  async function saveEditor() {
    const editing = state.editorMode === 'edit';
    const points = editing && state.editableOverlay ? state.editableOverlay.getPath().getArray() : state.draftPoints;
    if (points.length < 3) return toast('Place at least three boundary points.', true);
    const municipalityId = el('editorMunicipality').value;
    if (!municipalityId) return toast('Select the municipality first.', true);
    const name = el('editorName').value.trim();
    if (!name) return toast('Enter a boundary name.', true);

    const body = {
      name: name,
      color: el('editorColor').value,
      geojson: pointsToGeoJson(points),
      replace_confirmed: el('replaceConfirmed').checked ? 1 : 0,
    };
    let url = settings.storeUrl;
    let method = 'POST';
    if (editing) {
      url = endpoint(settings.updateTemplate, state.selectedBoundary.id);
      method = 'PUT';
      body._record_version = state.selectedBoundary._record_version;
    } else {
      body.municipality_id = municipalityId;
      body.status = el('editorStatus').value;
    }

    try {
      const payload = await request(url, {method, headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf}, body: JSON.stringify(body)});
      toast(payload.message);
      cancelEditor();
      filter.value = String(municipalityId);
      await loadMunicipality(municipalityId, payload.boundary.id);
    } catch (error) { toast(error.message, true); }
  }

  async function changeStatus(boundary, action) {
    const verb = action === 'activate' ? 'activate this as the official boundary' : 'archive this boundary';
    if (!window.confirm('Are you sure you want to ' + verb + '?')) return;
    const body = {_record_version: boundary._record_version};
    body[action === 'activate' ? 'replace_confirmed' : 'archive_confirmed'] = 1;
    try {
      const payload = await request(endpoint(action === 'activate' ? settings.activateTemplate : settings.archiveTemplate, boundary.id), {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf}, body: JSON.stringify(body)});
      toast(payload.message);
      await loadMunicipality(boundary.municipality_id, payload.boundary.id);
    } catch (error) { toast(error.message, true); }
  }

  function bindUi() {
    if (settings.canChooseMunicipality) filter.addEventListener('change', () => loadMunicipality(filter.value));
    el('fitVisible').addEventListener('click', fitVisible);
    el('resetMap').addEventListener('click', () => settings.canChooseMunicipality ? resetDefaultView() : fitVisible());
    el('downloadSnapshot').addEventListener('click', downloadMunicipalitySnapshot);
    el('boundarySearch')?.addEventListener('input', event => {
      const value = event.target.value.trim().toLowerCase();
      if (!value) return;
      const match = settings.municipalities.find(item => item.name.toLowerCase().includes(value));
      if (match) { filter.value = String(match.id); loadMunicipality(match.id); }
    });

    panel.addEventListener('click', event => {
      if (event.target.closest('[data-retry-workspace]')) loadMunicipality(state.selectedMunicipality);
      const focusBoundaryButton = event.target.closest('[data-focus-boundary]');
      const editButton = event.target.closest('[data-edit-boundary]');
      const activateButton = event.target.closest('[data-activate-boundary]');
      const archiveButton = event.target.closest('[data-archive-boundary]');
      const plotCard = event.target.closest('[data-focus-plot]');
      if (focusBoundaryButton) focusOverlay(state.boundaryOverlays.get(String(focusBoundaryButton.dataset.focusBoundary)) || []);
      if (editButton) editBoundary(state.boundaries.find(item => String(item.id) === String(editButton.dataset.editBoundary)));
      if (activateButton) changeStatus(state.boundaries.find(item => String(item.id) === String(activateButton.dataset.activateBoundary)), 'activate');
      if (archiveButton) changeStatus(state.boundaries.find(item => String(item.id) === String(archiveButton.dataset.archiveBoundary)), 'archive');
      if (plotCard) {
        const overlay = state.parcelOverlays.get(String(plotCard.dataset.focusPlot));
        if (overlay) focusOverlay([overlay]);
      }
    });

    if (!settings.canManage) return;
    el('startBoundary').addEventListener('click', startDrawing);
    el('cancelEditor').addEventListener('click', cancelEditor);
    el('saveBoundary').addEventListener('click', saveEditor);
    el('undoPoint').addEventListener('click', () => { if (state.editorMode === 'create') { state.draftPoints.pop(); refreshDraftOverlay(); } });
    el('clearPoints').addEventListener('click', () => { if (state.editorMode === 'create') { state.draftPoints = []; refreshDraftOverlay(); } else if (state.editableOverlay) { state.editableOverlay.getPath().clear(); updateDrawState(); } });
    el('editorColor').addEventListener('input', event => { if (state.draftOverlay) state.draftOverlay.setOptions({strokeColor:event.target.value,fillColor:event.target.value}); if (state.editableOverlay) state.editableOverlay.setOptions({strokeColor:event.target.value,fillColor:event.target.value}); });
    el('openImport').addEventListener('click', () => { el('importMunicipality').value = state.selectedMunicipality || ''; el('importDialog').showModal(); });
    ['closeImport','cancelImport'].forEach(id => el(id).addEventListener('click', () => el('importDialog').close()));
    el('importForm').addEventListener('submit', async event => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      try {
        const payload = await request(settings.importUrl, {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':settings.csrf}, body:data});
        toast(payload.message); el('importDialog').close(); event.currentTarget.reset(); el('importColor').value='#15803d'; filter.value=String(payload.boundary.municipality_id); await loadMunicipality(payload.boundary.municipality_id,payload.boundary.id);
      } catch (error) { toast(error.message, true); }
    });
  }

  window.initMunicipalityGeofenceMap = function () {
    const options = {center:defaultViewport.center,zoom:defaultViewport.zoom,mapTypeId:'hybrid',streetViewControl:false,fullscreenControl:true,mapTypeControl:true,gestureHandling:'greedy'};
    if (settings.mapId) options.mapId = settings.mapId;
    state.map = new google.maps.Map(el('geofenceMap'), options);
    state.info = new google.maps.InfoWindow();
    state.boundaries.forEach(drawBoundary);
    bindUi();
    fitVisible();
    if (filter.value) loadMunicipality(filter.value);
  };

  el('geofenceOpacity').addEventListener('input', event => {
    const value = Number(event.target.value);
    const percentage = Number.isFinite(value) ? Math.max(0, Math.min(100, value)) : 20;
    state.fillOpacity = percentage / 100;
    el('geofenceOpacityValue').value = percentage + '%';
    event.target.setAttribute('aria-valuetext', percentage + '% color opacity');
    state.boundaryFills.forEach(group => group.overlays.forEach(overlay => {
      overlay.setOptions({fillOpacity: state.fillOpacity * group.scale});
    }));
  });

  if (!settings.key) {
    el('mapMessage').textContent = 'Google Maps is not configured. Add GOOGLE_MAPS_API_KEY and clear Laravel configuration cache.';
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(settings.key) + '&callback=initMunicipalityGeofenceMap&v=weekly&loading=async';
  script.async = true;
  script.defer = true;
  script.onerror = () => { el('mapMessage').textContent = 'Google Maps could not load. Check the API key, Maps JavaScript API, billing, and website restrictions.'; };
  document.head.appendChild(script);
})();
</script>
@endpush
