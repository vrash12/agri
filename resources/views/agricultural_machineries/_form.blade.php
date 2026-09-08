@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $dateValue = fn ($key) => old($key, data_get($record, $key) ? data_get($record, $key)->format('Y-m-d') : '');
  $holderType = old('holder_type', $record?->farmer_id ? 'farmer' : ($record?->farmers_cooperative_id ? 'cooperative' : 'farmer'));
  $holderId = old('holder_id', $record?->farmer_id ?? $record?->farmers_cooperative_id ?? '');
@endphp

@include('partials.record-version', ['record' => $record])

@if($errors->any())
  <div class="module-alert module-alert-error"><strong>Please review the highlighted machinery information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div id="machineryForm" data-default-municipality="{{ $selectedMunicipalityId }}" data-holders-url="{{ route('machinery-inventory.holders') }}">
  <div class="module-form-shell" style="margin-top:13px">
  <div class="module-form-main">
    <section class="module-form-section" id="machineryAssignment">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Accountability and assignment</h2><p>Select the responsible municipality and assign this equipment to one farmer or cooperative from the same office.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          @if($canChooseMunicipality ?? false)
            <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId) === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>@endforeach</select>@error('municipality_id')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>
          @endif
          <div class="module-form-field module-form-field-full">
            <label>Responsible holder <span class="module-required">*</span></label>
            <div class="machinery-holder-options">
              <label class="machinery-holder-choice"><input type="radio" name="holder_type" value="farmer" @checked($holderType === 'farmer')><span><div><strong>Individual farmer</strong><small>Assign accountability to one registered farmer.</small></div></span></label>
              <label class="machinery-holder-choice"><input type="radio" name="holder_type" value="cooperative" @checked($holderType === 'cooperative')><span><div><strong>Farmers cooperative</strong><small>Assign shared equipment to one registered organization.</small></div></span></label>
            </div>
            @error('holder_type')<div class="module-hint module-required">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-full" id="farmerHolderField"><label for="farmer_holder_id">Select farmer <span class="module-required">*</span></label><select class="module-input" id="farmer_holder_id" data-holder-select="farmer"><option value="">Choose a farmer</option>@foreach($farmers as $farmer)@php($farmerName = trim(implode(' ', array_filter([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name]))))<option value="{{ $farmer->id }}" data-municipality="{{ $farmer->municipality_id }}" @selected($holderType === 'farmer' && (string) $holderId === (string) $farmer->id)>{{ $farmerName }}{{ $farmer->ffrs ? ' · '.$farmer->ffrs : '' }}</option>@endforeach</select><div class="module-hint">Only farmers registered under the selected municipality are available.</div></div>
          <div class="module-form-field module-form-field-full" id="cooperativeHolderField"><label for="cooperative_holder_id">Select cooperative <span class="module-required">*</span></label><select class="module-input" id="cooperative_holder_id" data-holder-select="cooperative"><option value="">Choose a cooperative</option>@foreach($cooperatives as $cooperative)<option value="{{ $cooperative->id }}" data-municipality="{{ $cooperative->municipality_id }}" @selected($holderType === 'cooperative' && (string) $holderId === (string) $cooperative->id)>{{ $cooperative->name }}</option>@endforeach</select><div class="module-hint">Only cooperatives registered under the selected municipality are available.</div></div>
          <div class="module-form-field module-form-field-full"><div class="machinery-inline-status" id="machineryHolderStatus" aria-live="polite"><i></i><span>Select a municipality, holder type, and responsible holder.</span></div></div>
          @error('holder_id')<div class="module-form-field module-form-field-full"><div class="module-hint module-required">{{ $message }}</div></div>@enderror
        </div>
      </div>
    </section>

    <section class="module-form-section" id="machineryIdentity">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Equipment identity</h2><p>Use a stable asset code so the physical machine and inventory record can be matched quickly.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-third"><label for="asset_code">Asset code <span class="module-required">*</span></label><input class="module-input module-mono" id="asset_code" type="text" name="asset_code" value="{{ $value('asset_code') }}" maxlength="60" placeholder="e.g. ANAO-TRC-001" autocomplete="off" required>@error('asset_code')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="name">Machinery name <span class="module-required">*</span></label><input class="module-input" id="name" type="text" name="name" value="{{ $value('name') }}" maxlength="150" placeholder="e.g. Kubota farm tractor" required>@error('name')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="category">Machinery type <span class="module-required">*</span></label><select class="module-input" id="category" name="category" required><option value="">Select type</option>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected($value('category') === $key)>{{ $label }}</option>@endforeach</select>@error('category')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>

        </div>
      </div>
    </section>

    <section class="module-form-section" id="machineryOperations">
      <div class="module-form-section-head"><span class="module-step">3</span><div><h2>Current condition and location</h2><p>Record whether the equipment is ready for use and where it is kept.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-third"><label for="condition_status">Condition <span class="module-required">*</span></label><select class="module-input" id="condition_status" name="condition_status" required aria-describedby="condition_status_error">@foreach($conditions as $key => $label)<option value="{{ $key }}" @selected($value('condition_status', 'good') === $key)>{{ $label }}</option>@endforeach</select>@error('condition_status')<span class="module-hint module-required" id="condition_status_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="availability_status">Availability <span class="module-required">*</span></label><select class="module-input" id="availability_status" name="availability_status" required aria-describedby="availability_status_error">@foreach($availabilityStatuses as $key => $label)<option value="{{ $key }}" @selected($value('availability_status', 'available') === $key)>{{ $label }}</option>@endforeach</select>@error('availability_status')<span class="module-hint module-required" id="availability_status_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="service_hours">Service hours</label><input class="module-input" id="service_hours" type="number" name="service_hours" value="{{ $value('service_hours') }}" min="0" step="0.1" placeholder="0.0" aria-describedby="service_hours_error">@error('service_hours')<span class="module-hint module-required" id="service_hours_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-full"><label for="location">Current storage or operating location</label><input class="module-input" id="location" type="text" name="location" value="{{ $value('location') }}" maxlength="255" placeholder="Barangay, facility, shed, or service area" aria-describedby="location_error">@error('location')<span class="module-hint module-required" id="location_error">{{ $message }}</span>@enderror</div>

        </div>
      </div>
    </section>

    <details class="module-more" id="machineryAcquisition" @if(collect(['brand', 'model', 'serial_number', 'acquisition_source', 'acquisition_date', 'year_acquired', 'acquisition_cost'])->contains(fn ($key) => filled($value($key)) || $errors->has($key))) open @endif>
      <summary>Acquisition and manufacturer details <span class="module-hint">Optional</span></summary>
      <div class="module-more-content"><div class="module-form-grid">
          <div class="module-form-field module-form-field-third"><label for="brand">Brand</label><input class="module-input" id="brand" type="text" name="brand" value="{{ $value('brand') }}" maxlength="100" placeholder="Manufacturer" aria-describedby="brand_error">@error('brand')<span class="module-hint module-required" id="brand_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="model">Model</label><input class="module-input" id="model" type="text" name="model" value="{{ $value('model') }}" maxlength="100" placeholder="Model or variant" aria-describedby="model_error">@error('model')<span class="module-hint module-required" id="model_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="serial_number">Serial number</label><input class="module-input module-mono" id="serial_number" type="text" name="serial_number" value="{{ $value('serial_number') }}" maxlength="120" placeholder="Manufacturer serial number" aria-describedby="serial_number_error">@error('serial_number')<span class="module-hint module-required" id="serial_number_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="acquisition_source">Acquisition source</label><select class="module-input" id="acquisition_source" name="acquisition_source" aria-describedby="acquisition_source_error"><option value="">Not recorded</option>@foreach($acquisitionSources as $key => $label)<option value="{{ $key }}" @selected($value('acquisition_source', 'municipal_purchase') === $key)>{{ $label }}</option>@endforeach</select>@error('acquisition_source')<span class="module-hint module-required" id="acquisition_source_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="acquisition_date">Acquisition date</label><input class="module-input" id="acquisition_date" type="date" name="acquisition_date" value="{{ $dateValue('acquisition_date') }}" aria-describedby="acquisition_date_error">@error('acquisition_date')<span class="module-hint module-required" id="acquisition_date_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="year_acquired">Year acquired</label><input class="module-input" id="year_acquired" type="number" name="year_acquired" value="{{ $value('year_acquired') }}" min="1900" max="{{ now()->year + 1 }}" placeholder="{{ now()->year }}" aria-describedby="year_acquired_error">@error('year_acquired')<span class="module-hint module-required" id="year_acquired_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-third"><label for="acquisition_cost">Acquisition cost (₱)</label><input class="module-input" id="acquisition_cost" type="number" name="acquisition_cost" value="{{ $value('acquisition_cost') }}" min="0" step="0.01" placeholder="0.00" aria-describedby="acquisition_cost_error">@error('acquisition_cost')<span class="module-hint module-required" id="acquisition_cost_error">{{ $message }}</span>@enderror</div>
      </div></div>
    </details>

    <section class="module-form-section" id="machineryMaintenance">
      <div class="module-form-section-head"><span class="module-step">4</span><div><h2>Maintenance plan</h2><p>Keep the last service date and next schedule current so the dashboard can flag equipment early.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field"><label for="last_maintenance_date">Last maintenance</label><input class="module-input" id="last_maintenance_date" type="date" name="last_maintenance_date" value="{{ $dateValue('last_maintenance_date') }}" aria-describedby="last_maintenance_date_error">@error('last_maintenance_date')<span class="module-hint module-required" id="last_maintenance_date_error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="next_maintenance_date">Next maintenance</label><input class="module-input" id="next_maintenance_date" type="date" name="next_maintenance_date" value="{{ $dateValue('next_maintenance_date') }}">@error('next_maintenance_date')<div class="module-hint module-required">{{ $message }}</div>@enderror</div>
          <div class="module-form-field module-form-field-full"><div class="machinery-maintenance-warning" id="machineryMaintenanceWarning" hidden></div></div>

        </div>
      </div>
      <details class="module-more" @if(filled($value('notes')) || $errors->has('notes')) open @endif>
        <summary>Operational notes <span class="module-hint">Optional</span></summary>
        <div class="module-more-content">
          <div class="module-form-field module-form-field-full"><label for="notes">Operational notes</label><textarea class="module-input" id="notes" name="notes" rows="5" maxlength="3000" placeholder="Repairs, attachments, custodian instructions, restrictions, or service history" aria-describedby="notes_error">{{ $value('notes') }}</textarea><div class="module-hint">Do not place passwords or other sensitive personal information in operational notes.</div>@error('notes')<span class="module-hint module-required" id="notes_error">{{ $message }}</span>@enderror</div>
        </div>
      </details>
      <div class="module-form-actions"><a class="module-button" href="{{ route('machinery-inventory.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5zM8 4v6h7V4M8 20v-6h8v6"></path></svg>{{ $buttonText ?? 'Save machinery' }}</button></div>
    </section>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card machinery-aside-highlight"><h3>Record summary</h3><div class="module-preview-grid"><div class="module-preview-item module-preview-item-wide"><span>Asset</span><strong id="machineryPreviewAsset">Not entered</strong></div><div class="module-preview-item module-preview-item-wide"><span>Holder</span><strong id="machineryPreviewHolder">Select a holder</strong></div><div class="module-preview-item"><span>Condition</span><strong id="machineryPreviewCondition">Good</strong></div><div class="module-preview-item"><span>Availability</span><strong id="machineryPreviewAvailability">Available</strong></div><div class="module-preview-item"><span>Next service</span><strong id="machineryPreviewMaintenance">Not scheduled</strong></div></div></section>
    <section class="module-aside-card"><h3>Office ownership</h3><p>@if($canChooseMunicipality ?? false)Choose the municipal office accountable for this asset. Changing it also requires selecting a holder from that municipality.@elseThis asset will be saved under <strong>{{ auth()->user()->municipality?->name ?? 'your municipal office' }}</strong>.@endif</p></section>
  </aside>
  </div>
