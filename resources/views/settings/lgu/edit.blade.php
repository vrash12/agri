@extends('layouts.app')
@section('title', 'LGU Settings — '.$municipality->name)
@section('content')
@include('partials.operations-ui-styles')
@include('settings.lgu.partials.styles')
@php
  $canEdit = auth()->user()->can('update', $profile);
  // A profile that has never been saved starts from the office defaults the card
  // already uses, so saving it unchanged does not alter existing farmer cards.
  $initial = function (string $name) use ($profile, $signatory) {
      if ($profile->exists) {
          return $profile->$name;
      }

      return match ($name) {
          'office_name' => 'Municipal / City Agriculture Office',
          'signatory_name' => $signatory['name'] ?? null,
          'signatory_title' => $signatory['title'] ?? null,
          default => $profile->$name,
      };
  };
  $input = function (string $name, string $label, ?int $maximum, string $type = 'text', array $extra = []) use ($initial) {
      return compact('name', 'label', 'maximum', 'type', 'extra') + ['value' => old($name, $initial($name))];
  };
  $sections = [
      'contact' => [
          $input('website_url', 'Official LGU website', 200, 'url', ['placeholder' => 'https://', 'hint' => 'Must start with https:// or http://.']),
          $input('email', 'Office email', 150, 'email'),
          $input('phone', 'Office telephone / mobile', 60, 'tel'),
          $input('office_address', 'Office address', 200, 'text', ['full' => true]),
          $input('latitude', 'Office latitude', null, 'number', ['min' => -90, 'max' => 90, 'placeholder' => 'e.g. 15.6667']),
          $input('longitude', 'Office longitude', null, 'number', ['min' => -180, 'max' => 180, 'placeholder' => 'e.g. 120.6417']),
      ],
      'signatory' => [
          $input('signatory_name', 'ID signatory name', 100),
          $input('signatory_title', 'ID signatory position', 100),
      ],
  ];
  $mapQuery = $profile->latitude !== null && $profile->longitude !== null ? $profile->latitude.','.$profile->longitude : $profile->office_address;
  $logoUrl = $profile->logo_path ? route('settings.lgu.logo', $municipality) : null;
  $province = $municipality->supervisingProvince->name;
