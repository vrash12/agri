@extends('farmer_portal.layout')
@section('title', 'Farmer sign in')
@section('content')
<section class="fp-auth-panel" aria-labelledby="sign-in-title">
    <header><p class="fp-eyebrow">Your agriculture records</p><h1 id="sign-in-title">Farmer sign in</h1><p>View your profile, recorded farm parcels, and assistance history.</p></header>
    <form method="POST" action="{{ route('farmer-portal.login.attempt') }}" class="fp-form" data-portal-submit>
        @csrf
        <x-module.field name="login_id" label="RSBSA number or AgriGOV login ID" required hint="Use the login ID your agriculture office provided.">
            <input class="module-input" id="login_id" name="login_id" type="text" value="{{ old('login_id') }}" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="100" required aria-describedby="login_id_hint login_id_error">
        </x-module.field>
        <x-module.field name="password" label="Password" required>
            <div class="fp-password"><input class="module-input" id="password" name="password" type="password" autocomplete="current-password" maxlength="72" required aria-describedby="password_error"><button class="fp-reveal" type="button" data-password-toggle="password" aria-controls="password" aria-pressed="false" hidden>Show</button></div>
        </x-module.field>
        <button class="module-button module-button-primary fp-full" type="submit" data-submitting-label="Signing in…">Sign in</button>
        <p class="fp-small">Use your chosen password. Your birthday is not a password.</p>
    </form>
    <div class="fp-auth-help"><h2>First time here?</h2><p>Ask your agriculture office to verify your record and issue an activation code.</p><a class="module-button" href="{{ route('farmer-portal.activate') }}">Activate my account</a></div>
    <details class="fp-help"><summary>Forgot your password or login ID?</summary><p>Contact your city or municipal agriculture office. Staff will verify your identity and issue a new activation code. Do not send your password to anyone.</p></details>
</section>
@endsection
