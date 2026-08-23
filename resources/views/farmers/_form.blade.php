@php
  $r = $record ?? null;
  $value = fn ($key, $default = '') => old($key, data_get($r, $key, $default));
  $dateValue = function ($key) use ($r) {
      $current = old($key, data_get($r, $key));
      return $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current;
  };
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

@if ($errors->any())
  <div class="module-alert module-alert-error" role="alert">
    <strong>Please review the highlighted information.</strong>
    <ul>
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head">
        <span class="module-step">1</span>
        <div>
          <h2>Registry identity</h2>
          <p>Use the names and identifiers shown on the farmer's official registry record.</p>
        </div>
      </div>
      <div class="module-form-body">
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
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG, or WebP · maximum 3 MB · at least 200×200 pixels</small>
            @if ($photoUrl)
              <label class="farmer-photo-remove"><input type="checkbox" name="remove_profile_photo" value="1"><span>Remove current picture</span></label>
            @endif
          </div>
        </div>

        <div class="module-form-grid">
          <div class="module-form-field module-form-field-third">
            <label for="last_name">Last name <span class="module-required">*</span></label>
            <input class="module-input" id="last_name" name="last_name" value="{{ $value('last_name') }}" autocomplete="family-name" required>
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="first_name">First name <span class="module-required">*</span></label>
            <input class="module-input" id="first_name" name="first_name" value="{{ $value('first_name') }}" autocomplete="given-name" required>
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="middle_name">Middle name</label>
            <input class="module-input" id="middle_name" name="middle_name" value="{{ $value('middle_name') }}" autocomplete="additional-name">
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ext_name">Name suffix</label>
            <input class="module-input" id="ext_name" name="ext_name" value="{{ $value('ext_name') }}" placeholder="Jr., Sr., III">
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="rsbsa_no">RSBSA number</label>
            <input class="module-input module-mono" id="rsbsa_no" name="rsbsa_no" value="{{ $value('rsbsa_no') }}" placeholder="Registry reference">
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ffrs">FFRS number</label>
            <input class="module-input module-mono" id="ffrs" name="ffrs" value="{{ $value('ffrs') }}" placeholder="FFRS reference">
            <div class="module-hint">Leave blank only when an FFRS number has not yet been issued.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head">
        <span class="module-step">2</span>
        <div>
          <h2>Personal and contact details</h2>
          <p>Contact information helps staff verify claims and coordinate field visits.</p>
        </div>
      </div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-third">
            <label for="gender">Gender</label>
            <select class="module-input" id="gender" name="gender">
              <option value="">Select gender</option>
              @foreach (['Male', 'Female', 'Other', 'Unspecified'] as $gender)
                <option value="{{ $gender }}" @selected($value('gender') === $gender)>{{ $gender }}</option>
              @endforeach
            </select>
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="date_of_birth">Date of birth</label>
            <input class="module-input" type="date" id="date_of_birth" name="date_of_birth" value="{{ $dateValue('date_of_birth') }}">
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="contact_number">Contact number</label>
            <input class="module-input" type="tel" id="contact_number" name="contact_number" value="{{ $value('contact_number') }}" autocomplete="tel" placeholder="09xx xxx xxxx">
          </div>
          <div class="module-form-field module-form-field-full">
            <label for="owner_name">Registered land owner</label>
            <input class="module-input" id="owner_name" name="owner_name" value="{{ $value('owner_name') }}" placeholder="Only if different from the farmer">
          </div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head">
        <span class="module-step">3</span>
        <div>
          <h2>Farm profile</h2>
          <p>Record the barangay, production area, and ecosystem used for planning and map validation.</p>
        </div>
      </div>
      <div class="module-form-body">
        <div class="module-form-grid">
          <div class="module-form-field module-form-field-full">
            <label for="farm_location">Farm location / barangay</label>
            <input class="module-input" id="farm_location" name="farm_location" value="{{ $value('farm_location') }}" placeholder="e.g. Poblacion North">
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="farm_area_ha">Declared farm area</label>
            <input class="module-input" type="number" step="0.01" min="0" id="farm_area_ha" name="farm_area_ha" value="{{ $value('farm_area_ha') }}" placeholder="0.00">
            <div class="module-hint">Area in hectares.</div>
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ecosystem">Ecosystem</label>
            <input class="module-input" id="ecosystem" name="ecosystem" value="{{ $value('ecosystem') }}" placeholder="Irrigated, rainfed, upland">
          </div>
          <div class="module-form-field module-form-field-third">
            <label for="ecosystem_source">Ecosystem source</label>
            <input class="module-input" id="ecosystem_source" name="ecosystem_source" value="{{ $value('ecosystem_source') }}" placeholder="Survey or registry source">
          </div>
        </div>
      </div>
    </section>

    <section class="module-form-section">
      <div class="module-form-section-head">
        <span class="module-step">4</span>
        <div>
          <h2>Sector classifications</h2>
          <p>Select only classifications supported by the farmer's submitted documents.</p>
        </div>
      </div>
      <div class="module-form-body">
        <div class="farmer-check-grid">
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
              <input type="checkbox" name="{{ $key }}" value="1" @checked($checked($key))>
              <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
            </label>
          @endforeach
        </div>
      </div>
    </section>
  </div>

  <aside class="module-form-aside">
    <section class="module-aside-card">
      <h3>Municipality assignment</h3>
      @if ($canChooseMunicipality ?? false)
        <label for="municipality_id" class="farmer-aside-label">Municipality <span class="module-required">*</span></label>
        <select class="module-input" id="municipality_id" name="municipality_id" required>
          <option value="">Select municipality</option>
          @foreach (($municipalities ?? collect()) as $municipality)
            <option value="{{ $municipality->id }}" @selected((string) old('municipality_id', data_get($r, 'municipality_id')) === (string) $municipality->id)>
              {{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}
            </option>
          @endforeach
        </select>
        <p class="farmer-aside-note">This controls which municipal users can access the profile.</p>
      @else
        <div class="farmer-assignment-value">
          <span>Assigned office</span>
          <strong>{{ data_get($assignedMunicipality, 'name', 'Municipal office') }}</strong>
          <small>{{ data_get($assignedMunicipality, 'province', 'Tarlac') }}</small>
        </div>
      @endif
    </section>

    <section class="module-aside-card">
      <h3>Before saving</h3>
      <ul>
        <li>Confirm spelling against the RSBSA or FFRS record.</li>
        <li>Use the barangay as the farm location for consistent reports.</li>
        <li>Add the exact farm boundary later from the Mapping Workspace.</li>
      </ul>
    </section>

    @if (data_get($r, 'exists'))
      <section class="module-aside-card">
        <h3>Profile record</h3>
        <div class="farmer-registry-id"><span>Farmer ID</span><strong>{{ $r->registry_id }}</strong></div>
        <p>Last updated {{ optional(data_get($r, 'updated_at'))->format('M d, Y · h:i A') ?: '—' }}.</p>
        <a class="farmer-card-link" href="{{ route('farmers.id-card', $r) }}">Open digital ID card</a>
      </section>
    @endif

    <section class="farmer-submit-card">
      <button class="module-button module-button-primary" type="submit">{{ $buttonText ?? 'Save farmer' }}</button>
      <a class="module-button" href="{{ route('farmers.index') }}">Cancel</a>
    </section>
  </aside>
</div>

@push('styles')
<style>
  .farmer-check-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
  .farmer-photo-control{display:flex;align-items:center;gap:15px;margin-bottom:15px;padding:13px;border:1px solid var(--module-border);border-radius:9px;background:#fbfcfb}.farmer-photo-preview{width:86px;height:96px;display:grid;place-items:center;flex:0 0 auto;overflow:hidden;border:1px solid #cfdbd3;border-radius:9px;color:#fff;background:#285a3b;font-size:18px;font-weight:900}.farmer-photo-preview img{width:100%;height:100%;object-fit:cover}.farmer-photo-copy{min-width:0}.farmer-photo-copy>label{display:block;color:var(--module-ink);font-size:11px;font-weight:850}.farmer-photo-copy p{max-width:560px;margin:4px 0 8px;color:var(--module-muted);font-size:9px;line-height:1.45}.farmer-photo-copy input[type="file"]{display:block;max-width:100%;font-size:9px}.farmer-photo-copy>small{display:block;margin-top:5px;color:var(--module-muted);font-size:8px}.farmer-photo-remove{display:flex!important;align-items:center;gap:6px;margin-top:8px;color:var(--module-red)!important;font-size:9px!important;cursor:pointer}.farmer-photo-remove input{accent-color:var(--module-red)}
  .farmer-registry-id{margin:7px 0;padding:8px 9px;border-radius:7px;background:var(--module-green-soft)}.farmer-registry-id span,.farmer-registry-id strong{display:block}.farmer-registry-id span{color:var(--module-muted);font-size:8px;font-weight:800;text-transform:uppercase}.farmer-registry-id strong{margin-top:3px;color:var(--module-green);font:900 11px ui-monospace,monospace}.farmer-card-link{display:inline-flex;margin-top:6px;color:var(--module-green);font-size:9px;font-weight:850;text-decoration:none}.farmer-card-link:hover{text-decoration:underline}
  .farmer-check-option{display:flex;align-items:flex-start;gap:9px;min-width:0;padding:11px;border:1px solid var(--module-border);border-radius:8px;background:#fff;cursor:pointer}
  .farmer-check-option:hover{border-color:#9fb6a8;background:#fbfcfb}.farmer-check-option input{margin-top:2px;accent-color:var(--module-green)}
  .farmer-check-option span,.farmer-check-option strong,.farmer-check-option small{display:block;min-width:0}.farmer-check-option strong{color:var(--module-ink);font-size:10px}.farmer-check-option small{margin-top:3px;color:var(--module-muted);font-size:8px;line-height:1.35}
  .farmer-aside-label{display:block;margin:0 0 6px;color:#45534a;font-size:9px;font-weight:850;text-transform:uppercase}.farmer-aside-note{margin-top:7px!important}
  .farmer-assignment-value{padding:11px;border-radius:8px;background:var(--module-green-soft)}.farmer-assignment-value span,.farmer-assignment-value strong,.farmer-assignment-value small{display:block}.farmer-assignment-value span{color:var(--module-green);font-size:8px;font-weight:850;text-transform:uppercase}.farmer-assignment-value strong{margin-top:5px;color:var(--module-ink);font-size:13px}.farmer-assignment-value small{margin-top:2px;color:var(--module-muted);font-size:9px}
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
