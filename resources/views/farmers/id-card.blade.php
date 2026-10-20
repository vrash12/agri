@extends('layouts.app')

@section('title', 'Farmer Registry Card')

@section('content')
@include('partials.operations-ui-styles')
@php
  $fullName = trim(collect([
      $farmer->first_name,
      $farmer->middle_name,
      $farmer->last_name,
      $farmer->ext_name,
  ])->filter()->implode(' '));
  $initials = mb_strtoupper(
      mb_substr($farmer->first_name ?: 'F', 0, 1)
      .mb_substr($farmer->last_name ?: 'R', 0, 1)
  );
  $photoUrl = $farmer->profile_photo_path
      ? route('farmers.photo', $farmer)
      : null;
  $municipalityName = optional($farmer->municipality)->name
      ?: $farmer->farm_municipality
      ?: 'Municipality not recorded';
  $provinceName = optional($farmer->municipality)->province
      ?: $farmer->farm_province
      ?: 'Tarlac';
  if ($cardIssuer['configured'] && $cardIssuer['province']) {
      $provinceName = $cardIssuer['province'];
  }
  $sectorTags = collect([
      $farmer->is_arb ? 'ARB' : null,
      $farmer->is_4ps ? '4Ps' : null,
      $farmer->is_ip ? 'IP' : null,
      $farmer->is_pwd ? 'PWD' : null,
      $farmer->is_sc ? 'Senior Citizen' : null,
      $farmer->is_ofw ? 'OFW' : null,
  ])->filter()->values();
  $plotCount = $farmer->farmPlots->count();
  $cardFarmLocation = $cardFarmLocation ?? 'Parcel address not recorded';
  // 24x24 stroke icons. The same path data is drawn inline on the printed card and
  // through Path2D on the canvas export, so both renderings show identical icons.
  $cardIcons = [
      'phone' => 'M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM11 18h2',
      'pin' => 'M12 22s7-6.4 7-12a7 7 0 1 0-14 0c0 5.6 7 12 7 12zM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
      'area' => 'M3 3h18v18H3zM3 10h18M10 3v18',
      'sprout' => 'M12 21v-9M12 12c0-4.2 3-7 8-7 0 5-3 7.6-8 7zM12 14.5c0-3.4-2.4-6-7-6 0 4 2.8 6.3 7 6z',
      'people' => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21c0-4 3-7 7-7s7 3 7 7M16 3.6a4 4 0 0 1 0 7.3M22 21c0-3.1-1.9-5.6-4.6-6.5',
      'shield' => 'M12 2 4 5.2V11c0 5.1 3.4 9.4 8 11 4.6-1.6 8-5.9 8-11V5.2zM8.6 12.1l2.4 2.4 4.6-4.8',
  ];
  $cardIcon = fn (string $name): string => '<svg class="farmer-card-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="'.$cardIcons[$name].'"/></svg>';
@endphp

