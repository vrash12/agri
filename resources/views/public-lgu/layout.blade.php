<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'LGU services') · eAgriLGU</title>
  <meta name="description" content="@yield('description', 'Find your local agriculture office, services, announcements and contact information on eAgriLGU.')">
  @if($preview ?? false)<meta name="robots" content="noindex, nofollow">@endif
  @include('partials.branding-head')
  @include('partials.design-tokens')
  <link rel="stylesheet" href="{{ asset('css/lgu-website.css') }}?v={{ filemtime(public_path('css/lgu-website.css')) }}">
</head>
<body>
  <a class="lgu-skip" href="#main">Skip to content</a>
  <div class="lgu-topbar"><div class="lgu-container"><span>eAgriLGU · Local agriculture services</span><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('lgu.index') }}">Find your LGU <span aria-hidden="true">↗</span></a></div></div>
  <header class="lgu-header"><div class="lgu-container lgu-header-inner">
    <a class="lgu-brand" href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('welcome') }}"><x-brand /></a>
    <nav aria-label="Main navigation">@yield('navigation')<a class="lgu-button lgu-button-outline" href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('login') }}">Office sign in</a></nav>
  </div></header>
  @if($preview ?? false)<aside class="lgu-preview"><div class="lgu-container">Staff preview · {{ $site->is_published ? 'This website is published.' : 'This website is unpublished.' }} <a href="{{ route('settings.lgu.website.edit', $municipality) }}">Return to website settings</a></div></aside>@endif
  <main id="main">@yield('content')</main>
  <footer class="lgu-footer"><div class="lgu-container"><div><x-brand compact /><p>Connecting communities<br>with their agriculture offices.</p></div><nav aria-label="Footer navigation"><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('lgu.index') }}">LGU directory</a><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('farmer-portal.login') }}">Farmer sign in</a><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('welcome') }}">About eAgriLGU</a></nav><p>Powered by eAgriLGU<br><span>Information maintained by participating LGUs.</span></p></div></footer>
</body>
</html>
