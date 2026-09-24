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
  $initials = strtoupper(
      substr($farmer->first_name ?: 'F', 0, 1)
      .substr($farmer->last_name ?: 'R', 0, 1)
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
      @if(auth()->user()->canManageOperationalData())<a class="module-button" href="{{ route('farmers.edit', $farmer) }}">Edit profile</a>@endif
      <a class="module-button" href="{{ route('farmers.index') }}">Back to registry</a>
      <button class="module-button module-button-primary" type="button" id="openFarmerDigitalId">Open digital ID</button>
      <button class="module-button" type="button" id="printFarmerCard">Print cards</button>
      <button class="module-button" type="button" id="downloadFarmerCardFront">Download front</button>
      <button class="module-button" type="button" id="downloadFarmerCardBack">Download back</button>
    </div>
  </header>

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
    <div class="farmer-card-workspace-head farmer-card-address-list"><div><strong>Parcel addresses</strong><span>{{ $cardFarmLocation }}</span></div></div>
    <div class="farmer-card-workspace-head farmer-card-screen-only">
      <div><strong>Print-ready preview</strong><span>Standard CR80 card ratio · front and back</span></div>
      <span class="farmer-card-id-chip">{{ $farmer->agri_gov_id }}</span>
    </div>

    <div class="farmer-card-grid">
      <article>
        <div class="farmer-card-side-label farmer-card-screen-only"><strong>Front</strong><span>Identity and registry details</span></div>
        <div class="farmer-id-card farmer-id-card-front" id="farmerIdCardFront">
          <div class="farmer-card-header-brand">
            <img class="farmer-card-republic-logo" src="{{ asset('images/branding/philippines-coat-of-arms.png') }}" alt="Coat of arms of the Republic of the Philippines">
            <div>
              <small>REPUBLIKA NG PILIPINAS</small>
              <span class="farmer-card-republic-translation">Republic of the Philippines</span>
              <strong>PROVINCIAL AGRICULTURE OFFICE</strong>
              <b>FARMER REGISTRY CARD</b>
              <span>{{ strtoupper($provinceName) }}</span>
            </div>
            <img class="farmer-card-da-logo" src="{{ asset('images/da.jpg') }}" alt="Department of Agriculture logo">
          </div>

          <div class="farmer-card-photo">
            @if($photoUrl)
              <img src="{{ $photoUrl }}" alt="{{ $fullName }}">
            @else
              <span>{{ $initials }}</span>
            @endif
          </div>

          <div class="farmer-card-front-details">
            <div class="farmer-card-field farmer-card-field-name"><span>Full name</span><strong>{{ strtoupper($fullName ?: 'NAME NOT RECORDED') }}</strong></div>
            <div class="farmer-card-field"><span>AgriGOV ID <em>System-generated</em></span><strong class="farmer-card-code">{{ $farmer->agri_gov_id }}</strong></div>
            <div class="farmer-card-two-fields">
              <div class="farmer-card-field"><span>RSBSA number</span><strong>{{ $farmer->rsbsa_no ?: 'Not recorded' }}</strong></div>
              <div class="farmer-card-field"><span>FFRS number</span><strong>{{ $farmer->ffrs ?: 'Not recorded' }}</strong></div>
            </div>
            <div class="farmer-card-field"><span>Registry municipality</span><strong>{{ strtoupper($municipalityName) }}</strong></div>
          </div>

          <div class="farmer-card-front-footer">
            <span>AgriGOV <i>AGRICULTURE INFORMATION SYSTEM</i></span>
            <b>REGISTERED FARMER</b>
            <strong>{{ $farmer->created_at ? $farmer->created_at->format('Y') : now()->format('Y') }}</strong>
          </div>
        </div>
      </article>

      <article>
        <div class="farmer-card-side-label farmer-card-screen-only"><strong>Back</strong><span>Farm details and scannable interactive land map</span></div>
        <div class="farmer-id-card farmer-id-card-back" id="farmerIdCardBack">
          <header>
            <img src="{{ asset('images/mao-logo.jpg') }}" alt="Agriculture office logo">
            <div><small>AgriGOV · Agriculture Information System</small><strong>{{ $farmer->agri_gov_id }}</strong></div>
          </header>
          <div class="farmer-card-back-body">
            <div class="farmer-card-back-column">
              <section><span>Contact number</span><strong>{{ $farmer->contact_number ?: 'Not recorded' }}</strong></section>
              <section class="farmer-card-parcel-address"><span>Farm location · Parcel address</span><strong>{{ mb_strlen($cardFarmLocation) <= 120 ? $cardFarmLocation : 'Full parcel address list attached.' }}</strong></section>
              <section><span>Declared farm area</span><strong>{{ $farmer->farm_area_ha !== null ? number_format((float)$farmer->farm_area_ha, 2).' hectares' : 'Not recorded' }}</strong></section>
              <section><span>Ecosystem</span><strong>{{ $farmer->ecosystem ?: 'Not recorded' }}</strong></section>
            </div>
            <div class="farmer-card-back-column farmer-card-back-column-right">
              <section><span>Sector classifications</span><div class="farmer-card-sector-list">@forelse($sectorTags as $tag)<b>{{ $tag }}</b>@empty<small>No classifications recorded</small>@endforelse</div></section>
              <div class="farmer-card-qr-card">
                <a href="{{ $scanUrl }}" target="_blank" rel="noopener" title="Open interactive land map">
                  <img src="{{ $qrDataUri }}" alt="QR code for {{ $fullName }}'s interactive land map">
                </a>
                <div><strong>SCAN LAND MAP</strong><small>{{ $plotCount }} mapped {{ Str::plural('parcel', $plotCount) }} · Interactive view</small></div>
              </div>
            </div>
          </div>
          <footer>
            <p>This card identifies a record in the local agriculture information system. It is not a substitute for a Philippine national government ID.</p>
            <span>Issued {{ $farmer->created_at ? $farmer->created_at->format('M d, Y') : now()->format('M d, Y') }}</span>
          </footer>
        </div>
      </article>
    </div>
  </section>
  <p class="farmer-card-art-credit farmer-card-screen-only">Coat of arms: Galo Ocampo; vector by Zachary Harden, <a href="https://commons.wikimedia.org/wiki/File:Coat_of_arms_of_the_Philippines.svg" target="_blank" rel="noopener">Wikimedia Commons</a>, <a href="https://creativecommons.org/licenses/by-sa/2.5/" target="_blank" rel="noopener">CC BY-SA 2.5</a>. Raster reproduction; colors unchanged.</p>

  <dialog class="farmer-digital-dialog farmer-card-screen-only" id="farmerDigitalIdDialog" aria-labelledby="farmerDigitalIdTitle">
    <div class="farmer-digital-shell">
      <header class="farmer-digital-header">
        <div>
          <span class="farmer-digital-kicker">Agriculture registry card</span>
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
<style>
  .farmer-card-notice{display:flex;align-items:center;gap:11px;padding:11px 13px;border:1px solid #ead39d;border-radius:9px;background:#fffaf0}.farmer-card-notice>span{padding:5px 8px;border-radius:999px;color:#8a5b08;background:#f9e9bd;font-size:9px;font-weight:900;text-transform:uppercase}.farmer-card-notice p{margin:0;color:#6e624b;font-size:10px}.farmer-card-notice a{color:var(--module-green);font-weight:800}
  .farmer-card-workspace{overflow:hidden;border:1px solid var(--module-border);border-radius:12px;background:#eef3ef}.farmer-card-workspace-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-bottom:1px solid var(--module-border);background:#fff}.farmer-card-workspace-head strong,.farmer-card-workspace-head span{display:block}.farmer-card-workspace-head strong{font-size:12px}.farmer-card-workspace-head div>span{margin-top:3px;color:var(--module-muted);font-size:9px}.farmer-card-id-chip{padding:6px 9px;border-radius:7px;color:var(--module-green);background:var(--module-green-soft);font:800 9px ui-monospace,monospace}
  .farmer-card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;padding:24px}.farmer-card-grid>article{min-width:0}.farmer-card-side-label{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px}.farmer-card-side-label strong{font-size:11px}.farmer-card-side-label span{color:var(--module-muted);font-size:9px}
  .farmer-digital-dialog,.farmer-qr-dialog{width:min(880px,calc(100vw - 28px));max-width:none;max-height:calc(100dvh - 28px);padding:0;overflow:hidden;border:0;border-radius:22px;background:transparent;box-shadow:0 32px 90px rgba(8,29,17,.28)}.farmer-digital-dialog::backdrop,.farmer-qr-dialog::backdrop{background:rgba(9,24,15,.72);backdrop-filter:blur(6px)}
  .farmer-digital-shell{display:grid;max-height:calc(100dvh - 28px);overflow:auto;background:#f8fbf8}.farmer-digital-header{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 20px;border-bottom:1px solid #d9e4dc;background:#fff}.farmer-digital-header h2,.farmer-qr-shell h2{margin:3px 0 0;color:#102219;font-size:22px;line-height:1.1}.farmer-digital-kicker,.farmer-qr-shell header span{color:#14743f;font-size:10px;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.farmer-digital-icon-button{display:grid;flex:0 0 38px;width:38px;height:38px;place-items:center;border:1px solid #d5dfd8;border-radius:50%;color:#425248;background:#fff;font-size:24px;line-height:1;cursor:pointer}.farmer-digital-icon-button:hover{color:#0b6736;background:#edf7f0}.farmer-digital-icon-button:focus-visible{outline:3px solid rgba(22,131,75,.24);outline-offset:2px}
  .farmer-digital-stage{display:grid;justify-items:center;padding:18px 20px 12px;background:radial-gradient(circle at 50% 0,#eff8f1 0,#e2eee6 48%,#dbe8df 100%)}.farmer-digital-status{display:flex;align-items:center;gap:7px;margin-bottom:10px;padding:6px 10px;border:1px solid rgba(23,119,65,.16);border-radius:999px;color:#135f35;background:rgba(255,255,255,.82);font-size:10px}.farmer-digital-status span{width:7px;height:7px;border-radius:50%;background:#24b865;box-shadow:0 0 0 4px rgba(36,184,101,.12)}.farmer-digital-card-frame{position:relative;width:min(650px,100%);aspect-ratio:1.585;display:grid;place-items:center;overflow:hidden;border-radius:22px;background:#fff;box-shadow:0 24px 62px rgba(15,54,31,.2)}.farmer-digital-card-frame img{display:block;width:100%;height:100%;object-fit:contain;opacity:0;transform:scale(.985);transition:opacity .22s ease,transform .22s ease}.farmer-digital-card-frame img.is-ready{opacity:1;transform:scale(1)}.farmer-digital-card-frame.is-changing img{opacity:.15;transform:scale(.975)}.farmer-digital-loading{position:absolute;max-width:90%;text-align:center;color:#65746a;font-size:12px;font-weight:800}.farmer-digital-hint{max-width:660px;margin:10px 0 0;color:#607067;font-size:11px;line-height:1.45;text-align:center}.farmer-digital-side-switch{display:flex;justify-content:center;gap:4px;padding:10px 20px 3px;background:#f8fbf8}.farmer-digital-side-switch button{min-width:120px;padding:9px 14px;border:0;border-radius:999px;color:#637168;background:transparent;font-size:11px;font-weight:850;cursor:pointer}.farmer-digital-side-switch button.is-active{color:#fff;background:#146f3c;box-shadow:0 6px 16px rgba(20,111,60,.2)}.farmer-digital-side-switch button:focus-visible{outline:3px solid rgba(22,131,75,.22);outline-offset:2px}.farmer-digital-actions{display:flex;align-items:center;justify-content:center;gap:8px;padding:9px 20px 14px;background:#f8fbf8}
  .farmer-qr-dialog{width:min(440px,calc(100vw - 28px))}.farmer-qr-shell{padding:20px;background:#fff;text-align:center}.farmer-qr-shell>header{display:flex;align-items:center;justify-content:space-between;gap:14px;text-align:left}.farmer-qr-image-wrap{width:min(330px,100%);margin:20px auto 12px;padding:16px;border:1px solid #d8e3db;border-radius:20px;background:#fff;box-shadow:0 16px 40px rgba(16,62,34,.1)}.farmer-qr-image-wrap img{display:block;width:100%;aspect-ratio:1;object-fit:contain}.farmer-qr-shell>strong{display:block;color:#155f36;font:900 14px ui-monospace,monospace;letter-spacing:.04em}.farmer-qr-shell>p{margin:8px auto 18px;color:#607067;font-size:11px;line-height:1.55}.farmer-qr-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.farmer-qr-actions .module-button{justify-content:center;text-align:center}
  @media(max-width:1180px){.farmer-card-grid{grid-template-columns:1fr}.farmer-card-grid>article{width:min(856px,100%);margin:auto}}
  @media(max-width:560px){.farmer-card-grid{padding:10px;gap:16px}.farmer-card-workspace-head{align-items:flex-start;flex-direction:column}.farmer-card-notice{align-items:flex-start;flex-direction:column}.farmer-digital-dialog{width:calc(100vw - 12px);max-height:calc(100dvh - 12px);border-radius:18px}.farmer-digital-shell{max-height:calc(100dvh - 12px)}.farmer-digital-header{padding:14px 15px}.farmer-digital-header h2{font-size:17px}.farmer-digital-stage{padding:16px 10px 12px}.farmer-digital-card-frame{border-radius:14px}.farmer-digital-hint{font-size:10px}.farmer-digital-side-switch{padding:11px 10px 3px}.farmer-digital-side-switch button{min-width:105px;padding:8px 12px}.farmer-digital-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:10px 12px 14px}.farmer-digital-actions .module-button{justify-content:center;padding-inline:8px}.farmer-qr-shell{padding:16px}.farmer-qr-image-wrap{margin-top:16px}}
  @media print{
    @page{size:A4 portrait;margin:12mm}.sidebar,.topbar,.farmer-card-screen-only,.farmer-digital-dialog,.farmer-qr-dialog{display:none!important}.main,.content{margin:0!important;padding:0!important;width:100%!important}.farmer-card-page,.farmer-card-workspace,.farmer-card-grid{display:block!important;border:0!important;background:#fff!important;padding:0!important}.farmer-card-grid>article{width:85.6mm!important;margin:0 auto 12mm!important;break-inside:avoid;page-break-inside:avoid}.farmer-id-card{width:85.6mm!important;height:54mm!important;border-radius:2.5mm!important;box-shadow:none!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  }
</style>
<link rel="stylesheet" href="{{ asset('css/farmer-id-card.css') }}?v={{ @filemtime(public_path('css/farmer-id-card.css')) ?: 1 }}">
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const cardData = {
      farmerId: @json($farmer->agri_gov_id),
      fullName: @json(strtoupper($fullName ?: 'NAME NOT RECORDED')),
      rsbsa: @json($farmer->rsbsa_no ?: 'Not recorded'),
      ffrs: @json($farmer->ffrs ?: 'Not recorded'),
      municipality: @json(strtoupper($municipalityName)),
      province: @json(strtoupper($provinceName)),
      farmLocation: @json($cardFarmLocation),
      contact: @json($farmer->contact_number ?: 'Not recorded'),
      area: @json($farmer->farm_area_ha !== null ? number_format((float)$farmer->farm_area_ha, 2).' hectares' : 'Not recorded'),
      ecosystem: @json($farmer->ecosystem ?: 'Not recorded'),
      sectors: @json($sectorTags),
      issued: @json($farmer->created_at ? $farmer->created_at->format('M d, Y') : now()->format('M d, Y')),
      year: @json($farmer->created_at ? $farmer->created_at->format('Y') : now()->format('Y')),
      initials: @json($initials),
      photoUrl: @json($photoUrl),
      daLogo: @json(asset('images/da.jpg')),
      republicLogo: @json(asset('images/branding/philippines-coat-of-arms.png')),
      background: @json(asset('images/branding/farmer-card-background.svg')),
      officeLogo: @json(asset('images/mao-logo.jpg')),
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

    function fittedText(ctx, text, x, y, maxWidth, startSize, weight, color, family) {
      let size = startSize;
      family = family || 'Arial';
      do { ctx.font = `${weight || 700} ${size}px ${family}`; size -= 1; }
      while (ctx.measureText(String(text)).width > maxWidth && size > 18);
      ctx.fillStyle = color || '#132018';
      ctx.fillText(String(text), x, y, maxWidth);
    }

    function field(ctx, label, value, x, y, maxWidth, valueSize, mono) {
      ctx.fillStyle = '#607067';
      ctx.font = '700 20px Arial';
      ctx.fillText(label, x, y);
      fittedText(ctx, value, x, y + 35, maxWidth, valueSize || 28, 800, '#132018', mono ? 'monospace' : 'Arial');
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

    const printedAddress = document.querySelector('.farmer-card-parcel-address>strong');
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
      const [photo, daLogo, republicLogo, background] = await Promise.all([
        loadImage(cardData.photoUrl), loadImage(cardData.daLogo), loadImage(cardData.republicLogo), loadImage(cardData.background)
      ]);
      if (!daLogo || !republicLogo || !background) throw new Error('The card artwork could not load. Reload this page before downloading the ID.');
      const canvas = document.createElement('canvas');
      canvas.width = 1011; canvas.height = 638;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(background, 0, 0, 1011, 638);
      ctx.drawImage(republicLogo, 52, 30, 98, 109);
      ctx.save(); ctx.beginPath(); ctx.arc(916, 86, 54, 0, Math.PI * 2); ctx.clip(); ctx.drawImage(daLogo, 862, 32, 108, 108); ctx.restore();
      ctx.textAlign = 'center'; ctx.fillStyle = '#173e2d'; ctx.font = '700 22px Arial'; ctx.fillText('REPUBLIKA NG PILIPINAS', 505, 43);
      ctx.fillStyle = '#53685c'; ctx.font = '15px Arial'; ctx.fillText('Republic of the Philippines', 505, 64);
      ctx.fillStyle = '#243e30'; ctx.font = '700 22px Arial'; ctx.fillText('PROVINCIAL AGRICULTURE OFFICE', 505, 94);
      ctx.fillStyle = '#075b2e'; ctx.font = '900 34px Arial'; ctx.fillText('FARMER REGISTRY CARD', 505, 131);
      fittedText(ctx, cardData.province, 505, 155, 650, 17, 700, '#53685c'); ctx.textAlign = 'left';
      ctx.strokeStyle='rgba(35,99,68,.2)'; ctx.lineWidth=1; ctx.beginPath(); ctx.moveTo(48,172); ctx.lineTo(963,172); ctx.stroke();
      roundRect(ctx, 52, 190, 250, 305, 18, '#245e3a', '#173f28');
      ctx.save(); ctx.beginPath(); ctx.roundRect(60, 198, 234, 289, 12); ctx.clip();
      if (photo) coverImage(ctx, photo, 60, 198, 234, 289);
      else { ctx.fillStyle = '#245e3a'; ctx.fillRect(60,198,234,289); ctx.fillStyle='#fff'; ctx.textAlign='center'; ctx.font='900 92px Arial'; ctx.fillText(cardData.initials,177,370); ctx.textAlign='left'; }
      ctx.restore();
      field(ctx, 'FULL NAME', cardData.fullName, 340, 205, 610, 38);
      field(ctx, 'AGRIGOV ID · SYSTEM-GENERATED', cardData.farmerId, 340, 285, 610, 35, true);
      field(ctx, 'RSBSA NUMBER', cardData.rsbsa, 340, 365, 285, 27);
      field(ctx, 'FFRS NUMBER', cardData.ffrs, 650, 365, 300, 27);
      field(ctx, 'REGISTRY MUNICIPALITY', cardData.municipality, 340, 445, 610, 26);
      const footer = ctx.createLinearGradient(0, 555, 1011, 638); footer.addColorStop(0,'#144c32'); footer.addColorStop(1,'#25804c');
      ctx.fillStyle=footer; ctx.fillRect(0,555,1011,83); ctx.fillStyle='#eac64d'; ctx.fillRect(0,550,1011,5);
      ctx.fillStyle='#fff'; ctx.font='800 27px Arial'; ctx.fillText('AgriGOV',48,588); ctx.fillStyle='#e2f1e6'; ctx.font='13px Arial'; ctx.fillText('AGRICULTURE INFORMATION SYSTEM',48,612);
      ctx.textAlign='right'; ctx.fillStyle='#fff'; ctx.font='800 19px Arial'; ctx.fillText('REGISTERED FARMER',860,602); ctx.font='800 22px Arial'; ctx.fillText(cardData.year,963,602); ctx.textAlign='left';
      return canvas;
    }

    async function renderBack() {
      const [officeLogo, qrImage, background] = await Promise.all([
        loadImage(cardData.officeLogo), loadImage(cardData.qrDataUri), loadImage(cardData.background)
      ]);
      if (!officeLogo || !qrImage || !background) throw new Error('The card artwork or QR code could not load. Reload this page before downloading the ID.');
      const canvas = document.createElement('canvas');
      canvas.width = 1011; canvas.height = 638;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(background, 0, 0, 1011, 638);
      const header = ctx.createLinearGradient(0,0,1011,132); header.addColorStop(0,'#144c32'); header.addColorStop(1,'#25804c');
      ctx.fillStyle=header; ctx.fillRect(0,0,1011,132); ctx.fillStyle='#eac64d'; ctx.fillRect(0,132,1011,5);
      if (officeLogo) ctx.drawImage(officeLogo,42,24,86,86);
      ctx.fillStyle='#fff'; ctx.font='20px Arial'; ctx.fillText('AgriGOV · Agriculture Information System',155,54);
      ctx.font='900 37px monospace'; ctx.fillText(cardData.farmerId,155,98);
      field(ctx,'CONTACT NUMBER',cardData.contact,52,180,420,28);
      ctx.fillStyle='#607067'; ctx.font='700 20px Arial'; ctx.fillText('FARM LOCATION · PARCEL ADDRESS',52,255);
      addressText(ctx,cardData.farmLocation,52,288,440,100);
      field(ctx,'DECLARED FARM AREA',cardData.area,52,405,420,28);
      field(ctx,'ECOSYSTEM',cardData.ecosystem,52,480,420,28);
      ctx.fillStyle='#607067'; ctx.font='700 20px Arial'; ctx.fillText('SECTOR CLASSIFICATIONS',535,180);
      const sectors = cardData.sectors.length ? cardData.sectors.join(' · ') : 'None recorded';
      fittedText(ctx,sectors,535,215,415,25,800,'#132018');
      roundRect(ctx, 615, 238, 260, 295, 14, '#ffffff', '#c4d6c7');
      ctx.save(); ctx.imageSmoothingEnabled=false; ctx.drawImage(qrImage, 638, 250, 214, 214); ctx.restore();
      ctx.fillStyle='#175334'; ctx.font='900 18px Arial'; ctx.textAlign='center'; ctx.fillText('SCAN LAND MAP',745,493);
      ctx.fillStyle='#53685c'; ctx.font='700 14px Arial'; ctx.fillText(cardData.plotCount+' mapped parcel'+(cardData.plotCount === 1 ? '' : 's')+' · Interactive view',745,517); ctx.textAlign='left';
      ctx.strokeStyle='#cfdbd3'; ctx.beginPath(); ctx.moveTo(42,548); ctx.lineTo(969,548); ctx.stroke();
      ctx.fillStyle='#68756d'; ctx.font='16px Arial'; ctx.fillText('Local agriculture registry card — not a substitute for a Philippine national government ID.',42,579);
      ctx.textAlign='right'; ctx.font='700 16px Arial'; ctx.fillText('Issued '+cardData.issued,969,610); ctx.textAlign='left';
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

    async function digitalPreview(side) {
      if (!digitalPreviewCache[side]) {
        const canvas = side === 'back' ? await renderBack() : await renderFront();
        digitalPreviewCache[side] = canvas.toDataURL('image/png', 1);
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
      digitalSide = side === 'back' ? 'back' : 'front';
      updateDigitalControls(digitalSide);
      digitalFrame.classList.add('is-changing');
      digitalImage.classList.remove('is-ready');
      digitalLoading.hidden = false;

      try {
        digitalImage.src = await digitalPreview(digitalSide);
        digitalLoading.hidden = true;
        requestAnimationFrame(() => {
          digitalFrame.classList.remove('is-changing');
          digitalImage.classList.add('is-ready');
        });
      } catch (error) {
        digitalLoading.hidden = false;
        digitalLoading.textContent = error.message || 'The digital card could not be prepared.';
        digitalFrame.classList.remove('is-changing');
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

    document.getElementById('printFarmerCard')?.addEventListener('click', () => window.print());
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
