{{-- resources/views/anti_rabies_vaccinations/_form.blade.php --}}

@php
  $r = $record ?? null;
  $val = fn($k, $d='') => old($k, data_get($r, $k, $d));

  $barangays = [
    'Poblacion  Center',
    'Poblacion  South',
    'Poblacion  North',
    'Toledo',
    'Coral-Iloco',
    'Guiteb',
    'San Raymundo',
    'Balite',
    'Pance',
  ];

  $dogBreeds = [
    'Aspin (Asong Pinoy)',
    'Shih Tzu',
    'Poodle',
    'Chihuahua',
    'Golden Retriever',
    'Labrador Retriever',
    'German Shepherd',
    'Siberian Husky',
    'Pomeranian',
    'Rottweiler',
    'Doberman Pinscher',
    'Beagle',
    'Dachshund',
    'Pug',
    'American Bully',
    'French Bulldog',
    'Corgi (Pembroke Welsh Corgi)',
    'Maltese',
    'Yorkshire Terrier',
    'Mixed Breed',
    'Other',
  ];

  $catBreeds = [
    'Domestic Shorthair (Puspin)',
    'Persian',
    'Siamese',
    'Maine Coon',
    'Ragdoll',
    'British Shorthair',
    'Bengal',
    'Scottish Fold',
    'Sphynx',
    'Mixed Breed',
    'Other',
  ];

  $petColors = [
    'Black','White','Brown','Tan','Golden','Cream','Gray','Fawn','Brindle','Spotted',
    'Tabby','Calico','Tricolor','Bicolor','Red','Chocolate','Mixed / Other',
  ];
@endphp