@endphp
<div class="module-page lgu-set">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Settings · LGU profile &amp; farmer IDs</div>
      <h1>{{ $municipality->name }}</h1>
      <p>These details identify the issuing office on {{ $municipality->name }}'s farmer cards and on its public LGU website.</p>
      <div class="module-scope-note"><span>{{ $province }}</span><span aria-hidden="true">·</span>
        @if($profile->exists)<span class="lgu-badge lgu-badge-green">Profile saved</span>@else<span class="lgu-badge">Not yet saved</span>@endif
        @unless($canEdit)<span class="lgu-badge lgu-badge-blue">View only</span>@endunless
      </div>
    </div>
    <div class="module-header-actions">
      <a class="module-button" href="{{ route('settings.lgu.index') }}">All LGU settings</a>
      @if($websiteReady)<a class="module-button" href="{{ route('settings.lgu.website.edit', $municipality) }}">Manage LGU website</a>@endif
    </div>
  </header>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert" tabindex="-1" data-error-summary>
      <strong>Review the settings below. Nothing was saved.</strong>
      <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#{{ $field === '_record_version' ? 'office_name' : $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
    </div>
  @endif
  @unless($canEdit)<div class="module-alert lgu-set-alert-info">You can view this profile. The System Owner, provincial Super Admin, or this municipality's head can update it.</div>@endunless

  <form action="{{ route('settings.lgu.update', $municipality) }}" method="POST" enctype="multipart/form-data" id="lguProfileForm" class="module-form-shell">
    @csrf @method('PUT')
    <input type="hidden" name="_record_version" value="{{ old('_record_version', $version) }}">
    <div class="module-form-main">
      <fieldset class="lgu-set-fieldset" @disabled(!$canEdit)>
        <legend class="lgu-sr-only">LGU office profile for {{ $municipality->name }}</legend>

        <section class="module-form-section" aria-labelledby="lguOfficeHeading">
          <div class="module-form-section-head">@include('public-lgu.partials.icon', ['name' => 'building'])<div><h2 id="lguOfficeHeading">Office identity</h2><p>Use the name and description approved by the LGU. Fields marked <span class="module-required">*</span> are required.</p></div></div>
          <div class="module-form-body"><div class="module-form-grid">
            <x-module.field name="office_name" label="Agriculture office name" :required="true" :full="true" hint="Printed in capitals on farmer cards. Keep it concise.">
              <input id="office_name" name="office_name" type="text" value="{{ old('office_name', $initial('office_name')) }}" maxlength="100" required data-preview-source="office" aria-describedby="office_name_hint office_name_error" @error('office_name') aria-invalid="true" @enderror>
            </x-module.field>
            <x-module.field name="description" label="About this office" :full="true" hint="Shown on this LGU's website when published. Plain text, up to 2,000 characters.">
              <textarea id="description" name="description" rows="4" maxlength="2000" aria-describedby="description_hint description_error" @error('description') aria-invalid="true" @enderror>{{ old('description', $profile->description) }}</textarea>
            </x-module.field>
          </div></div>
        </section>

        <section class="module-form-section" aria-labelledby="lguContactHeading">
          <div class="module-form-section-head">@include('public-lgu.partials.icon', ['name' => 'pin'])<div><h2 id="lguContactHeading">Contact &amp; location</h2><p>How residents reach the office. All fields are optional.</p></div></div>
          <div class="module-form-body"><div class="module-form-grid">
            @foreach($sections['contact'] as $field)
              <x-module.field :name="$field['name']" :label="$field['label']" :full="$field['extra']['full'] ?? false" :hint="$field['extra']['hint'] ?? null">
                <input id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $field['value'] }}"
                  @if($field['maximum']) maxlength="{{ $field['maximum'] }}" @endif
                  @if($field['type'] === 'number') step="0.0000001" min="{{ $field['extra']['min'] }}" max="{{ $field['extra']['max'] }}" inputmode="decimal" @endif
                  @isset($field['extra']['placeholder']) placeholder="{{ $field['extra']['placeholder'] }}" @endisset
                  @if($field['name'] === 'office_address') data-preview-source="address" @endif
                  aria-describedby="{{ $field['name'] }}_hint {{ $field['name'] }}_error" @error($field['name']) aria-invalid="true" @enderror>
              </x-module.field>
            @endforeach
          </div>
          <p class="lgu-set-note">@include('public-lgu.partials.icon', ['name' => 'pin', 'size' => 18])<span>Coordinates mark the office itself, not farmland. Enter both coordinates or leave both blank. Without them, the office address is used for map links.</span></p>
          </div>
        </section>

        <section class="module-form-section" aria-labelledby="lguSignatoryHeading">
          <div class="module-form-section-head">@include('public-lgu.partials.icon', ['name' => 'card'])<div><h2 id="lguSignatoryHeading">Farmer ID signatory</h2><p>Printed beneath the office signature line on the back of each card.</p></div></div>
          <div class="module-form-body"><div class="module-form-grid">
            @foreach($sections['signatory'] as $field)
              <x-module.field :name="$field['name']" :label="$field['label']">
                <input id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="text" value="{{ $field['value'] }}" maxlength="{{ $field['maximum'] }}"
                  data-preview-source="{{ $field['name'] === 'signatory_name' ? 'signer' : 'signer-title' }}"
                  aria-describedby="{{ $field['name'] }}_error" @error($field['name']) aria-invalid="true" @enderror>
              </x-module.field>
            @endforeach
          </div>
          <p class="lgu-set-note">@include('public-lgu.partials.icon', ['name' => 'user', 'size' => 18])<span>Enter the name and position together, or clear both to omit the office signature line. Cards are signed in ink after printing; no signature image is stored.</span></p>
          </div>
        </section>

        <section class="module-form-section" aria-labelledby="lguLogoHeading">
          <div class="module-form-section-head">@include('public-lgu.partials.icon', ['name' => 'image'])<div><h2 id="lguLogoHeading">LGU logo</h2><p>The official municipality or city logo for cards and the public website.</p></div></div>
          <div class="module-form-body">
            <div class="lgu-set-media">
              <div class="lgu-set-tile" id="logoTile" data-saved-src="{{ $logoUrl }}">
                @if($logoUrl)<img src="{{ $logoUrl }}" alt="Current {{ $municipality->name }} LGU logo" width="120" height="120">
                @else<span>@include('public-lgu.partials.icon', ['name' => 'image'])No logo uploaded</span>@endif
              </div>
              <div class="lgu-set-picker">
                <x-module.field name="logo" :label="$logoUrl ? 'Replace logo' : 'Upload logo'" hint="PNG, JPEG or WebP; up to 2 MB and 64–2,400 pixels per side. A transparent PNG works best. If saving fails, choose the file again.">
                  <div class="module-dropzone">
                    <span class="module-dropzone-icon">@include('public-lgu.partials.icon', ['name' => 'upload'])</span>
                    <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp" data-max-bytes="2097152" data-preview-tile="logoTile" aria-describedby="logo_hint logo_status logo_error" @error('logo') aria-invalid="true" @enderror>
                  </div>
                  <p class="lgu-set-file-status" id="logo_status" aria-live="polite"></p>
                </x-module.field>
                @if($logoUrl)
                  <div class="module-form-field">
                    <label class="lgu-set-check" for="remove_logo"><input type="checkbox" id="remove_logo" name="remove_logo" value="1" @checked(old('remove_logo')) aria-describedby="remove_logo_hint remove_logo_error"><span>Remove current logo<span class="module-hint lgu-set-check-hint" id="remove_logo_hint">The standard DA logo is used when no LGU logo is configured.</span></span></label>
                    @error('remove_logo')<span class="module-hint module-required" id="remove_logo_error">{{ $message }}</span>@enderror
                  </div>
                @endif
              </div>
            </div>
          </div>
        </section>

        @if($canEdit)
          <div class="module-form-actions lgu-set-sticky-actions">
            <span class="module-hint" style="margin-right:auto">Saving updates the next card preview, print and PNG download. Printed cards keep their original details.</span>
            <a class="module-button" href="{{ route('settings.lgu.edit', $municipality) }}">Discard changes</a>
            <button class="module-button module-button-primary" type="submit" id="saveLguProfile">Save LGU settings</button>
          </div>
        @endif
      </fieldset>
    </div>

    <aside class="module-form-aside" aria-label="Profile preview and links">
      <div class="module-aside-card">
        <h3>@include('public-lgu.partials.icon', ['name' => 'card'])Farmer ID issuer preview</h3>
        <template id="issuerLogoPlaceholder">@include('public-lgu.partials.icon', ['name' => 'building'])</template>
        <div class="lgu-issuer" id="issuerPreview">
          <div class="lgu-issuer-band"></div>
          <div class="lgu-issuer-head">
            <span class="lgu-issuer-logo" data-preview-logo>@if($logoUrl)<img src="{{ $logoUrl }}" alt="" width="46" height="46">@else @include('public-lgu.partials.icon', ['name' => 'building']) @endif</span>
            <span><span class="lgu-issuer-office" data-preview="office">{{ old('office_name', $initial('office_name')) ?: 'Agriculture office' }}</span><span class="lgu-issuer-place">{{ $municipality->name }}, {{ $province }}</span></span>
          </div>
          @php $signer = old('signatory_name', $initial('signatory_name')); $signerTitle = old('signatory_title', $initial('signatory_title')); @endphp
          <div class="lgu-issuer-sign" data-preview-sign @if(!$signer && !$signerTitle) hidden @endif><strong data-preview="signer">{{ $signer }}</strong><span data-preview="signer-title">{{ $signerTitle }}</span></div>
          <p class="lgu-issuer-nosign" data-preview-nosign @if($signer || $signerTitle) hidden @endif>No office signature line</p>
        </div>
        <p style="margin-top:10px">A simplified preview. Open a farmer's ID to see the full card.</p>
      </div>

      <div class="module-aside-card">
        <h3>@include('public-lgu.partials.icon', ['name' => 'eye'])Where these details appear</h3>
        <ul class="lgu-set-list">
          <li data-done="true">@include('public-lgu.partials.icon', ['name' => 'check'])<span>Farmer card preview, print and PNG download<small>For farmers registered in {{ $municipality->name }}</small></span></li>
          <li data-done="true">@include('public-lgu.partials.icon', ['name' => 'check'])<span>Public LGU website<small>Office name, logo, description, contacts and location become public once the website is published</small></span></li>
        </ul>
      </div>

      @if($profile->exists)
        <section class="module-aside-card" aria-labelledby="lguOfficeLinks">
          <h3 id="lguOfficeLinks">@include('public-lgu.partials.icon', ['name' => 'external'])Office links</h3>
          <div class="lgu-set-links">
            @if($profile->website_url)<a class="module-button" href="{{ $profile->website_url }}" target="_blank" rel="noopener noreferrer">@include('public-lgu.partials.icon', ['name' => 'globe', 'size' => 18])Visit official website</a>@endif
            @if($mapQuery)<a class="module-button" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($mapQuery) }}" target="_blank" rel="noopener noreferrer">@include('public-lgu.partials.icon', ['name' => 'pin', 'size' => 18])View office location</a>@endif
            <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $municipality->id]) }}">@include('public-lgu.partials.icon', ['name' => 'card', 'size' => 18])Open farmer registry &amp; IDs</a>
          </div>
        </section>
      @endif

      <section class="module-aside-card" aria-labelledby="lguWebsiteLink">
        <h3 id="lguWebsiteLink">@include('public-lgu.partials.icon', ['name' => 'globe'])LGU public website</h3>
        <p>Set up a dedicated address, welcome page, services and announcements using this office profile.</p>
        @if($websiteReady)<a class="module-button module-button-primary" style="width:100%;margin-top:12px" href="{{ route('settings.lgu.website.edit', $municipality) }}">Manage LGU website</a>@else<p class="module-hint" style="margin-top:8px">Website settings are awaiting the database update.</p>@endif
      </section>
    </aside>
  </form>
