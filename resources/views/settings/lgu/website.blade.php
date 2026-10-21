@extends('layouts.app')
@section('title', 'LGU Website — '.$municipality->name)
@section('content')
@include('partials.operations-ui-styles')
@include('settings.lgu.partials.styles')
@php
  $canEdit = auth()->user()->can('update', $website);
  $province = $municipality->supervisingProvince->name;
  $profile = $municipality->profile;
  $published = (bool) old('is_published', $website->is_published);
  $addressPrefix = $domain ? 'https://' : rtrim(config('app.url'), '/').'/lgus/';
  $addressSuffix = $domain ? '.'.$domain : '';
  $services = collect($website->services ?? [])->filter(fn ($service) => filled($service['title'] ?? null));
  $announcements = collect($website->announcements ?? [])->filter(fn ($notice) => filled($notice['title'] ?? null));
  $checklist = [
      ['Office profile saved', (bool) $profile, 'Required before publishing'],
      ['LGU logo uploaded', (bool) $profile?->logo_path, 'From Office profile & IDs'],
      ['Cover photograph', (bool) $website->cover_path, 'Optional; an office panel is shown instead'],
      ['Office hours', filled($website->office_hours), 'Helps residents plan a visit'],
      ['Services listed', $services->isNotEmpty(), $services->count().' of 6 saved'],
      ['Announcements', $announcements->isNotEmpty(), 'Optional · '.$announcements->count().' of 3 saved'],
  ];