@if ($errors->any())
  <div class="errorbox">
    <strong>Please fix the following:</strong>
    <ul>
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@push('styles')
<style>
  .errorbox{
    border: 1px solid rgba(239,68,68,.30);
    background: rgba(239,68,68,.08);
    color:#991b1b;
    padding: 12px 14px;
    border-radius: 14px;
    font-size: 13px;
    margin-bottom: 14px;
  }
  .errorbox ul{ margin: 6px 0 0 18px; padding: 0; }
  label{ font-weight:900; font-size:13px; display:block; margin-bottom:6px; }
  .field{ display:flex; flex-direction:column; gap:6px; }
  .req{ color:#b91c1c; font-weight:900; }
  .hint{ font-size: 12px; color: var(--muted); line-height: 1.35; }
  .panel{ border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: #fff; }
  .pill{
    display:inline-flex; align-items:center; gap: 6px;
    padding: 6px 10px; border-radius: 999px;
    border: 1px solid rgba(2,6,23,.10);
    background: #f8fafc; font-size: 12px; font-weight: 900;
    margin: 4px 6px 0 0;
  }
</style>
@endpush

<div class="card">
  <div class="card-header">
    <div>
      <h1 class="h1">Anti-Rabies Vaccination Form</h1>
      <p class="p">Start with the owner name. If the owner already exists, choose an existing pet or add a new pet.</p>
    </div>
  </div>

  <div style="padding:16px;">
    {{-- STEP 1: OWNER NAME --}}
    <div class="panel" style="margin-bottom:12px;">
      <div class="grid grid-3">
        <div class="field col-span-3">
          <label>Owner Name <span class="req">*</span></label>

          <input
            class="input"
            name="owner_name"
            id="owner_name"
            list="ownerNameList"
            value="{{ $val('owner_name') }}"
            placeholder="Search or type owner name"
            autocomplete="off"
            required
          />

          <datalist id="ownerNameList">
            @foreach(($ownerNameOptions ?? []) as $nm)
              <option value="{{ $nm }}"></option>
            @endforeach
          </datalist>

          <div class="hint">Tip: pick from suggestions to auto-detect existing owner and pets.</div>
        </div>
      </div>

      <div id="ownerLookupPanel" style="display:none; margin-top:12px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <div style="font-weight:900;">Existing owner found</div>
          <div id="ownerBadges"></div>
        </div>

        <div class="grid grid-3" style="margin-top:10px;">
          <div class="field col-span-3">
            <label>Select Existing Pet (optional)</label>
            <select class="input js-select" id="existingPetSelect">
              <option value="">— Choose a pet —</option>
            </select>
            <div class="hint">Choose a pet then click “Use selected pet” to fill the pet fields.</div>
          </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; margin-top:10px;">
          <button class="btn btn-soft" type="button" id="btnAddNewPet">+ Add New Pet</button>
          <button class="btn" type="button" id="btnUseSelectedPet">Use Selected Pet (Update Vaccination)</button>
        </div>
      </div>
    </div>

    {{-- STEP 2: DETAILS --}}
    <div class="grid grid-3">
      {{-- Owner --}}
      <div class="field">
        <label>Barangay <span class="req">*</span></label>
        <select class="input js-select" name="barangay" id="barangay" required>
          <option value="">— Select —</option>
          @foreach($barangays as $b)
            <option value="{{ $b }}" @selected($val('barangay') === $b)>{{ $b }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Birthday <span class="req">*</span></label>
        <input class="input" type="date" name="birthday" id="birthday" value="{{ $val('birthday') }}" required>
      </div>

      <div class="field">
        <label>Pet Type <span class="req">*</span></label>
        <select class="input js-select" name="pet_type" id="pet_type" required>
          <option value="">— Select —</option>
          <option value="Dog" @selected($val('pet_type') === 'Dog')>Dog</option>
          <option value="Cat" @selected($val('pet_type') === 'Cat')>Cat</option>
        </select>
      </div>

      {{-- Pet --}}
      <div class="field">
        <label>Pet Name <span class="req">*</span></label>
        <input class="input" name="pet_name" id="pet_name" value="{{ $val('pet_name') }}" required>
      </div>

      <div class="field">
        <label>Pet Breed <span class="req">*</span></label>
        <select class="input js-select" name="pet_breed" id="pet_breed" required>
          <option value="">— Select —</option>

          <optgroup label="Dog Breeds">
            @foreach($dogBreeds as $b)
              <option value="{{ $b }}" @selected($val('pet_breed') === $b)>{{ $b }}</option>
            @endforeach
          </optgroup>

          <optgroup label="Cat Breeds">
            @foreach($catBreeds as $b)
              <option value="{{ $b }}" @selected($val('pet_breed') === $b)>{{ $b }}</option>
            @endforeach
          </optgroup>

          @if($val('pet_breed') && !in_array($val('pet_breed'), array_merge($dogBreeds, $catBreeds), true))
            <option value="{{ $val('pet_breed') }}" selected>{{ $val('pet_breed') }}</option>
          @endif
        </select>
      </div>

      <div class="field">
        <label>Pet Color</label>
        <select class="input js-select" name="pet_color" id="pet_color">
          <option value="">— Select —</option>
          @foreach($petColors as $c)
            <option value="{{ $c }}" @selected($val('pet_color') === $c)>{{ $c }}</option>
          @endforeach

          @if($val('pet_color') && !in_array($val('pet_color'), $petColors, true))
            <option value="{{ $val('pet_color') }}" selected>{{ $val('pet_color') }}</option>
          @endif
        </select>
      </div>

      {{-- Vaccination --}}
      <div class="field">
        <label>Vaccination Date <span class="req">*</span></label>
        <input class="input" type="date" name="vaccination_date" id="vaccination_date"
               value="{{ $val('vaccination_date', now()->format('Y-m-d')) }}" required>
        <div class="hint">Year is automatically derived from the vaccination date.</div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  (function(){
    const lookupUrl = @json(route('anti-rabies-vaccinations.owner-lookup'));

    const ownerInput = document.getElementById('owner_name');
    const panel = document.getElementById('ownerLookupPanel');
    const badges = document.getElementById('ownerBadges');
    const petSelect = document.getElementById('existingPetSelect');

    const barangayEl = document.getElementById('barangay');
    const birthdayEl = document.getElementById('birthday');

    const petTypeEl = document.getElementById('pet_type');
    const petNameEl = document.getElementById('pet_name');
    const petBreedEl = document.getElementById('pet_breed');
    const petColorEl = document.getElementById('pet_color');

    const vaxDateEl = document.getElementById('vaccination_date');

    const btnAddNewPet = document.getElementById('btnAddNewPet');
    const btnUseSelectedPet = document.getElementById('btnUseSelectedPet');

    let petsCache = [];

    function debounce(fn, ms){
      let t;
      return function(...args){
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), ms);
      }
    }

    function setSelectValue(selectEl, value){
      if (!selectEl) return;
      selectEl.value = value || '';
      if (selectEl.tomselect) {
        selectEl.tomselect.setValue(value || '', true);
      }
    }

    function showExistingOwner(owner){
      badges.innerHTML = '';
      const makePill = (text) => {
        const s = document.createElement('span');
        s.className = 'pill';
        s.textContent = text;
        return s;
      };
      badges.appendChild(makePill(owner.barangay || '—'));
      badges.appendChild(makePill(owner.birthday || '—'));

      panel.style.display = 'block';

      if (barangayEl && !barangayEl.value && owner.barangay) setSelectValue(barangayEl, owner.barangay);
      if (birthdayEl && !birthdayEl.value && owner.birthday) birthdayEl.value = owner.birthday;
    }

    function hideExistingOwner(){
      panel.style.display = 'none';
      badges.innerHTML = '';
      petsCache = [];
      if (petSelect) {
        petSelect.innerHTML = '<option value="">— Choose a pet —</option>';
        if (petSelect.tomselect) {
          petSelect.tomselect.clearOptions();
          petSelect.tomselect.addOption({value:'', text:'— Choose a pet —'});
          petSelect.tomselect.setValue('', true);
        }
      }
    }

    function fillPetFields(pet){
      if (!pet) return;
      setSelectValue(petTypeEl, pet.pet_type);
      if (petNameEl) petNameEl.value = pet.pet_name || '';
      setSelectValue(petBreedEl, pet.pet_breed);
      setSelectValue(petColorEl, pet.pet_color || '');

      const today = new Date().toISOString().slice(0,10);
      if (vaxDateEl) vaxDateEl.value = today;
    }

    function clearPetFields(){
      setSelectValue(petTypeEl, '');
      if (petNameEl) petNameEl.value = '';
      setSelectValue(petBreedEl, '');
      setSelectValue(petColorEl, '');

      const today = new Date().toISOString().slice(0,10);
      if (vaxDateEl) vaxDateEl.value = today;
    }

    async function lookupOwner(name){
      const url = lookupUrl + '?name=' + encodeURIComponent(name);
      const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      if (!resp.ok) return { exists:false, pets:[] };
      return await resp.json();
    }

    const onOwnerChange = debounce(async function(){
      const name = (ownerInput?.value || '').trim();
      if (name.length < 2) { hideExistingOwner(); return; }

      try{
        const data = await lookupOwner(name);
        if (!data.exists) { hideExistingOwner(); return; }

        showExistingOwner(data.owner || {});
        petsCache = data.pets || [];

        if (petSelect) {
          petSelect.innerHTML = '<option value="">— Choose a pet —</option>';
          petsCache.forEach((p, idx) => {
            const label = `${p.pet_type} • ${p.pet_name} • ${p.pet_breed}${p.pet_color ? ' • ' + p.pet_color : ''} (last: ${p.last_vaccination_date || '—'})`;
            const opt = document.createElement('option');
            opt.value = String(idx);
            opt.textContent = label;
            petSelect.appendChild(opt);
          });

          if (petSelect.tomselect) {
            petSelect.tomselect.clearOptions();
            petSelect.tomselect.addOption({value:'', text:'— Choose a pet —'});
            petsCache.forEach((p, idx) => {
              const label = `${p.pet_type} • ${p.pet_name} • ${p.pet_breed}${p.pet_color ? ' • ' + p.pet_color : ''} (last: ${p.last_vaccination_date || '—'})`;
              petSelect.tomselect.addOption({ value: String(idx), text: label });
            });
            petSelect.tomselect.refreshOptions(false);
            petSelect.tomselect.setValue('', true);
          }
        }
      } catch(e){
        hideExistingOwner();
      }
    }, 350);

    ownerInput?.addEventListener('input', onOwnerChange);
    ownerInput?.addEventListener('change', onOwnerChange);

    btnUseSelectedPet?.addEventListener('click', function(){
      const idx = petSelect?.value;
      if (idx === '' || idx == null) return;
      const pet = petsCache[Number(idx)];
      fillPetFields(pet);
    });

    btnAddNewPet?.addEventListener('click', function(){
      if (petSelect?.tomselect) petSelect.tomselect.setValue('', true);
      if (petSelect) petSelect.value = '';
      clearPetFields();
    });

    if ((ownerInput?.value || '').trim().length >= 2) {
      onOwnerChange();
    }
  })();
</script>
@endpush