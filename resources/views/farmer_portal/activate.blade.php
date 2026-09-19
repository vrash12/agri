@extends('farmer_portal.layout')
@section('title', 'Activate farmer account')
@section('content')
<section class="fp-auth-panel" aria-labelledby="activate-title">
    <header><p class="fp-eyebrow">First sign in or account recovery</p><h1 id="activate-title">Activate your account</h1><p>Use the login ID and one-time code given to you by your agriculture office. Codes expire after 24 hours.</p></header>
    <form method="POST" action="{{ route('farmer-portal.activate.submit') }}" class="fp-form" data-portal-submit>
        @csrf
        <x-module.field name="login_id" label="AgriGOV login ID" required>
            <input class="module-input" id="login_id" name="login_id" type="text" value="{{ old('login_id') }}" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="100" required aria-describedby="login_id_error">
        </x-module.field>
        <x-module.field name="activation_code" label="One-time activation code" required hint="Enter the code exactly as your office provided it.">
            <input class="module-input" id="activation_code" name="activation_code" type="text" autocomplete="one-time-code" autocapitalize="none" spellcheck="false" maxlength="100" required aria-describedby="activation_code_hint activation_code_error">
        </x-module.field>
        <x-module.field name="password" label="Create a password" required hint="Use at least 15 characters. A few unrelated words can be easier to remember. Avoid your name, birthday, or RSBSA number.">
            <div class="fp-password"><input class="module-input" id="password" name="password" type="password" autocomplete="new-password" minlength="15" maxlength="72" required aria-describedby="password_hint password_error"><button class="fp-reveal" type="button" data-password-toggle="password" aria-controls="password" aria-pressed="false" hidden>Show</button></div>
        </x-module.field>
        <x-module.field name="password_confirmation" label="Confirm password" required>
            <div class="fp-password"><input class="module-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="15" maxlength="72" required aria-describedby="password_confirmation_error"><button class="fp-reveal" type="button" data-password-toggle="password_confirmation" aria-controls="password_confirmation" aria-pressed="false" hidden>Show</button></div>
        </x-module.field>
        <button class="module-button module-button-primary fp-full" type="submit" data-submitting-label="Activating…">Set my password</button>
    </form>
    <p class="fp-auth-help">Code expired or already used? Ask your agriculture office for a new code after they verify your identity.</p>
    <a href="{{ route('farmer-portal.login') }}">Back to sign in</a>
</section>
@endsection
