@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $selectedFarmerId = old('farmer_id', $record->farmer_id ?? ($farmer_id ?? ''));
  $fallbackName = trim(($record->last_name ?? '').', '.($record->first_name ?? '').' '.($record->middle_name ?? '').' '.($record->ext_name ?? ''), ', ');
  $fallbackTags = collect(['ARB' => $record?->is_arb, '4Ps' => $record?->is_4ps, 'IP' => $record?->is_ip, 'PWD' => $record?->is_pwd, 'SC' => $record?->is_sc, 'OFW' => $record?->is_ofw])->filter()->keys()->implode(', ');
  $selectedInputCategory = old('input_category', $record->input_category ?? ($defaultInputCategory ?? 'rice_seed'));
  $selectedQuantityUnit = old('quantity_unit', $record->quantity_unit ?? (($preferredUnitsByCategory ?? [])[$selectedInputCategory] ?? 'kg'));
  $categoryMeta = [
    'rice_seed' => ['code' => 'RS', 'description' => 'Palay varieties and certified seed', 'sector' => 'agriculture'],
    'corn_seed' => ['code' => 'CS', 'description' => 'Yellow, white, or hybrid corn', 'sector' => 'agriculture'],
    'vegetable_seed' => ['code' => 'VS', 'description' => 'Vegetable seed and planting packs', 'sector' => 'agriculture'],
    'fertilizer' => ['code' => 'AB', 'description' => 'Fertilizer, abono, and formulations', 'sector' => 'agriculture'],
    'soil_amendment' => ['code' => 'SA', 'description' => 'Lime, compost, and soil treatment', 'sector' => 'agriculture'],
    'other_input' => ['code' => 'OT', 'description' => 'Other agricultural assistance', 'sector' => 'agriculture'],
    'fish_fingerlings' => ['code' => 'FI', 'description' => 'Tilapia, hito, bangus, and other fingerlings', 'sector' => 'fisheries'],
    'fish_feed' => ['code' => 'FF', 'description' => 'Starter, grower, and floating fish feeds', 'sector' => 'fisheries'],
    'fishing_gear' => ['code' => 'FG', 'description' => 'Nets, line, hooks, cages, and fishing tools', 'sector' => 'fisheries'],
    'aquaculture_input' => ['code' => 'AQ', 'description' => 'Fishpond and water-management supplies', 'sector' => 'fisheries'],
    'other_fisheries' => ['code' => 'OF', 'description' => 'Other capture or aquaculture assistance', 'sector' => 'fisheries'],
  ];
  $categoryGroups = collect($inputCategoryOptions ?? [])->groupBy(
      fn ($label, $key) => ($categoryMeta[$key]['sector'] ?? 'agriculture'),
      true
  );
@endphp

@include('partials.record-version', ['record' => $record])

