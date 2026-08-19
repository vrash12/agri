@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $barangays = ['Poblacion Center','Poblacion South','Poblacion North','Toledo','Coral-Iloco','Guiteb','San Raymundo','Balite','Pance'];
  $dogBreeds = ['Aspin (Asong Pinoy)','Shih Tzu','Poodle','Chihuahua','Golden Retriever','Labrador Retriever','German Shepherd','Siberian Husky','Pomeranian','Rottweiler','Doberman Pinscher','Beagle','Dachshund','Pug','American Bully','French Bulldog','Corgi (Pembroke Welsh Corgi)','Maltese','Yorkshire Terrier','Mixed Breed','Other'];
  $catBreeds = ['Domestic Shorthair (Puspin)','Persian','Siamese','Maine Coon','Ragdoll','British Shorthair','Bengal','Scottish Fold','Sphynx','Mixed Breed','Other'];
  $petColors = ['Black','White','Brown','Tan','Golden','Cream','Gray','Fawn','Brindle','Spotted','Tabby','Calico','Tricolor','Bicolor','Red','Chocolate','Mixed / Other'];
@endphp

@push('styles')
<style>
  .vaccination-owner-lookup{display:none;margin-top:12px;padding:12px;border:1px solid #b9d8c4;border-radius:9px;background:#f1f8f3}.vaccination-owner-lookup-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.vaccination-owner-lookup h3{margin:0;color:var(--module-green);font-size:11px;font-weight:850}.vaccination-owner-lookup p{margin:3px 0 0;color:var(--module-muted);font-size:9px}.vaccination-owner-actions{display:flex;justify-content:flex-end;gap:7px;margin-top:10px;flex-wrap:wrap}.vaccination-pet-type-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.vaccination-type-option{position:relative}.vaccination-type-option input{position:absolute;opacity:0;pointer-events:none}.vaccination-type-option label{display:flex;align-items:center;gap:9px;margin:0;padding:11px;border:1px solid var(--module-border);border-radius:8px;background:#fff;cursor:pointer}.vaccination-type-option input:checked+label{color:var(--module-green);border-color:#75a587;background:var(--module-green-soft);box-shadow:0 0 0 3px rgba(23,100,58,.07)}.vaccination-type-icon{width:29px;height:29px;display:grid;place-items:center;border-radius:7px;background:#f1f4f2;font-size:15px}.vaccination-type-option label strong{font-size:10px}.vaccination-type-option label small{display:block;margin-top:2px;color:var(--module-muted);font-size:8px}
</style>
@endpush

@if($errors->any())
  <div class="module-alert module-alert-error"><strong>Please review the vaccination information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Find or add the owner</h2><p>Existing owner and pet records can be reused to reduce duplicate entry.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          @if($canChooseMunicipality ?? false)
            <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input js-select" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>@endforeach</select><div class="module-hint">Suggestions are limited to the selected municipality.</div></div>
          @endif
          <div class="module-form-field module-form-field-full"><label for="owner_name">Owner name <span class="module-required">*</span></label><input class="module-input" id="owner_name" name="owner_name" list="ownerNameList" value="{{ $value('owner_name') }}" placeholder="Search an existing owner or enter a new name" autocomplete="off" required><datalist id="ownerNameList">@foreach(($ownerNameOptions ?? []) as $name)<option value="{{ $name }}"></option>@endforeach</datalist><div class="module-hint">Type at least two characters. Matching owner and pet records will appear below.</div></div>
        </div>
        <div class="vaccination-owner-lookup" id="ownerLookupPanel">
          <div class="vaccination-owner-lookup-head"><div><h3>Existing owner found</h3><p>Confirm the owner and reuse a registered pet, or continue with a new animal.</p></div><div id="ownerBadges"></div></div>
          <div class="module-form-field module-form-field-full" style="margin-top:10px"><label for="existingPetSelect">Registered pets</label><select class="module-input js-select" id="existingPetSelect"><option value="">Choose a pet</option></select></div>
          <div class="vaccination-owner-actions"><button class="module-button" type="button" id="btnAddNewPet">Enter a new pet</button><button class="module-button module-button-primary" type="button" id="btnUseSelectedPet">Use selected pet</button></div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Owner information</h2><p>Confirm the service location and owner birthday.</p></div></div>
      <div class="module-form-body"><div class="module-form-grid">
        <div class="module-form-field"><label for="barangay">Barangay <span class="module-required">*</span></label><input class="module-input" id="barangay" name="barangay" list="barangaySuggestions" value="{{ $value('barangay') }}" maxlength="120" placeholder="Type or select a barangay" autocomplete="address-level3" required><datalist id="barangaySuggestions">@foreach($barangays as $option)<option value="{{ $option }}"></option>@endforeach</datalist><div class="module-hint">You can enter any barangay for the selected municipality.</div></div>
        <div class="module-form-field"><label for="birthday">Owner birthday <span class="module-required">*</span></label><input class="module-input" type="date" id="birthday" name="birthday" value="{{ $value('birthday') }}" max="{{ now()->toDateString() }}" required></div>
      </div></div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">3</span><div><h2>Animal and vaccination</h2><p>Identify the animal and record the date the service was administered.</p></div></div>
      <div class="module-form-body"><div class="module-form-grid">
        <div class="module-form-field module-form-field-full"><label>Animal type <span class="module-required">*</span></label><div class="vaccination-pet-type-grid"><div class="vaccination-type-option"><input type="radio" id="pet_type_dog" name="pet_type" value="Dog" @checked($value('pet_type') === 'Dog') required><label for="pet_type_dog"><span class="vaccination-type-icon">D</span><span><strong>Dog</strong><small>Canine vaccination record</small></span></label></div><div class="vaccination-type-option"><input type="radio" id="pet_type_cat" name="pet_type" value="Cat" @checked($value('pet_type') === 'Cat') required><label for="pet_type_cat"><span class="vaccination-type-icon">C</span><span><strong>Cat</strong><small>Feline vaccination record</small></span></label></div></div><select id="pet_type" hidden><option value="{{ $value('pet_type') }}" selected>{{ $value('pet_type') }}</option></select></div>
        <div class="module-form-field"><label for="pet_name">Pet name <span class="module-required">*</span></label><input class="module-input" id="pet_name" name="pet_name" value="{{ $value('pet_name') }}" placeholder="Animal name" required></div>
        <div class="module-form-field"><label for="pet_breed">Breed <span class="module-required">*</span></label><select class="module-input js-select" id="pet_breed" name="pet_breed" required><option value="">Select breed</option><optgroup label="Dog breeds">@foreach($dogBreeds as $option)<option value="{{ $option }}" @selected($value('pet_breed') === $option)>{{ $option }}</option>@endforeach</optgroup><optgroup label="Cat breeds">@foreach($catBreeds as $option)<option value="{{ $option }}" @selected($value('pet_breed') === $option)>{{ $option }}</option>@endforeach</optgroup>@if($value('pet_breed') && !in_array($value('pet_breed'), array_merge($dogBreeds, $catBreeds), true))<option value="{{ $value('pet_breed') }}" selected>{{ $value('pet_breed') }}</option>@endif</select></div>
        <div class="module-form-field"><label for="pet_color">Color or markings</label><select class="module-input js-select" id="pet_color" name="pet_color"><option value="">Select color</option>@foreach($petColors as $option)<option value="{{ $option }}" @selected($value('pet_color') === $option)>{{ $option }}</option>@endforeach @if($value('pet_color') && !in_array($value('pet_color'), $petColors, true))<option value="{{ $value('pet_color') }}" selected>{{ $value('pet_color') }}</option>@endif</select></div>
        <div class="module-form-field"><label for="vaccination_date">Vaccination date <span class="module-required">*</span></label><input class="module-input" type="date" id="vaccination_date" name="vaccination_date" value="{{ $value('vaccination_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required><div class="module-hint">The reporting year is derived automatically.</div></div>
      </div></div>
      <div class="module-form-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save vaccination' }}</button></div>
    </section>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card"><h3>Fast recording workflow</h3><ol><li>Search the owner first.</li><li>Reuse a matching pet when available.</li><li>Verify barangay and animal details.</li><li>Save the vaccination date.</li></ol></section>
    <section class="module-aside-card"><h3>Avoid duplicate animals</h3><p>Use an existing pet when the type, name, breed, and color match. Choose “Enter a new pet” only for a different animal.</p></section>
    <section class="module-aside-card"><h3>Office scope</h3><p>
      @if($canChooseMunicipality ?? false)
        The owner lookup and saved record follow the municipality selected in this form.
      @else
        This vaccination is assigned to <strong>{{ auth()->user()->municipality?->name ?? 'your municipal office' }}</strong>.
      @endif
    </p></section>
  </aside>
</div>

@push('scripts')
<script>
(() => {
  const lookupUrl = @json(route('anti-rabies-vaccinations.owner-lookup'));
  const ownerInput = document.getElementById('owner_name');
  const municipalityInput = document.getElementById('municipality_id');
  const panel = document.getElementById('ownerLookupPanel');
  const badges = document.getElementById('ownerBadges');
  const petSelect = document.getElementById('existingPetSelect');
  const barangay = document.getElementById('barangay');
  const birthday = document.getElementById('birthday');
  const petTypeBridge = document.getElementById('pet_type');
  const petName = document.getElementById('pet_name');
  const petBreed = document.getElementById('pet_breed');
  const petColor = document.getElementById('pet_color');
  const vaccinationDate = document.getElementById('vaccination_date');
  const breedsByType = { Dog: @json($dogBreeds), Cat: @json($catBreeds) };
  let pets = [];
  let timer;

  const setSelect = (element, value) => {
    if (!element) return;
    element.value = value || '';
    if (element.tomselect) element.tomselect.setValue(value || '', true);
  };
  const refreshBreeds = (type, selectedValue = petBreed?.value || '') => {
    if (!petBreed) return;
    const allowed = type ? (breedsByType[type] || []) : [...new Set([...breedsByType.Dog, ...breedsByType.Cat])];
    if (selectedValue && !allowed.includes(selectedValue)) allowed.push(selectedValue);
    if (petBreed.tomselect) {
      petBreed.tomselect.clear(true);
      petBreed.tomselect.clearOptions();
      petBreed.tomselect.addOptions(allowed.map(value => ({ value, text:value })));
      petBreed.tomselect.refreshOptions(false);
      if (selectedValue) petBreed.tomselect.setValue(selectedValue, true);
    } else {
      Array.from(petBreed.options).forEach(option => {
        if (!option.value) return;
        option.hidden = !allowed.includes(option.value);
        option.disabled = option.hidden;
      });
      petBreed.value = selectedValue;
    }
  };
  const setPetType = value => {
    document.querySelectorAll('input[name="pet_type"]').forEach(input => input.checked = input.value === value);
    if (petTypeBridge) petTypeBridge.value = value || '';
    refreshBreeds(value);
  };
  const hideOwner = () => {
    panel.style.display = 'none'; badges.innerHTML = ''; pets = [];
    if (petSelect?.tomselect) { petSelect.tomselect.clear(); petSelect.tomselect.clearOptions(); }
    else if (petSelect) petSelect.innerHTML = '<option value="">Choose a pet</option>';
  };
  const showOwner = owner => {
    badges.innerHTML = '';
    [owner.barangay || 'Barangay unavailable', owner.birthday || 'Birthday unavailable'].forEach(text => { const badge=document.createElement('span'); badge.className='module-badge module-badge-green'; badge.textContent=text; badges.appendChild(badge); });
    panel.style.display = 'block';
    if (!barangay.value && owner.barangay) setSelect(barangay, owner.barangay);
    if (!birthday.value && owner.birthday) birthday.value = owner.birthday;
  };
  const populatePets = list => {
    pets = list || [];
    const options = pets.map((pet,index) => ({ value:String(index), text:`${pet.pet_type} · ${pet.pet_name} · ${pet.pet_breed}${pet.pet_color ? ' · '+pet.pet_color : ''} · last ${pet.last_vaccination_date || 'unknown'}` }));
    if (petSelect.tomselect) { petSelect.tomselect.clear(); petSelect.tomselect.clearOptions(); petSelect.tomselect.addOptions(options); petSelect.tomselect.refreshOptions(false); }
    else { petSelect.innerHTML='<option value="">Choose a pet</option>'; options.forEach(item => petSelect.add(new Option(item.text,item.value))); }
  };
  const lookup = async () => {
    const name = (ownerInput.value || '').trim();
    const municipalityId = municipalityInput?.value || '';
    if (name.length < 2 || (municipalityInput && !municipalityId)) { hideOwner(); return; }
    try {
      const response = await fetch(`${lookupUrl}?name=${encodeURIComponent(name)}${municipalityId ? '&municipality_id='+encodeURIComponent(municipalityId) : ''}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      const data = response.ok ? await response.json() : { exists:false };
      if (!data.exists) { hideOwner(); return; }
      showOwner(data.owner || {}); populatePets(data.pets || []);
    } catch (error) { hideOwner(); }
  };
  const scheduleLookup = () => { clearTimeout(timer); timer=setTimeout(lookup,350); };
  ownerInput?.addEventListener('input', scheduleLookup);
  ownerInput?.addEventListener('change', scheduleLookup);
  municipalityInput?.addEventListener('change', () => { hideOwner(); scheduleLookup(); });
  document.getElementById('btnUseSelectedPet')?.addEventListener('click', () => {
    const index = petSelect?.value; if (index === '' || index == null || !pets[Number(index)]) return;
    const pet = pets[Number(index)]; setPetType(pet.pet_type); petName.value=pet.pet_name || ''; setSelect(petBreed,pet.pet_breed); setSelect(petColor,pet.pet_color); vaccinationDate.value=new Date().toISOString().slice(0,10);
  });
  document.getElementById('btnAddNewPet')?.addEventListener('click', () => { setPetType(''); petName.value=''; setSelect(petBreed,''); setSelect(petColor,''); vaccinationDate.value=new Date().toISOString().slice(0,10); });
  document.querySelectorAll('input[name="pet_type"]').forEach(input => input.addEventListener('change', () => setPetType(input.value)));
  refreshBreeds(document.querySelector('input[name="pet_type"]:checked')?.value || '');
  if ((ownerInput?.value || '').trim().length >= 2) scheduleLookup();
})();
</script>
@endpush
