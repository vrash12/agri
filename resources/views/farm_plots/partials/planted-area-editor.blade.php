@php
  $areaDraft = old('planted_areas', $cropRecord?->planted_areas ?? []);
  if (is_string($areaDraft)) $areaDraft = json_decode($areaDraft, true) ?: [];
  if (!is_array($areaDraft)) $areaDraft = [];
  $areaDraft = array_slice(array_values($areaDraft), 0, 8);
@endphp
<div class="module-form-body planted-area-editor" id="plantedAreaEditor">
  <h3>{{ $canDraw ? 'Draw the planted areas' : 'Recorded planted areas' }}</h3>
  <p>Keep the parcel outline as the outside boundary. Draw a smaller boundary for each planted section, such as Corn and Rice. Areas may touch but cannot overlap. These are recorded planning areas, not proof of current growth.</p>
  @if($canDraw)
    <input type="hidden" name="_plot_version" value="{{ old('_plot_version', $plotVersion) }}">
    <input type="hidden" name="planted_areas" id="plantedAreasInput" value="{{ json_encode($areaDraft) }}">
    <div class="module-form-grid">
      <div class="module-form-field"><label for="plantedCrop">Crop in this section</label><select class="module-input" id="plantedCrop">@foreach($cropChoices as $code => $label) @if($code !== 'not_recorded')<option value="{{ $code }}">{{ $label }}</option>@endif @endforeach</select></div>
      <div class="module-form-field"><label for="plantedName">Section name <span class="module-hint">Optional</span></label><input class="module-input" id="plantedName" maxlength="80" placeholder="e.g. Corn beside the road"></div>
      <div class="module-form-field"><label for="plantedVariety">Seed / crop variety <span class="module-hint">Optional</span></label><input class="module-input" id="plantedVariety" maxlength="80" placeholder="Enter the recorded variety"></div>
    </div>
    <div class="module-actions planted-draw-actions">
      <button type="button" class="module-button module-button-primary" id="plantedStart" disabled>Draw this crop area</button>
      <button type="button" class="module-button" id="plantedFinish" disabled>Finish boundary</button>
      <button type="button" class="module-button" id="plantedUndo" disabled>Undo last point</button>
      <button type="button" class="module-button" id="plantedCenter" disabled>Add map center as corner</button>
      <button type="button" class="module-button" id="plantedCancel" disabled>Cancel drawing</button>
      <button type="button" class="module-button" id="plantedUpdate" disabled>Update selected section</button>
    </div>
  @endif
  <p id="plantedMapStatus" role="status" aria-live="polite">Loading the parcel map…</p>
  <div id="plantedAreaMap" class="planted-area-map" aria-label="Parcel and planted crop boundaries"></div>
  <p class="module-hint">Click or tap to add corners, then finish the boundary. For keyboard entry, move the map with its arrow controls and add the map center as a corner. Choose a saved section to adjust its points. Up to 8 areas, 50 corners per area. Unmarked land remains unclassified. Mapped hectares are approximate and are calculated when saved.</p>
  <div id="plantedAreaList" class="planted-area-list" aria-label="Planted crop areas"></div>
  <p id="plantedAreaSummary" role="status"></p>
  @if($canDraw)<noscript><p>Drawing requires JavaScript. Existing saved boundaries are retained when you save the crop summary.</p></noscript>@endif
</div>
@push('styles')
<style>
  .planted-area-editor .planted-area-map{height:440px;width:100%;margin:14px 0;border:1px solid var(--ui-control-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface-subtle)}
  .planted-area-editor p{line-height:1.5}.planted-area-editor .planted-draw-actions{margin-top:12px;gap:8px;flex-wrap:wrap}
  .planted-area-editor .planted-area-list{display:grid;gap:10px}.planted-area-editor .planted-area-item{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:12px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel)}
  .planted-area-editor .planted-area-item p{margin:0;flex:1 1 180px}.planted-area-editor .planted-area-item button{min-height:44px}.planted-area-editor .planted-area-swatch{width:18px;height:18px;flex:none;border:1px solid var(--ui-text);border-radius:3px}
  @media(max-width:600px){.planted-area-editor .planted-area-map{height:350px}.planted-area-editor .module-actions button{flex:1 1 140px}}
</style>
@endpush
@push('scripts')
<script id="plantedAreaConfig" type="application/json">{!! json_encode(['polygon' => $plot->polygon_json, 'areas' => $areaDraft, 'crops' => $cropChoices, 'colors' => \App\Models\ParcelCropSeason::COLORS, 'canDraw' => $canDraw, 'key' => config('services.google_maps.key')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/planted-area-editor.js') }}?v={{ @filemtime(public_path('js/planted-area-editor.js')) ?: 1 }}" defer></script>
@endpush
