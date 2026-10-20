@extends('layouts.app')
@section('title', 'LGU Settings — '.$municipality->name)
@section('content')
@include('partials.operations-ui-styles')
@php
  $canEdit = auth()->user()->can('update', $profile);
  $fields = [
    'office_name' => ['Agriculture office name', 100, 'text'],
    'website_url' => ['Official LGU website', 200, 'url'],
    'email' => ['Office email', 150, 'email'],
    'phone' => ['Office telephone / mobile', 60, 'text'],
    'office_address' => ['Office address', 200, 'text'],
    'latitude' => ['Office latitude', null, 'number'],
    'longitude' => ['Office longitude', null, 'number'],
    'signatory_name' => ['ID signatory name', 100, 'text'],
    'signatory_title' => ['ID signatory position', 100, 'text'],
  ];
  $mapQuery = $profile->latitude !== null && $profile->longitude !== null ? $profile->latitude.','.$profile->longitude : $profile->office_address;
@endphp
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Settings · LGU profile</div><h1>{{ $municipality->name }}</h1><p>{{ $municipality->supervisingProvince->name }} · These details identify the issuing office on this LGU's farmer cards.</p></div><a class="module-button" href="{{ route('settings.lgu.index') }}">All LGU settings</a></header>
  @if(session('success'))<div class="module-alert" role="status">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert" role="alert">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="module-alert" role="alert"><strong>Review the settings below.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @unless($canEdit)<div class="module-alert">You can view this profile. The System Owner, provincial Super Admin, or this municipality's head can update it.</div>@endunless
  <form action="{{ route('settings.lgu.update', $municipality) }}" method="POST" enctype="multipart/form-data" id="lguProfileForm" class="module-panel" style="padding:24px">
    @csrf @method('PUT')
    <input type="hidden" name="_record_version" value="{{ old('_record_version', $version) }}">
    <fieldset @disabled(!$canEdit) style="border:0;padding:0;margin:0;min-width:0">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Official office profile</h2><p>Use details approved by the LGU. Website addresses must start with https:// or http://.</p></div></div>
      <div class="module-form-grid">
        @foreach($fields as $name => [$label, $maximum, $type])
          @php
            $value = $profile->$name;
            if (!$profile->exists) {
              $value = match($name) {
                'office_name' => 'Municipal / City Agriculture Office',
                'signatory_name' => $signatory['name'] ?? null,
                'signatory_title' => $signatory['title'] ?? null,
                default => $value,
              };
            }
          @endphp
          <x-module.field :name="$name" :label="$label" :required="$name === 'office_name'">
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @if($maximum) maxlength="{{ $maximum }}" @endif @if($type === 'number') step="0.0000001" min="{{ $name === 'latitude' ? '-90' : '-180' }}" max="{{ $name === 'latitude' ? '90' : '180' }}" @endif @required($name === 'office_name') aria-describedby="{{ $name }}_error" @error($name) aria-invalid="true" @enderror>
          </x-module.field>
        @endforeach
        <x-module.field name="description" label="About this office" :full="true" hint="Used on this LGU's website when published. Up to 2,000 characters.">
          <textarea id="description" name="description" rows="3" maxlength="2000" aria-describedby="description_hint description_error">{{ old('description', $profile->description) }}</textarea>
        </x-module.field>
      </div>
      <p class="module-hint">Coordinates refer to the office. Enter both coordinates or leave both blank. Signatory name and position must be supplied together; clear both to omit the office signature line. Signatures are written in ink after printing.</p>
      <div class="module-form-section-head" style="margin-top:24px"><span class="module-step">2</span><div><h2>LGU logo</h2><p>Upload the official municipality or city logo. PNG, JPEG, or WebP; up to 2 MB and 64–2,400 pixels per side.</p></div></div>
      @if($profile->logo_path)<img src="{{ route('settings.lgu.logo', $municipality) }}" alt="{{ $municipality->name }} LGU logo" width="120" height="120" style="object-fit:contain;background:white;margin-bottom:16px">@endif
      <div class="module-form-grid">
        <x-module.field name="logo" label="Upload or replace logo" hint="A transparent PNG works well. If a save fails, select the file again."><input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp" aria-describedby="logo_hint logo_error"></x-module.field>
        @if($profile->logo_path)<x-module.field name="remove_logo" label="Remove current logo"><input type="checkbox" id="remove_logo" name="remove_logo" value="1" @checked(old('remove_logo')) aria-describedby="remove_logo_error"><span class="module-hint">The standard DA logo will be used when no LGU logo is configured.</span></x-module.field>@endif
      </div>
      <p class="module-hint">Saving updates the next card preview, print, and PNG download for farmers registered in {{ $municipality->name }}. Keep office details concise for the card; oversized text will require shortening before export. Previously printed or downloaded cards retain their original details.</p>
      <p class="module-hint">When this LGU's website is published, its office name, logo, website link, description, contact details and location are public. Changes here also update that website.</p>
      @if($canEdit)<div class="module-actions" style="margin-top:24px"><button class="module-button module-button-primary" type="submit" id="saveLguProfile">Save LGU settings</button><a class="module-button" href="{{ route('settings.lgu.edit', $municipality) }}">Discard changes</a></div>@endif
    </fieldset>
  </form>
  @if($profile->exists)
    <section class="module-panel" style="padding:24px;margin-top:20px" aria-labelledby="lguOfficeLinks"><h2 id="lguOfficeLinks">Office links</h2><div class="module-actions">
      @if($profile->website_url)<a class="module-button" href="{{ $profile->website_url }}" target="_blank" rel="noopener noreferrer">Visit official website</a>@endif
      @if($mapQuery)<a class="module-button" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($mapQuery) }}" target="_blank" rel="noopener noreferrer">View office location</a>@endif
      <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $municipality->id]) }}">Open farmer registry &amp; IDs</a>
    </div></section>
  @endif
  <section class="module-panel" style="padding:24px;margin-top:20px"><h2>LGU public website</h2><p>Set up a dedicated address, welcome page, service links and announcements using this office profile.</p>
    @if($websiteReady)<a class="module-button module-button-primary" href="{{ route('settings.lgu.website.edit', $municipality) }}">Manage LGU website</a>@else<p class="module-hint">Website settings are awaiting the database update.</p>@endif
  </section>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('lguProfileForm')?.addEventListener('submit', function () {
  const button = document.getElementById('saveLguProfile');
  if (button) { button.disabled = true; button.textContent = 'Saving…'; }
});
</script>
@endpush