@endphp
<div class="module-page lgu-set">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Settings · Public website</div>
      <h1>{{ $municipality->name }} website</h1>
      <p>Share office information, services and announcements with your community.</p>
      <div class="module-scope-note"><span>{{ $province }}</span><span aria-hidden="true">·</span>
        @if(!$website->exists)<span class="lgu-badge">Not yet saved</span>
        @elseif($website->is_published)<span class="lgu-badge lgu-badge-green">Published</span>
        @else<span class="lgu-badge lgu-badge-amber">Unpublished · staff preview only</span>@endif
        @unless($canEdit)<span class="lgu-badge lgu-badge-blue">View only</span>@endunless
      </div>
    </div>
    <div class="module-header-actions">
      <a class="module-button" href="{{ route('settings.lgu.edit', $municipality) }}">Office profile &amp; IDs</a>
      @if($website->exists)<a class="module-button" href="{{ route('settings.lgu.website.preview', $municipality) }}" target="_blank" rel="noopener">@include('public-lgu.partials.icon', ['name' => 'eye', 'size' => 18])Preview saved website</a>@endif
    </div>
  </header>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert" tabindex="-1" id="websiteErrors" data-error-summary>
      <strong>Review your website settings. Nothing was saved.</strong>
      <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#{{ $field === '_record_version' ? 'headline' : ($field === 'is_published' ? 'is_published_0' : $field) }}">{{ $messages[0] }}</a></li>@endforeach</ul>
    </div>
  @endif
  @unless($canEdit)<div class="module-alert lgu-set-alert-info">You can view and preview this website. An authorized LGU head or administrator can edit and publish it.</div>@endunless
  @unless($profile)<div class="module-alert lgu-set-alert-warning">Save the <a href="{{ route('settings.lgu.edit', $municipality) }}">office profile</a> before publishing. You can save website content and preview it first.</div>@endunless

  <form class="module-form-shell" action="{{ route('settings.lgu.website.update', $municipality) }}" method="POST" enctype="multipart/form-data" id="lguWebsiteForm">
    @csrf @method('PUT')
    <input type="hidden" name="_record_version" value="{{ old('_record_version', $version) }}">
    <div class="module-form-main">
      <fieldset class="lgu-set-fieldset" @disabled(!$canEdit)>
        <legend class="lgu-sr-only">Public website for {{ $municipality->name }}</legend>

        <section class="module-form-section" aria-labelledby="websiteAddressHeading">
          <div class="module-form-section-head"><span class="module-step">1</span><div><h2 id="websiteAddressHeading">Address &amp; publication</h2><p>Fields marked <span class="module-required">*</span> are required. Choose an address that identifies the LGU and province.</p></div></div>
          <div class="module-form-body"><div class="module-form-grid">
            <x-module.field name="slug" label="LGU subdomain" :required="true" :full="true" :hint="$website->exists ? 'The subdomain is fixed to keep shared links working.' : 'Use a short lowercase address such as ramos-mao. It cannot be changed after the first save.'">
              <div class="lgu-set-address">
                <span aria-hidden="true">{{ $addressPrefix }}</span>
                <input id="slug" name="slug" value="{{ old('slug', $website->slug) }}" minlength="3" maxlength="63" pattern="[a-z0-9]+(-[a-z0-9]+)*" required autocapitalize="none" spellcheck="false" @readonly($website->exists) aria-describedby="slug_hint slug_url slug_error" @error('slug') aria-invalid="true" @enderror>
                @if($addressSuffix)<span aria-hidden="true">{{ $addressSuffix }}</span>@endif
              </div>
              <span class="lgu-set-url" id="slug_url" data-prefix="{{ $addressPrefix }}" data-suffix="{{ $addressSuffix }}">{{ $addressPrefix }}{{ old('slug', $website->slug) }}{{ $addressSuffix }}</span>
            </x-module.field>
            <fieldset class="lgu-set-group module-form-field module-form-field-full" aria-describedby="is_published_hint is_published_error">
              <legend class="lgu-set-legend">Publication status <span class="module-required">*</span></legend>
              <div class="lgu-set-choices">
                <label class="lgu-set-choice" for="is_published_0"><input type="radio" id="is_published_0" name="is_published" value="0" @checked(!$published) required><span><strong>Unpublished</strong><span>Only authorized staff can preview the page.</span></span></label>
                <label class="lgu-set-choice" for="is_published_1"><input type="radio" id="is_published_1" name="is_published" value="1" @checked($published) required><span><strong>Published</strong><span>The page, office profile and logo are visible to everyone.</span></span></label>
              </div>
              <div class="module-hint" id="is_published_hint">Unpublishing withdraws the page and its images from the public directory.</div>
              @error('is_published')<span class="module-hint module-required" id="is_published_error">{{ $message }}</span>@enderror
            </fieldset>
          </div>
          @if($domain)
            <p class="lgu-set-note">@include('public-lgu.partials.icon', ['name' => 'globe', 'size' => 18])<span>This office will use <strong>{{ old('slug', $website->slug) ?: '[slug]' }}.{{ $domain }}</strong>. The matching Hostinger hostname and HTTPS certificate must exist before publishing.</span></p>
          @else
            <p class="lgu-set-note">@include('public-lgu.partials.icon', ['name' => 'globe', 'size' => 18])<span>The website currently uses the central <code>/lgus/{slug}</code> address. Subdomains become available after the platform domain, DNS and HTTPS are configured.</span></p>
          @endif
          </div>
        </section>

        <section class="module-form-section" aria-labelledby="websiteWelcomeHeading">
          <div class="module-form-section-head"><span class="module-step">2</span><div><h2 id="websiteWelcomeHeading">Welcome page</h2><p>The logo, office details, location and description come from <a href="{{ route('settings.lgu.edit', $municipality) }}">Office profile &amp; IDs</a>.</p></div></div>
          <div class="module-form-body"><div class="module-form-grid">
            <x-module.field name="headline" label="Welcome heading" :required="true" :full="true"><input id="headline" name="headline" maxlength="120" value="{{ old('headline', $website->headline) }}" required aria-describedby="headline_error" @error('headline') aria-invalid="true" @enderror></x-module.field>
            <x-module.field name="tagline" label="Short introduction" :full="true" hint="One sentence shown under the heading and in the LGU directory."><input id="tagline" name="tagline" maxlength="200" value="{{ old('tagline', $website->tagline) }}" aria-describedby="tagline_hint tagline_error" @error('tagline') aria-invalid="true" @enderror></x-module.field>
            <x-module.field name="welcome_text" label="Welcome message" :full="true" hint="Plain text; line breaks are kept and HTML is shown as text."><textarea id="welcome_text" name="welcome_text" rows="5" maxlength="4000" aria-describedby="welcome_text_hint welcome_text_error" @error('welcome_text') aria-invalid="true" @enderror>{{ old('welcome_text', $website->welcome_text) }}</textarea></x-module.field>
            <x-module.field name="office_hours" label="Office hours" :full="true" hint="For example: Monday to Friday, 8:00 AM to 5:00 PM (except holidays)."><input id="office_hours" name="office_hours" maxlength="200" value="{{ old('office_hours', $website->office_hours) }}" aria-describedby="office_hours_hint office_hours_error" @error('office_hours') aria-invalid="true" @enderror></x-module.field>
          </div>
          <div class="lgu-set-media" style="margin-top:20px">
            <div class="lgu-set-tile lgu-set-tile-cover" id="coverTile">
              @if($website->cover_path)<img src="{{ route('settings.lgu.website.media', [$municipality, 'cover']) }}" alt="Saved website cover" width="240" height="135">
              @else<span>@include('public-lgu.partials.icon', ['name' => 'image'])No cover photograph</span>@endif
            </div>
            <div class="lgu-set-picker">
              <x-module.field name="cover" :label="$website->cover_path ? 'Replace cover photograph' : 'Cover photograph'" hint="Use an LGU-approved photograph. PNG, JPEG or WebP; up to 4 MB, 320 × 180 to 4,000 × 4,000 pixels. Choose it again if saving fails.">
                <div class="module-dropzone">
                  <span class="module-dropzone-icon">@include('public-lgu.partials.icon', ['name' => 'upload'])</span>
                  <input type="file" name="cover" id="cover" accept="image/png,image/jpeg,image/webp" data-max-bytes="4194304" data-preview-tile="coverTile" aria-describedby="cover_hint cover_status cover_error" @error('cover') aria-invalid="true" @enderror>
                </div>
                <p class="lgu-set-file-status" id="cover_status" aria-live="polite"></p>
              </x-module.field>
              @if($website->cover_path)
                <div class="module-form-field">
                  <label class="lgu-set-check" for="remove_cover"><input type="checkbox" id="remove_cover" name="remove_cover" value="1" @checked(old('remove_cover')) aria-describedby="remove_cover_error"><span>Remove current cover<span class="module-hint lgu-set-check-hint">The page shows an office panel instead.</span></span></label>
                  @error('remove_cover')<span class="module-hint module-required" id="remove_cover_error">{{ $message }}</span>@enderror
                </div>
              @endif
            </div>
          </div>
          </div>
        </section>

        <section class="module-form-section" aria-labelledby="websiteServicesHeading">
          <div class="module-form-section-head"><span class="module-step">3</span><div><h2 id="websiteServicesHeading">Services</h2><p>Up to six services. A service without a link directs residents to the office contacts.</p></div></div>
          <div class="module-form-body">
            <div class="lgu-set-entries-meta"><span>Leave unused entries blank.</span><span>{{ $services->count() }} of 6 saved</span></div>
            <div class="lgu-set-entries">
            @for($i = 0; $i < 6; $i++)
              @php $title = old('services.'.$i.'.title', $website->services[$i]['title'] ?? ''); @endphp
              <details class="lgu-set-entry" @if($i === 0 || $errors->has('services.'.$i.'.*')) open @endif>
                <summary><span class="lgu-set-entry-number">{{ $i + 1 }}</span><span class="lgu-set-entry-title" data-entry-title data-placeholder="Empty service slot" @if(!filled($title)) data-empty @endif>{{ filled($title) ? $title : 'Empty service slot' }}</span></summary>
                <div class="module-form-grid">
                  <x-module.field :name="'services.'.$i.'.title'" label="Service name"><input id="services.{{ $i }}.title" name="services[{{ $i }}][title]" maxlength="80" value="{{ $title }}" data-entry-source aria-describedby="services.{{ $i }}.title_error" @error('services.'.$i.'.title') aria-invalid="true" @enderror></x-module.field>
                  <x-module.field :name="'services.'.$i.'.url'" label="Service website (optional)"><input id="services.{{ $i }}.url" type="url" name="services[{{ $i }}][url]" maxlength="500" placeholder="https://" value="{{ old('services.'.$i.'.url', $website->services[$i]['url'] ?? '') }}" aria-describedby="services.{{ $i }}.url_error" @error('services.'.$i.'.url') aria-invalid="true" @enderror></x-module.field>
                  <x-module.field :name="'services.'.$i.'.description'" label="Service information / requirements" :full="true"><textarea id="services.{{ $i }}.description" name="services[{{ $i }}][description]" rows="3" maxlength="600" aria-describedby="services.{{ $i }}.description_error" @error('services.'.$i.'.description') aria-invalid="true" @enderror>{{ old('services.'.$i.'.description', $website->services[$i]['description'] ?? '') }}</textarea></x-module.field>
                </div>
              </details>
            @endfor
            </div>
          </div>
        </section>

        <section class="module-form-section" aria-labelledby="websiteNoticesHeading">
          <div class="module-form-section-head"><span class="module-step">4</span><div><h2 id="websiteNoticesHeading">Announcements</h2><p>Up to three current notices, shown in this order. Clear an entry to remove it.</p></div></div>
          <div class="module-form-body">
            <div class="lgu-set-entries-meta"><span>The first notice is highlighted as the latest.</span><span>{{ $announcements->count() }} of 3 saved</span></div>
            <div class="lgu-set-entries">
            @for($i = 0; $i < 3; $i++)
              @php $title = old('announcements.'.$i.'.title', $website->announcements[$i]['title'] ?? ''); @endphp
              <details class="lgu-set-entry" @if($errors->has('announcements.'.$i.'.*')) open @endif>
                <summary><span class="lgu-set-entry-number">{{ $i + 1 }}</span><span class="lgu-set-entry-title" data-entry-title data-placeholder="Empty announcement slot" @if(!filled($title)) data-empty @endif>{{ filled($title) ? $title : 'Empty announcement slot' }}</span></summary>
                <div class="module-form-grid">
                  <x-module.field :name="'announcements.'.$i.'.title'" label="Notice title"><input id="announcements.{{ $i }}.title" name="announcements[{{ $i }}][title]" maxlength="100" value="{{ $title }}" data-entry-source aria-describedby="announcements.{{ $i }}.title_error" @error('announcements.'.$i.'.title') aria-invalid="true" @enderror></x-module.field>
                  <x-module.field :name="'announcements.'.$i.'.date'" label="Notice date (optional)"><input id="announcements.{{ $i }}.date" type="date" name="announcements[{{ $i }}][date]" value="{{ old('announcements.'.$i.'.date', $website->announcements[$i]['date'] ?? '') }}" aria-describedby="announcements.{{ $i }}.date_error" @error('announcements.'.$i.'.date') aria-invalid="true" @enderror></x-module.field>
                  <x-module.field :name="'announcements.'.$i.'.body'" label="Notice details" :full="true"><textarea id="announcements.{{ $i }}.body" name="announcements[{{ $i }}][body]" rows="4" maxlength="1200" aria-describedby="announcements.{{ $i }}.body_error" @error('announcements.'.$i.'.body') aria-invalid="true" @enderror>{{ old('announcements.'.$i.'.body', $website->announcements[$i]['body'] ?? '') }}</textarea></x-module.field>
                </div>
              </details>
            @endfor
            </div>
          </div>
        </section>

        @if($canEdit)
          <div class="module-form-actions lgu-set-sticky-actions">
            <span class="module-hint" style="margin-right:auto" id="publishSummary">{{ $published ? 'Saving publishes these changes immediately.' : 'Saving keeps the website unpublished.' }}</span>
            <a class="module-button" href="{{ route('settings.lgu.website.edit', $municipality) }}">Discard changes</a>
            <button class="module-button module-button-primary" type="submit" id="saveLguWebsite">Save website settings</button>
          </div>
        @endif
      </fieldset>
    </div>

    <aside class="module-form-aside" aria-label="Website status and checklist">
      @if($website->exists)
        <section class="module-aside-card" aria-labelledby="websiteStatusHeading">
          <h3 id="websiteStatusHeading">@include('public-lgu.partials.icon', ['name' => 'globe']){{ $website->is_published ? 'Published' : 'Unpublished' }}</h3>
          <code class="lgu-set-url" id="publicAddress">{{ $publicUrl }}</code>
          <div class="lgu-set-links" style="margin-top:12px">
            <a class="module-button" href="{{ route('settings.lgu.website.preview', $municipality) }}" target="_blank" rel="noopener">@include('public-lgu.partials.icon', ['name' => 'eye', 'size' => 18])Preview saved website</a>
            @if($website->is_published)<a class="module-button" href="{{ $publicUrl }}" target="_blank" rel="noopener">@include('public-lgu.partials.icon', ['name' => 'external', 'size' => 18])Open public website</a>@endif
            @if($website->is_published)<a class="module-button" href="{{ $officeLoginUrl }}" target="_blank" rel="noopener">@include('public-lgu.partials.icon', ['name' => 'external', 'size' => 18])Open LGU office sign in</a>@endif
            <button class="module-button" type="button" id="copyAddress" hidden>@include('public-lgu.partials.icon', ['name' => 'copy', 'size' => 18])<span>Copy website address</span></button>
          </div>
          <p style="margin-top:10px">Preview shows saved content. Save your changes before opening it.</p>
        </section>
      @endif

      <section class="module-aside-card" aria-labelledby="websiteChecklistHeading">
        <h3 id="websiteChecklistHeading">@include('public-lgu.partials.icon', ['name' => 'check'])Website checklist</h3>
        <ul class="lgu-set-list">
          @foreach($checklist as [$label, $done, $note])
            <li data-done="{{ $done ? 'true' : 'false' }}">@include('public-lgu.partials.icon', ['name' => $done ? 'check' : 'circle'])<span>{{ $label }}<span class="lgu-sr-only">: {{ $done ? 'done' : 'not yet' }}</span><small>{{ $note }}</small></span></li>
          @endforeach
        </ul>
        <p style="margin-top:10px">Reflects saved settings.</p>
      </section>

      <section class="module-aside-card" aria-labelledby="websitePrivacyHeading">
        <h3 id="websitePrivacyHeading">@include('public-lgu.partials.icon', ['name' => 'eye'])What the public sees</h3>
        <p>Only the office profile and the content on this page. Farmer records, parcels, dashboards and account details are never shown.</p>
      </section>
    </aside>
  </form>