@push('styles')
<style>
  .rice-progress{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;margin-bottom:13px;overflow:hidden;border:1px solid var(--module-border);border-radius:11px;background:#fff}.rice-progress button{position:relative;display:flex;align-items:center;gap:10px;min-width:0;padding:12px 14px;border:0;border-right:1px solid var(--module-border);color:#647168;background:#fff;text-align:left;cursor:pointer}.rice-progress button:last-child{border-right:0}.rice-progress button:hover{background:#f8fbf9}.rice-progress button.is-complete{color:#17643a;background:#f4faf6}.rice-progress-index{width:24px;height:24px;display:grid;place-items:center;flex:0 0 auto;border-radius:7px;color:#516159;background:#edf2ef;font-size:9px;font-weight:900}.rice-progress button.is-complete .rice-progress-index{color:#fff;background:#268253}.rice-progress-copy{min-width:0}.rice-progress-copy strong,.rice-progress-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rice-progress-copy strong{font-size:10px}.rice-progress-copy small{margin-top:2px;color:var(--module-muted);font-size:8px}.rice-progress-check{margin-left:auto;color:#268253;font-size:13px;font-weight:900;opacity:0}.rice-progress button.is-complete .rice-progress-check{opacity:1}
  .rice-farmer-preview{display:none;margin-top:13px}.rice-farmer-preview.is-visible{display:block}.rice-preview-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}.rice-preview-heading strong{font-size:11px}.rice-preview-heading span{color:var(--module-green);font-size:9px;font-weight:850}.rice-form-summary-value{display:block;margin-top:4px;color:var(--module-ink);font-size:16px;font-weight:850;line-height:1.25;overflow-wrap:anywhere}.rice-monitoring>summary{border-bottom:0}.rice-monitoring[open]>summary{border-bottom:1px solid var(--module-border)}.rice-monitoring .module-more-content{padding:15px}.rice-input-callout{display:flex;gap:10px;align-items:flex-start;margin-bottom:15px;padding:12px 13px;border:1px solid #cce7d5;border-radius:10px;background:#f2faf5;color:#28523a}.rice-input-callout svg{width:18px;height:18px;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:1.8}.rice-input-callout strong{display:block;margin-bottom:2px;color:#18442b;font-size:10px}.rice-input-callout span{display:block;font-size:9px;line-height:1.45}
  .rice-category-fieldset{grid-column:1/-1;min-width:0;margin:0;padding:0;border:0}.rice-category-fieldset legend{margin-bottom:8px;color:#45534a;font-size:10px;font-weight:850}.rice-category-groups{display:grid;gap:12px}.rice-category-group{padding:10px;border:1px solid #e2e9e4;border-radius:11px;background:#fbfcfb}.rice-category-group[data-sector="fisheries"]{border-color:#cfe3eb;background:#f7fbfd}.rice-category-group-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}.rice-category-group-head strong{color:var(--module-ink);font-size:10px}.rice-category-group-head span{color:var(--module-muted);font-size:8px}.rice-category-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.rice-category-fieldset.is-invalid .rice-category-groups{padding:5px;border:1px solid #cb625d;border-radius:10px;background:#fffafa}.rice-category-option{position:relative;min-width:0}.rice-category-option input{position:absolute;opacity:0;pointer-events:none}.rice-category-card{display:grid;grid-template-columns:34px minmax(0,1fr) 18px;align-items:center;gap:9px;min-height:64px;padding:9px 10px;border:1px solid #dce5df;border-radius:9px;background:#fff;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease,background .15s ease,transform .15s ease}.rice-category-card:hover{border-color:#9ab8a5;background:#fafdfb;transform:translateY(-1px)}.rice-category-option input:focus-visible+.rice-category-card{outline:3px solid rgba(38,130,83,.16);outline-offset:1px}.rice-category-option input:checked+.rice-category-card{border-color:#5b9c73;background:#f1f9f4;box-shadow:0 0 0 2px rgba(38,130,83,.08)}.rice-category-group[data-sector="fisheries"] .rice-category-option input:checked+.rice-category-card{border-color:#4b8ca4;background:#eef8fb;box-shadow:0 0 0 2px rgba(75,140,164,.09)}.rice-category-code{width:34px;height:34px;display:grid;place-items:center;border-radius:8px;color:#17643a;background:#e7f4eb;font-size:9px;font-weight:950}.rice-category-group[data-sector="fisheries"] .rice-category-code{color:#236b85;background:#e7f4f8}.rice-category-copy{min-width:0}.rice-category-copy strong,.rice-category-copy small{display:block}.rice-category-copy strong{color:var(--module-ink);font-size:10px}.rice-category-copy small{margin-top:3px;color:var(--module-muted);font-size:8px;line-height:1.3}.rice-category-mark{width:16px;height:16px;display:grid;place-items:center;border:1px solid #bccbc1;border-radius:50%;color:#fff;font-size:9px}.rice-category-option input:checked+.rice-category-card .rice-category-mark{border-color:#268253;background:#268253}.rice-category-group[data-sector="fisheries"] .rice-category-option input:checked+.rice-category-card .rice-category-mark{border-color:#347e99;background:#347e99}.rice-category-option input:checked+.rice-category-card .rice-category-mark:after{content:'✓'}
  .rice-field-label-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px}.rice-field-label-row label{margin:0}.rice-inline-action{padding:0;border:0;color:var(--module-green);background:transparent;font:inherit;font-size:8px;font-weight:850;cursor:pointer}.rice-inline-action:hover{text-decoration:underline}.rice-inline-note{display:flex;align-items:flex-start;gap:6px;margin-top:6px;color:var(--module-muted);font-size:8px;line-height:1.4}.rice-inline-note:before{content:'i';width:14px;height:14px;display:grid;place-items:center;flex:0 0 auto;border-radius:50%;color:#17643a;background:#e8f5ec;font-size:8px;font-weight:900}.module-input.is-invalid{border-color:#cb625d;background:#fffafa;box-shadow:0 0 0 3px rgba(203,98,93,.08)}.rice-field-error{display:block;margin-top:5px;color:#a43d38;font-size:8px;font-weight:750}
  .rice-subsection{grid-column:1/-1;padding:13px;border:1px solid var(--module-border);border-radius:10px;background:#fafcfb}.rice-subsection-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:11px}.rice-subsection-head strong{font-size:10px}.rice-subsection-head span{color:var(--module-muted);font-size:9px}.rice-subsection[hidden]{display:none}.rice-subsection .module-form-grid{margin:0}.rice-category-preview{display:inline-flex;align-items:center;margin-top:6px;padding:4px 7px;border-radius:999px;background:#eaf7ee;color:#17643a;font-size:8px;font-weight:850;text-transform:uppercase;letter-spacing:.04em}
  .rice-readiness{margin-top:12px;padding-top:12px;border-top:1px solid var(--module-border)}.rice-readiness-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.rice-readiness-head strong{font-size:9px}.rice-readiness-head span{color:var(--module-muted);font-size:8px}.rice-readiness-track{height:5px;margin-top:7px;overflow:hidden;border-radius:999px;background:#e9eeeb}.rice-readiness-track i{display:block;width:0;height:100%;border-radius:inherit;background:#268253;transition:width .2s ease}.rice-readiness-list{display:grid;gap:6px;margin-top:10px}.rice-readiness-item{display:flex;align-items:center;gap:7px;color:#7b877f;font-size:8px}.rice-readiness-item i{width:15px;height:15px;display:grid;place-items:center;border:1px solid #ced8d1;border-radius:50%;font-style:normal}.rice-readiness-item.is-complete{color:#245d39;font-weight:800}.rice-readiness-item.is-complete i{color:#fff;border-color:#268253;background:#268253}.rice-readiness-item.is-complete i:after{content:'✓'}.rice-submit-button{min-width:130px}.rice-submit-button.is-saving{pointer-events:none;opacity:.72}.rice-submit-button.is-saving .rice-submit-idle{display:none}.rice-submit-saving{display:none}.rice-submit-button.is-saving .rice-submit-saving{display:inline}.rice-form-actions-copy{margin-right:auto}.rice-form-actions-copy strong,.rice-form-actions-copy span{display:block}.rice-form-actions-copy strong{font-size:10px}.rice-form-actions-copy span{margin-top:2px;color:var(--module-muted);font-size:8px}
  @media(max-width:820px){.rice-category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rice-form-actions-copy{display:none}}
  @media(max-width:560px){.rice-progress{grid-template-columns:1fr}.rice-progress button{border-right:0;border-bottom:1px solid var(--module-border)}.rice-progress button:last-child{border-bottom:0}.rice-category-grid{grid-template-columns:1fr}.rice-preview-heading{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@if($errors->any())
  <div class="module-alert module-alert-error"><strong>Please review the distribution information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<nav class="rice-progress" aria-label="Release form progress">
  <button type="button" data-rice-progress="recipient" data-rice-target="riceRecipientSection"><span class="rice-progress-index">1</span><span class="rice-progress-copy"><strong>Recipient</strong><small>Select the correct beneficiary</small></span><span class="rice-progress-check" aria-hidden="true">✓</span></button>
  <button type="button" data-rice-progress="release" data-rice-target="riceReleaseSection"><span class="rice-progress-index">2</span><span class="rice-progress-copy"><strong>Release details</strong><small>Item, quantity, and date</small></span><span class="rice-progress-check" aria-hidden="true">✓</span></button>
  <button type="button" data-rice-progress="review" data-rice-target="riceReviewSection"><span class="rice-progress-index">3</span><span class="rice-progress-copy"><strong>Review</strong><small>Confirm before saving</small></span><span class="rice-progress-check" aria-hidden="true">✓</span></button>
</nav>

<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section" id="riceRecipientSection">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Select the recipient</h2><p>The registered farmer or fisherfolk profile supplies identity, location, and eligibility information automatically.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          @if($canChooseMunicipality ?? false)
            <div class="module-form-field module-form-field-full">
              <label for="municipality_id">Municipality <span class="module-required">*</span></label>
              <select class="module-input js-select @error('municipality_id') is-invalid @enderror" id="municipality_id" name="municipality_id" required aria-describedby="municipalityHelp @error('municipality_id') municipalityError @enderror">
                <option value="">Select municipality first</option>
                @foreach(($municipalities ?? []) as $municipality)
                  <option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>
                @endforeach
              </select>
              <div class="module-hint" id="municipalityHelp">Selecting an office narrows the farmer list to records owned by that municipality.</div>
              @error('municipality_id')<span class="rice-field-error" id="municipalityError">{{ $message }}</span>@enderror
            </div>
          @endif
          <div class="module-form-field module-form-field-full"><label for="farmer_id">Registered beneficiary <span class="module-required">*</span></label><select class="module-input js-select @error('farmer_id') is-invalid @enderror" id="farmer_id" name="farmer_id" required aria-describedby="farmerHelp @error('farmer_id') farmerError @enderror"><option value="">Search and select beneficiary</option>
            @foreach($farmers as $farmer)
              @php
                $fullName = trim($farmer->last_name.', '.$farmer->first_name.' '.($farmer->middle_name ?? '').' '.($farmer->ext_name ?? ''));
                $tags = collect(['ARB' => $farmer->is_arb, '4Ps' => $farmer->is_4ps, 'IP' => $farmer->is_ip, 'PWD' => $farmer->is_pwd, 'SC' => $farmer->is_sc, 'OFW' => $farmer->is_ofw])->filter()->keys()->implode(', ');
              @endphp
              <option value="{{ $farmer->id }}" data-municipality-id="{{ $farmer->municipality_id }}" data-name="{{ $fullName }}" data-ffrs="{{ $farmer->ffrs ?: ($farmer->rsbsa_no ?: 'Not assigned') }}" data-location="{{ $farmer->farm_location ?: 'Not recorded' }}" data-municipality="{{ $farmer->farm_municipality ?: 'Not recorded' }}" data-province="{{ $farmer->farm_province ?: 'Not recorded' }}" data-area="{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'Not recorded' }}" data-contact="{{ $farmer->contact_number ?: 'Not recorded' }}" data-tags="{{ $tags ?: 'None' }}" @selected((string) $selectedFarmerId === (string) $farmer->id)>{{ $fullName }}{{ $farmer->ffrs ? ' — '.$farmer->ffrs : '' }}</option>
            @endforeach
          </select><div class="module-hint" id="farmerHelp">Search by beneficiary name or FFRS/RSBSA number, then verify the profile preview below.</div>@error('farmer_id')<span class="rice-field-error" id="farmerError">{{ $message }}</span>@enderror</div>
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

    <section class="module-form-section" id="riceReleaseSection">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Choose the assistance issued</h2><p>Record crop inputs or fisheries assistance such as tilapia/hito fingerlings, fish feed, nets, and aquaculture supplies.</p></div></div>
      <div class="module-form-body">
        <div class="rice-input-callout"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v6M12 7h.01"></path></svg><div><strong>Choose agriculture or fisheries first</strong><span>The item suggestions, recommended unit, and optional fields adapt automatically. Custom product, species, or equipment names are still accepted.</span></div></div>
        <div class="module-form-grid">
          <fieldset class="rice-category-fieldset @error('input_category') is-invalid @enderror">
            <legend>Input category <span class="module-required">*</span></legend>
            <div class="rice-category-groups">
              @foreach(['agriculture' => ['Crops & farm inputs', 'Seed, fertilizer, soil, and crop support'], 'fisheries' => ['Fisheries assistance', 'Fingerlings, feeds, fishing gear, and aquaculture']] as $sector => [$sectorLabel, $sectorDescription])
                @if(($categoryGroups[$sector] ?? collect())->isNotEmpty())
                  <section class="rice-category-group" data-sector="{{ $sector }}">
                    <div class="rice-category-group-head"><strong>{{ $sectorLabel }}</strong><span>{{ $sectorDescription }}</span></div>
                    <div class="rice-category-grid">
                      @foreach($categoryGroups[$sector] as $valueKey => $label)
                        @php($meta = $categoryMeta[$valueKey] ?? ['code' => strtoupper(substr($label, 0, 2)), 'description' => 'Assistance item'])
                        <div class="rice-category-option">
                          <input id="input_category_{{ $valueKey }}" type="radio" name="input_category" value="{{ $valueKey }}" @checked($selectedInputCategory === $valueKey) required>
                          <label class="rice-category-card" for="input_category_{{ $valueKey }}">
                            <span class="rice-category-code">{{ $meta['code'] }}</span>
                            <span class="rice-category-copy"><strong>{{ $label }}</strong><small>{{ $meta['description'] }}</small></span>
                            <span class="rice-category-mark" aria-hidden="true"></span>
                          </label>
                        </div>
                      @endforeach
                    </div>
                  </section>
                @endif
              @endforeach
            </div>
            @error('input_category')<span class="rice-field-error">{{ $message }}</span>@enderror
          </fieldset>

          <div class="module-form-field module-form-field-full">
            <label for="seed_variety_claimed">Item, product, or variety <span class="module-required">*</span></label>
            <input class="module-input @error('seed_variety_claimed') is-invalid @enderror" id="seed_variety_claimed" name="seed_variety_claimed" list="distributionItemSuggestions" value="{{ $value('seed_variety_claimed') }}" maxlength="200" autocomplete="off" placeholder="Start typing a product name or select a suggestion" required aria-describedby="itemHelp @error('seed_variety_claimed') itemError @enderror">
            <datalist id="distributionItemSuggestions"></datalist>
            <div class="module-hint" id="itemHelp">Suggestions match the selected category; custom names are accepted.</div>
            @error('seed_variety_claimed')<span class="rice-field-error" id="itemError">{{ $message }}</span>@enderror
          </div>

          <div class="module-form-field module-form-field-third">
            <label for="kgs_received">Quantity released <span class="module-required">*</span></label>
            <input class="module-input @error('kgs_received') is-invalid @enderror" id="kgs_received" type="number" inputmode="decimal" step="0.01" min="0.01" name="kgs_received" value="{{ $value('kgs_received') }}" placeholder="0.00" required aria-describedby="quantityHelp @error('kgs_received') quantityError @enderror">
            <div class="module-hint" id="quantityHelp">Enter the number of units actually received.</div>
            @error('kgs_received')<span class="rice-field-error" id="quantityError">{{ $message }}</span>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="quantity_unit">Unit <span class="module-required">*</span></label>
            <select class="module-input js-select @error('quantity_unit') is-invalid @enderror" id="quantity_unit" name="quantity_unit" required>@foreach(($quantityUnitOptions ?? []) as $valueKey => $label)<option value="{{ $valueKey }}" @selected($selectedQuantityUnit === $valueKey)>{{ $label }}</option>@endforeach</select>
            <div class="rice-inline-note" id="riceUnitGuidance">Kilogram entries contribute to kilogram dashboard totals.</div>
            @error('quantity_unit')<span class="rice-field-error">{{ $message }}</span>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <div class="rice-field-label-row"><label for="date_received">Date received <span class="module-required">*</span></label><button class="rice-inline-action" id="riceSetToday" type="button">Use today</button></div>
            <input class="module-input @error('date_received') is-invalid @enderror" id="date_received" type="date" name="date_received" value="{{ $value('date_received', \App\Support\LocalTime::now()->toDateString()) }}" max="{{ \App\Support\LocalTime::now()->toDateString() }}" required>
            @error('date_received')<span class="rice-field-error">{{ $message }}</span>@enderror
          </div>
          <div class="module-form-field module-form-field-full"><label for="lot_series">Lot or batch reference</label><textarea class="module-input @error('lot_series') is-invalid @enderror" id="lot_series" name="lot_series" rows="2" placeholder="Seed lot, fingerling batch, feed batch, voucher, or delivery reference">{{ $value('lot_series') }}</textarea>@error('lot_series')<span class="rice-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-full">
            <div class="rice-field-label-row"><label for="input_notes">Release notes</label><span class="module-hint" id="riceNotesCounter" style="margin:0">0 / 1,000</span></div>
            <textarea class="module-input @error('input_notes') is-invalid @enderror" id="input_notes" name="input_notes" rows="3" maxlength="1000" placeholder="Brand, species, size, source hatchery, intended use, pond/site, instructions, or other release details">{{ $value('input_notes') }}</textarea>
            @error('input_notes')<span class="rice-field-error">{{ $message }}</span>@enderror
          </div>

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

    <section class="module-form-section" id="riceReviewSection"><div class="module-form-actions"><div class="rice-form-actions-copy"><strong>Ready to record this release?</strong><span>Review the recipient and release summary before saving.</span></div><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Cancel</a><button class="module-button module-button-primary rice-submit-button" id="riceSubmitButton" type="submit"><span class="rice-submit-idle">{{ $buttonText ?? 'Save release' }}</span><span class="rice-submit-saving">Saving release…</span></button></div></section>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card" id="riceReviewSummary"><h3>Release summary</h3><p>Recipient</p><span class="rice-form-summary-value" id="riceSummaryFarmer">Not selected</span><span class="rice-category-preview" id="riceSummaryCategory">Rice seed</span><p style="margin-top:12px">Item and quantity</p><span class="rice-form-summary-value" id="riceSummaryRelease">Not entered</span><p style="margin-top:12px">Date</p><span class="rice-form-summary-value" id="riceSummaryDate">Not entered</span><div class="rice-readiness"><div class="rice-readiness-head"><strong>Required information</strong><span id="riceReadinessText">0 of 4 ready</span></div><div class="rice-readiness-track" aria-hidden="true"><i id="riceReadinessBar"></i></div><div class="rice-readiness-list"><span class="rice-readiness-item" data-ready-item="farmer"><i></i>Recipient selected</span><span class="rice-readiness-item" data-ready-item="item"><i></i>Item identified</span><span class="rice-readiness-item" data-ready-item="quantity"><i></i>Quantity and unit entered</span><span class="rice-readiness-item" data-ready-item="date"><i></i>Release date confirmed</span></div></div></section>
    <section class="module-aside-card"><h3>Before saving</h3><ol><li>Confirm the correct beneficiary profile.</li><li>Choose agriculture or fisheries and the correct category.</li><li>Verify the item/species, quantity, unit, and date.</li><li>Add a lot, hatchery/batch, or note when available.</li></ol></section>
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
  const categoryInputs = Array.from(document.querySelectorAll('input[name="input_category"]'));
  const seed = document.getElementById('seed_variety_claimed');
  const kilograms = document.getElementById('kgs_received');
  const quantityUnit = document.getElementById('quantity_unit');
  const receivedDate = document.getElementById('date_received');
  const setToday = document.getElementById('riceSetToday');
  const unitGuidance = document.getElementById('riceUnitGuidance');
  const notes = document.getElementById('input_notes');
  const notesCounter = document.getElementById('riceNotesCounter');
  const submitButton = document.getElementById('riceSubmitButton');
  const form = farmer?.form || document.querySelector('.module-page form');
  const progressButtons = Array.from(document.querySelectorAll('[data-rice-progress]'));
  const readinessItems = Array.from(document.querySelectorAll('[data-ready-item]'));
  const suggestions = document.getElementById('distributionItemSuggestions');
  const seedFields = document.getElementById('riceSeedSpecificFields');
  const productionMonitoring = document.getElementById('riceProductionMonitoring');
  const inputSuggestions = @json($inputSuggestions ?? []);
  const inputCategoryLabels = @json($inputCategoryOptions ?? []);
  const quantityUnitLabels = @json($quantityUnitOptions ?? []);
  const preferredUnitsByCategory = @json($preferredUnitsByCategory ?? []);
  const farmerChoices = farmer ? Array.from(farmer.options).filter(option => option.value).map(option => ({
    value: option.value,
    text: option.textContent.trim(),
    municipalityId: option.dataset.municipalityId || '',
    dataset: { ...option.dataset }
  })) : [];
  const farmerChoiceById = new Map(farmerChoices.map(choice => [String(choice.value), choice]));
  const setText = (id,value,fallbackText='—') => { const element=document.getElementById(id); if(element) element.textContent=value && String(value).trim() ? value : fallbackText; };
  const selectedOption = () => farmerChoiceById.get(String(farmer?.value || ''));
  const selectedCategory = () => categoryInputs.find(input => input.checked)?.value || 'rice_seed';
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
  const isSeedCategory = () => selectedCategory().endsWith('_seed');
  const refreshSuggestions = () => {
    if (!suggestions) return;
    suggestions.replaceChildren();
    (inputSuggestions[selectedCategory()] || []).forEach(item => {
      const option = document.createElement('option');
      option.value = item;
      suggestions.appendChild(option);
    });
  };
  const refreshCategoryFields = (applyPreferredUnit = false) => {
    const showSeedFields = isSeedCategory();
    if (applyPreferredUnit && quantityUnit) {
      const preferredUnit = preferredUnitsByCategory[selectedCategory()];
      if (preferredUnit) {
        if (quantityUnit.tomselect) quantityUnit.tomselect.setValue(preferredUnit, true);
        else quantityUnit.value = preferredUnit;
      }
    }
    if (seedFields) {
      seedFields.hidden = !showSeedFields;
      seedFields.querySelectorAll('input,select,textarea').forEach(field => { field.disabled = !showSeedFields; });
    }
    if (productionMonitoring) {
      productionMonitoring.hidden = !showSeedFields;
      productionMonitoring.querySelectorAll('input,select,textarea').forEach(field => { field.disabled = !showSeedFields; });
    }
    setText('riceSummaryCategory', inputCategoryLabels[selectedCategory()], 'Farm input');
    refreshSuggestions();
  };
  const refreshUnitGuidance = () => {
    if (!unitGuidance) return;
    const unit = quantityUnit?.value || 'kg';
    unitGuidance.textContent = unit === 'kg'
      ? 'Kilogram entries contribute to kilogram dashboard totals.'
      : `This release is tracked in ${String(quantityUnitLabels[unit] || unit).toLowerCase()} and is not converted into kilograms.`;
  };
  const completionState = () => ({
    farmer: Boolean(farmer?.value),
    item: Boolean(seed?.value.trim()),
    quantity: Number(kilograms?.value || 0) > 0 && Boolean(quantityUnit?.value),
    date: Boolean(receivedDate?.value)
  });
  const refreshReadiness = () => {
    const state = completionState();
    const completed = Object.values(state).filter(Boolean).length;
    readinessItems.forEach(item => item.classList.toggle('is-complete', Boolean(state[item.dataset.readyItem])));
    setText('riceReadinessText', `${completed} of 4 ready`, '0 of 4 ready');
    const bar = document.getElementById('riceReadinessBar');
    if (bar) bar.style.width = `${completed * 25}%`;
    progressButtons.forEach(button => {
      const step = button.dataset.riceProgress;
      const isComplete = step === 'recipient'
        ? state.farmer
        : step === 'release'
          ? state.item && state.quantity && state.date
          : completed === 4;
      button.classList.toggle('is-complete', isComplete);
    });
  };
  const refreshNotesCounter = () => {
    if (notesCounter) notesCounter.textContent = `${(notes?.value || '').length.toLocaleString()} / 1,000`;
  };
  const refreshRelease = () => {
    const item=seed?.value || '';
    const quantity=kilograms?.value || '';
    const unit=quantityUnit?.value || 'kg';
    const unitLabel=quantityUnitLabels[unit] || unit;
    setText('riceSummaryRelease', item && quantity ? `${item} · ${Number(quantity).toLocaleString(undefined,{maximumFractionDigits:2})} ${unitLabel}` : item || (quantity ? `${quantity} ${unitLabel}` : ''),'Not entered');
    setText('riceSummaryDate',receivedDate?.value ? new Date(`${receivedDate.value}T00:00:00`).toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'}) : '','Not entered');
    refreshUnitGuidance();
    refreshReadiness();
  };
  const refreshFarmerAndProgress = () => { refreshFarmer(); refreshReadiness(); };
  farmer?.addEventListener('change', refreshFarmerAndProgress);
  seed?.addEventListener('input', refreshRelease);
  kilograms?.addEventListener('input', refreshRelease);
  quantityUnit?.addEventListener('change', refreshRelease);
  receivedDate?.addEventListener('change', refreshRelease);
  notes?.addEventListener('input', refreshNotesCounter);
  categoryInputs.forEach(input => input.addEventListener('change', () => { refreshCategoryFields(true); refreshRelease(); }));
  municipality?.addEventListener('change',() => { filterFarmers(); refreshFarmerAndProgress(); });
  setToday?.addEventListener('click', () => {
    if (!receivedDate) return;
    receivedDate.value = receivedDate.max || new Date().toISOString().slice(0, 10);
    receivedDate.dispatchEvent(new Event('change', { bubbles: true }));
  });
  progressButtons.forEach(button => button.addEventListener('click', () => {
    const target = document.getElementById(button.dataset.riceTarget || '');
    target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));
  form?.addEventListener('submit', () => {
    if (!form.checkValidity() || !submitButton) return;
    submitButton.disabled = true;
    submitButton.classList.add('is-saving');
    form.setAttribute('aria-busy', 'true');
  });
  form?.querySelectorAll('.is-invalid').forEach(field => field.addEventListener('input', () => field.classList.remove('is-invalid'), { once: true }));
  filterFarmers();
  refreshFarmer();
  refreshCategoryFields();
  refreshNotesCounter();
  refreshRelease();
  document.querySelector('.is-invalid')?.scrollIntoView({ block: 'center' });
});
</script>
@endpush