</div>
@endsection
@push('scripts')
@include('settings.lgu.partials.scripts')
<script>
(() => {
  const form = document.getElementById('lguProfileForm');
  const preview = document.getElementById('issuerPreview');
  if (!form || !preview) return;
  const value = key => (form.querySelector(`[data-preview-source="${key}"]`)?.value || '').trim();
  const sign = preview.querySelector('[data-preview-sign]');
  const noSign = preview.querySelector('[data-preview-nosign]');
  const refresh = () => {
    preview.querySelector('[data-preview="office"]').textContent = value('office') || 'Agriculture office';
    const signer = value('signer');
    const title = value('signer-title');
    preview.querySelector('[data-preview="signer"]').textContent = signer;
    preview.querySelector('[data-preview="signer-title"]').textContent = title;
    sign.hidden = !signer && !title;
    noSign.hidden = !sign.hidden;
  };
  form.addEventListener('input', refresh);

  // Mirror the logo tile (current, newly chosen, or marked for removal) in the issuer preview.
  const tile = document.getElementById('logoTile');
  const mark = preview.querySelector('[data-preview-logo]');
  const placeholder = document.getElementById('issuerLogoPlaceholder');
  const syncLogo = () => {
    const removing = Boolean(document.getElementById('remove_logo')?.checked);
    const image = tile?.querySelector('img');
    mark.replaceChildren();
    if (image && !removing) {
      const copy = new Image(46, 46);
      copy.alt = '';
      copy.src = image.src;
      mark.append(copy);
    } else if (placeholder) {
      mark.append(placeholder.content.cloneNode(true));
    }
    tile?.classList.toggle('is-removing', removing);
  };
  form.addEventListener('lgu:preview-changed', syncLogo);
  document.getElementById('remove_logo')?.addEventListener('change', syncLogo);
  syncLogo();

  form.addEventListener('submit', () => {
    const button = document.getElementById('saveLguProfile');
    if (button) { button.disabled = true; button.textContent = 'Saving…'; }
  });
})();
</script>
@endpush
