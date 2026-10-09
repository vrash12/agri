@php
  $savedAreas = old('planted_areas', $cropRecord?->planted_areas ?? []);
  if (is_string($savedAreas)) $savedAreas = json_decode($savedAreas, true) ?: [];
  $hasAreas = is_array($savedAreas) && count($savedAreas) > 0;
@endphp
<div class="crop-modal-content" data-crop-modal-content>
  <div class="crop-modal-context" aria-label="Crop season details">
    <span>{{ number_format((float) $plot->area_ha, 2) }} ha parcel</span>
    <span>{{ $year }} · {{ $seasonChoices[$season] }}</span>
    <span>{{ $canEdit ? 'Editing enabled' : 'Read-only view' }}</span>
  </div>

  @if($canEdit)
    <form id="seasonalCropForm" method="POST" action="{{ route('farm-plots.seasonal-crops.store', $plot) }}" data-crop-modal-form>
      @csrf
      <input type="hidden" name="crop_year" value="{{ $year }}">
      <input type="hidden" name="season" value="{{ $season }}">
      <input type="hidden" name="_record_version" value="{{ old('_record_version', $recordVersion) }}">
      @if($plantedAreasAvailable)
        @include('farm_plots.partials.planted-area-editor', ['canDraw' => true, 'includeScripts' => false])
      @else
        <p class="module-form-body crop-record-note">Drawing is unavailable. You can still choose a crop for the whole parcel below.</p>
      @endif
      <details class="crop-record-details" @if(!$hasAreas) open @endif>
        <summary>Season notes and whole-parcel crop</summary>
        <div class="module-form-body module-form-grid">
          <x-module.field name="crop" label="Crop for the whole parcel" :required="true" :full="true" hint="If you draw areas, this follows their crops automatically. Without drawings, choose the crop here.">
            <select class="module-input" id="crop" name="crop" required>
              @foreach($cropChoices as $code => $label)<option value="{{ $code }}" @selected(old('crop', $cropRecord?->crop ?? 'not_recorded') === $code)>{{ $label }}</option>@endforeach
            </select>
          </x-module.field>
          <x-module.field name="notes" label="Notes for this season" :full="true" hint="Optional: field visit date or how the crops were confirmed. Leave out private contact details.">
            <textarea class="module-input" name="notes" id="notes" maxlength="500" rows="3">{{ old('notes', $cropRecord?->notes) }}</textarea>
          </x-module.field>
        </div>
      </details>
      <div class="crop-modal-save-bar">
        <p id="plantedSaveState">{{ $cropRecord ? 'No changes yet.' : 'Nothing saved for this season yet.' }}</p>
        <button class="module-button module-button-primary" type="submit" data-crop-modal-save>Save season</button>
      </div>
    </form>
  @else
    @if($plantedAreasAvailable)
      @include('farm_plots.partials.planted-area-editor', ['canDraw' => false, 'includeScripts' => false])
    @endif
    <div class="module-form-body crop-readonly-note">
      <strong>Recorded crop:</strong> {{ $cropChoices[$cropRecord?->crop ?? 'not_recorded'] ?? 'Not recorded' }}
      @if($cropRecord?->notes)<p>{{ $cropRecord->notes }}</p>@endif
      <p>Your account can view this record. Agricultural staff can update it.</p>
    </div>
  @endif
</div>
