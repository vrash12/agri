@php
  $account = $account ?? new \App\Models\User();
  $editing = $account->exists;
  $lockedAssignment = $isOwnAccount ?? false;
  $selectedRole = old('role', $account->role ?: \App\Models\User::ROLE_MUNICIPAL_STAFF);
  $selectedMunicipality = old('municipality_id', $account->municipality_id);
  $selectedProvince = old('province_id', $account->province_id ?? $manager->province_id);
  $activeValue = (bool) old('is_active', $account->exists ? $account->is_active : true);
  $municipalManager = $isMunicipalHeadManager ?? false;
@endphp

@include('partials.record-version', ['record' => $account])

@if($errors->any())
  <div class="module-alert module-alert-error user-form-errors" role="alert" tabindex="-1">
    <strong>Please correct the following:</strong>
    <ul>
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="user-form-grid">
  <section class="user-form-card">
    <div class="user-form-card-head">
      <div>
        <h2>Account information</h2>
        <p>Name and login credentials used by the staff member.</p>
      </div>
    </div>

    <div class="user-field-grid">
      <div class="user-field user-field-wide">
        <label for="name">Full name <span>*</span></label>
        <input
          class="module-input input"
          id="name"
          name="name"
          type="text"
          value="{{ old('name', $account->name) }}"
          maxlength="255"
          autocomplete="name"
          required
        >
      </div>

      <div class="user-field user-field-wide">
        <label for="email">Email address <span>*</span></label>
        <input
          class="module-input input"
          id="email"
          name="email"
          type="email"
          value="{{ old('email', $account->email) }}"
          maxlength="255"
          autocomplete="email"
          required
        >
        <small>This email will be used to sign in.</small>
      </div>
    </div>
  </section>

  <section class="user-form-card">
    <div class="user-form-card-head">
      <div>
        <h2>Access assignment</h2>
        <p>Choose the role and the office scope this account may access.</p>
      </div>
    </div>

    <div class="user-field-grid">
      <div class="user-field">
        <label for="role">System role <span>*</span></label>

        @if($lockedAssignment)
          <input type="hidden" name="role" value="{{ $account->role }}">
          <div class="locked-value">
            <strong>{{ $account->role_label }}</strong>
            <small>Your own access assignment cannot be changed here.</small>
          </div>
        @elseif($municipalManager)
          <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_MUNICIPAL_STAFF }}">
          <div class="locked-value">
            <strong>Municipal Staff</strong>
            <small>Head agriculturists can only manage municipal-staff accounts.</small>
          </div>
        @else
          <select class="module-input input js-select" id="role" name="role" required>
            @foreach($roleOptions as $value => $label)
              <option value="{{ $value }}" @selected($selectedRole === $value)>
                {{ $label }}
              </option>
            @endforeach
          </select>
        @endif
      </div>

      <div class="user-field {{ in_array($selectedRole, \App\Models\User::PROVINCIAL_ROLES, true) ? '' : 'is-hidden' }}" id="provinceField">
        <label for="province_id">Province <span>*</span></label>
        @if($manager->isSystemOwner() && !$lockedAssignment)
          <select class="module-input input js-select" id="province_id" name="province_id">
            <option value="">— Select province —</option>
            @foreach($provinces as $province)
              <option value="{{ $province->id }}" @selected((int) $selectedProvince === (int) $province->id)>{{ $province->name }}</option>
            @endforeach
          </select>
          <small>This account can access only municipalities in the selected province.</small>
        @else
          <input type="hidden" name="province_id" value="{{ $lockedAssignment ? $account->province_id : $manager->province_id }}">
          <div class="locked-value">
            <strong>{{ ($lockedAssignment ? $account->province?->name : $manager->province?->name) ?? 'All supervised provinces' }}</strong>
            <small>The province assignment is fixed for your account.</small>
          </div>
        @endif
      </div>

      <div class="user-field {{ in_array($selectedRole, \App\Models\User::MUNICIPAL_ROLES, true) ? '' : 'is-hidden' }}" id="municipalityField">
        <label for="municipality_id">Municipality <span>*</span></label>
        @if($municipalManager)
          @php $managedMunicipality = $municipalities->first(); @endphp
          <input type="hidden" name="municipality_id" value="{{ $managedMunicipality?->id }}">
          <div class="locked-value">
            <strong>{{ $managedMunicipality?->name ?? 'Municipality not assigned' }}</strong>
            <small>Staff accounts are automatically assigned to your municipality.</small>
          </div>
        @else
          <select class="module-input input js-select" id="municipality_id" name="municipality_id">
            <option value="">— Select municipality —</option>
            @foreach($municipalities as $municipality)
              <option
                value="{{ $municipality->id }}"
                @selected((int) $selectedMunicipality === (int) $municipality->id)
              >
                {{ $municipality->name }}{{ $manager->isSystemOwner() ? ' · ' . $municipality->province : '' }}
              </option>
            @endforeach
          </select>
          <small>Required for head agriculturists and municipal staff. Their province is determined by this municipality.</small>
        @endif
      </div>

      <div class="user-field user-field-wide">
        @if($lockedAssignment)
          <input type="hidden" name="is_active" value="1">
          <div class="locked-value is-success">
            <strong>Account active</strong>
            <small>Your own account cannot be disabled here.</small>
          </div>
        @else
          <input type="hidden" name="is_active" value="0">
          <label class="active-toggle" for="is_active">
            <input
              id="is_active"
              name="is_active"
              type="checkbox"
              value="1"
              @checked($activeValue)
            >
            <span class="active-toggle-ui"></span>
            <span>
              <strong>Active account</strong>
              <small>{{ $editing && $account->isSuperAdmin() && !$account->is_active ? 'Set a new password below before activating this Super Admin.' : 'Inactive accounts cannot sign in.' }}</small>
            </span>
          </label>
        @endif
      </div>
    </div>
  </section>

  <details class="user-form-card user-password-section" id="accountPasswordSection" @if(!$editing || $errors->has('password') || $errors->has('password_confirmation')) open @endif>
    <summary><strong>{{ $editing ? 'Change password (optional)' : 'Set password' }}</strong><span>{{ $editing ? 'Leave both fields empty to keep the current password.' : 'Create the initial password for this account.' }}</span></summary>

    <div class="user-field-grid">
      <div class="user-field">
        <label for="password">Password {{ $editing ? '' : '*' }}</label>
        <div class="password-field-wrap">
          <input
            class="module-input input"
            id="password"
            name="password"
            type="password"
            minlength="8"
            autocomplete="new-password"
            {{ $editing ? '' : 'required' }}
          >
          <button type="button" class="password-peek" data-password-target="password" aria-label="Show password" aria-pressed="false">Show</button>
        </div>
        <small>Use at least 8 characters.</small>
      </div>

      <div class="user-field">
        <label for="password_confirmation">Confirm password {{ $editing ? '' : '*' }}</label>
        <div class="password-field-wrap">
          <input
            class="module-input input"
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            minlength="8"
            autocomplete="new-password"
            {{ $editing ? '' : 'required' }}
          >
          <button type="button" class="password-peek" data-password-target="password_confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button>
        </div>
      </div>
    </div>
  </details>
