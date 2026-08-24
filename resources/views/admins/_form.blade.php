@php
  $account = $account ?? new \App\Models\User();
  $editing = $account->exists;
  $lockedSuperAdmin = $editing && $account->role === \App\Models\User::ROLE_SUPER_ADMIN;
  $selectedRole = old('role', $account->role ?: \App\Models\User::ROLE_MUNICIPAL_STAFF);
  $selectedMunicipality = old('municipality_id', $account->municipality_id);
  $activeValue = (bool) old('is_active', $account->exists ? $account->is_active : true);
  $municipalManager = $isMunicipalHeadManager ?? false;
@endphp

@include('partials.record-version', ['record' => $account])

@if($errors->any())
  <div class="user-form-errors" role="alert">
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
      <span class="user-form-card-icon">01</span>
      <div>
        <h2>Account information</h2>
        <p>Name and login credentials used by the staff member.</p>
      </div>
    </div>

    <div class="user-field-grid">
      <div class="user-field user-field-wide">
        <label for="name">Full name <span>*</span></label>
        <input
          class="input"
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
          class="input"
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
      <span class="user-form-card-icon">02</span>
      <div>
        <h2>Access assignment</h2>
        <p>Choose the role and the office scope this account may access.</p>
      </div>
    </div>

    <div class="user-field-grid">
      <div class="user-field">
        <label for="role">System role <span>*</span></label>

        @if($lockedSuperAdmin)
          <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_SUPER_ADMIN }}">
          <div class="locked-value">
            <strong>Super Admin</strong>
            <small>This protected role cannot be changed.</small>
          </div>
        @elseif($municipalManager)
          <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_MUNICIPAL_STAFF }}">
          <div class="locked-value">
            <strong>Municipal Staff</strong>
            <small>Head agriculturists can only manage municipal-staff accounts.</small>
          </div>
        @else
          <select class="input js-select" id="role" name="role" required>
            @foreach($roleOptions as $value => $label)
              <option value="{{ $value }}" @selected($selectedRole === $value)>
                {{ $label }}
              </option>
            @endforeach
          </select>
        @endif
      </div>

      <div class="user-field" id="municipalityField">
        <label for="municipality_id">Municipality <span>*</span></label>
        @if($municipalManager)
          @php $managedMunicipality = $municipalities->first(); @endphp
          <input type="hidden" name="municipality_id" value="{{ $managedMunicipality?->id }}">
          <div class="locked-value">
            <strong>{{ $managedMunicipality?->name ?? 'Municipality not assigned' }}</strong>
            <small>Staff accounts are automatically assigned to your municipality.</small>
          </div>
        @else
          <select class="input js-select" id="municipality_id" name="municipality_id">
            <option value="">— Select municipality —</option>
            @foreach($municipalities as $municipality)
              <option
                value="{{ $municipality->id }}"
                @selected((int) $selectedMunicipality === (int) $municipality->id)
              >
                {{ $municipality->name }}
              </option>
            @endforeach
          </select>
          <small>Required for head agriculturists and municipal staff. Provincial Veterinary Office accounts use province-wide Animal Health access.</small>
        @endif
      </div>

      <div class="user-field user-field-wide">
        @if($lockedSuperAdmin || ($isOwnAccount ?? false))
          <input type="hidden" name="is_active" value="1">
          <div class="locked-value is-success">
            <strong>Account active</strong>
            <small>Your own super-admin account cannot be disabled here.</small>
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
              <small>Inactive accounts cannot sign in.</small>
            </span>
          </label>
        @endif
      </div>
    </div>
  </section>

  <section class="user-form-card">
    <div class="user-form-card-head">
      <span class="user-form-card-icon">03</span>
      <div>
        <h2>{{ $editing ? 'Change password' : 'Set password' }}</h2>
        <p>{{ $editing ? 'Leave both fields empty to keep the current password.' : 'Create the initial password for this account.' }}</p>
      </div>
    </div>

    <div class="user-field-grid">
      <div class="user-field">
        <label for="password">Password {{ $editing ? '' : '*' }}</label>
        <div class="password-field-wrap">
          <input
            class="input"
            id="password"
            name="password"
            type="password"
            minlength="8"
            autocomplete="new-password"
            {{ $editing ? '' : 'required' }}
          >
          <button type="button" class="password-peek" data-password-target="password">Show</button>
        </div>
        <small>Use at least 8 characters.</small>
      </div>

      <div class="user-field">
        <label for="password_confirmation">Confirm password {{ $editing ? '' : '*' }}</label>
        <div class="password-field-wrap">
          <input
            class="input"
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            minlength="8"
            autocomplete="new-password"
            {{ $editing ? '' : 'required' }}
          >
          <button type="button" class="password-peek" data-password-target="password_confirmation">Show</button>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="user-form-actions">
  <a class="btn btn-soft" href="{{ route('admins.index') }}">Cancel</a>
  <button class="btn user-save-button" type="submit">
    {{ $editing ? 'Save Changes' : 'Create User Account' }}
  </button>
