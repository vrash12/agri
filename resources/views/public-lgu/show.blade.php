@extends('public-lgu.layout')
@section('title', $municipality->name.' · '.$municipality->supervisingProvince->name)
@section('description', $site->tagline ?: 'Agriculture office information and services for '.$municipality->name.'.')
@section('navigation')<a href="#services">Services</a><a href="#announcements">Announcements</a><a href="#contact">Contact</a>@endsection
@section('content')
@php
  $province = $municipality->supervisingProvince->name;
  $officeName = $profile?->office_name ?: 'Agriculture office';
  // Only offer a call link when the recorded number actually contains a dialable number.
  $dialable = $profile?->phone ? preg_replace('/[^0-9+]/', '', $profile->phone) : '';
  $phoneHref = strlen($dialable) >= 7 ? 'tel:'.$dialable : null;
  $services = $site->services ?? [];
  $announcements = $site->announcements ?? [];
@endphp

<section class="lgu-hero">
  <div class="lgu-container lgu-hero-grid">
    <div class="lgu-hero-copy">
      <div class="lgu-office-identity">
        <span class="lgu-seal">@if($logoUrl)<img src="{{ $logoUrl }}" width="64" height="64" alt="{{ $municipality->name }} LGU logo">@else<x-brand compact />@endif</span>
        <div><p class="lgu-eyebrow">{{ $province }} · Philippines</p><strong>{{ $municipality->name }}</strong><span class="lgu-office-name">{{ $officeName }}</span></div>
      </div>
      <h1>{{ $site->headline }}</h1>
      <p class="lgu-lead">{{ $site->tagline ?: 'Your local connection to agriculture services, office information and community updates.' }}</p>
      <div class="lgu-actions">
        <a class="lgu-button" href="#services">Explore services @include('public-lgu.partials.icon', ['name' => 'arrow-down', 'size' => 18])</a>
        <a class="lgu-button lgu-button-outline" href="#contact">Contact our office</a>
      </div>
      @if($site->office_hours || $phoneHref || $profile?->email)
        <ul class="lgu-facts" aria-label="Office at a glance">
          @if($site->office_hours)<li>@include('public-lgu.partials.icon', ['name' => 'clock', 'size' => 18])<span><span class="lgu-sr-only">Office hours: </span>{{ $site->office_hours }}</span></li>@endif
          @if($phoneHref)<li>@include('public-lgu.partials.icon', ['name' => 'phone', 'size' => 18])<a href="{{ $phoneHref }}"><span class="lgu-sr-only">Call </span>{{ $profile->phone }}</a></li>@endif
          @if($profile?->email)<li>@include('public-lgu.partials.icon', ['name' => 'mail', 'size' => 18])<a href="mailto:{{ $profile->email }}"><span class="lgu-sr-only">Email </span>{{ $profile->email }}</a></li>@endif
        </ul>
      @endif
    </div>
    @if($coverUrl)
      <figure class="lgu-hero-photo"><img src="{{ $coverUrl }}" alt="{{ $municipality->name }} community photograph" fetchpriority="high" width="1000" height="700"><figcaption>@include('public-lgu.partials.icon', ['name' => 'pin', 'size' => 16]){{ $municipality->name }} · {{ $province }}</figcaption></figure>
    @else
      <div class="lgu-hero-panel">
        <span class="lgu-eyebrow">Local agriculture office</span>
        <h2>Rooted in our community.</h2>
        <p>Information and support for the people who grow, raise and harvest.</p>
        <div class="lgu-hero-office">@include('public-lgu.partials.icon', ['name' => 'leaf'])<div><strong>{{ $officeName }}</strong><p>{{ $municipality->name }}, {{ $province }}</p></div></div>
      </div>
    @endif
  </div>
</section>

