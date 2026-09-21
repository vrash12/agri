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
  .geo-editor .geo-editor-feedback{margin:10px 0;padding:10px;border-left:3px solid var(--ui-danger);border-radius:4px;background:#FAEFED;color:var(--ui-danger);font-size:14px;line-height:1.5}
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
  .geo-boundary-label{padding:2px 4px;opacity:.72;text-shadow:-1px -1px 1px #20362c,1px 1px 1px #20362c;pointer-events:none}
  .geo-style-panel{margin:12px}.geo-style-panel .geo-field{margin-bottom:10px}.geo-style-panel input[type=color]{height:44px;padding:4px}.geo-style-panel .geo-btn{min-height:44px}.geo-style-panel [role=alert]{color:var(--ui-danger)}
  .geo-barangays{padding:16px;border-bottom:1px solid var(--ui-border);background:var(--ui-surface)}
  .geo-barangays h3{margin:0 0 8px;font-size:15px}
  .geo-barangays p{margin:8px 0;font-size:12px;color:var(--ui-text-muted);line-height:1.5}
  .geo-barangays .geo-check{font-size:14px;color:var(--ui-text);align-items:center;min-height:36px}
  .geo-barangays input[type=checkbox]{width:18px;height:18px;accent-color:var(--ui-primary)}
  .geo-barangay-actions{display:flex;gap:8px;align-items:flex-end}
  .geo-barangay-actions .geo-field{flex:1;min-width:0}
  .geo-barangay-label{padding:3px 6px;border:1px solid #146c68;border-radius:4px;background:#fff;color:#173a27}
  .geo-barangays a{color:var(--ui-primary);text-decoration:underline}
  .geo-barangays [hidden]{display:none}
  @media(min-width:1101px){.geo-panel{display:flex;flex-direction:column;height:650px;overflow:auto}.geo-panel-scroll{height:auto;overflow:visible}.geo-panel-head,.geo-barangays{flex-shrink:0}}
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
      <button class="geo-btn" type="button" id="toggleMunicipalityMapLabels" aria-pressed="true" title="Show or hide Google place and road labels. AgriGOV boundaries, parcels and municipality labels stay visible." disabled>Map labels: On</button>
      <details class="geo-map-tools"><summary>Map tools</summary><div class="geo-actions">
      <button class="geo-btn" type="button" id="fitVisible">Fit visible boundaries</button>
      <button class="geo-btn" type="button" id="resetMap">{{ $canChooseMunicipality ? 'Reset province view' : 'Reset municipality view' }}</button>
      <button class="geo-btn primary" type="button" id="downloadSnapshot" disabled>Download municipality snapshot</button>
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
            <p>Name and color changes do not require replacement confirmation.</p>
            <div class="geo-draw-state" id="drawState">0 points placed</div>
            <p class="geo-editor-feedback" id="editorFeedback" role="alert" tabindex="-1" hidden></p>
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
        <section class="geo-opacity geo-style-panel" aria-labelledby="geofenceStyleTitle" id="geofenceStylePanel" aria-busy="false">
          <h3 id="geofenceStyleTitle">Geofence appearance</h3>
          <div class="geo-field"><label for="geofenceStyleBoundary">Boundary</label><select id="geofenceStyleBoundary" disabled><option value="">Select a municipality first</option></select></div>
          <div class="geo-field"><label for="geofenceColor">Geofence color</label><input id="geofenceColor" type="color" value="#15803d" disabled aria-describedby="geofenceOpacityHelp"></div>
          <div class="geo-opacity-heading"><label for="geofenceOpacity">Geofence color opacity</label><output id="geofenceOpacityValue" for="geofenceOpacity">20%</output></div>
          <input id="geofenceOpacity" type="range" min="0" max="100" step="1" value="20" disabled aria-describedby="geofenceOpacityHelp" aria-valuetext="20% color opacity">
          <div class="geo-opacity-scale"><span>0% Clear</span><span>100% Solid</span></div>
          <p id="geofenceOpacityHelp">{{ $canManageBoundaries ? 'Preview this boundary, then save for both maps. Reload an open Farmers page to see the saved style.' : 'Both maps use this saved appearance. Your Super Administrator or System Owner can change it.' }}</p>
          @if($canManageBoundaries)
          <div class="geo-card-actions"><button class="geo-btn primary" type="button" id="saveGeofenceStyle" disabled>Save color &amp; opacity</button><button class="geo-btn" type="button" id="resetGeofenceStyle" disabled>Discard changes</button></div>
          @endif
          <p id="geofenceStyleStatus" role="status">Select a municipality to see its saved appearance.</p>
          <p id="geofenceStyleError" role="alert" hidden></p>
        </section>
        <section class="geo-barangays" id="barangayControls" aria-labelledby="barangayHeading" aria-busy="false">
          <h3 id="barangayHeading">Barangay boundaries</h3>
          <label class="geo-check"><input type="checkbox" id="showBarangays" checked disabled aria-describedby="barangayStatus"> Show planning references</label>
          <p id="barangayStatus" role="status">Select a municipality to see available barangay boundaries. The map must finish loading first.</p>
          <p id="barangayEditingNote" hidden>Barangay boundaries are hidden while editing the municipality boundary.</p>
          <div class="geo-barangay-actions">
            <div class="geo-field"><label for="barangaySelect">Barangay</label><select id="barangaySelect" disabled><option value="">All barangays</option></select></div>
            <button type="button" class="geo-btn" id="focusBarangay" disabled>Focus</button>
          </div>
          <button type="button" class="geo-btn" id="retryBarangays" hidden>Try again</button>
          <details id="barangaySource" hidden><summary>Source &amp; accuracy</summary><p id="barangaySourceNote"></p><p><a id="barangaySourceLink" target="_blank" rel="noopener noreferrer">Boundary source</a></p></details>
          <noscript><p>Enable JavaScript to display barangay boundaries.</p></noscript>
        </section>
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
  // Everything the server decides about this workspace. The script itself is
  // public/js/municipality-boundaries.js and holds no Blade syntax.
  window.__municipalityBoundarySettings = {
    key: @json($googleMapsApiKey),
    mapId: @json($googleMapsMapId),
    canManage: @json($canManageBoundaries),
    canChooseMunicipality: @json($canChooseMunicipality),
    assignedMunicipalityId: @json($assignedMunicipality?->id),
    csrf: @json(csrf_token()),
    dataUrl: @json(route('municipality-boundaries.data')),
    barangayUrl: @json(route('municipality-boundaries.barangays')),
    barangayMunicipalityIds: @json($barangayMunicipalityIds),
    storeUrl: @json(route('municipality-boundaries.store')),
    importUrl: @json(route('municipality-boundaries.import')),
    updateTemplate: @json(route('municipality-boundaries.update', ['boundary' => '__ID__'])),
    styleTemplate: @json(route('municipality-boundaries.style', ['boundary' => '__ID__'])),
    activateTemplate: @json(route('municipality-boundaries.activate', ['boundary' => '__ID__'])),
    archiveTemplate: @json(route('municipality-boundaries.archive', ['boundary' => '__ID__'])),
    municipalities: @json($municipalities->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values()),
    initialBoundaries: @json($boundaries->values()),
    initialSummary: @json($summary),
  };
</script>
@php($municipalityBoundaryScriptVersion = @filemtime(public_path('js/municipality-boundaries.js')) ?: 1)
<script src="{{ asset('js/barangay-boundaries.js') }}?v={{ @filemtime(public_path('js/barangay-boundaries.js')) ?: 1 }}"></script>
<script src="{{ asset('js/geofence-style.js') }}?v={{ @filemtime(public_path('js/geofence-style.js')) ?: 1 }}"></script>
<script src="{{ asset('js/municipality-boundaries.js') }}?v={{ $municipalityBoundaryScriptVersion }}"></script>
@endpush
