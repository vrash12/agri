<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'LGU services') · AgriLGU</title>
  <meta name="description" content="@yield('description', 'Find your local agriculture office, services, announcements and contact information on AgriLGU.')">
  <meta name="theme-color" content="#173e2d">
  @if($preview ?? false)<meta name="robots" content="noindex, nofollow">@endif
  @include('partials.branding-head')
  @include('partials.design-tokens')
  <link rel="stylesheet" href="{{ asset('css/lgu-website.css') }}?v={{ filemtime(public_path('css/lgu-website.css')) }}">
</head>
<body>
  <a class="lgu-skip" href="#main">Skip to content</a>
  <div class="lgu-topbar"><div class="lgu-container"><span>AgriLGU · Local agriculture services</span><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('lgu.index') }}">Find your LGU @include('public-lgu.partials.icon', ['name' => 'external', 'size' => 14])</a></div></div>
  <header class="lgu-header"><div class="lgu-container lgu-header-inner">
    <a class="lgu-brand" href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('welcome') }}" aria-label="AgriLGU home"><x-brand /></a>
    <nav aria-label="Main navigation">@yield('navigation')</nav>
    <a class="lgu-button lgu-button-outline lgu-button-sm lgu-header-action" href="{{ $officeLoginUrl ?? \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('login') }}">Office sign in</a>
  </div></header>
  @if($preview ?? false)<aside class="lgu-preview" aria-label="Staff preview notice"><div class="lgu-container"><strong>@include('public-lgu.partials.icon', ['name' => 'eye', 'size' => 18])Staff preview</strong><span>{{ $site->is_published ? 'This website is published.' : 'This website is unpublished. Only authorized staff can see this page.' }}</span><a href="{{ route('settings.lgu.website.edit', $municipality) }}">Return to website settings</a></div></aside>@endif
  <main id="main">@yield('content')</main>
  <footer class="lgu-footer"><div class="lgu-container">
    <div class="lgu-footer-identity"><img class="lgu-footer-mark" src="{{ asset('images/branding/eagrilgu-mark-v1.png') }}" alt="AgriLGU" width="44" height="44" loading="lazy" decoding="async"><p>Connecting communities<br>with their agriculture offices.</p></div>
    <nav aria-label="Footer navigation"><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('lgu.index') }}">LGU directory</a><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('farmer-portal.login') }}">Farmer sign in</a><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('welcome') }}">About AgriLGU</a></nav>
    <p class="lgu-footer-note">Powered by AgriLGU<br><span>Information maintained by participating LGUs.</span></p>
  </div></footer>
</body>
</html>