</div>

@push('scripts')
<script>
  (() => {
    const root = document.getElementById('machineryForm');
    if (!root) return;
    const municipality = document.getElementById('municipality_id');
    const radios = [...root.querySelectorAll('input[name="holder_type"]')];
    const selects = { farmer:document.getElementById('farmer_holder_id'), cooperative:document.getElementById('cooperative_holder_id') };
    const fields = { farmer:document.getElementById('farmerHolderField'), cooperative:document.getElementById('cooperativeHolderField') };
    const holderStatus = document.getElementById('machineryHolderStatus');
    const progressPercent = document.getElementById('machineryProgressPercent');
    const progressSummary = document.getElementById('machineryProgressSummary');
    const progressBar = document.getElementById('machineryProgressBar');
    const maintenanceWarning = document.getElementById('machineryMaintenanceWarning');
    const currentMunicipality = () => municipality ? municipality.value : root.dataset.defaultMunicipality;
    const currentType = () => radios.find(radio => radio.checked)?.value || 'farmer';
    const setHolderStatus = (message, state = '') => {
      if (!holderStatus) return;
      holderStatus.classList.remove('ready', 'bad');
      if (state) holderStatus.classList.add(state);
      holderStatus.querySelector('span').textContent = message;
    };
    const filterHolders = () => {
      const municipalityId = currentMunicipality();
      Object.values(selects).forEach(select => {
        if (!select) return;
        [...select.options].forEach(option => {
          if (!option.value) return;
          const visible = !municipalityId || option.dataset.municipality === municipalityId;
          option.hidden = !visible;
          option.disabled = !visible;
          if (!visible && option.selected) select.value = '';
        });
      });
    };
    const loadHolders = async type => {
      const select = selects[type];
      const municipalityId = currentMunicipality();
      if (!select) return;
      if (!municipalityId) {
        select.innerHTML = `<option value="">Select a municipality first</option>`;
        select.disabled = type !== currentType();
        updateAll();
        return;
      }
      select.disabled = true;
      select.innerHTML = `<option value="">Loading ${type === 'farmer' ? 'farmers' : 'cooperatives'}…</option>`;
      if (type === currentType()) setHolderStatus(`Loading ${type === 'farmer' ? 'farmers' : 'cooperatives'} for this municipality…`);
      let failed = false;
      try {
        const url = new URL(root.dataset.holdersUrl, window.location.origin);
        url.searchParams.set('municipality_id', municipalityId);
        url.searchParams.set('holder_type', type);
        const response = await fetch(url, { headers:{ 'Accept':'application/json' }, credentials:'same-origin' });
        if (!response.ok) throw new Error('Unable to load holders');
        const payload = await response.json();
        select.innerHTML = `<option value="">Choose a ${type === 'farmer' ? 'farmer' : 'cooperative'}</option>`;
        (payload.holders || []).forEach(holder => select.add(new Option(holder.label, holder.id)));
      } catch (error) {
        failed = true;
        select.innerHTML = '<option value="">Unable to load holder list</option>';
      } finally {
        select.disabled = type !== currentType();
        select.required = type === currentType();
        updateAll();
        if (failed && type === currentType()) setHolderStatus('The holder list could not be loaded. Check your connection and try the municipality again.', 'bad');
      }
    };
    const syncHolder = () => {
      const type = currentType();
      Object.keys(selects).forEach(key => {
        const active = key === type;
        fields[key].hidden = !active;
        selects[key].disabled = !active;
        selects[key].required = active;
        if (active) selects[key].name = 'holder_id'; else selects[key].removeAttribute('name');
      });
      updateAll();
    };
    const label = select => select?.selectedOptions?.[0]?.textContent?.trim() || '';
    const updatePreview = () => {
      const assetCode = document.getElementById('asset_code')?.value.trim();
      const name = document.getElementById('name')?.value.trim();
      document.getElementById('machineryPreviewAsset').textContent = [assetCode, name].filter(Boolean).join(' · ') || 'Not entered';
      const holderLabel = label(selects[currentType()]);
      document.getElementById('machineryPreviewHolder').textContent = holderLabel && !holderLabel.startsWith('Choose') ? holderLabel : 'Select a holder';
      document.getElementById('machineryPreviewCondition').textContent = label(document.getElementById('condition_status')) || 'Not set';
      document.getElementById('machineryPreviewAvailability').textContent = label(document.getElementById('availability_status')) || 'Not set';
      const maintenance = document.getElementById('next_maintenance_date')?.value;
      document.getElementById('machineryPreviewMaintenance').textContent = maintenance ? new Date(maintenance+'T00:00:00').toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'}) : 'Not scheduled';
    };
    const updateHolderStatus = () => {
      if (!currentMunicipality()) {
        setHolderStatus('Select the accountable municipality before choosing a holder.');
        return;
      }
      const type = currentType();
      const select = selects[type];
      if (select?.value) {
        setHolderStatus(`${type === 'farmer' ? 'Farmer' : 'Cooperative'} selected: ${label(select)}`, 'ready');
        return;
      }
      setHolderStatus(`Choose the responsible ${type === 'farmer' ? 'farmer' : 'cooperative'} from this municipality.`);
    };
    const updateProgress = () => {
      const checks = [
        Boolean(currentMunicipality()),
        Boolean(selects[currentType()]?.value),
        Boolean(document.getElementById('asset_code')?.value.trim()),
        Boolean(document.getElementById('name')?.value.trim()),
        Boolean(document.getElementById('category')?.value),
        Boolean(document.getElementById('condition_status')?.value),
        Boolean(document.getElementById('availability_status')?.value),
      ];
      const complete = checks.filter(Boolean).length;
      const percentage = Math.round((complete / checks.length) * 100);
      if (progressPercent) progressPercent.textContent = `${percentage}%`;
      if (progressBar) progressBar.style.width = `${percentage}%`;
      if (progressSummary) progressSummary.textContent = percentage === 100 ? 'Required information is ready' : `${complete} of ${checks.length} required items complete`;
    };
    const validateMaintenanceDates = () => {
      if (!maintenanceWarning) return;
      const lastValue = document.getElementById('last_maintenance_date')?.value;
      const nextValue = document.getElementById('next_maintenance_date')?.value;
      maintenanceWarning.hidden = true;
      if (!nextValue) return;
      const nextDate = new Date(`${nextValue}T00:00:00`);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      if (lastValue && nextDate < new Date(`${lastValue}T00:00:00`)) {
        maintenanceWarning.textContent = 'Check the schedule: the next maintenance date is earlier than the last maintenance date.';
        maintenanceWarning.hidden = false;
      } else if (nextDate < today) {
        maintenanceWarning.textContent = 'This maintenance date has already passed. The asset will be flagged as overdue.';
        maintenanceWarning.hidden = false;
      } else if ((nextDate - today) / 86400000 <= 30) {
        maintenanceWarning.textContent = 'This asset will appear in the 30-day maintenance attention queue.';
        maintenanceWarning.hidden = false;
      }
    };
    const updateAll = () => {
      updatePreview();
      updateHolderStatus();
      updateProgress();
      validateMaintenanceDates();
    };
    municipality?.addEventListener('change', async () => { await Promise.all([loadHolders('farmer'), loadHolders('cooperative')]); syncHolder(); });
    radios.forEach(radio => radio.addEventListener('change', syncHolder));
    root.querySelectorAll('input, select, textarea').forEach(input => {
      input.addEventListener('input', updateAll);
      input.addEventListener('change', updateAll);
    });
    filterHolders(); syncHolder(); updateAll();
  })();
</script>
@endpush