</div>

@push('styles')
<style>
  .user-form-errors{
    margin-bottom:16px;
    padding:13px 15px;
    border:1px solid #fecaca;
    border-radius:15px;
    color:#991b1b;
    background:#fef2f2;
    font-size:13px;
  }
  .user-form-errors ul{margin:7px 0 0 18px;padding:0;}
  .user-form-grid{display:grid;gap:14px;}
  .user-form-card{
    padding:18px;
    border:1px solid var(--border);
    border-radius:20px;
    background:#fff;
    box-shadow:0 10px 28px rgba(15,23,42,.05);
  }
  .user-form-card-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;}
  .user-form-card-icon{
    width:38px;height:38px;display:grid;place-items:center;flex:0 0 auto;
    border-radius:13px;color:#166534;background:rgba(34,197,94,.12);
    font-size:12px;font-weight:900;
  }
  .user-form-card h2{margin:0;color:#0f172a;font-size:16px;font-weight:900;}
  .user-form-card p{margin:4px 0 0;color:#64748b;font-size:12px;line-height:1.45;}
  .user-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
  .user-field-wide{grid-column:1/-1;}
  .user-field label{display:block;margin-bottom:7px;color:#334155;font-size:12px;font-weight:900;}
  .user-field label > span{color:#dc2626;}
  .user-field small{display:block;margin-top:6px;color:#64748b;font-size:11px;line-height:1.4;}
  .locked-value{
    min-height:47px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:13px;background:#f8fafc;
  }
  .locked-value strong{display:block;color:#0f172a;font-size:13px;}
  .locked-value small{margin-top:3px;}
  .locked-value.is-success{border-color:rgba(34,197,94,.24);background:rgba(34,197,94,.08);}
  .active-toggle{display:flex!important;align-items:center;gap:11px;margin:0!important;padding:12px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc;cursor:pointer;}
  .active-toggle input{position:absolute;opacity:0;pointer-events:none;}
  .active-toggle-ui{position:relative;width:42px;height:24px;flex:0 0 auto;border-radius:999px;background:#cbd5e1;transition:.18s ease;}
  .active-toggle-ui::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(15,23,42,.2);transition:.18s ease;}
  .active-toggle input:checked + .active-toggle-ui{background:#22c55e;}
  .active-toggle input:checked + .active-toggle-ui::after{transform:translateX(18px);}
  .active-toggle strong{display:block;color:#0f172a;font-size:12px;}
  .active-toggle small{margin-top:2px;}
  .password-field-wrap{position:relative;}
  .password-field-wrap .input{padding-right:68px;}
  .password-peek{position:absolute;right:7px;top:50%;transform:translateY(-50%);padding:6px 9px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#475569;font-size:10px;font-weight:900;cursor:pointer;}
  .user-form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;flex-wrap:wrap;}
  .user-save-button{color:#fff!important;border-color:#16a34a!important;background:linear-gradient(135deg,#22c55e,#15803d)!important;box-shadow:0 10px 22px rgba(22,163,74,.2);}
  #municipalityField.is-hidden{display:none;}
  @media(max-width:720px){
    .user-field-grid{grid-template-columns:1fr;}
    .user-field-wide{grid-column:auto;}
    .user-form-actions .btn{width:100%;}
  }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const municipalityField = document.getElementById('municipalityField');
    const municipalitySelect = document.getElementById('municipality_id');
    const municipalRoles = ['municipal_head', 'municipal_staff'];

    function syncMunicipalityField() {
      if (!roleSelect || !municipalityField || !municipalitySelect) return;

      const municipal = municipalRoles.includes(roleSelect.value);
      municipalityField.classList.toggle('is-hidden', !municipal);
      municipalitySelect.required = municipal;

      if (!municipal) {
        if (municipalitySelect.tomselect) {
          municipalitySelect.tomselect.clear(true);
        } else {
          municipalitySelect.value = '';
        }
      }
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
      });
    });
  });
</script>
@endpush
