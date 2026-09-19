@extends('layouts.app')
@section('title', 'Farmer portal account')
@push('styles')
    @include('partials.operations-ui-styles')
    <link rel="stylesheet" href="{{ asset('css/farmer-portal.css') }}?v={{ filemtime(public_path('css/farmer-portal.css')) }}">
@endpush
@section('content')
<div class="module-page fp-staff">
    @if($status)<div class="fp-message fp-message-success" role="status">{{ $status }}</div>@endif
    <header class="module-header"><div><div class="module-eyebrow">{{ $farmer->municipality?->name ?: 'Farmer registry' }}</div><h1>Farmer portal account</h1><p>{{ collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' ') ?: $farmer->owner_name }} · {{ $farmer->registry_id }}</p></div><a class="module-button" href="{{ route('farmers.records', $farmer) }}">Back to farmer record</a></header>
    @if($errors->any())<div class="form-error-summary" data-error-summary role="alert"><strong>Account not changed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($activationCode)
        <section class="fp-panel fp-activation-result" aria-labelledby="activation-result-title"><h2 id="activation-result-title">Activation code issued</h2><p>Give these details privately to the verified farmer. This code is shown only now and can be used once within 24 hours.</p><dl class="fp-details"><div><dt>Login ID</dt><dd>{{ $account->login_id }}</dd></div><div><dt>Activation page</dt><dd><a href="{{ route('farmer-portal.activate') }}">{{ route('farmer-portal.activate') }}</a></dd></div></dl><label class="fp-code-label" for="issued_activation_code">One-time activation code</label><div class="fp-password fp-code"><input class="module-input" id="issued_activation_code" type="password" readonly value="{{ $activationCode }}" autocomplete="off" spellcheck="false"><button class="fp-reveal" type="button" data-password-toggle="issued_activation_code" aria-controls="issued_activation_code" aria-pressed="false" hidden>Show</button></div><p class="fp-small">The farmer chooses their own password. Do not record this code in notes, screenshots, or shared files.</p></section>
    @endif
    <section class="fp-panel"><h2>Account status</h2><dl class="fp-details"><div><dt>Status</dt><dd>{{ ! $account ? 'Not issued' : (! $account->is_active ? 'Disabled' : ($account->activated_at ? 'Active' : 'Awaiting activation')) }}</dd></div><div><dt>Login ID</dt><dd>{{ $account?->login_id ?: 'Assigned when activation is issued' }}</dd></div>@if($account?->activation_expires_at)<div><dt>Activation expires</dt><dd>{{ \App\Support\LocalTime::fromUtc($account->activation_expires_at)?->format('M j, Y g:i A') }} ({{ \App\Support\LocalTime::timezone() }})</dd></div>@endif</dl><p class="fp-small">The farmer can view only their linked profile, parcels, and assistance. Office records remain staff-managed.</p></section>
    <section class="fp-panel"><h2>{{ $account ? 'Recover or reissue access' : 'Issue farmer access' }}</h2><p>Verify the farmer’s identity against your office records before issuing access. An RSBSA number or birthday alone is not proof of identity.</p>@if($account)<p class="fp-message fp-message-warning">Issuing another code resets the account password and signs out existing farmer sessions. Any earlier activation code stops working.</p>@endif
        <form method="POST" action="{{ route('farmers.portal-account.issue', $farmer) }}" class="fp-form" data-portal-submit @if($account) data-confirm="Issue a new activation code? The previous password, activation code, and farmer sessions will stop working." @endif>
            @csrf<input type="hidden" name="_record_version" value="{{ $version }}">
            <label class="fp-checkbox"><input type="checkbox" name="identity_verified" value="1" required @checked(old('identity_verified'))><span>I verified this farmer’s identity and confirmed that this is their correct farmer record.</span></label>
            <button class="module-button module-button-primary" type="submit" data-submitting-label="Issuing code…">{{ $account ? 'Issue new activation code' : 'Issue activation code' }}</button>
        </form>
    </section>
    @if($account && $account->is_active)
        <section class="fp-panel"><h2>Disable farmer access</h2><p>Stop sign-ins and end the farmer’s current sessions. Their farmer record and assistance history are retained.</p><form method="POST" action="{{ route('farmers.portal-account.disable', $farmer) }}" data-portal-submit data-confirm="Disable this farmer’s portal access? Their existing sessions will stop working.">@csrf<input type="hidden" name="_record_version" value="{{ $version }}"><button class="module-button module-button-danger" type="submit" data-submitting-label="Disabling…">Disable access</button></form></section>
    @endif
</div>
@endsection
@push('scripts')<script src="{{ asset('js/farmer-portal.js') }}?v={{ filemtime(public_path('js/farmer-portal.js')) }}" defer></script>@endpush
