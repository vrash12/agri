@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $memberCount = $record ? ($record->farmers_count ?? ($record->relationLoaded('farmers') ? $record->farmers->count() : $record->farmers()->count())) : 0;
@endphp

@include('partials.record-version', ['record' => $record])

@if($errors->any())
  <div class="module-alert module-alert-error"><strong>Please review the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Cooperative identity</h2><p>Enter the official organization and office ownership information.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          @if($canChooseMunicipality ?? false)
            <x-module.field name="municipality_id" label="Municipality" :required="true" :full="true">
              <select class="module-input" id="municipality_id" name="municipality_id" required aria-describedby="municipality_id_error">
                <option value="">Select municipality</option>
                @foreach(($municipalities ?? []) as $municipality)
                  <option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>
                @endforeach
              </select>
            </x-module.field>
          @endif
          <x-module.field name="name" label="Cooperative name" :required="true" :full="true">
            <input class="module-input" id="name" type="text" name="name" value="{{ $value('name') }}" maxlength="255" placeholder="Official registered name" autocomplete="organization" required aria-describedby="name_error">
          </x-module.field>
          <x-module.field name="chairperson" label="Chairperson">
            <input class="module-input" id="chairperson" type="text" name="chairperson" value="{{ $value('chairperson') }}" maxlength="255" placeholder="Full name" autocomplete="name" aria-describedby="chairperson_error">
          </x-module.field>
          <x-module.field name="contact_number" label="Contact number">
            <input class="module-input" id="contact_number" type="tel" name="contact_number" value="{{ $value('contact_number') }}" maxlength="50" placeholder="e.g. 09XX XXX XXXX" autocomplete="tel" aria-describedby="contact_number_error">
          </x-module.field>
          <x-module.field name="address" label="Office or meeting address" :full="true">
            <input class="module-input" id="address" type="text" name="address" value="{{ $value('address') }}" maxlength="255" placeholder="Barangay, municipality, province" autocomplete="street-address" aria-describedby="address_error">
          </x-module.field>
        </div>
      </div>
    </section>

    <details class="module-more" @if(filled($value('description')) || $errors->has('description')) open @endif>
      <summary>Profile description <span class="module-hint">Optional</span></summary>
      <div class="module-form-body">
        <x-module.field name="description" label="Description" :full="true" hint="Keep this concise and useful for staff handling membership and reports.">
          <textarea class="module-input" id="description" name="description" rows="5" placeholder="Services, commodities, coverage area, or other useful notes" aria-describedby="description_hint description_error">{{ $value('description') }}</textarea>
        </x-module.field>
      </div>
    </details>
      <div class="module-form-actions"><a class="module-button" href="{{ route('farmers-cooperatives.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save cooperative' }}</button></div>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card"><h3>What happens next?</h3><ol><li>Save the cooperative profile.</li><li>Select farmers from the same municipality.</li><li>Export the final membership workbook when needed.</li></ol></section>
    <section class="module-aside-card"><h3>Record ownership</h3><p>
      @if($canChooseMunicipality ?? false)
        Choose the municipality responsible for this cooperative. Assigned farmers must belong to the same municipality.
      @else
        This profile will be saved under <strong>{{ auth()->user()->municipality?->name ?? 'your municipal office' }}</strong>.
      @endif
    </p></section>
    @if($record)<section class="module-aside-card"><h3>Current membership</h3><p>This cooperative currently has <strong>{{ number_format($memberCount) }}</strong> assigned {{ Str::plural('farmer', $memberCount) }}. A municipality change requires removing existing assignments first.</p></section>@endif
  </aside>
</div>