<div class="module-page farmer-card-page">
  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif

  <header class="module-header farmer-card-screen-only">
    <div>
      <div class="module-eyebrow">Farmer registry</div>
      <h1>Farmer digital ID</h1>
      <p>Present, print, or download {{ $fullName ?: 'this farmer' }}'s QR-enabled agriculture registry card.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('farmers.index') }}">Back to registry</a>
      @if(auth()->user()->canManageOperationalData())<a class="module-button" href="{{ route('farmers.edit', $farmer) }}">Edit profile</a>@endif
    </div>
  </header>

  <div class="farmer-card-toolbar farmer-card-screen-only" aria-label="Card actions">
    <div><span class="farmer-card-toolbar-label">Present this ID</span><button class="module-button module-button-primary" type="button" id="openFarmerDigitalId">Open digital ID <span aria-hidden="true">↗</span></button></div>
    <div><span class="farmer-card-toolbar-label">Print or save a copy</span><div class="module-actions"><button class="module-button" type="button" id="printFarmerCard">Print both sides</button><button class="module-button" type="button" id="downloadFarmerCardFront">Download front</button><button class="module-button" type="button" id="downloadFarmerCardBack">Download back</button></div></div>
  </div>
  <div class="module-alert farmer-card-screen-only" id="farmerCardLayoutNotice" role="status" hidden>Some card text exceeds its space. Review the complete details on this page and in the farmer profile before printing or downloading.</div>

  @unless($photoUrl)
    <div class="farmer-card-notice farmer-card-screen-only">
      <span>Photo needed</span>
      <p>
        This card currently uses the farmer's initials.
        @if(auth()->user()->canManageOperationalData())
          Upload a clear profile picture from <a href="{{ route('farmers.edit', $farmer) }}">Edit profile</a> before final printing.
        @else
          An authorized agriculture staff member can upload the profile picture.
        @endif
      </p>
    </div>
  @endunless

  <section class="farmer-card-workspace">
    @if($cardIssuer['configured'])
      <div class="farmer-card-workspace-head farmer-card-address-list"><div><strong>Issuing LGU · {{ $cardIssuer['location'] }}</strong><span>{{ $cardIssuer['office_name'] }}@if($cardIssuer['office_address']) · {{ $cardIssuer['office_address'] }}@endif</span><span>{{ collect([$cardIssuer['phone'], $cardIssuer['email'], $cardIssuer['website_url']])->filter()->implode(' · ') }}</span></div></div>
    @endif
    <div class="farmer-card-workspace-head farmer-card-address-list"><div><strong>Parcel addresses</strong><span>{{ $cardFarmLocation }}</span></div></div>
    <div class="farmer-card-workspace-head farmer-card-screen-only">
      <div><strong>Front &amp; back preview</strong><span>CR80 · 85.6 × 54 mm when printed at actual size</span></div>
      <span class="farmer-card-id-chip">{{ $farmer->agri_gov_id }}</span>
    </div>

    <div class="farmer-card-grid">
      <article>
        <div class="farmer-card-side-label farmer-card-screen-only"><strong>Front</strong><span>Identity and registry details</span></div>
        <div class="farmer-id-card farmer-id-card-front" id="farmerIdCardFront">
          <div @class(['farmer-card-header-brand', 'has-lgu-profile' => $cardIssuer['configured']])>
            <img class="farmer-card-eagrilgu-brand" src="{{ asset('images/branding/eagrilgu-wordmark-v4.png') }}" alt="eAgriLGU">
            <div>
              <strong data-card-fit="1.8" data-card-min="1.38">{{ mb_strtoupper($cardIssuer['office_name']) }}</strong>
              <b>FARMER REGISTRY CARD</b>
              <span data-card-fit="1.55" data-card-min="1.3">{{ mb_strtoupper($cardIssuer['configured'] ? $cardIssuer['location'].' · '.$provinceName : $provinceName) }}</span>
            </div>
            <img @class(['farmer-card-da-logo', 'farmer-card-lgu-logo' => $cardIssuer['configured']]) src="{{ $cardIssuer['logo_url'] }}" alt="{{ $cardIssuer['logo_alt'] }}">
          </div>

          <div class="farmer-card-photo">
            @if($photoUrl)
              <img src="{{ $photoUrl }}" alt="{{ $fullName }}">
            @else
              <span>{{ $initials }}</span>
            @endif
          </div>
          <div class="farmer-card-front-details">
            <div class="farmer-card-field farmer-card-field-name"><span>Full name</span><strong data-card-fit="3.36" data-card-min="2.37" data-card-lines="2">{{ mb_strtoupper($fullName ?: 'NAME NOT RECORDED') }}</strong></div>
            <div class="farmer-card-field farmer-card-field-id"><span>eAgriLGU ID <em>System-generated</em></span><strong class="farmer-card-code" data-card-fit="3.46" data-card-min="2">{{ $farmer->agri_gov_id }}</strong></div>
            <div class="farmer-card-two-fields">
              <div class="farmer-card-field"><span>RSBSA number</span><strong data-card-fit="2.5" data-card-min="1.38">{{ $farmer->rsbsa_no ?: 'Not recorded' }}</strong></div>
              <div class="farmer-card-field"><span>FFRS number</span><strong data-card-fit="2.5" data-card-min="1.38">{{ $farmer->ffrs ?: 'Not recorded' }}</strong></div>
            </div>
            <div class="farmer-card-field farmer-card-field-municipality"><span>Registry municipality</span><strong data-card-fit="2.5" data-card-min="1.38">{{ mb_strtoupper($municipalityName) }}</strong></div>
          </div>

          <div class="farmer-card-front-footer">
            <span class="farmer-card-service-label">LOCAL AGRICULTURE SERVICES</span>
            <b>{!! $cardIcon('shield') !!}REGISTERED FARMER</b>
            <strong>{{ $farmer->created_at ? $farmer->created_at->format('Y') : now()->format('Y') }}</strong>
          </div>
        </div>
      </article>

      <article>
        <div class="farmer-card-side-label farmer-card-screen-only"><strong>Back</strong><span>Farm details and scannable interactive land map</span></div>
        <div class="farmer-id-card farmer-id-card-back" id="farmerIdCardBack">
          <header @class(['farmer-card-issuer-header' => $cardIssuer['configured']])>
            @if($cardIssuer['configured'])<div class="farmer-card-issuer-info">{{ collect([$cardIssuer['office_name'], $cardIssuer['website_url']])->filter()->implode(' · ') }}</div>@endif
            <img class="farmer-card-eagrilgu-brand" src="{{ asset('images/branding/eagrilgu-wordmark-v4.png') }}" alt="eAgriLGU">
            @if($cardIssuer['configured'])<div class="farmer-card-issuer-info">{{ collect([$cardIssuer['office_address'], $cardIssuer['phone'], $cardIssuer['email']])->filter()->implode(' · ') }}</div>@endif
          </header>
          <div class="farmer-card-back-body">
            <div class="farmer-card-back-column">
              <section><span>{!! $cardIcon('phone') !!}Contact number</span><strong data-card-fit="2.55" data-card-min="1.38">{{ $farmer->contact_number ?: 'Not recorded' }}</strong></section>
              <section class="farmer-card-parcel-address"><span>{!! $cardIcon('pin') !!}Farm location · Parcel address</span><strong>{{ mb_strlen($cardFarmLocation) <= 120 ? $cardFarmLocation : 'Full parcel address list attached.' }}</strong></section>
              <section><span>{!! $cardIcon('area') !!}Declared farm area</span><strong data-card-fit="2.55" data-card-min="1.38">{{ $farmer->farm_area_ha !== null ? number_format((float)$farmer->farm_area_ha, 2).' hectares' : 'Not recorded' }}</strong></section>
              <section><span>{!! $cardIcon('sprout') !!}Ecosystem</span><strong data-card-fit="2.55" data-card-min="1.38">{{ $farmer->ecosystem ?: 'Not recorded' }}</strong></section>
            </div>
            <div class="farmer-card-back-column farmer-card-back-column-right">
              <section><span>{!! $cardIcon('people') !!}Sector classifications</span><div class="farmer-card-sector-list">@forelse($sectorTags as $tag)<b>{{ $tag }}</b>@empty<small>No classifications recorded</small>@endforelse</div></section>
              <div class="farmer-card-qr-card">
                <a href="{{ $scanUrl }}" target="_blank" rel="noopener" title="Open interactive land map">
                  <img src="{{ $qrDataUri }}" alt="QR code for {{ $fullName }}'s interactive land map">
                </a>
                <div><strong>SCAN LAND MAP</strong><small>{{ $plotCount }} mapped {{ Str::plural('parcel', $plotCount) }} · Interactive view</small></div>
              </div>
            </div>
          </div>
          {{-- Both signatures are written in ink above their lines after printing; no signature image is stored. --}}
          <footer @class(['has-signatory' => $cardSignatory])>
            <div class="farmer-card-signature farmer-card-holder-signature">
              @if($fullName)<strong>{{ mb_strtoupper($fullName) }}</strong>@endif
              <small>Cardholder's signature</small>
            </div>
            <div class="farmer-card-back-notes">
              <p>Local agriculture registry card — not a substitute for a Philippine national government ID.</p>
              <span>Issued {{ $farmer->created_at ? $farmer->created_at->format('M d, Y') : now()->format('M d, Y') }}</span>
            </div>
            @if($cardSignatory)
              <div class="farmer-card-signature farmer-card-signatory">
                <strong>{{ mb_strtoupper($cardSignatory['name']) }}</strong>
                <small>{{ $cardSignatory['title'] }}</small>
              </div>
            @endif
          </footer>
        </div>
      </article>
    </div>
  </section>

  <dialog class="farmer-digital-dialog farmer-card-screen-only" id="farmerDigitalIdDialog" aria-labelledby="farmerDigitalIdTitle">
    <div class="farmer-digital-shell">
      <header class="farmer-digital-header">
        <div>
          <span class="farmer-digital-kicker">eAgriLGU farmer registry card</span>
          <h2 id="farmerDigitalIdTitle">{{ $fullName ?: 'Farmer' }}'s digital ID</h2>
        </div>
        <button class="farmer-digital-icon-button" type="button" data-close-digital-id aria-label="Close digital ID">&times;</button>
      </header>

      <div class="farmer-digital-stage">
        <div class="farmer-digital-status">
          <span></span>
          <strong id="farmerDigitalSideStatus" aria-live="polite">Front of ID</strong>
        </div>
        <div class="farmer-digital-card-frame" id="farmerDigitalCardFrame">
          <div class="farmer-digital-loading" id="farmerDigitalLoading">Preparing digital card…</div>
          <img id="farmerDigitalCardImage" alt="Front of {{ $fullName }}'s farmer registry card">
        </div>
        <p class="farmer-digital-hint">Present this screen for local registry verification. Use the back of the card to scan the interactive land map.</p>
      </div>

      <div class="farmer-digital-side-switch" aria-label="Choose ID side">
        <button type="button" class="is-active" data-digital-side="front" aria-pressed="true">Front</button>
        <button type="button" data-digital-side="back" aria-pressed="false">Back &amp; QR</button>
      </div>

      <footer class="farmer-digital-actions">
        <button class="module-button" type="button" id="farmerDigitalFlip">Show back</button>
        <button class="module-button" type="button" id="farmerDigitalEnlargeQr">Enlarge QR</button>
        <button class="module-button" type="button" id="farmerDigitalDownload">Download front</button>
        <button class="module-button module-button-primary" type="button" data-close-digital-id>Done</button>
      </footer>
    </div>
  </dialog>

  <dialog class="farmer-qr-dialog farmer-card-screen-only" id="farmerQrDialog" aria-labelledby="farmerQrTitle">
    <div class="farmer-qr-shell">
      <header>
        <div>
          <span>Interactive parcel verification</span>
          <h2 id="farmerQrTitle">Scan land map</h2>
        </div>
        <button class="farmer-digital-icon-button" type="button" data-close-qr aria-label="Close enlarged QR code">&times;</button>
      </header>
      <div class="farmer-qr-image-wrap">
        <img src="{{ $qrDataUri }}" alt="Enlarged QR code for {{ $fullName }}'s interactive land map">
      </div>
      <strong>{{ $farmer->agri_gov_id }}</strong>
      <p>Scanning opens a read-only map with {{ $plotCount }} mapped {{ Str::plural('parcel', $plotCount) }}. Personal and assistance records remain private.</p>
      <div class="farmer-qr-actions">
        <button class="module-button" type="button" data-close-qr>Back to ID</button>
        <a class="module-button module-button-primary" href="{{ $scanUrl }}" target="_blank" rel="noopener">Open land map</a>
      </div>
    </div>
  </dialog>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/farmer-id-workspace.css') }}?v={{ filemtime(public_path('css/farmer-id-workspace.css')) }}">
