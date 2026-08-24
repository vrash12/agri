@php
  $record = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($record, $key, $default));
  $dateValue = function ($key, $default = '') use ($record) {
      $raw = old($key, data_get($record, $key, $default));
      if (blank($raw)) return '';
      try { return \Illuminate\Support\Carbon::parse($raw)->toDateString(); }
      catch (\Throwable $e) { return ''; }
  };
  $today = \App\Support\LocalTime::now()->toDateString();
  $selectedServiceType = old('service_type', $record->service_type ?? ($defaultServiceType ?? 'vaccination'));
  $selectedAnimalType = old('pet_type', $record->pet_type ?? '');
  $serviceNameValue = old('service_name', $record?->service_name ?: ($selectedServiceType === 'vaccination' ? 'Anti-rabies vaccine' : ''));
  $barangays = ['Poblacion Center','Poblacion South','Poblacion North','Toledo','Coral-Iloco','Guiteb','San Raymundo','Balite','Pance'];
  $serviceSuggestions = [
    'vaccination' => ['Anti-rabies vaccine','Hemorrhagic septicemia vaccine','Hog cholera vaccine','Newcastle disease vaccine','Fowl pox vaccine','Other livestock vaccine'],
    'deworming' => ['Ivermectin','Albendazole','Levamisole','Fenbendazole','Piperazine','Other dewormer'],
    'vitamins' => ['Multivitamins','Vitamin A-D-E','Vitamin B complex','Iron dextran','Electrolytes with vitamins','Mineral supplementation'],
    'treatment' => ['Wound treatment','Antibiotic treatment','Respiratory treatment','Diarrhea treatment','Ectoparasite treatment','Supportive treatment'],
  ];
  $breedsByType = [
    'Dog' => ['Aspin (Asong Pinoy)','Mixed Breed','Other'], 'Cat' => ['Domestic Shorthair (Puspin)','Mixed Breed','Other'],
    'Cattle' => ['Brahman','Holstein Friesian','Sahiwal','Native cattle','Crossbreed','Other'], 'Carabao' => ['Philippine native carabao','Murrah cross','Other'],
    'Goat' => ['Native goat','Boer','Anglo-Nubian','Crossbreed','Other'], 'Sheep' => ['Native sheep','Dorper','Crossbreed','Other'],
    'Swine' => ['Large White','Landrace','Duroc','Native pig','Crossbreed','Other'], 'Chicken' => ['Native chicken','Broiler','Layer','Free-range','Other'],
    'Duck' => ['Itik Pinas','Muscovy','Mallard','Other'], 'Turkey' => ['Native turkey','Broad Breasted White','Other'],
    'Horse' => ['Native horse','Thoroughbred','Crossbreed','Other'], 'Rabbit' => ['New Zealand White','Californian','Native / Mixed','Other'],
    'Other' => ['Not specified','Other'],
  ];
  $administrationRoutes = ['Oral','Injectable - intramuscular','Injectable - subcutaneous','Topical','In drinking water','Mixed with feed','Spray / dip','Other'];
@endphp

@include('partials.record-version', ['record' => $record])