</div>
@endsection
@push('scripts')
@include('settings.lgu.partials.scripts')
<script>
(() => {
  const form = document.getElementById('lguWebsiteForm');
  if (!form) return;

  // Keep each collapsed entry's summary in step with its title.
  form.querySelectorAll('.lgu-set-entry').forEach(entry => {
    const source = entry.querySelector('[data-entry-source]');
    const title = entry.querySelector('[data-entry-title]');
    source?.addEventListener('input', () => {
      const text = source.value.trim();
      title.textContent = text || title.dataset.placeholder;
      title.toggleAttribute('data-empty', !text);
    });
  });

  // Show the full public address as it is typed.
  const slug = document.getElementById('slug');
  const url = document.getElementById('slug_url');
  slug?.addEventListener('input', () => { url.textContent = `${url.dataset.prefix}${slug.value.trim() || '[address]'}${url.dataset.suffix}`; });

  const summary = document.getElementById('publishSummary');
  form.querySelectorAll('input[name=is_published]').forEach(choice => choice.addEventListener('change', () => {
    if (summary) summary.textContent = choice.value === '1' ? 'Saving publishes these changes immediately.' : 'Saving keeps the website unpublished.';
  }));

  const copy = document.getElementById('copyAddress');
  const address = document.getElementById('publicAddress');
  if (copy && address && navigator.clipboard && window.isSecureContext) {
    copy.hidden = false;
    copy.addEventListener('click', async () => {
      const label = copy.querySelector('span');
      try {
        await navigator.clipboard.writeText(address.textContent.trim());
        label.textContent = 'Address copied';
      } catch (error) {
        label.textContent = 'Copy failed — select the address instead';
      }
      setTimeout(() => { label.textContent = 'Copy website address'; }, 2500);
    });
  }

  document.getElementById('websiteErrors')?.focus();
  form.addEventListener('submit', () => {
    const button = document.getElementById('saveLguWebsite');
    if (button) { button.disabled = true; button.textContent = 'Saving…'; }
  });
})();
</script>
@endpush
