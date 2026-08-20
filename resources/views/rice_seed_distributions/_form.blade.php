@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $selectedFarmerId = old('farmer_id', $record->farmer_id ?? ($farmer_id ?? ''));
  $fallbackName = trim(($record->last_name ?? '').', '.($record->first_name ?? '').' '.($record->middle_name ?? '').' '.($record->ext_name ?? ''), ', ');
  $fallbackTags = collect(['ARB' => $record?->is_arb, '4Ps' => $record?->is_4ps, 'IP' => $record?->is_ip, 'PWD' => $record?->is_pwd, 'SC' => $record?->is_sc, 'OFW' => $record?->is_ofw])->filter()->keys()->implode(', ');
  $selectedInputCategory = old('input_category', $record->input_category ?? 'rice_seed');
  $selectedQuantityUnit = old('quantity_unit', $record->quantity_unit ?? 'kg');
@endphp

@push('styles')
<style>
  .rice-farmer-preview{display:none;margin-top:13px}.rice-farmer-preview.is-visible{display:block}.rice-preview-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}.rice-preview-heading strong{font-size:11px}.rice-preview-heading span{color:var(--module-green);font-size:9px;font-weight:850}.rice-form-summary-value{display:block;margin-top:4px;color:var(--module-ink);font-size:16px;font-weight:850}.rice-monitoring>summary{border-bottom:0}.rice-monitoring[open]>summary{border-bottom:1px solid var(--module-border)}.rice-monitoring .module-more-content{padding:15px}.rice-input-callout{display:flex;gap:10px;align-items:flex-start;margin-bottom:15px;padding:12px 13px;border:1px solid #cce7d5;border-radius:10px;background:#f2faf5;color:#28523a}.rice-input-callout svg{width:18px;height:18px;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:1.8}.rice-input-callout strong{display:block;margin-bottom:2px;color:#18442b;font-size:10px}.rice-input-callout span{display:block;font-size:9px;line-height:1.45}.rice-subsection{grid-column:1/-1;padding:13px;border:1px solid var(--module-border);border-radius:10px;background:#fafcfb}.rice-subsection-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:11px}.rice-subsection-head strong{font-size:10px}.rice-subsection-head span{color:var(--module-muted);font-size:9px}.rice-subsection[hidden]{display:none}.rice-subsection .module-form-grid{margin:0}.rice-category-preview{display:inline-flex;align-items:center;margin-top:6px;padding:4px 7px;border-radius:999px;background:#eaf7ee;color:#17643a;font-size:8px;font-weight:850;text-transform:uppercase;letter-spacing:.04em}
</style>
@endpush

@if($errors->any())
  <div class="module-alert module-alert-error"><strong>Please review the distribution information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Select the recipient</h2><p>The farmer profile supplies identity, farm location, and eligibility information automatically.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          @if($canChooseMunicipality ?? false)
            <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input js-select" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>@endforeach</select><div class="module-hint">The selected farmer must belong to this municipality.</div></div>
          @endif
          <div class="module-form-field module-form-field-full"><label for="farmer_id">Farmer <span class="module-required">*</span></label><select class="module-input js-select" id="farmer_id" name="farmer_id" required><option value="">Search and select farmer</option>
            @foreach($farmers as $farmer)
              @php
                $fullName = trim($farmer->last_name.', '.$farmer->first_name.' '.($farmer->middle_name ?? '').' '.($farmer->ext_name ?? ''));
                $tags = collect(['ARB' => $farmer->is_arb, '4Ps' => $farmer->is_4ps, 'IP' => $farmer->is_ip, 'PWD' => $farmer->is_pwd, 'SC' => $farmer->is_sc, 'OFW' => $farmer->is_ofw])->filter()->keys()->implode(', ');
              @endphp
              <option value="{{ $farmer->id }}" data-municipality-id="{{ $farmer->municipality_id }}" data-name="{{ $fullName }}" data-ffrs="{{ $farmer->ffrs ?: ($farmer->rsbsa_no ?: 'Not assigned') }}" data-location="{{ $farmer->farm_location ?: 'Not recorded' }}" data-municipality="{{ $farmer->farm_municipality ?: 'Not recorded' }}" data-province="{{ $farmer->farm_province ?: 'Not recorded' }}" data-area="{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'Not recorded' }}" data-contact="{{ $farmer->contact_number ?: 'Not recorded' }}" data-tags="{{ $tags ?: 'None' }}" @selected((string) $selectedFarmerId === (string) $farmer->id)>{{ $fullName }}{{ $farmer->ffrs ? ' — '.$farmer->ffrs : '' }}</option>
            @endforeach
          </select><div class="module-hint">Search by farmer name or FFRS number.</div></div>
        </div>

        <div id="farmerPreviewFallback" data-name="{{ $fallbackName ?: 'No farmer selected' }}" data-ffrs="{{ $record->ffrs ?? 'Not assigned' }}" data-location="{{ $record->farm_location ?? 'Not recorded' }}" data-municipality="{{ $record->farm_municipality ?? 'Not recorded' }}" data-province="{{ $record->farm_province ?? 'Not recorded' }}" data-area="{{ isset($record->farm_area_ha) ? number_format((float) $record->farm_area_ha, 2).' ha' : 'Not recorded' }}" data-contact="{{ $record->contact_number ?? 'Not recorded' }}" data-tags="{{ $fallbackTags ?: 'None' }}" hidden></div>
        <div class="rice-farmer-preview" id="riceFarmerPreview">
          <div class="rice-preview-heading"><strong id="farmer_preview_name">No farmer selected</strong><span>Profile information will be copied</span></div>
          <div class="module-preview-grid">
            <div class="module-preview-item"><span>FFRS / RSBSA</span><strong id="farmer_preview_ffrs">—</strong></div>
            <div class="module-preview-item"><span>Farm area</span><strong id="farmer_preview_area">—</strong></div>
            <div class="module-preview-item"><span>Contact</span><strong id="farmer_preview_contact">—</strong></div>
            <div class="module-preview-item module-preview-item-wide"><span>Farm location</span><strong id="farmer_preview_location">—</strong></div>
            <div class="module-preview-item module-preview-item-wide"><span>Eligibility tags</span><strong id="farmer_preview_tags">None</strong></div>
          </div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Choose the seed or farm input</h2><p>Record rice and other seeds, fertilizer (abono), soil amendments, or another agricultural input.</p></div></div>
      <div class="module-form-body">
        <div class="rice-input-callout"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v6M12 7h.01"></path></svg><div><strong>Cannot find the item?</strong><span>Type the exact seed variety, fertilizer brand, or farm input in the item field. Suggested choices are provided, but new names are always accepted.</span></div></div>
        <div class="module-form-grid">
          <div class="module-form-field"><label for="input_category">Input category <span class="module-required">*</span></label><select class="module-input js-select" id="input_category" name="input_category" required>@foreach(($inputCategoryOptions ?? []) as $valueKey => $label)<option value="{{ $valueKey }}" @selected($selectedInputCategory === $valueKey)>{{ $label }}</option>@endforeach</select><div class="module-hint">Choose what kind of assistance was released.</div></div>
          <div class="module-form-field"><label for="seed_variety_claimed">Item, product, or variety <span class="module-required">*</span></label><input class="module-input" id="seed_variety_claimed" name="seed_variety_claimed" list="distributionItemSuggestions" value="{{ $value('seed_variety_claimed') }}" maxlength="200" autocomplete="off" placeholder="Select a suggestion or type another item" required><datalist id="distributionItemSuggestions"></datalist><div class="module-hint">Custom seed and fertilizer names are accepted.</div></div>
          <div class="module-form-field module-form-field-third"><label for="kgs_received">Quantity released <span class="module-required">*</span></label><input class="module-input" id="kgs_received" type="number" step="0.01" min="0.01" name="kgs_received" value="{{ $value('kgs_received') }}" placeholder="0.00" required></div>
          <div class="module-form-field module-form-field-third"><label for="quantity_unit">Unit <span class="module-required">*</span></label><select class="module-input js-select" id="quantity_unit" name="quantity_unit" required>@foreach(($quantityUnitOptions ?? []) as $valueKey => $label)<option value="{{ $valueKey }}" @selected($selectedQuantityUnit === $valueKey)>{{ $label }}</option>@endforeach</select></div>
          <div class="module-form-field module-form-field-third"><label for="date_received">Date received <span class="module-required">*</span></label><input class="module-input" id="date_received" type="date" name="date_received" value="{{ $value('date_received', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
          <div class="module-form-field module-form-field-full"><label for="lot_series">Lot or batch reference</label><textarea class="module-input" id="lot_series" name="lot_series" rows="2" placeholder="Enter seed lot, fertilizer batch, or reference numbers">{{ $value('lot_series') }}</textarea></div>
          <div class="module-form-field module-form-field-full"><label for="input_notes">Release notes</label><textarea class="module-input" id="input_notes" name="input_notes" rows="3" maxlength="1000" placeholder="Brand, formulation, purpose, instructions, or other useful details">{{ $value('input_notes') }}</textarea></div>

          <div class="rice-subsection" id="riceSeedSpecificFields">
            <div class="rice-subsection-head"><strong>Seed-specific information</strong><span>Only shown for seed categories</span></div>
            <div class="module-form-grid">
              <div class="module-form-field module-form-field-third"><label for="claimed_area_ha">Claimed area (ha)</label><input class="module-input" id="claimed_area_ha" type="number" step="0.01" min="0" name="claimed_area_ha" value="{{ $value('claimed_area_ha') }}" placeholder="0.00"></div>
              <div class="module-form-field module-form-field-third"><label for="claimed_seeds_kg">Claimed seed (kg)</label><input class="module-input" id="claimed_seeds_kg" type="number" step="0.01" min="0" name="claimed_seeds_kg" value="{{ $value('claimed_seeds_kg') }}" placeholder="0.00"></div>
              <div class="module-form-field module-form-field-third"><label for="crop_establishment">Crop establishment</label><select class="module-input js-select" id="crop_establishment" name="crop_establishment"><option value="">Select method</option>@foreach($cropEstablishmentOptions as $option)<option value="{{ $option }}" @selected($value('crop_establishment') === $option)>{{ $option }}</option>@endforeach</select></div>
              <div class="module-form-field"><label for="seed_class">Seed class</label><select class="module-input js-select" id="seed_class" name="seed_class"><option value="">Select class</option>@foreach($seedClassOptions as $option)<option value="{{ $option }}" @selected($value('seed_class') === $option)>{{ $option }}</option>@endforeach</select></div>
              <div class="module-form-field"><label for="date_of_sowing_label">Sowing schedule</label><input class="module-input" id="date_of_sowing_label" name="date_of_sowing_label" value="{{ $value('date_of_sowing_label') }}" maxlength="60" placeholder="e.g. Third week of June"></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <details class="module-more rice-monitoring" id="riceProductionMonitoring" @if(filled($value('avg_weight_per_bag_kg')) || filled($value('total_production_bags')) || filled($value('avg_area_harvested_ha')) || filled($value('seed_variety_planted'))) open @endif>
      <summary>Production monitoring <span style="color:var(--module-muted);font-size:9px;font-weight:600">Optional follow-up information</span></summary>
      <div class="module-more-content"><div class="module-form-grid">
        <div class="module-form-field module-form-field-third"><label for="avg_weight_per_bag_kg">Average bag weight (kg)</label><input class="module-input" id="avg_weight_per_bag_kg" type="number" min="0" name="avg_weight_per_bag_kg" value="{{ $value('avg_weight_per_bag_kg') }}"></div>
        <div class="module-form-field module-form-field-third"><label for="total_production_bags">Production bags</label><input class="module-input" id="total_production_bags" type="number" min="0" name="total_production_bags" value="{{ $value('total_production_bags') }}"></div>
        <div class="module-form-field module-form-field-third"><label for="avg_area_harvested_ha">Harvested area (ha)</label><input class="module-input" id="avg_area_harvested_ha" type="number" min="0" step="0.01" name="avg_area_harvested_ha" value="{{ $value('avg_area_harvested_ha') }}"></div>
        <div class="module-form-field module-form-field-full"><label for="seed_variety_planted">Seed variety planted</label><input class="module-input" id="seed_variety_planted" name="seed_variety_planted" value="{{ $value('seed_variety_planted') }}" maxlength="200" placeholder="e.g. NSIC Rc 222"></div>
      </div></div>
    </details>

    <section class="module-form-section"><div class="module-form-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save release' }}</button></div></section>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card"><h3>Release summary</h3><p>Recipient</p><span class="rice-form-summary-value" id="riceSummaryFarmer">Not selected</span><span class="rice-category-preview" id="riceSummaryCategory">Rice seed</span><p style="margin-top:12px">Item and quantity</p><span class="rice-form-summary-value" id="riceSummaryRelease">Not entered</span><p style="margin-top:12px">Date</p><span class="rice-form-summary-value" id="riceSummaryDate">Not entered</span></section>
    <section class="module-aside-card"><h3>Before saving</h3><ol><li>Confirm the correct farmer profile.</li><li>Choose the correct input category.</li><li>Verify the item, quantity, unit, and date.</li><li>Add a lot, batch, or note when available.</li></ol></section>
    <section class="module-aside-card"><h3>Municipality ownership</h3><p>
      @if($canChooseMunicipality ?? false)
        The farmer and distribution must belong to the selected municipality.
      @else
        This release is automatically assigned to <strong>{{ auth()->user()->municipality?->name ?? 'your municipal office' }}</strong>.
      @endif
    </p></section>
  </aside>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const farmer = document.getElementById('farmer_id');
  const municipality = document.getElementById('municipality_id');
  const fallback = document.getElementById('farmerPreviewFallback');
  const preview = document.getElementById('riceFarmerPreview');
  const inputCategory = document.getElementById('input_category');
  const seed = document.getElementById('seed_variety_claimed');
  const kilograms = document.getElementById('kgs_received');
  const quantityUnit = document.getElementById('quantity_unit');
  const receivedDate = document.getElementById('date_received');
  const suggestions = document.getElementById('distributionItemSuggestions');
  const seedFields = document.getElementById('riceSeedSpecificFields');
  const productionMonitoring = document.getElementById('riceProductionMonitoring');
  const inputSuggestions = @json($inputSuggestions ?? []);
  const inputCategoryLabels = @json($inputCategoryOptions ?? []);
  const quantityUnitLabels = @json($quantityUnitOptions ?? []);
  const farmerChoices = farmer ? Array.from(farmer.options).filter(option => option.value).map(option => ({
    value: option.value,
    text: option.textContent.trim(),
    municipalityId: option.dataset.municipalityId || '',
    dataset: { ...option.dataset }
  })) : [];
  const farmerChoiceById = new Map(farmerChoices.map(choice => [String(choice.value), choice]));
  const setText = (id,value,fallbackText='—') => { const element=document.getElementById(id); if(element) element.textContent=value && String(value).trim() ? value : fallbackText; };
  const selectedOption = () => farmerChoiceById.get(String(farmer?.value || ''));
  const applyFarmer = source => {
    const hasFarmer = Boolean(source && (source.value || source.dataset.name !== 'No farmer selected'));
    preview?.classList.toggle('is-visible', hasFarmer);
    setText('farmer_preview_name',source?.dataset.name,'No farmer selected'); setText('farmer_preview_ffrs',source?.dataset.ffrs); setText('farmer_preview_area',source?.dataset.area); setText('farmer_preview_contact',source?.dataset.contact); setText('farmer_preview_location',source ? `${source.dataset.location || 'Not recorded'} · ${source.dataset.municipality || ''}, ${source.dataset.province || ''}` : ''); setText('farmer_preview_tags',source?.dataset.tags,'None'); setText('riceSummaryFarmer',source?.dataset.name,'Not selected');
  };
  const refreshFarmer = () => { const selected=selectedOption(); applyFarmer(selected?.value ? selected : fallback); };
  const filterFarmers = () => {
    if (!municipality || !farmer) return;
    const municipalityId = municipality.value;
    const available = farmerChoices.filter(choice => !municipalityId || choice.municipalityId === municipalityId);
    const currentValue = String(farmer.value || '');
    const currentIsAvailable = available.some(choice => String(choice.value) === currentValue);

    if (farmer.tomselect) {
      farmer.tomselect.clear(true);
      farmer.tomselect.clearOptions();
      farmer.tomselect.addOptions(available.map(choice => ({ value: choice.value, text: choice.text })));
      farmer.tomselect.refreshOptions(false);
      if (currentIsAvailable) farmer.tomselect.setValue(currentValue, true);
    } else {
      Array.from(farmer.options).forEach(option => {
        if (!option.value) return;
        option.hidden = Boolean(municipalityId) && option.dataset.municipalityId !== municipalityId;
        option.disabled = option.hidden;
      });
      if (!currentIsAvailable) farmer.value = '';
    }
  };
  const isSeedCategory = () => String(inputCategory?.value || '').endsWith('_seed');
  const refreshSuggestions = () => {
    if (!suggestions) return;
    suggestions.replaceChildren();
    (inputSuggestions[inputCategory?.value] || []).forEach(item => {
      const option = document.createElement('option');
      option.value = item;
      suggestions.appendChild(option);
    });
  };
  const refreshCategoryFields = () => {
    const showSeedFields = isSeedCategory();
    if (seedFields) {
      seedFields.hidden = !showSeedFields;
      seedFields.querySelectorAll('input,select,textarea').forEach(field => { field.disabled = !showSeedFields; });
    }
    if (productionMonitoring) {
      productionMonitoring.hidden = !showSeedFields;
      productionMonitoring.querySelectorAll('input,select,textarea').forEach(field => { field.disabled = !showSeedFields; });
    }
    setText('riceSummaryCategory', inputCategoryLabels[inputCategory?.value], 'Farm input');
    refreshSuggestions();
  };
  const refreshRelease = () => {
    const item=seed?.value || '';
    const quantity=kilograms?.value || '';
    const unit=quantityUnit?.value || 'kg';
    const unitLabel=quantityUnitLabels[unit] || unit;
    setText('riceSummaryRelease', item && quantity ? `${item} · ${Number(quantity).toLocaleString(undefined,{maximumFractionDigits:2})} ${unitLabel}` : item || (quantity ? `${quantity} ${unitLabel}` : ''),'Not entered');
    setText('riceSummaryDate',receivedDate?.value ? new Date(`${receivedDate.value}T00:00:00`).toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'}) : '','Not entered');
  };
  farmer?.addEventListener('change',refreshFarmer); seed?.addEventListener('input',refreshRelease); kilograms?.addEventListener('input',refreshRelease); quantityUnit?.addEventListener('change',refreshRelease); receivedDate?.addEventListener('change',refreshRelease);
  inputCategory?.addEventListener('change',() => { refreshCategoryFields(); refreshRelease(); });
  municipality?.addEventListener('change',() => { filterFarmers(); refreshFarmer(); });
  filterFarmers(); refreshFarmer(); refreshCategoryFields(); refreshRelease();
});
</script>
@endpush