<nav class="lgu-quick-links lgu-container" aria-label="Quick links">
  <a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('farmer-portal.login') }}">@include('public-lgu.partials.icon', ['name' => 'user'])<div><strong>Farmer portal</strong><span>Sign in to see your farm and records</span></div>@include('public-lgu.partials.icon', ['name' => 'external', 'size' => 18])</a>
  <a href="#farmer-id">@include('public-lgu.partials.icon', ['name' => 'card'])<div><strong>Farmer registry ID</strong><span>How to contact your issuing office</span></div>@include('public-lgu.partials.icon', ['name' => 'arrow-down', 'size' => 18])</a>
  <a href="#contact">@include('public-lgu.partials.icon', ['name' => 'pin'])<div><strong>Visit our office</strong><span>Location, contacts and office hours</span></div>@include('public-lgu.partials.icon', ['name' => 'arrow-down', 'size' => 18])</a>
</nav>

@if($site->welcome_text || $profile?->description)
  <section class="lgu-section lgu-container lgu-about" id="about" aria-labelledby="about-title">
    <div><p class="lgu-eyebrow">Serving {{ $municipality->name }}</p><h2 id="about-title">Your local agriculture office</h2></div>
    <div class="lgu-about-body">
      @if($site->welcome_text)<p class="lgu-preserve-lines">{{ $site->welcome_text }}</p>@endif
      @if($profile?->description)<p class="lgu-preserve-lines">{{ $profile->description }}</p>@endif
    </div>
  </section>
@endif

