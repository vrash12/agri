@php
  $areaDraft = old('planted_areas', $cropRecord?->planted_areas ?? []);
  if (is_string($areaDraft)) $areaDraft = json_decode($areaDraft, true) ?: [];
  if (!is_array($areaDraft)) $areaDraft = [];
  $areaDraft = array_slice(array_values($areaDraft), 0, 8);
@endphp
<div class="planted-area-editor" id="plantedAreaEditor">
  @if($canDraw)
    <input type="hidden" name="_plot_version" value="{{ old('_plot_version', $plotVersion) }}">
    <input type="hidden" name="planted_areas" id="plantedAreasInput" value="{{ json_encode($areaDraft) }}">
  @endif
  <div class="planted-workspace">
    <div class="planted-map-panel">
      <div class="planted-map-heading"><strong>Your parcel</strong><span><i class="planted-outline-key" aria-hidden="true"></i> White line = parcel boundary</span></div>
      <div id="plantedAreaMap" class="planted-area-map" aria-label="Parcel and planted crop boundaries" aria-describedby="plantedMapStatus"></div>
      <p id="plantedMapStatus" role="status" aria-live="polite">Loading the parcel map…</p>
      <details class="planted-help"><summary>How to mark an area</summary><div><ol><li>Choose the crop and click Start drawing.</li><li>Click at least three corners inside the white outline.</li><li>Finish the boundary, then save the season.</li></ol><p>Separate crops need separate areas. Boundaries may touch, but cannot overlap. Choose Edit area to move existing corners.</p><p>For keyboard drawing, move the map with its arrow controls, then use Add corner at map center. Up to 8 areas, with 50 corners each.</p></div></details>
    </div>
    <aside class="planted-control-panel" aria-label="Crop area controls">
      @if($canDraw)
        <div class="planted-section-form">
          <h3 id="plantedControlHeading">Add a crop area</h3>
          <p class="module-hint" id="plantedControlHint">Which crop is planted here?</p>
          <div class="module-form-field"><label for="plantedCrop">Crop</label><select class="module-input" id="plantedCrop">@foreach($cropChoices as $code => $label) @if($code !== 'not_recorded')<option value="{{ $code }}">{{ $label }}</option>@endif @endforeach</select></div>
          <details class="planted-section-details" id="plantedSectionDetails"><summary>Area name and variety <span>Optional</span></summary><div>
            <div class="module-form-field"><label for="plantedName">Area name</label><input class="module-input" id="plantedName" maxlength="80" placeholder="e.g. Beside the road"></div>
            <div class="module-form-field"><label for="plantedVariety">Seed / crop variety</label><input class="module-input" id="plantedVariety" maxlength="80" placeholder="e.g. Recorded corn variety"></div>
          </div></details>
          <div class="planted-idle-actions" id="plantedIdleActions">
            <button type="button" class="module-button module-button-primary" id="plantedUpdate" disabled hidden>Apply area details</button>
            <button type="button" class="module-button module-button-primary" id="plantedStart" disabled>Start drawing</button>
          </div>
          <div class="planted-draft-actions" id="plantedDraftActions" hidden>
            <button type="button" class="module-button module-button-primary" id="plantedFinish" disabled>Finish boundary</button>
            <div class="planted-secondary-actions"><button type="button" class="module-button" id="plantedUndo" disabled>Undo corner</button><button type="button" class="module-button" id="plantedCancel" disabled>Cancel</button></div>
            <details class="planted-keyboard"><summary>Draw with the keyboard</summary><button type="button" class="module-button" id="plantedCenter" disabled>Add corner at map center</button></details>
            <p class="module-hint">Finish this boundary before saving.</p>
          </div>
        </div>
      @endif
      <section class="planted-sections" aria-labelledby="plantedListHeading"><div class="planted-list-heading"><h3 id="plantedListHeading">Crop areas</h3><span id="plantedAreaCount">{{ count($areaDraft) }}/8</span></div>
        <p id="plantedAreaSummary" class="module-hint" role="status"></p>
        <div id="plantedAreaList" class="planted-area-list" aria-label="Planted crop areas"></div>
      </section>
    </aside>
  </div>
  @if($canDraw)<noscript><p class="module-form-body">Drawing requires JavaScript. Existing saved boundaries are retained when you save the crop summary.</p></noscript>@endif
</div>
<script id="plantedAreaConfig" type="application/json">{!! json_encode(['polygon' => $plot->polygon_json, 'areas' => $areaDraft, 'crops' => $cropChoices, 'colors' => \App\Models\ParcelCropSeason::COLORS, 'canDraw' => $canDraw, 'key' => config('services.google_maps.key')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@if($includeScripts ?? true)
@push('scripts')
<script src="{{ asset('js/map-drawing-guide.js') }}?v={{ @filemtime(public_path('js/map-drawing-guide.js')) ?: 1 }}" defer></script>
<script src="{{ asset('js/crop-area-badges.js') }}?v={{ @filemtime(public_path('js/crop-area-badges.js')) ?: 1 }}" defer></script>
<script src="{{ asset('js/planted-area-editor.js') }}?v={{ @filemtime(public_path('js/planted-area-editor.js')) ?: 1 }}" defer></script>
@endpush
@endif
