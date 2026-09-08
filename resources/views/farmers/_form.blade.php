@php
  $r = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($r, $key, $default));
  $dateValue = function ($key) use ($r) {
      $current = old($key, data_get($r, $key));
      return $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current;
  };
  $showDetails = fn (array $keys) => $errors->hasAny($keys) || collect($keys)->contains(fn ($key) => filled(old($key, data_get($r, $key))));
  $checked = fn ($key) => (bool) old($key, data_get($r, $key, false));
  $assignedMunicipality = data_get($r, 'municipality') ?: data_get(auth()->user(), 'municipality');
  $photoUrl = data_get($r, 'exists') && data_get($r, 'profile_photo_path')
      ? route('farmers.photo', $r)
      : null;
  $photoInitials = strtoupper(
      substr((string) data_get($r, 'first_name', 'F'), 0, 1)
      .substr((string) data_get($r, 'last_name', 'R'), 0, 1)
  );
@endphp

@include('partials.record-version', ['record' => $r])

@if ($errors->any())
  <div class="module-alert module-alert-error" role="alert" tabindex="-1" data-form-error-summary>
    <strong>Please review the highlighted information.</strong>
    <ul>
      @foreach ($errors->messages() as $field => $messages)
        <li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>
      @endforeach
    </ul>
  </div>
@endif

