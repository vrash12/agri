@extends('layouts.app')
@section('title', 'LGU Website — '.$municipality->name)
@section('content')
@include('partials.operations-ui-styles')
@php $canEdit = auth()->user()->can('update', $website); @endphp
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Settings · Public website</div><h1>{{ $municipality->name }} website</h1><p>{{ $municipality->supervisingProvince->name }} · Share office information and services with your community.</p></div><a class="module-button" href="{{ route('settings.lgu.edit', $municipality) }}">Office profile &amp; IDs</a></header>
  @if(session('success'))<div class="module-alert" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="module-alert" role="alert" tabindex="-1" id="websiteErrors"><strong>Review your website settings.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @unless($canEdit)<div class="module-alert">You can view and preview this website. An authorized LGU head or administrator can edit and publish it.</div>@endunless
  @unless($municipality->profile)<div class="module-alert">Save the <a href="{{ route('settings.lgu.edit', $municipality) }}">office profile</a> before publishing. You can save website content and preview it first.</div>@endunless
  @if($website->exists)
    <section class="module-panel" style="padding:20px;margin-bottom:20px"><h2>{{ $website->is_published ? 'Published' : 'Unpublished' }}</h2><p style="overflow-wrap:anywhere">{{ $publicUrl }}</p><div class="module-actions"><a class="module-button" href="{{ route('settings.lgu.website.preview', $municipality) }}" target="_blank" rel="noopener">Preview saved website</a>@if($website->is_published)<a class="module-button" href="{{ $publicUrl }}" target="_blank" rel="noopener">Open public website</a>@endif</div><p class="module-hint">Preview shows saved content. Save your changes before opening it.</p></section>
  @endif
  <form class="module-panel" style="padding:24px" action="{{ route('settings.lgu.website.update', $municipality) }}" method="POST" enctype="multipart/form-data" id="lguWebsiteForm">
    @csrf @method('PUT')
    <input type="hidden" name="_record_version" value="{{ old('_record_version', $version) }}">
    <fieldset @disabled(!$canEdit) style="border:0;padding:0;margin:0;min-width:0">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Website address &amp; publication</h2><p>Fields marked * are required. Use an address that identifies the LGU and province.</p></div></div>
      <div class="module-form-grid">
        <x-module.field name="slug" label="Website address" :required="true" hint="Lowercase letters, numbers and hyphens; 3–63 characters. The address is fixed after the first save to preserve shared links.">
          <input id="slug" name="slug" value="{{ old('slug', $website->slug) }}" minlength="3" maxlength="63" pattern="[a-z0-9]+(-[a-z0-9]+)*" required @readonly($website->exists) aria-describedby="slug_hint slug_error">
          <span class="module-hint">{{ $domain ? 'https://[address].'.$domain : rtrim(config('app.url'), '/').'/lgus/[address]' }}</span>
        </x-module.field>
        <x-module.field name="is_published" label="Publication status" :required="true" hint="Publishing makes the office profile, logo and website content visible to everyone. Unpublishing withdraws the page and its images.">
          <select name="is_published" id="is_published" aria-describedby="is_published_hint is_published_error"><option value="0" @selected(!old('is_published', $website->is_published))>Unpublished — staff preview only</option><option value="1" @selected(old('is_published', $website->is_published))>Published — visible to everyone</option></select>
        </x-module.field>
      </div>
      @unless($domain)<p class="module-hint">This website can publish at the path above. Subdomains become available after the platform domain, DNS and HTTPS are configured.</p>@endunless
      <div class="module-form-section-head" style="margin-top:24px"><span class="module-step">2</span><div><h2>Welcome page</h2><p>The LGU logo, office details, location and description come from <a href="{{ route('settings.lgu.edit', $municipality) }}">Office profile &amp; IDs</a>.</p></div></div>
      <div class="module-form-grid">
        <x-module.field name="headline" label="Welcome heading" :required="true"><input id="headline" name="headline" maxlength="120" value="{{ old('headline', $website->headline) }}" required aria-describedby="headline_error"></x-module.field>
        <x-module.field name="tagline" label="Short introduction"><input id="tagline" name="tagline" maxlength="200" value="{{ old('tagline', $website->tagline) }}" aria-describedby="tagline_error"></x-module.field>
        <x-module.field name="welcome_text" label="Welcome message" :full="true" hint="Plain text, up to 4,000 characters. HTML is displayed as text."><textarea id="welcome_text" name="welcome_text" rows="5" maxlength="4000" aria-describedby="welcome_text_hint welcome_text_error">{{ old('welcome_text', $website->welcome_text) }}</textarea></x-module.field>
        <x-module.field name="office_hours" label="Office hours"><input id="office_hours" name="office_hours" maxlength="200" value="{{ old('office_hours', $website->office_hours) }}" aria-describedby="office_hours_error"></x-module.field>
        <x-module.field name="cover" label="Cover photograph" hint="Use an LGU-approved photograph. PNG, JPEG or WebP; up to 4 MB, 320 × 180 to 4,000 × 4,000 pixels. Select it again if saving fails."><input type="file" name="cover" id="cover" accept="image/png,image/jpeg,image/webp" aria-describedby="cover_hint cover_error"></x-module.field>
        @if($website->cover_path)
          <div class="module-form-field"><img src="{{ route('settings.lgu.website.media', [$municipality, 'cover']) }}" alt="Saved website cover" width="280" height="160" style="max-width:100%;object-fit:cover"><label for="remove_cover"><input type="checkbox" id="remove_cover" name="remove_cover" value="1" @checked(old('remove_cover'))> Remove current cover</label>@error('remove_cover')<p role="alert">{{ $message }}</p>@enderror</div>
        @endif
      </div>
      <div class="module-form-section-head" style="margin-top:24px"><span class="module-step">3</span><div><h2>Services</h2><p>Add up to six services. Leave unused entries blank. A service without a link directs residents to the office contact section.</p></div></div>
      @for($i = 0; $i < 6; $i++)
        <details style="padding:12px 0;border-bottom:1px solid var(--ui-border)" @if($i === 0 || $errors->has('services.'.$i.'.*')) open @endif><summary style="cursor:pointer;min-height:44px">Service {{ $i + 1 }}{{ isset($website->services[$i]['title']) ? ' · '.$website->services[$i]['title'] : '' }}</summary><div class="module-form-grid">
          <x-module.field :name="'services.'.$i.'.title'" label="Service name"><input id="services.{{ $i }}.title" name="services[{{ $i }}][title]" maxlength="80" value="{{ old('services.'.$i.'.title', $website->services[$i]['title'] ?? '') }}" aria-describedby="services.{{ $i }}.title_error"></x-module.field>
          <x-module.field :name="'services.'.$i.'.url'" label="Service website (optional)"><input id="services.{{ $i }}.url" type="url" name="services[{{ $i }}][url]" maxlength="500" value="{{ old('services.'.$i.'.url', $website->services[$i]['url'] ?? '') }}" aria-describedby="services.{{ $i }}.url_error"></x-module.field>
          <x-module.field :name="'services.'.$i.'.description'" label="Service information / requirements" :full="true"><textarea id="services.{{ $i }}.description" name="services[{{ $i }}][description]" rows="3" maxlength="600" aria-describedby="services.{{ $i }}.description_error">{{ old('services.'.$i.'.description', $website->services[$i]['description'] ?? '') }}</textarea></x-module.field>
        </div></details>
      @endfor
      <div class="module-form-section-head" style="margin-top:24px"><span class="module-step">4</span><div><h2>Announcements</h2><p>Up to three current notices, displayed in this order. Clear an entry to remove it.</p></div></div>
      @for($i = 0; $i < 3; $i++)
        <details style="padding:12px 0;border-bottom:1px solid var(--ui-border)" @if($errors->has('announcements.'.$i.'.*')) open @endif><summary style="cursor:pointer;min-height:44px">Announcement {{ $i + 1 }}{{ isset($website->announcements[$i]['title']) ? ' · '.$website->announcements[$i]['title'] : '' }}</summary><div class="module-form-grid">
          <x-module.field :name="'announcements.'.$i.'.title'" label="Notice title"><input id="announcements.{{ $i }}.title" name="announcements[{{ $i }}][title]" maxlength="100" value="{{ old('announcements.'.$i.'.title', $website->announcements[$i]['title'] ?? '') }}" aria-describedby="announcements.{{ $i }}.title_error"></x-module.field>
          <x-module.field :name="'announcements.'.$i.'.date'" label="Notice date (optional)"><input id="announcements.{{ $i }}.date" type="date" name="announcements[{{ $i }}][date]" value="{{ old('announcements.'.$i.'.date', $website->announcements[$i]['date'] ?? '') }}" aria-describedby="announcements.{{ $i }}.date_error"></x-module.field>
          <x-module.field :name="'announcements.'.$i.'.body'" label="Notice details" :full="true"><textarea id="announcements.{{ $i }}.body" name="announcements[{{ $i }}][body]" rows="4" maxlength="1200" aria-describedby="announcements.{{ $i }}.body_error">{{ old('announcements.'.$i.'.body', $website->announcements[$i]['body'] ?? '') }}</textarea></x-module.field>
        </div></details>
      @endfor
      @if($canEdit)<div class="module-actions" style="margin-top:24px"><a class="module-button" href="{{ route('settings.lgu.website.edit', $municipality) }}">Discard changes</a><button class="module-button module-button-primary" type="submit" id="saveLguWebsite">Save website settings</button></div>@endif
    </fieldset>
  </form>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('websiteErrors')?.focus();
document.getElementById('lguWebsiteForm')?.addEventListener('invalid', function (event) {
  event.target.closest('details')?.setAttribute('open', '');
}, true);
document.getElementById('lguWebsiteForm')?.addEventListener('submit', function () {
  const button = document.getElementById('saveLguWebsite');
  if (button) { button.disabled = true; button.textContent = 'Saving…'; }
});
</script>
@endpush