<link rel="stylesheet" href="{{ asset('css/farmer-id-card.css') }}?v={{ filemtime(public_path('css/farmer-id-card.css')) }}">
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const cardData = {
      farmerId: @json($farmer->agri_gov_id),
      fullName: @json(mb_strtoupper($fullName ?: 'NAME NOT RECORDED')),
      rsbsa: @json($farmer->rsbsa_no ?: 'Not recorded'),
      ffrs: @json($farmer->ffrs ?: 'Not recorded'),
      municipality: @json(mb_strtoupper($municipalityName)),
      province: @json(mb_strtoupper($provinceName)),
      farmLocation: @json($cardFarmLocation),
      contact: @json($farmer->contact_number ?: 'Not recorded'),
      area: @json($farmer->farm_area_ha !== null ? number_format((float)$farmer->farm_area_ha, 2).' hectares' : 'Not recorded'),
      ecosystem: @json($farmer->ecosystem ?: 'Not recorded'),
      sectors: @json($sectorTags),
      issued: @json($farmer->created_at ? $farmer->created_at->format('M d, Y') : now()->format('M d, Y')),
      year: @json($farmer->created_at ? $farmer->created_at->format('Y') : now()->format('Y')),
      initials: @json($initials),
      photoUrl: @json($photoUrl),
      daLogo: @json($cardIssuer['logo_url']),
      issuer: @json($cardIssuer),
      background: @json(asset('images/branding/farmer-card-background.svg').'?v=20261020-field'),
      frontArt: @json(asset('images/branding/farmer-card-front-art.svg').'?v=20261020-field'),
      backArt: @json(asset('images/branding/farmer-card-back-art.svg').'?v=20261020-field'),
      eAgriLguWordmark: @json(asset('images/branding/eagrilgu-wordmark-v4.png')),
      icons: @json($cardIcons),
      signatory: @json($cardSignatory ? ['name' => mb_strtoupper($cardSignatory['name']), 'title' => $cardSignatory['title']] : null),
      holderName: @json($fullName ? mb_strtoupper($fullName) : null),
      scanUrl: @json($scanUrl),
      qrDataUri: @json($qrDataUri),
      plotCount: @json($plotCount),
    };

    function loadImage(url) {
      return new Promise(resolve => {
        if (!url) return resolve(null);
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = url;
      });
    }

    function roundRect(ctx, x, y, width, height, radius, fill, stroke) {
      ctx.beginPath();
      ctx.roundRect(x, y, width, height, radius);
      if (fill) { ctx.fillStyle = fill; ctx.fill(); }
      if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 2; ctx.stroke(); }
    }

    function coverImage(ctx, image, x, y, width, height) {
      const scale = Math.max(width / image.width, height / image.height);
      const sourceWidth = width / scale;
      const sourceHeight = height / scale;
      const sourceX = (image.width - sourceWidth) / 2;
      const sourceY = (image.height - sourceHeight) / 2;
      ctx.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, x, y, width, height);
    }

    function fittedText(ctx, text, x, y, maxWidth, startSize, weight, color, family, minimumSize) {
      let size = startSize;
      family = family || 'Arial';
      const minimum = Math.min(startSize, minimumSize || 14);
      while (size >= minimum) {
        ctx.font = `${weight || 700} ${size}px ${family}`;
        if (ctx.measureText(String(text)).width <= maxWidth) break;
        size -= 1;
      }
      if (size < minimum) throw new Error('Some card text exceeds its space. Review the complete details before printing or downloading.');
      ctx.fillStyle = color || '#20362c';
      ctx.fillText(String(text), x, y);
    }

    // The same outlined wordmark is used in Blade, print and canvas exports.
    function drawEAgriLgu(ctx, wordmark, x, y, width, height) {
      const scale = Math.min(width / wordmark.width, height / wordmark.height);
      const w = wordmark.width * scale, h = wordmark.height * scale;
      ctx.drawImage(wordmark, x + (width - w) / 2, y + (height - h) / 2, w, h);
    }

    function drawCardHeading(ctx) {
      ctx.save();
      ctx.textAlign = 'center';
      fittedText(ctx, cardData.issuer.office_name.toUpperCase(), 578.5, 67, 505, 18, 700, '#294a38');
      fittedText(ctx, 'FARMER REGISTRY CARD', 578.5, 105, 505, 30, 700, '#173e2d');
      fittedText(ctx, cardData.issuer.configured ? cardData.municipality+' · '+cardData.province : cardData.province, 578.5, 132, 505, 16, 700, '#52685b');
      ctx.restore();
    }

    function drawIcon(ctx, name, x, y, size, color, width) {
      if (!cardData.icons[name] || typeof Path2D === 'undefined') return;
      ctx.save();
      ctx.translate(x, y);
      ctx.scale(size / 24, size / 24);
      ctx.strokeStyle = color; ctx.lineWidth = width || 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
      ctx.stroke(new Path2D(cardData.icons[name]));
      ctx.restore();
    }

    function label(ctx, text, x, y, icon) {
      if (icon) { drawIcon(ctx, icon, x, y - 18, 22, '#2f7a52'); x += 30; }
      ctx.fillStyle = '#52685b';
      ctx.font = '700 16px Arial';
      ctx.fillText(text, x, y);
    }

    function field(ctx, text, value, x, y, maxWidth, valueSize, mono, icon) {
      label(ctx, text, x, y, icon);
      fittedText(ctx, value, x, y + 34, maxWidth, valueSize || 26, 700, '#20362c', mono ? 'monospace' : 'Arial');
    }

    function wrapText(ctx, text, maxWidth) {
      const lines = [''];
      for (const word of String(text).split(/\s+/)) {
        const current = lines[lines.length - 1];
        const next = current ? current + ' ' + word : word;
        if (!current || ctx.measureText(next).width <= maxWidth) lines[lines.length - 1] = next;
        else lines.push(word);
      }
      return lines;
    }

    // A line to sign above, with the printed name and caption centred beneath it.
    function signatureName(ctx, name, middle, width) {
      for (let size = 16; size >= 12; size--) {
        ctx.font = `700 ${size}px Arial`;
        const lines = wrapText(ctx, name, width);
        if (lines.length <= 2 && lines.every(line => ctx.measureText(line).width <= width) && lines.length * size * 1.12 <= 32) {
          ctx.fillStyle = '#20362c';
          lines.forEach((line, index) => ctx.fillText(line, middle, 593 + index * size * 1.12));
          return;
        }
      }
      throw new Error('The complete signature name exceeds the card space. Review the full name before printing or downloading.');
    }

    function signatureLine(ctx, left, right, name, caption) {
      const middle = (left + right) / 2;
      ctx.strokeStyle = '#284d36'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(left, 576); ctx.lineTo(right, 576); ctx.stroke();
      ctx.textAlign = 'center';
      if (name) signatureName(ctx, name, middle, right - left - 8);
      fittedText(ctx, caption, middle, name ? 623 : 598, right - left, 13, 700, '#52685b', 'Arial', 11);
      ctx.textAlign = 'left';
    }

    // Classification pills matching the printed card; false when they need more
    // than one row, so the caller can fall back to a single fitted line.
    function sectorChips(ctx, tags, x, y, maxWidth) {
      ctx.font = '700 16px Arial';
      const widths = tags.map(tag => ctx.measureText(tag).width + 24);
      if (!tags.length || widths.reduce((sum, width) => sum + width, 0) + (tags.length - 1) * 8 > maxWidth) return false;
      tags.forEach((tag, index) => {
        roundRect(ctx, x, y, widths[index], 29, 5, '#edf4ed', '#d1dfd4');
        ctx.fillStyle = '#294a38'; ctx.fillText(tag, x + 12, y + 20);
        x += widths[index] + 8;
      });
      return true;
    }

    // Wrap the complete name into two lines; never squeeze or ellipsize identity text.
    function identityText(ctx, text, x, y, width, height) {
      const value = String(text);
      for (let size = 34; size >= 24; size--) {
        ctx.font = `700 ${size}px Arial`;
        const lines = [''];
        for (const word of value.split(/\s+/)) {
          const last = lines.length - 1;
          const next = lines[last] ? lines[last] + ' ' + word : word;
          if (ctx.measureText(next).width <= width) { lines[last] = next; continue; }
          if (lines[last]) lines.push('');
          for (const character of word) {
            const index = lines.length - 1;
            if (lines[index] && ctx.measureText(lines[index] + character).width > width) lines.push(character);
            else lines[index] += character;
          }
        }
        if (lines.length <= 2 && lines.length * size * 1.12 <= height && lines.every(line => ctx.measureText(line).width <= width)) {
          ctx.fillStyle = '#20362c';
          lines.forEach((line, index) => ctx.fillText(line, x, y + size + index * size * 1.12));
          return;
        }
      }
      throw new Error('The complete farmer name exceeds the card space. Review the name in the farmer profile before printing or downloading.');
    }

    function addressText(ctx, text, x, y, width, height) {
      let lines = [], size = 25;
      for (; size >= 14; size--) {
        ctx.font = '700 ' + size + 'px Arial';
        lines = [''];
        for (const word of String(text).split(/\s+/)) {
          const index = lines.length - 1;
          const next = lines[index] ? lines[index] + ' ' + word : word;
          if (ctx.measureText(next).width <= width) {
            lines[index] = next;
            continue;
          }
          if (lines[index]) lines.push('');
          for (const character of word) {
            const last = lines.length - 1;
            if (lines[last] && ctx.measureText(lines[last] + character).width > width) lines.push(character);
            else lines[last] += character;
          }
        }
        if (lines.length * size * 1.25 <= height && lines.every(line => ctx.measureText(line).width <= width)) break;
      }
      if (size < 14) throw new Error('The complete parcel addresses exceed the card space. Refer to the full address list on this page.');
      ctx.fillStyle = '#132018';
      lines.forEach((line, index) => ctx.fillText(line, x, y + index * size * 1.25));
    }

    function issuerText(ctx, text, x, y, width, height) {
      if (!text) return;
      for (let size = 16; size >= 14; size--) {
        ctx.font = `700 ${size}px Arial`;
        const lines = [''];
        for (const character of text) {
          const last = lines.length - 1;
          if (ctx.measureText(lines[last] + character).width > width) {
            const boundary = lines[last].lastIndexOf(' ');
            if (boundary > 0) {
              const remainder = lines[last].slice(boundary + 1);
              lines[last] = lines[last].slice(0, boundary + 1);
              lines.push(remainder + character);
            } else lines.push(character);
          } else lines[last] += character;
        }
        if (lines.length * size * 1.25 <= height) {
          ctx.fillStyle = '#243e30';
          lines.forEach((line, index) => ctx.fillText(line, x, y + size + index * size * 1.25));
          return;
        }
      }
      throw new Error('The LGU office details exceed the card space. Shorten the details in Settings before downloading or printing.');
    }

    // The full issuer details remain on the printed sheet if card text is too long.
    function fitIssuerInfo() {
      document.querySelectorAll('.farmer-card-issuer-info').forEach(element => {
        if (!element.dataset.fullText) element.dataset.fullText = element.textContent;
        element.textContent = element.dataset.fullText;
        for (let size = 1.55; size >= 1.39; size -= .05) {
          element.style.fontSize = size + 'cqw';
          if (element.scrollHeight <= element.clientHeight + 1) return;
        }
        element.textContent = 'Full office details attached.';
        element.style.fontSize = '1.55cqw';
      });
    }
    const printedAddress = document.querySelector('.farmer-card-parcel-address>strong');
    function fitCardValues() {
      let overflow = false;
      document.querySelectorAll('.farmer-card-signature strong, .farmer-card-signatory small').forEach(element => {
        element.dataset.cardFit = element.tagName === 'STRONG' ? '1.6' : '1.3';
        element.dataset.cardMin = element.tagName === 'STRONG' ? '1.18' : '1.05';
        if (element.tagName === 'STRONG') element.dataset.cardLines = '2';
      });
      document.querySelectorAll('[data-card-fit]').forEach(element => {
        const start = Number(element.dataset.cardFit), minimum = Number(element.dataset.cardMin);
        for (let size = start; size >= minimum; size = Math.round((size - .05) * 100) / 100) {
          element.style.fontSize = size + 'cqw';
          if (element.scrollWidth <= element.clientWidth + 1 && (!element.dataset.cardLines || element.scrollHeight <= element.clientHeight + 1)) return;
        }
        overflow = true;
      });
      document.getElementById('farmerCardLayoutNotice').hidden = !overflow;
    }
    fitCardValues();
    window.addEventListener('beforeprint', fitCardValues);
    document.fonts?.ready.then(fitCardValues);
    if (typeof ResizeObserver !== 'undefined') {
      const cardResize = new ResizeObserver(fitCardValues);
      document.querySelectorAll('.farmer-id-card').forEach(card => cardResize.observe(card));
    }
    fitIssuerInfo();
    window.addEventListener('beforeprint', fitIssuerInfo);
    if (typeof ResizeObserver !== 'undefined') {
      const issuerHeader = document.querySelector('.farmer-card-issuer-header');
      if (issuerHeader) new ResizeObserver(fitIssuerInfo).observe(issuerHeader);
    }
    function fitPrintedAddress() {
      if (!printedAddress || !printedAddress.clientWidth) return;
      printedAddress.textContent = cardData.farmLocation;
      for (let size = 1.7; size >= 1.39; size -= .1) {
        printedAddress.style.fontSize = size + 'cqw';
        if (printedAddress.scrollHeight <= printedAddress.clientHeight + 1) return;
      }
      printedAddress.style.fontSize = '1.7cqw';
      printedAddress.textContent = 'Full parcel address list attached.';
    }
    fitPrintedAddress();
    if (typeof ResizeObserver !== 'undefined' && printedAddress) {
      new ResizeObserver(fitPrintedAddress).observe(printedAddress);
    }
    window.addEventListener('beforeprint', fitPrintedAddress);

    async function renderFront() {
      const [photo, daLogo, background, frontArt, eAgriLguWordmark] = await Promise.all([
        loadImage(cardData.photoUrl), loadImage(cardData.daLogo), loadImage(cardData.background),
        loadImage(cardData.frontArt), loadImage(cardData.eAgriLguWordmark)
      ]);
      if (!daLogo || !background || !frontArt || !eAgriLguWordmark) throw new Error('The card artwork could not load. Reload this page before downloading the ID.');
      if (cardData.photoUrl && !photo) throw new Error('The farmer photo could not load. Reload this page before downloading or printing the ID.');
      const canvas = document.createElement('canvas');
      canvas.width = 1011; canvas.height = 638;
      const ctx = canvas.getContext('2d');
      ctx.beginPath(); ctx.roundRect(0, 0, 1011, 638, 25); ctx.clip();
      ctx.drawImage(background, 0, 0, 1011, 638);
      // Shared identity panels, gold rule and footer artwork used by CSS/print.
      ctx.drawImage(frontArt, 0, 0, 1011, 638);
      drawEAgriLgu(ctx, eAgriLguWordmark, 52, 38, 245, 98);
      // Contain the complete LGU seal instead of cropping non-square artwork.
      const logoScale = Math.min(108 / daLogo.width, 108 / daLogo.height);
      ctx.drawImage(daLogo, 916 - daLogo.width * logoScale / 2, 86 - daLogo.height * logoScale / 2, daLogo.width * logoScale, daLogo.height * logoScale);
      drawCardHeading(ctx);
      roundRect(ctx, 52, 200, 245, 312, 12, '#ffffff', '#b9cdbf');
      ctx.save(); ctx.beginPath(); ctx.roundRect(59, 207, 231, 298, 7); ctx.clip();
      if (photo) coverImage(ctx, photo, 59, 207, 231, 298);
      else { ctx.fillStyle = '#236344'; ctx.fillRect(59,207,231,298); ctx.fillStyle='#fff'; ctx.textAlign='center'; ctx.font='700 82px Arial'; ctx.fillText(cardData.initials,174,381); ctx.textAlign='left'; }
      ctx.restore();
      label(ctx, 'FULL NAME', 340, 216);
      identityText(ctx, cardData.fullName, 340, 221, 610, 77);
      label(ctx, 'eAgriLGU ID · SYSTEM-GENERATED', 340, 317);
      ctx.font = '700 35px monospace';
      const codeWidth = Math.min(610, ctx.measureText(cardData.farmerId).width + 20);
      roundRect(ctx, 340, 327, codeWidth, 48, 7, '#eaf3ed', '#c5d9cb');
      fittedText(ctx, cardData.farmerId, 350, 363, codeWidth - 20, 35, 700, '#173e2d', 'monospace');
      field(ctx, 'RSBSA NUMBER', cardData.rsbsa, 340, 411, 285, 25);
      field(ctx, 'FFRS NUMBER', cardData.ffrs, 650, 411, 300, 25);
      field(ctx, 'REGISTRY MUNICIPALITY', cardData.municipality, 340, 491, 610, 25);
      ctx.fillStyle = '#fff'; ctx.font = '700 18px Arial';
      ctx.fillText('LOCAL AGRICULTURE SERVICES', 48, 602);
      ctx.textAlign='right'; ctx.fillStyle='#fff'; ctx.font='700 18px Arial'; ctx.fillText('REGISTERED FARMER',860,602);
      drawIcon(ctx, 'shield', 860 - ctx.measureText('REGISTERED FARMER').width - 32, 582, 24, '#eac64d', 2.2);
      ctx.strokeStyle='rgba(255,255,255,.35)'; ctx.lineWidth=1; ctx.beginPath(); ctx.moveTo(888,581); ctx.lineTo(888,610); ctx.stroke();
      ctx.font='700 22px Arial'; ctx.fillText(cardData.year,963,602); ctx.textAlign='left';
      return canvas;
    }

    async function renderBack() {
      const [eAgriLguWordmark, qrImage, background, backArt] = await Promise.all([
        loadImage(cardData.eAgriLguWordmark), loadImage(cardData.qrDataUri), loadImage(cardData.background), loadImage(cardData.backArt)
      ]);
      if (!eAgriLguWordmark || !qrImage || !background || !backArt) throw new Error('The card artwork or QR code could not load. Reload this page before downloading the ID.');
      const canvas = document.createElement('canvas');
      canvas.width = 1011; canvas.height = 638;
      const ctx = canvas.getContext('2d');
      ctx.beginPath(); ctx.roundRect(0, 0, 1011, 638, 25); ctx.clip();
      ctx.drawImage(background, 0, 0, 1011, 638);
      // The light header keeps configured LGU details readable beside the wordmark.
      ctx.drawImage(backArt, 0, 0, 1011, 638);
      drawEAgriLgu(ctx, eAgriLguWordmark, 366, 8, 279, 98);
      if (cardData.issuer.configured) {
        issuerText(ctx, [cardData.issuer.office_name, cardData.issuer.website_url].filter(Boolean).join(' · '), 52, 25, 290, 76);
        issuerText(ctx, [cardData.issuer.office_address, cardData.issuer.phone, cardData.issuer.email].filter(Boolean).join(' · '), 682, 25, 277, 76);
      }
      field(ctx,'CONTACT NUMBER',cardData.contact,52,162,420,28,false,'phone');
      label(ctx,'FARM LOCATION · PARCEL ADDRESS',52,237,'pin');
      addressText(ctx,cardData.farmLocation,52,270,440,100);
      field(ctx,'DECLARED FARM AREA',cardData.area,52,387,420,28,false,'area');
      field(ctx,'ECOSYSTEM',cardData.ecosystem,52,462,420,28,false,'sprout');
      label(ctx,'SECTOR CLASSIFICATIONS',535,162,'people');
      if (!sectorChips(ctx,cardData.sectors,535,174,415)) {
        fittedText(ctx,cardData.sectors.length ? cardData.sectors.join(' · ') : 'None recorded',535,197,415,25,800,'#132018');
      }
      roundRect(ctx, 615, 218, 260, 297, 14, '#ffffff', '#c4d6c7');
      ctx.save(); ctx.imageSmoothingEnabled=false; ctx.drawImage(qrImage, 638, 234, 214, 214); ctx.restore();
      // Scan-frame corners, kept 12px clear of the code's own quiet zone.
      ctx.save(); ctx.strokeStyle='#1f7a46'; ctx.lineWidth=4; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.beginPath();
      [[626,222,1,1],[864,222,-1,1],[626,460,1,-1],[864,460,-1,-1]].forEach(([x,y,dx,dy]) => {
        ctx.moveTo(x, y + dy * 24); ctx.lineTo(x, y); ctx.lineTo(x + dx * 24, y);
      });
      ctx.stroke(); ctx.restore();
      ctx.fillStyle='#173e2d'; ctx.font='700 17px Arial'; ctx.textAlign='center'; ctx.fillText('SCAN LAND MAP',745,482);
      ctx.fillStyle='#53685c'; ctx.font='700 14px Arial'; ctx.fillText(cardData.plotCount+' mapped parcel'+(cardData.plotCount === 1 ? '' : 's')+' · Interactive view',745,504); ctx.textAlign='left';
      // Ink signatures go above these lines after printing: cardholder on the left,
      // the office signatory on the right when one is configured, notes between.
      signatureLine(ctx, 42, 320, cardData.holderName, "Cardholder's signature");
      if (cardData.signatory) signatureLine(ctx, 705, 969, cardData.signatory.name, cardData.signatory.title);
      const notesLeft = 350, notesRight = cardData.signatory ? 675 : 969, notesMid = (notesLeft + notesRight) / 2;
      ctx.font = '13px Arial';
      const notice = wrapText(ctx, 'Local agriculture registry card — not a substitute for a Philippine national government ID.', notesRight - notesLeft);
      const firstLine = 597 - (notice.length - 1) * 17;
      ctx.textAlign='center'; ctx.fillStyle='#68756d';
      notice.forEach((line, index) => ctx.fillText(line, notesMid, firstLine + index * 17));
      ctx.fillStyle='#284d36'; ctx.font='700 14px Arial'; ctx.fillText('Issued '+cardData.issued, notesMid, 617);
      ctx.textAlign='left';
      return canvas;
    }

    function downloadCanvas(canvas, side) {
      canvas.toBlob(blob => {
        if (!blob) return;
        const link = document.createElement('a');
        const objectUrl = URL.createObjectURL(blob);
        link.href = objectUrl;
        link.download = cardData.farmerId + '_' + side + '.png';
        document.body.appendChild(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1500);
      }, 'image/png', 1);
    }

    const digitalDialog = document.getElementById('farmerDigitalIdDialog');
    const qrDialog = document.getElementById('farmerQrDialog');
    const digitalImage = document.getElementById('farmerDigitalCardImage');
    const digitalFrame = document.getElementById('farmerDigitalCardFrame');
    const digitalLoading = document.getElementById('farmerDigitalLoading');
    const digitalStatus = document.getElementById('farmerDigitalSideStatus');
    const digitalFlipButton = document.getElementById('farmerDigitalFlip');
    const digitalDownloadButton = document.getElementById('farmerDigitalDownload');
    const digitalPreviewCache = {};
    let digitalSide = 'front';
    let digitalRequest = 0;

    async function digitalPreview(side) {
      if (!digitalPreviewCache[side]) {
        digitalPreviewCache[side] = (side === 'back' ? renderBack() : renderFront())
          .then(canvas => canvas.toDataURL('image/png', 1))
          .catch(error => { delete digitalPreviewCache[side]; throw error; });
      }
      return digitalPreviewCache[side];
    }

    function updateDigitalControls(side) {
      document.querySelectorAll('[data-digital-side]').forEach(button => {
        const isActive = button.dataset.digitalSide === side;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      digitalStatus.textContent = side === 'front' ? 'Front of ID' : 'Back of ID · QR ready';
      digitalFlipButton.textContent = side === 'front' ? 'Show back' : 'Show front';
      digitalDownloadButton.textContent = side === 'front' ? 'Download front' : 'Download back';
      digitalImage.alt = `${side === 'front' ? 'Front' : 'Back'} of ${cardData.fullName}'s farmer registry card`;
    }

    async function showDigitalSide(side) {
      const request = ++digitalRequest;
      digitalSide = side === 'back' ? 'back' : 'front';
      updateDigitalControls(digitalSide);
      digitalFrame.classList.add('is-changing');
      digitalImage.classList.remove('is-ready');
      digitalLoading.hidden = false;
      digitalLoading.textContent = 'Preparing digital card…';
      digitalFrame.setAttribute('aria-busy', 'true');

      try {
        const preview = await digitalPreview(digitalSide);
        if (request !== digitalRequest) return;
        digitalImage.src = preview;
        digitalLoading.hidden = true;
        digitalFrame.setAttribute('aria-busy', 'false');
        requestAnimationFrame(() => {
          if (request !== digitalRequest) return;
          digitalFrame.classList.remove('is-changing');
          digitalImage.classList.add('is-ready');
        });
      } catch (error) {
        if (request !== digitalRequest) return;
        digitalLoading.hidden = false;
        digitalLoading.textContent = error.message || 'The digital card could not be prepared.';
        digitalFrame.classList.remove('is-changing');
        digitalFrame.setAttribute('aria-busy', 'false');
        console.error('Digital farmer ID preview failed.', error);
      }
    }

    function openDigitalId(side) {
      if (!digitalDialog || typeof digitalDialog.showModal !== 'function') return;
      if (!digitalDialog.open) digitalDialog.showModal();
      showDigitalSide(side || 'front');
    }

    document.getElementById('openFarmerDigitalId')?.addEventListener('click', () => openDigitalId('front'));
    document.querySelectorAll('[data-close-digital-id]').forEach(button => {
      button.addEventListener('click', () => digitalDialog.close());
    });
    document.querySelectorAll('[data-digital-side]').forEach(button => {
      button.addEventListener('click', () => showDigitalSide(button.dataset.digitalSide));
    });
    digitalFlipButton?.addEventListener('click', () => showDigitalSide(digitalSide === 'front' ? 'back' : 'front'));
    digitalDownloadButton?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const originalLabel = button.textContent;
      button.disabled = true;
      button.textContent = 'Preparing…';
      try {
        downloadCanvas(digitalSide === 'back' ? await renderBack() : await renderFront(), digitalSide);
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
        button.textContent = originalLabel;
      }
    });
    document.getElementById('farmerDigitalEnlargeQr')?.addEventListener('click', () => {
      if (!qrDialog.open) qrDialog.showModal();
    });
    document.querySelectorAll('[data-close-qr]').forEach(button => {
      button.addEventListener('click', () => qrDialog.close());
    });
    [digitalDialog, qrDialog].forEach(dialog => {
      dialog?.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
      });
    });

    document.getElementById('printFarmerCard')?.addEventListener('click', async event => {
      const button = event.currentTarget; button.disabled = true;
      try { await Promise.all([renderFront(), renderBack()]); window.print(); }
      catch (error) { alert(error.message); }
      finally { button.disabled = false; }
    });
    document.getElementById('downloadFarmerCardFront')?.addEventListener('click', async event => {
      const button = event.currentTarget; button.disabled = true; button.textContent = 'Preparing…';
      try { downloadCanvas(await renderFront(), 'front'); } catch (error) { alert(error.message); } finally { button.disabled = false; button.textContent = 'Download front'; }
    });
    document.getElementById('downloadFarmerCardBack')?.addEventListener('click', async event => {
      const button = event.currentTarget; button.disabled = true; button.textContent = 'Preparing…';
      try { downloadCanvas(await renderBack(), 'back'); } catch (error) { alert(error.message); } finally { button.disabled = false; button.textContent = 'Download back'; }
    });
    window.__renderFarmerIdCard = side => side === 'back' ? renderBack() : renderFront();

    const requestedSide = new URLSearchParams(window.location.search).get('side');
    requestAnimationFrame(() => openDigitalId(requestedSide === 'back' ? 'back' : 'front'));
  });
</script>
@endpush
