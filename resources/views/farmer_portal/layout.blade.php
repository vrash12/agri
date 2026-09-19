<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Farmer portal') | AgriGOV</title>
    @include('partials.branding-head')
    @include('partials.operations-ui-styles')
    <link rel="stylesheet" href="{{ asset('css/farmer-portal.css') }}?v={{ filemtime(public_path('css/farmer-portal.css')) }}">
    @if(isset($account))
        <script src="{{ asset('js/session-history.js') }}?v={{ filemtime(public_path('js/session-history.js')) }}"></script>
    @endif
</head>
<body class="farmer-portal">
    <a class="fp-skip" href="#main-content">Skip to content</a>
    <header class="fp-header">
        <div class="fp-header-inner">
            <a class="fp-brand" href="{{ isset($account) ? route('farmer-portal.home') : route('welcome') }}" aria-label="AgriGOV {{ isset($account) ? 'farmer home' : 'welcome page' }}"><x-brand /></a>
            <span class="fp-header-label">Farmer portal</span>
            @if(isset($account))
                <form method="POST" action="{{ route('farmer-portal.logout') }}" data-portal-submit data-portal-logout>@csrf<button class="module-button" type="submit">Sign out</button></form>
            @else
                <a class="fp-office-link" href="{{ route('login') }}">Office sign in</a>
            @endif
        </div>
        @if(isset($account))
            <nav class="fp-nav" aria-label="Farmer portal">
                @foreach(['home' => 'Overview', 'profile' => 'My profile', 'parcels' => 'My farm', 'assistance' => 'My assistance'] as $page => $label)
                    <a href="{{ route('farmer-portal.'.$page) }}" @if(request()->routeIs('farmer-portal.'.$page)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        @endif
    </header>
    <main id="main-content" class="fp-main {{ isset($account) ? '' : 'fp-main-guest' }}" tabindex="-1">
        @if(session('success'))<div class="fp-message fp-message-success" role="status">{{ session('success') }}</div>@endif
        @if(session('status'))<div class="fp-message" role="status">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="fp-message fp-message-error" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="form-error-summary" data-error-summary role="alert" tabindex="-1"><strong>Please check the information below.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
    <footer class="fp-footer"><span>AgriGOV · Farmer portal</span><span>Need help? Contact your city or municipal agriculture office.</span></footer>
    @include('partials.form-feedback')
    @if(isset($account))<span hidden data-portal-heartbeat="{{ route('farmer-portal.heartbeat') }}" data-idle-seconds="{{ \App\Http\Middleware\EnforceIdleSession::timeoutMinutes() * 60 }}" data-login-url="{{ route('farmer-portal.login') }}"></span>@endif
    <script src="{{ asset('js/farmer-portal.js') }}?v={{ filemtime(public_path('js/farmer-portal.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