</div>

<div class="user-form-actions">
  <a class="module-button" href="{{ route('admins.index') }}">Cancel</a>
  <button class="module-button module-button-primary user-save-button" type="submit">
    {{ $editing ? 'Save changes' : 'Create user account' }}
  </button>
</div>

@push('styles')
  @include('partials.operations-ui-styles')
  @include('admins.partials.styles')
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.querySelector('[name="role"]');
    const municipalityField = document.getElementById('municipalityField');
    const municipalitySelect = document.getElementById('municipality_id');
    const municipalRoles = ['municipal_head', 'municipal_staff'];
    const provinceField = document.getElementById('provinceField');
    const provinceSelect = document.getElementById('province_id');
    const provincialRoles = ['super_admin', 'provincial_staff', 'provincial_vet'];

    function syncMunicipalityField() {
      if (!roleSelect) return;

      const municipal = municipalRoles.includes(roleSelect.value);
      const provincial = provincialRoles.includes(roleSelect.value);
      municipalityField?.classList.toggle('is-hidden', !municipal);
      provinceField?.classList.toggle('is-hidden', !provincial);
      if (municipalitySelect) municipalitySelect.required = municipal;
      if (provinceSelect) provinceSelect.required = provincial;
    }

    if (roleSelect) {
      roleSelect.addEventListener('change', syncMunicipalityField);
      if (roleSelect.tomselect) {
        roleSelect.tomselect.on('change', syncMunicipalityField);
      }
      syncMunicipalityField();
    }

    document.querySelectorAll('[data-password-target]').forEach(function (button) {
      button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.passwordTarget);
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input.id === 'password_confirmation' ? 'password confirmation' : 'password'}`);
      });
    });
  });
</script>
@endpush