<section class="lgu-section lgu-container" id="services" aria-labelledby="services-title">
  <div class="lgu-section-heading"><div><p class="lgu-eyebrow">How we can help</p><h2 id="services-title">Services for our community</h2></div><p>Requirements and schedules can change. Check with the office before your visit.</p></div>
  <div class="lgu-services-grid">
    @forelse($services as $service)
      <article class="lgu-service">
        <div class="lgu-service-top">@include('public-lgu.partials.icon', ['name' => 'leaf'])<span class="lgu-service-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
        <h3>{{ $service['title'] }}</h3>
        @if(filled($service['description'] ?? null))<p class="lgu-preserve-lines">{{ $service['description'] }}</p>@endif
        @if($service['url'] ?? null)
          <a class="lgu-link" href="{{ $service['url'] }}" target="_blank" rel="noopener noreferrer">Open service website<span class="lgu-sr-only"> for {{ $service['title'] }} (opens in a new tab)</span> @include('public-lgu.partials.icon', ['name' => 'external', 'size' => 16])</a>
        @else
          <a class="lgu-link" href="#contact">Ask our office<span class="lgu-sr-only"> about {{ $service['title'] }}</span> @include('public-lgu.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
        @endif
      </article>
    @empty
      <div class="lgu-empty">@include('public-lgu.partials.icon', ['name' => 'leaf'])<h3>Talk to our agriculture office</h3><p>Contact the office for available programs, requirements and schedules.</p><a class="lgu-link" href="#contact">Find office contacts @include('public-lgu.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a></div>
    @endforelse
  </div>
</section>

<section class="lgu-notice-section" id="announcements" aria-labelledby="announcements-title">
  <div class="lgu-container lgu-section">
    <div class="lgu-section-heading"><div><p class="lgu-eyebrow">From the LGU</p><h2 id="announcements-title">Announcements &amp; advisories</h2></div></div>
    <div class="lgu-notices-grid">
      @forelse($announcements as $notice)
        <article class="lgu-notice @if($loop->first) lgu-notice-latest @endif">
          <div class="lgu-notice-meta">
            @if($notice['date'] ?? null)<time datetime="{{ $notice['date'] }}">@include('public-lgu.partials.icon', ['name' => 'calendar', 'size' => 16]){{ \Carbon\Carbon::parse($notice['date'])->format('M j, Y') }}</time>@else<span>@include('public-lgu.partials.icon', ['name' => 'megaphone', 'size' => 16])Office notice</span>@endif
            @if($loop->first)<span class="lgu-chip lgu-chip-accent">Latest</span>@endif
          </div>
          <h3>{{ $notice['title'] }}</h3>
          @if(filled($notice['body'] ?? null))<p class="lgu-preserve-lines">{{ $notice['body'] }}</p>@endif
        </article>
      @empty
        <div class="lgu-notices-empty">@include('public-lgu.partials.icon', ['name' => 'megaphone'])<p>No announcements have been posted. Contact the office for the latest schedules.</p></div>
      @endforelse
    </div>
  </div>
</section>

<section class="lgu-section lgu-container" id="farmer-id" aria-labelledby="farmer-id-title">
  <div class="lgu-id-panel">
    <div>
      <p class="lgu-eyebrow">Connected to your LGU</p>
      <h2 id="farmer-id-title">Your farmer registry ID</h2>
      <p>Your LGU's agriculture office maintains farmer registration and issues eAgriLGU farmer cards carrying its logo and office details. Contact the office for registration, corrections or card issuance.</p>
      <p class="lgu-id-note">@include('public-lgu.partials.icon', ['name' => 'card', 'size' => 18])<span>The farmer registry card is a local agriculture registry card. It is not a national government ID.</span></p>
      <a class="lgu-button" href="#contact">Contact the issuing office @include('public-lgu.partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
    </div>
    <div class="lgu-id-card" aria-label="Issuing office">
      <div class="lgu-id-card-body">
        @if($logoUrl)<img src="{{ $logoUrl }}" alt="" width="76" height="76" loading="lazy">@else<span class="lgu-id-seal"><x-brand compact /></span>@endif
        <span class="lgu-eyebrow">Issuing office</span>
        <h3>{{ $officeName }}</h3>
        <p>{{ $municipality->name }}<br>{{ $province }}</p>
      </div>
      <div class="lgu-id-card-foot"><x-brand /></div>
    </div>
  </div>
</section>

<section class="lgu-section lgu-container" id="contact" aria-labelledby="contact-title" style="padding-top:0">
  <div class="lgu-section-heading"><div><p class="lgu-eyebrow">We are here to help</p><h2 id="contact-title">Contact &amp; office location</h2></div></div>
  <div class="lgu-contact-grid">
    <div class="lgu-contact-card">
      <h3>{{ $officeName }}</h3>
      @if($profile?->office_address || $site->office_hours || $profile?->phone || $profile?->email)
        <dl class="lgu-contact-list">
          @if($profile?->office_address)<div>@include('public-lgu.partials.icon', ['name' => 'pin'])<dt>Office address</dt><dd>{{ $profile->office_address }}</dd></div>@endif
          @if($site->office_hours)<div>@include('public-lgu.partials.icon', ['name' => 'clock'])<dt>Office hours</dt><dd>{{ $site->office_hours }}</dd></div>@endif
          @if($profile?->phone)<div>@include('public-lgu.partials.icon', ['name' => 'phone'])<dt>Telephone / mobile</dt><dd>@if($phoneHref)<a href="{{ $phoneHref }}">{{ $profile->phone }}</a>@else{{ $profile->phone }}@endif</dd></div>@endif
          @if($profile?->email)<div>@include('public-lgu.partials.icon', ['name' => 'mail'])<dt>Email</dt><dd><a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></dd></div>@endif
        </dl>
      @endif
      @unless($profile?->office_address || $profile?->phone || $profile?->email)<p class="lgu-muted">Contact details will appear here when the office provides them.</p>@endunless
      @if($profile?->website_url)<a class="lgu-link" href="{{ $profile->website_url }}" target="_blank" rel="noopener noreferrer">@include('public-lgu.partials.icon', ['name' => 'globe', 'size' => 18])Visit the official LGU website<span class="lgu-sr-only"> (opens in a new tab)</span> @include('public-lgu.partials.icon', ['name' => 'external', 'size' => 16])</a>@endif
    </div>
    <aside class="lgu-location" aria-labelledby="location-title">
      <span class="lgu-eyebrow">Plan your visit</span>
      <h3 id="location-title">{{ $municipality->name }}</h3>
      <p>{{ $province }}, Philippines</p>
      @if($mapQuery)
        @if($profile?->office_address)<p>{{ $profile->office_address }}</p>@endif
        <a class="lgu-button" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($mapQuery) }}" target="_blank" rel="noopener noreferrer">@include('public-lgu.partials.icon', ['name' => 'pin', 'size' => 18])Open office location<span class="lgu-sr-only"> in Google Maps (opens in a new tab)</span></a>
      @else
        <p>The office has not added a map location yet.</p>
      @endif
    </aside>
  </div>
</section>
@endsection