@push('styles')
<style>
  .animal-owner-lookup{display:none;margin-top:12px;padding:13px;border:1px solid #b9d8c4;border-radius:10px;background:#f1f8f3}.animal-owner-lookup.is-visible{display:block}.animal-owner-lookup-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.animal-owner-lookup h3{margin:0;color:var(--module-green);font-size:11px;font-weight:850}.animal-owner-lookup p{margin:3px 0 0;color:var(--module-muted);font-size:9px}.animal-owner-actions{display:flex;justify-content:flex-end;gap:7px;margin-top:10px;flex-wrap:wrap}
  .animal-service-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.animal-service-option{position:relative}.animal-service-option input{position:absolute;opacity:0;pointer-events:none}.animal-service-option label{display:block;min-height:76px;margin:0;padding:11px;border:1px solid var(--module-border);border-radius:9px;background:#fff;cursor:pointer;transition:.15s ease}.animal-service-option label:hover{border-color:#98b4a2;transform:translateY(-1px)}.animal-service-option input:focus-visible+label{outline:3px solid rgba(23,100,58,.14);outline-offset:1px}.animal-service-option input:checked+label{color:var(--module-green);border-color:#75a587;background:var(--module-green-soft);box-shadow:0 0 0 3px rgba(23,100,58,.07)}.animal-service-code{display:inline-grid;width:28px;height:28px;place-items:center;margin-bottom:7px;border-radius:7px;color:#17643a;background:#e7f4eb;font-size:9px;font-weight:950}.animal-service-option label strong,.animal-service-option label small{display:block}.animal-service-option label strong{font-size:10px}.animal-service-option label small{margin-top:3px;color:var(--module-muted);font-size:8px;line-height:1.35}
  .animal-form-callout{display:flex;gap:10px;align-items:flex-start;margin-bottom:14px;padding:12px;border:1px solid #cbdde9;border-radius:9px;color:#2d5165;background:#f3f8fb}.animal-form-callout strong,.animal-form-callout span{display:block}.animal-form-callout strong{font-size:10px}.animal-form-callout span{margin-top:2px;font-size:8px;line-height:1.4}.animal-form-callout i{width:24px;height:24px;display:grid;place-items:center;flex:0 0 auto;border-radius:7px;color:#fff;background:#3978b5;font-style:normal;font-size:10px;font-weight:900}.animal-field-error{display:block;margin-top:5px;color:#a43d38;font-size:8px;font-weight:750}.module-input.is-invalid{border-color:#cb625d;background:#fffafa}.animal-form-aside-value{display:block;margin-top:5px;color:var(--module-ink);font-size:14px;font-weight:850;overflow-wrap:anywhere}.animal-form-aside-service{display:inline-flex;margin-top:8px;padding:5px 8px;border-radius:999px;color:#17643a;background:#e7f4eb;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
  @media(max-width:800px){.animal-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.animal-service-grid{grid-template-columns:1fr}.animal-owner-lookup-head{flex-direction:column}}
</style>
@endpush

@if($errors->any())
  <div class="module-alert module-alert-error"><strong>Please review the animal-health information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Owner and municipality</h2><p>Find an existing owner to reuse their location and animal information, or enter a new record.</p></div></div>
      <div class="module-form-body">
        <div class="module-form-grid">
          @if($canChooseMunicipality ?? false)
            <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input js-select @error('municipality_id') is-invalid @enderror" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>@endforeach</select><div class="module-hint">Owner suggestions and reports are limited to this municipality.</div>@error('municipality_id')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          @endif
          <div class="module-form-field module-form-field-full"><label for="owner_name">Owner / raiser name <span class="module-required">*</span></label><input class="module-input @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" list="ownerNameList" value="{{ $value('owner_name') }}" placeholder="Search an existing owner or enter a new name" autocomplete="off" required><datalist id="ownerNameList">@foreach(($ownerNameOptions ?? []) as $name)<option value="{{ $name }}"></option>@endforeach</datalist><div class="module-hint">Type at least two characters to find prior animals or livestock groups.</div>@error('owner_name')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="barangay">Barangay <span class="module-required">*</span></label><input class="module-input @error('barangay') is-invalid @enderror" id="barangay" name="barangay" list="barangaySuggestions" value="{{ $value('barangay') }}" maxlength="120" placeholder="Type or select a barangay" autocomplete="address-level3" required><datalist id="barangaySuggestions">@foreach($barangays as $option)<option value="{{ $option }}"></option>@endforeach</datalist>@error('barangay')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="birthday">Owner birthday <span class="module-hint">Optional</span></label><input class="module-input @error('birthday') is-invalid @enderror" type="date" id="birthday" name="birthday" value="{{ $dateValue('birthday') }}" max="{{ $today }}">@error('birthday')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
        </div>
        <div class="animal-owner-lookup" id="ownerLookupPanel">
          <div class="animal-owner-lookup-head"><div><h3>Existing owner found</h3><p>Reuse an animal or livestock group when it matches, or continue with a new one.</p></div><div id="ownerBadges"></div></div>
          <div class="module-form-field module-form-field-full" style="margin-top:10px"><label for="existingPetSelect">Previously served animals</label><select class="module-input" id="existingPetSelect"><option value="">Choose an animal or group</option></select></div>
          <div class="animal-owner-actions"><button class="module-button" type="button" id="btnAddNewPet">Enter a new animal</button><button class="module-button module-button-primary" type="button" id="btnUseSelectedPet">Use selected animal</button></div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Select the service</h2><p>Use one record for one service performed on an individual animal or a group treated together.</p></div></div>
      <div class="module-form-body"><div class="animal-service-grid">
        @foreach(($serviceTypeOptions ?? []) as $type => $label)
          @php $meta = ['vaccination'=>['VX','Disease prevention, including anti-rabies'],'deworming'=>['DW','Internal or external parasite control'],'vitamins'=>['VT','Vitamins, minerals, and supplements'],'treatment'=>['TX','Illness, injury, or supportive care']][$type] ?? ['AH','Animal-health service']; @endphp
          <div class="animal-service-option"><input type="radio" id="service_type_{{ $type }}" name="service_type" value="{{ $type }}" @checked($selectedServiceType === $type) required><label for="service_type_{{ $type }}"><span class="animal-service-code">{{ $meta[0] }}</span><strong>{{ $label }}</strong><small>{{ $meta[1] }}</small></label></div>
        @endforeach
      </div>@error('service_type')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">3</span><div><h2>Animal and service details</h2><p>Record the species, number served, product or treatment, dosage, and follow-up information.</p></div></div>
      <div class="module-form-body">
        <div class="animal-form-callout"><i>i</i><div><strong>Individual or group recording</strong><span>For one named pet, use a quantity of 1. For cattle, goats, swine, poultry, or other groups, enter the total number served and an optional herd/flock identifier.</span></div></div>
        <div class="module-form-grid">
          <div class="module-form-field"><label for="pet_type">Animal species <span class="module-required">*</span></label><select class="module-input js-select @error('pet_type') is-invalid @enderror" id="pet_type" name="pet_type" required><option value="">Select species</option>@foreach(($animalTypeOptions ?? []) as $type => $label)<option value="{{ $type }}" @selected($selectedAnimalType === $type)>{{ $label }}</option>@endforeach</select>@error('pet_type')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="animal_count">Number of animals served <span class="module-required">*</span></label><input class="module-input @error('animal_count') is-invalid @enderror" type="number" id="animal_count" name="animal_count" value="{{ $value('animal_count', 1) }}" min="1" max="1000000" required><div class="module-hint">Use 1 for an individual animal.</div>@error('animal_count')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="pet_name">Animal name or group ID</label><input class="module-input @error('pet_name') is-invalid @enderror" id="pet_name" name="pet_name" value="{{ $value('pet_name') }}" maxlength="120" placeholder="e.g. Bantay, Herd A, Flock 2">@error('pet_name')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="pet_breed">Breed / strain</label><input class="module-input @error('pet_breed') is-invalid @enderror" id="pet_breed" name="pet_breed" list="animalBreedSuggestions" value="{{ $value('pet_breed') }}" maxlength="120" placeholder="Select species first or type a breed"><datalist id="animalBreedSuggestions"></datalist>@error('pet_breed')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="pet_color">Color / markings</label><input class="module-input @error('pet_color') is-invalid @enderror" id="pet_color" name="pet_color" value="{{ $value('pet_color') }}" maxlength="80" placeholder="Optional identifying details">@error('pet_color')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="service_name">Product, medicine, or treatment <span class="module-required">*</span></label><input class="module-input @error('service_name') is-invalid @enderror" id="service_name" name="service_name" list="serviceNameSuggestions" value="{{ $serviceNameValue }}" maxlength="150" placeholder="Enter the product or treatment" required><datalist id="serviceNameSuggestions"></datalist>@error('service_name')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="dosage">Dosage / amount</label><input class="module-input @error('dosage') is-invalid @enderror" id="dosage" name="dosage" value="{{ $value('dosage') }}" maxlength="120" placeholder="e.g. 1 mL/head or 10 g/L water">@error('dosage')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="administration_route">Administration route</label><select class="module-input js-select @error('administration_route') is-invalid @enderror" id="administration_route" name="administration_route"><option value="">Select route</option>@foreach($administrationRoutes as $route)<option value="{{ $route }}" @selected($value('administration_route') === $route)>{{ $route }}</option>@endforeach</select>@error('administration_route')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="vaccination_date">Service date <span class="module-required">*</span></label><input class="module-input @error('vaccination_date') is-invalid @enderror" type="date" id="vaccination_date" name="vaccination_date" value="{{ $dateValue('vaccination_date', $today) }}" max="{{ $today }}" required>@error('vaccination_date')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="next_service_date">Next service / follow-up date</label><input class="module-input @error('next_service_date') is-invalid @enderror" type="date" id="next_service_date" name="next_service_date" value="{{ $dateValue('next_service_date') }}" min="{{ $dateValue('vaccination_date', $today) }}">@error('next_service_date')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-full"><label for="diagnosis">Diagnosis, reason, or indication</label><input class="module-input @error('diagnosis') is-invalid @enderror" id="diagnosis" name="diagnosis" value="{{ $value('diagnosis') }}" maxlength="255" placeholder="e.g. Routine deworming, vitamin support, wound, respiratory signs">@error('diagnosis')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field"><label for="administered_by">Administered by</label><input class="module-input @error('administered_by') is-invalid @enderror" id="administered_by" name="administered_by" value="{{ $value('administered_by') }}" maxlength="120" placeholder="Veterinarian, livestock inspector, or staff">@error('administered_by')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
          <div class="module-form-field module-form-field-full"><label for="treatment_notes">Service notes</label><textarea class="module-input @error('treatment_notes') is-invalid @enderror" id="treatment_notes" name="treatment_notes" rows="3" maxlength="3000" placeholder="Instructions, withdrawal period, observed condition, response, batch number, or follow-up notes">{{ $value('treatment_notes') }}</textarea>@error('treatment_notes')<span class="animal-field-error">{{ $message }}</span>@enderror</div>
        </div>
      </div>
      <div class="module-form-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save animal-health service' }}</button></div>
    </section>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card"><h3>Service summary</h3><p>Owner / raiser</p><span class="animal-form-aside-value" id="animalSummaryOwner">{{ $value('owner_name') ?: 'Not selected' }}</span><span class="animal-form-aside-service" id="animalSummaryService">{{ ($serviceTypeOptions ?? [])[$selectedServiceType] ?? 'Animal health' }}</span><p style="margin-top:12px">Animal coverage</p><span class="animal-form-aside-value" id="animalSummaryCoverage">Not entered</span></section>
    <section class="module-aside-card"><h3>Good recordkeeping</h3><ol><li>Choose the correct municipality and owner.</li><li>Record the exact species and number served.</li><li>Enter the medicine, dosage, and administration route.</li><li>Include a follow-up date or treatment instruction when needed.</li></ol></section>
    <section class="module-aside-card"><h3>Municipality ownership</h3><p>@if($canChooseMunicipality ?? false)The owner lookup and saved service follow the selected municipality.@else This service is automatically assigned to <strong>{{ auth()->user()->municipality?->name ?? 'your municipal office' }}</strong>.@endif</p></section>
  </aside>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const lookupUrl = @json(route('anti-rabies-vaccinations.owner-lookup'));
  const today = @json($today);
  const serviceLabels = @json($serviceTypeOptions ?? []);
  const serviceSuggestions = @json($serviceSuggestions);
  const breedsByType = @json($breedsByType);
  const ownerInput = document.getElementById('owner_name'); const municipalityInput = document.getElementById('municipality_id');
  const panel = document.getElementById('ownerLookupPanel'); const badges = document.getElementById('ownerBadges'); const animalSelect = document.getElementById('existingPetSelect');
  const barangay = document.getElementById('barangay'); const birthday = document.getElementById('birthday'); const animalType = document.getElementById('pet_type');
  const animalName = document.getElementById('pet_name'); const animalBreed = document.getElementById('pet_breed'); const animalColor = document.getElementById('pet_color'); const animalCount = document.getElementById('animal_count');
  const serviceName = document.getElementById('service_name'); const serviceDate = document.getElementById('vaccination_date'); const nextServiceDate = document.getElementById('next_service_date');
  const breedList = document.getElementById('animalBreedSuggestions'); const serviceList = document.getElementById('serviceNameSuggestions'); const form = ownerInput?.form;
  let animals = []; let timer;
  const serviceType = () => document.querySelector('input[name="service_type"]:checked')?.value || 'vaccination';
  const setSelect = (element, value) => { if (!element) return; if (element.tomselect) element.tomselect.setValue(value || '', true); else element.value = value || ''; };
  const setOptions = (datalist, options) => { if (!datalist) return; datalist.replaceChildren(...(options || []).map(value => { const option=document.createElement('option'); option.value=value; return option; })); };
  const refreshSuggestions = () => { setOptions(serviceList, serviceSuggestions[serviceType()] || []); setOptions(breedList, breedsByType[animalType?.value] || []); };
  const refreshSummary = () => {
    document.getElementById('animalSummaryOwner').textContent = ownerInput?.value.trim() || 'Not selected';
    document.getElementById('animalSummaryService').textContent = serviceLabels[serviceType()] || 'Animal health';
    const typeLabel=animalType?.selectedOptions?.[0]?.textContent || ''; const count=Number(animalCount?.value || 0);
    document.getElementById('animalSummaryCoverage').textContent = typeLabel && count > 0 ? `${count.toLocaleString()} ${typeLabel}${count === 1 ? '' : ' animals'}` : 'Not entered';
  };
  const hideOwner = () => { panel?.classList.remove('is-visible'); if (badges) badges.replaceChildren(); animals=[]; if(animalSelect) animalSelect.innerHTML='<option value="">Choose an animal or group</option>'; };
  const showOwner = owner => { if(!panel||!badges)return; badges.replaceChildren(); [owner.barangay||'Barangay unavailable',owner.birthday||'Birthday unavailable'].forEach(text=>{const badge=document.createElement('span');badge.className='module-badge module-badge-green';badge.textContent=text;badges.appendChild(badge);}); panel.classList.add('is-visible'); if(!barangay?.value&&owner.barangay)barangay.value=owner.barangay; if(!birthday?.value&&owner.birthday)birthday.value=owner.birthday; };
  const populateAnimals = list => { animals=list||[]; if(!animalSelect)return; animalSelect.innerHTML='<option value="">Choose an animal or group</option>'; animals.forEach((animal,index)=>{const name=animal.pet_name||`${animal.pet_type} group`;const details=[animal.pet_type,name,animal.pet_breed,animal.last_service_type,animal.last_service_date].filter(Boolean).join(' · ');animalSelect.add(new Option(details,String(index)));}); };
  const lookup = async () => { const name=ownerInput?.value.trim()||''; const municipalityId=municipalityInput?.value||''; if(name.length<2||(municipalityInput&&!municipalityId)){hideOwner();return;} try{const query=new URLSearchParams({name});if(municipalityId)query.set('municipality_id',municipalityId);const response=await fetch(`${lookupUrl}?${query}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const data=response.ok?await response.json():{exists:false};if(!data.exists){hideOwner();return;}showOwner(data.owner||{});populateAnimals(data.pets||[]);}catch(error){hideOwner();} };
  const scheduleLookup = () => { clearTimeout(timer); timer=setTimeout(lookup,350); refreshSummary(); };
  ownerInput?.addEventListener('input',scheduleLookup); ownerInput?.addEventListener('change',scheduleLookup); municipalityInput?.addEventListener('change',()=>{hideOwner();scheduleLookup();});
  document.getElementById('btnUseSelectedPet')?.addEventListener('click',()=>{const animal=animals[Number(animalSelect?.value)];if(!animal)return;setSelect(animalType,animal.pet_type);animalName.value=animal.pet_name||'';animalBreed.value=animal.pet_breed||'';animalColor.value=animal.pet_color||'';animalCount.value=1;serviceDate.value=today;refreshSuggestions();refreshSummary();});
  document.getElementById('btnAddNewPet')?.addEventListener('click',()=>{setSelect(animalType,'');animalName.value='';animalBreed.value='';animalColor.value='';animalCount.value=1;serviceDate.value=today;refreshSuggestions();refreshSummary();});
  document.querySelectorAll('input[name="service_type"]').forEach(input=>input.addEventListener('change',()=>{serviceName.value='';refreshSuggestions();refreshSummary();}));
  animalType?.addEventListener('change',()=>{refreshSuggestions();refreshSummary();}); animalCount?.addEventListener('input',refreshSummary); serviceDate?.addEventListener('change',()=>{if(nextServiceDate)nextServiceDate.min=serviceDate.value||today;});
  form?.addEventListener('submit',()=>{if(!form.checkValidity())return;const button=form.querySelector('button[type="submit"]');if(button){button.disabled=true;button.textContent='Saving service…';}});
  refreshSuggestions(); refreshSummary(); if((ownerInput?.value||'').trim().length>=2)scheduleLookup();
});
</script>
@endpush
