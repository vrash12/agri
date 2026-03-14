@php
  $a = $admin ?? null;
  $val = fn($k, $d='') => old($k, data_get($a, $k, $d));
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

<div class="grid grid-2">
  <div>
    <label>Name *</label>
    <input class="input" name="name" value="{{ $val('name') }}" required>
  </div>

  <div>
    <label>Email *</label>
    <input class="input" type="email" name="email" value="{{ $val('email') }}" required>
  </div>

  <div>
    <label>Password {{ $a ? '(optional)' : '*' }}</label>
    <input class="input" type="password" name="password" {{ $a ? '' : 'required' }} placeholder="{{ $a ? 'Leave blank to keep current password' : '' }}">
  </div>

  <div>
    <label>Confirm Password {{ $a ? '(optional)' : '*' }}</label>
    <input class="input" type="password" name="password_confirmation" {{ $a ? '' : 'required' }}>
  </div>
</div>

<div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
  <button class="btn" type="submit">{{ $buttonText ?? 'Save' }}</button>
  <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('admins.index') }}">Cancel</a>
</div>