<div class="module-form-shell farmer-simple-form">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head"><div><h2>Municipality</h2><p>Fields marked * are required.</p></div></div><div class="module-form-body">
      @if ($canChooseMunicipality ?? false)
        <label for="municipality_id" class="farmer-aside-label">Municipality <span class="module-required">*</span></label>
        <select class="module-input" id="municipality_id" @if($errors->has('municipality_id')) aria-invalid="true" aria-describedby="municipality_id-error" @endif name="municipality_id" required>
          <option value="">Select municipality</option>
          @foreach (($municipalities ?? collect()) as $municipality)
            <option value="{{ $municipality->id }}" @selected((string) old('municipality_id', data_get($r, 'municipality_id')) === (string) $municipality->id)>
              {{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}
            </option>
          @endforeach
        </select>
            @error('municipality_id')<div class="module-field-error" id="municipality_id-error">{{ $message }}</div>@enderror
        <p class="farmer-aside-note">This controls which municipal users can access the profile.</p>
      @else
        <div class="farmer-assignment-value">
          <span>Assigned office</span>
          <strong>{{ data_get($assignedMunicipality, 'name', 'Municipal office') }}</strong>
          <small>{{ data_get($assignedMunicipality, 'province', 'Tarlac') }}</small>
        </div>
      @endif
      </div>
    </section>


    <section class="module-form-section">
      <div class="module-form-section-head">
        <div>
          <h2>Registry identity</h2>
          <p>Enter the farmer's name and available registry numbers.</p>
        </div>
      </div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-third">
            <label for="last_name">Last name <span class="module-required">*</span></label>
            <input class="module-input" id="last_name" @if($errors->has('last_name')) aria-invalid="true" aria-describedby="last_name-error" @endif name="last_name" value="{{ $value('last_name') }}" autocomplete="family-name" required>
            @error('last_name')<div class="module-field-error" id="last_name-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="first_name">First name <span class="module-required">*</span></label>
            <input class="module-input" id="first_name" @if($errors->has('first_name')) aria-invalid="true" aria-describedby="first_name-error" @endif name="first_name" value="{{ $value('first_name') }}" autocomplete="given-name" required>
            @error('first_name')<div class="module-field-error" id="first_name-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="middle_name">Middle name</label>
            <input class="module-input" id="middle_name" @if($errors->has('middle_name')) aria-invalid="true" aria-describedby="middle_name-error" @endif name="middle_name" value="{{ $value('middle_name') }}" autocomplete="additional-name">
            @error('middle_name')<div class="module-field-error" id="middle_name-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ext_name">Name suffix</label>
            <input class="module-input" id="ext_name" @if($errors->has('ext_name')) aria-invalid="true" aria-describedby="ext_name-error" @endif name="ext_name" value="{{ $value('ext_name') }}" placeholder="Jr., Sr., III">
            @error('ext_name')<div class="module-field-error" id="ext_name-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="rsbsa_no">RSBSA number</label>
            <input class="module-input module-mono" id="rsbsa_no" @if($errors->has('rsbsa_no')) aria-invalid="true" aria-describedby="rsbsa_no-error" @endif name="rsbsa_no" value="{{ $value('rsbsa_no') }}" placeholder="Registry reference">
            @error('rsbsa_no')<div class="module-field-error" id="rsbsa_no-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ffrs">FFRS number</label>
            <input class="module-input module-mono" id="ffrs" @if($errors->has('ffrs')) aria-invalid="true" aria-describedby="ffrs-error" @endif name="ffrs" value="{{ $value('ffrs') }}" placeholder="FFRS reference">
            @error('ffrs')<div class="module-field-error" id="ffrs-error">{{ $message }}</div>@enderror
            <div class="module-hint">Leave blank only when an FFRS number has not yet been issued.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head">
        <div>
          <h2>Farm profile</h2>
          <p>Add the location and declared area when available.</p>
        </div>
      </div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-full">
            <label for="farm_location">Farm location / barangay</label>
            <input class="module-input" id="farm_location" @if($errors->has('farm_location')) aria-invalid="true" aria-describedby="farm_location-error" @endif name="farm_location" value="{{ $value('farm_location') }}" placeholder="e.g. Poblacion North">
            @error('farm_location')<div class="module-field-error" id="farm_location-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="farm_area_ha">Declared farm area</label>
            <input class="module-input" type="number" step="0.01" min="0" id="farm_area_ha" @if($errors->has('farm_area_ha')) aria-invalid="true" aria-describedby="farm_area_ha-error" @endif name="farm_area_ha" value="{{ $value('farm_area_ha') }}" placeholder="0.00">
            @error('farm_area_ha')<div class="module-field-error" id="farm_area_ha-error">{{ $message }}</div>@enderror
            <div class="module-hint">Area in hectares.</div>
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ecosystem">Ecosystem</label>
            <input class="module-input" id="ecosystem" @if($errors->has('ecosystem')) aria-invalid="true" aria-describedby="ecosystem-error" @endif name="ecosystem" value="{{ $value('ecosystem') }}" placeholder="Irrigated, rainfed, upland">
            @error('ecosystem')<div class="module-field-error" id="ecosystem-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ecosystem_source">Ecosystem source</label>
            <input class="module-input" id="ecosystem_source" @if($errors->has('ecosystem_source')) aria-invalid="true" aria-describedby="ecosystem_source-error" @endif name="ecosystem_source" value="{{ $value('ecosystem_source') }}" placeholder="Survey or registry source">
            @error('ecosystem_source')<div class="module-field-error" id="ecosystem_source-error">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    </section>

    <details class="module-more" @if($showDetails(['gender', 'date_of_birth', 'contact_number', 'owner_name'])) open @endif>
      <summary>Personal and contact details <span>Optional</span></summary>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-third">
            <label for="gender">Gender</label>
            <select class="module-input" id="gender" @if($errors->has('gender')) aria-invalid="true" aria-describedby="gender-error" @endif name="gender">
              <option value="">Select gender</option>
              @foreach (['Male', 'Female', 'Other', 'Unspecified'] as $gender)
                <option value="{{ $gender }}" @selected($value('gender') === $gender)>{{ $gender }}</option>
              @endforeach
            </select>
            @error('gender')<div class="module-field-error" id="gender-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="date_of_birth">Date of birth</label>
            <input class="module-input" type="date" id="date_of_birth" @if($errors->has('date_of_birth')) aria-invalid="true" aria-describedby="date_of_birth-error" @endif name="date_of_birth" value="{{ $dateValue('date_of_birth') }}">
            @error('date_of_birth')<div class="module-field-error" id="date_of_birth-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="contact_number">Contact number</label>
            <input class="module-input" type="tel" id="contact_number" @if($errors->has('contact_number')) aria-invalid="true" aria-describedby="contact_number-error" @endif name="contact_number" value="{{ $value('contact_number') }}" autocomplete="tel" placeholder="09xx xxx xxxx">
            @error('contact_number')<div class="module-field-error" id="contact_number-error">{{ $message }}</div>@enderror
          </div>
          <div class="module-form-field module-form-field-full">
            <label for="owner_name">Registered land owner</label>
            <input class="module-input" id="owner_name" @if($errors->has('owner_name')) aria-invalid="true" aria-describedby="owner_name-error" @endif name="owner_name" value="{{ $value('owner_name') }}" placeholder="Only if different from the farmer">
            @error('owner_name')<div class="module-field-error" id="owner_name-error">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    </details>

    <details class="module-more" @if($errors->hasAny(['profile_photo', 'remove_profile_photo']) || $photoUrl) open @endif>
      <summary>Profile photo <span>Optional picture for the farmer ID</span></summary>
      <div class="module-more-content">
        <div class="farmer-photo-control">
          <div class="farmer-photo-preview" id="farmerPhotoPreview">
            @if ($photoUrl)
              <img src="{{ $photoUrl }}" alt="Current farmer profile photo">
            @else
              <span>{{ $photoInitials }}</span>
            @endif
          </div>
          <div class="farmer-photo-copy">
            <label for="profile_photo">Profile picture</label>
            <p>Use a clear, front-facing photo with a plain background. This appears on the farmer registry card.</p>
            <input type="file" id="profile_photo" @if($errors->has('profile_photo')) aria-invalid="true" aria-describedby="profile_photo-error" @endif name="profile_photo" accept="image/jpeg,image/png,image/webp">
            @error('profile_photo')<div class="module-field-error" id="profile_photo-error">{{ $message }}</div>@enderror
            <small>JPG, PNG, or WebP · maximum 3 MB · at least 200×200 pixels</small>
            @if ($photoUrl)
              <label class="farmer-photo-remove"><input type="checkbox" id="remove_profile_photo" @if($errors->has('remove_profile_photo')) aria-invalid="true" aria-describedby="remove_profile_photo-error" @endif name="remove_profile_photo" value="1" @checked(old('remove_profile_photo', false))><span>Remove current picture</span></label>
            @error('remove_profile_photo')<div class="module-field-error" id="remove_profile_photo-error">{{ $message }}</div>@enderror
            @endif
          </div>
        </div>

      </div>
    </details>
    <details class="module-more" @if($errors->hasAny(['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw']) || collect(['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'])->contains(fn ($key) => $checked($key))) open @endif>
      <summary>Sector classifications <span>Optional eligibility details</span></summary>
      <div class="module-form-body">
        <fieldset class="farmer-check-grid"><legend class="module-hint">Select classifications supported by the farmer's records.</legend>
          @foreach ([
            'is_arb' => ['ARB', 'Agrarian reform beneficiary'],
            'is_4ps' => ['4Ps', 'Pantawid household member'],
            'is_ip' => ['IP', 'Indigenous people'],
            'is_pwd' => ['PWD', 'Person with disability'],
            'is_sc' => ['Senior citizen', 'Senior citizen classification'],
            'is_ofw' => ['OFW', 'Overseas Filipino worker'],
          ] as $key => [$label, $description])
            <label class="farmer-check-option">
              <input type="hidden" name="{{ $key }}" value="0">
              <input type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked($checked($key))>
              <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
            </label>
          @endforeach
        </fieldset>
      </div>
    </details>
  </div>

    @if (data_get($r, 'exists'))
      <section class="module-aside-card farmer-record-reference">
        <h3>Profile record</h3>
        <div class="farmer-registry-id"><span>Farmer ID</span><strong>{{ $r->registry_id }}</strong></div>
        <p>Last updated {{ \App\Support\LocalTime::fromUtc(data_get($r, 'updated_at'))?->format('M d, Y · h:i A') ?: '—' }} PHT.</p>
        <a class="farmer-card-link" href="{{ route('farmers.id-card', $r) }}">Open digital ID card</a>
      </section>
    @endif

    <section class="farmer-submit-card">
      <button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save farmer' }}</button>
      <a class="module-button" href="{{ route('farmers.index') }}">Cancel</a>
    </section>

</div>

@push('styles')
<style>
  .farmer-simple-form{display:block;max-width:1000px;margin-inline:auto}.farmer-simple-form>.module-form-main{display:grid;gap:24px}.farmer-simple-form .farmer-submit-card{display:flex;justify-content:flex-end;margin-top:24px}.farmer-simple-form .farmer-submit-card .module-button{width:auto}.farmer-simple-form .farmer-record-reference{margin-top:24px}.farmer-check-grid{min-width:0;margin:0;border:0;padding:0;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
  .farmer-check-grid legend{grid-column:1/-1;margin-bottom:12px}.farmer-photo-control{display:flex;align-items:center;gap:15px;margin-bottom:15px;padding:13px;border:1px solid var(--module-border);border-radius:9px;background:#fbfcfb}.farmer-photo-preview{width:86px;height:96px;display:grid;place-items:center;flex:0 0 auto;overflow:hidden;border:1px solid #cfdbd3;border-radius:9px;color:#fff;background:#285a3b;font-size:18px;font-weight:700}.farmer-photo-preview img{width:100%;height:100%;object-fit:cover}.farmer-photo-copy{min-width:0}.farmer-photo-copy>label{display:block;color:var(--module-ink);font-size:12px;font-weight:700}.farmer-photo-copy p{max-width:560px;margin:4px 0 8px;color:var(--module-muted);font-size:12px;line-height:1.45}.farmer-photo-copy input[type="file"]{display:block;max-width:100%;font-size:12px}.farmer-photo-copy>small{display:block;margin-top:5px;color:var(--module-muted);font-size:12px}.farmer-photo-remove{display:flex!important;align-items:center;gap:6px;margin-top:8px;color:var(--module-red)!important;font-size:12px!important;cursor:pointer}.farmer-photo-remove input{accent-color:var(--module-red)}
  .farmer-registry-id{margin:7px 0;padding:8px 9px;border-radius:7px;background:var(--module-green-soft)}.farmer-registry-id span,.farmer-registry-id strong{display:block}.farmer-registry-id span{color:var(--module-muted);font-size:12px;font-weight:700;text-transform:none}.farmer-registry-id strong{margin-top:3px;color:var(--module-green);font:900 11px ui-monospace,monospace}.farmer-card-link{display:inline-flex;margin-top:6px;color:var(--module-green);font-size:12px;font-weight:700;text-decoration:none}.farmer-card-link:hover{text-decoration:underline}
  .farmer-check-option{display:flex;align-items:flex-start;gap:9px;min-width:0;padding:11px;border:1px solid var(--module-border);border-radius:8px;background:#fff;cursor:pointer}
  .farmer-check-option:hover{border-color:#9fb6a8;background:#fbfcfb}.farmer-check-option input{margin-top:2px;accent-color:var(--module-green)}
  .farmer-check-option span,.farmer-check-option strong,.farmer-check-option small{display:block;min-width:0}.farmer-check-option strong{color:var(--module-ink);font-size:12px}.farmer-check-option small{margin-top:3px;color:var(--module-muted);font-size:12px;line-height:1.35}
  .farmer-aside-label{display:block;margin:0 0 6px;color:#45534a;font-size:12px;font-weight:700;text-transform:none}.farmer-aside-note{margin-top:7px!important}
  .farmer-assignment-value{padding:11px;border-radius:8px;background:var(--module-green-soft)}.farmer-assignment-value span,.farmer-assignment-value strong,.farmer-assignment-value small{display:block}.farmer-assignment-value span{color:var(--module-green);font-size:12px;font-weight:700;text-transform:none}.farmer-assignment-value strong{margin-top:5px;color:var(--module-ink);font-size:13px}.farmer-assignment-value small{margin-top:2px;color:var(--module-muted);font-size:12px}
  .farmer-submit-card{display:grid;gap:7px;padding:12px;border:1px solid var(--module-border);border-radius:10px;background:#fff}.farmer-submit-card .module-button{width:100%}
  @media(max-width:760px){.farmer-check-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:480px){.farmer-check-grid{grid-template-columns:1fr}.farmer-photo-control{align-items:flex-start;flex-direction:column}.farmer-photo-preview{width:78px;height:86px}}
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('profile_photo');
    const preview = document.getElementById('farmerPhotoPreview');
    input?.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file || !preview) return;
      const reader = new FileReader();
      reader.onload = event => {
        preview.innerHTML = '';
        const image = document.createElement('img');
        image.src = event.target.result;
        image.alt = 'Selected farmer profile photo';
        preview.appendChild(image);
      };
      reader.readAsDataURL(file);
    });
  });
</script>
@endpush
