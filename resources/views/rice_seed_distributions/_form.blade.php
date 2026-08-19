@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $selectedFarmerId = old('farmer_id', $record->farmer_id ?? ($farmer_id ?? ''));
  $fallbackName = trim(($record->last_name ?? '').', '.($record->first_name ?? '').' '.($record->middle_name ?? '').' '.($record->ext_name ?? ''), ', ');
  $fallbackTags = collect(['ARB' => $record?->is_arb, '4Ps' => $record?->is_4ps, 'IP' => $record?->is_ip, 'PWD' => $record?->is_pwd, 'SC' => $record?->is_sc, 'OFW' => $record?->is_ofw])->filter()->keys()->implode(', ');
@endphp

@push('styles')
<style>
  .rice-farmer-preview{display:none;margin-top:13px}.rice-farmer-preview.is-visible{display:block}.rice-preview-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}.rice-preview-heading strong{font-size:11px}.rice-preview-heading span{color:var(--module-green);font-size:9px;font-weight:850}.rice-form-summary-value{display:block;margin-top:4px;color:var(--module-ink);font-size:16px;font-weight:850}.rice-monitoring>summary{border-bottom:0}.rice-monitoring[open]>summary{border-bottom:1px solid var(--module-border)}.rice-monitoring .module-more-content{padding:15px}
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
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Record the seed release</h2><p>Required release details are grouped first; claim context follows below.</p></div></div>
      <div class="module-form-body"><div class="module-form-grid">
        <div class="module-form-field module-form-field-full"><label for="seed_variety_claimed">Seed variety issued <span class="module-required">*</span></label><select class="module-input js-select" id="seed_variety_claimed" name="seed_variety_claimed" required><option value="">Select seed variety</option>@foreach($seedVarietyClaimedOptions as $option)<option value="{{ $option }}" @selected($value('seed_variety_claimed') === $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-form-field"><label for="kgs_received">Quantity released (kg) <span class="module-required">*</span></label><input class="module-input" id="kgs_received" type="number" step="0.01" min="0.01" name="kgs_received" value="{{ $value('kgs_received') }}" placeholder="0.00" required></div>
        <div class="module-form-field"><label for="date_received">Date received <span class="module-required">*</span></label><input class="module-input" id="date_received" type="date" name="date_received" value="{{ $value('date_received', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
        <div class="module-form-field module-form-field-third"><label for="claimed_area_ha">Claimed area (ha)</label><input class="module-input" id="claimed_area_ha" type="number" step="0.01" min="0" name="claimed_area_ha" value="{{ $value('claimed_area_ha') }}" placeholder="0.00"></div>
        <div class="module-form-field module-form-field-third"><label for="claimed_seeds_kg">Claimed seed (kg)</label><input class="module-input" id="claimed_seeds_kg" type="number" step="0.01" min="0" name="claimed_seeds_kg" value="{{ $value('claimed_seeds_kg') }}" placeholder="0.00"></div>
        <div class="module-form-field module-form-field-third"><label for="crop_establishment">Crop establishment</label><select class="module-input js-select" id="crop_establishment" name="crop_establishment"><option value="">Select method</option>@foreach($cropEstablishmentOptions as $option)<option value="{{ $option }}" @selected($value('crop_establishment') === $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-form-field"><label for="seed_class">Seed class</label><select class="module-input js-select" id="seed_class" name="seed_class"><option value="">Select class</option>@foreach($seedClassOptions as $option)<option value="{{ $option }}" @selected($value('seed_class') === $option)>{{ $option }}</option>@endforeach</select></div>
        <div class="module-form-field"><label for="date_of_sowing_label">Sowing schedule</label><input class="module-input" id="date_of_sowing_label" name="date_of_sowing_label" value="{{ $value('date_of_sowing_label') }}" maxlength="60" placeholder="e.g. Third week of June"></div>
        <div class="module-form-field module-form-field-full"><label for="lot_series">Lot series</label><textarea class="module-input" id="lot_series" name="lot_series" rows="3" placeholder="Enter lot codes, separated by commas or new lines">{{ $value('lot_series') }}</textarea></div>
      </div></div>
    </section>

    <details class="module-more rice-monitoring" @if(filled($value('avg_weight_per_bag_kg')) || filled($value('total_production_bags')) || filled($value('avg_area_harvested_ha')) || filled($value('seed_variety_planted'))) open @endif>
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
    <section class="module-aside-card"><h3>Release summary</h3><p>Recipient</p><span class="rice-form-summary-value" id="riceSummaryFarmer">Not selected</span><p style="margin-top:12px">Seed and quantity</p><span class="rice-form-summary-value" id="riceSummaryRelease">Not entered</span><p style="margin-top:12px">Date</p><span class="rice-form-summary-value" id="riceSummaryDate">Not entered</span></section>
    <section class="module-aside-card"><h3>Before saving</h3><ol><li>Confirm the correct farmer profile.</li><li>Verify variety, quantity, and received date.</li><li>Add lot or claim information when available.</li><li>Use production monitoring during follow-up.</li></ol></section>
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
  const seed = document.getElementById('seed_variety_claimed');
  const kilograms = document.getElementById('kgs_received');
  const receivedDate = document.getElementById('date_received');
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
  const refreshRelease = () => { const variety=seed?.value || ''; const quantity=kilograms?.value || ''; setText('riceSummaryRelease', variety && quantity ? `${variety} · ${Number(quantity).toLocaleString(undefined,{maximumFractionDigits:2})} kg` : variety || (quantity ? `${quantity} kg` : ''),'Not entered'); setText('riceSummaryDate',receivedDate?.value ? new Date(`${receivedDate.value}T00:00:00`).toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'}) : '','Not entered'); };
  farmer?.addEventListener('change',refreshFarmer); seed?.addEventListener('change',refreshRelease); kilograms?.addEventListener('input',refreshRelease); receivedDate?.addEventListener('change',refreshRelease);
  municipality?.addEventListener('change',() => { filterFarmers(); refreshFarmer(); });
  filterFarmers(); refreshFarmer(); refreshRelease();
});
</script>
@endpush
