@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
@endphp

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
            <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>@endforeach</select>@error('municipality_id')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>
          @endif
          <div class="module-form-field module-form-field-full"><label for="name">Cooperative name <span class="module-required">*</span></label><input class="module-input" id="name" type="text" name="name" value="{{ $value('name') }}" maxlength="255" placeholder="Official registered name" autocomplete="organization" required>@error('name')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>
          <div class="module-form-field"><label for="chairperson">Chairperson</label><input class="module-input" id="chairperson" type="text" name="chairperson" value="{{ $value('chairperson') }}" maxlength="255" placeholder="Full name" autocomplete="name"></div>
          <div class="module-form-field"><label for="contact_number">Contact number</label><input class="module-input" id="contact_number" type="tel" name="contact_number" value="{{ $value('contact_number') }}" maxlength="50" placeholder="e.g. 09XX XXX XXXX" autocomplete="tel"></div>
          <div class="module-form-field module-form-field-full"><label for="address">Office or meeting address</label><input class="module-input" id="address" type="text" name="address" value="{{ $value('address') }}" maxlength="255" placeholder="Barangay, municipality, province" autocomplete="street-address"></div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Profile description</h2><p>Add a short operational note that helps staff identify this organization.</p></div></div>
      <div class="module-form-body"><div class="module-form-field module-form-field-full"><label for="description">Description</label><textarea class="module-input" id="description" name="description" rows="5" placeholder="Services, commodities, coverage area, or other useful notes">{{ $value('description') }}</textarea><div class="module-hint">Keep this concise and useful for staff handling membership and reports.</div></div></div>
      <div class="module-form-actions"><a class="module-button" href="{{ route('farmers-cooperatives.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save cooperative' }}</button></div>
    </section>
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
    @if($record)<section class="module-aside-card"><h3>Current membership</h3><p>This cooperative currently has <strong>{{ number_format($record->farmers()->count()) }}</strong> assigned {{ Str::plural('farmer', $record->farmers()->count()) }}. A municipality change requires removing existing assignments first.</p></section>@endif
  </aside>
</div>
