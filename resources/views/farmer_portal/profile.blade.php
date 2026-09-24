@extends('farmer_portal.layout')
@section('title', 'My profile')
@section('content')
<header class="fp-page-heading">
    <p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p>
    <h1>My profile</h1>
    <p>Your farmer record, contact details and farm information in one place.</p>
</header>
<section class="fp-panel fp-profile" aria-labelledby="profile-name">
    <header class="fp-profile-heading">
        <div class="fp-profile-identity">
            <div class="fp-profile-mark"><x-brand compact /></div>
            <div>
                <p class="fp-eyebrow">Local farmer registry</p>
                <h2 id="profile-name">{{ collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' ') ?: $farmer->owner_name }}</h2>
                <p class="fp-profile-office">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p>
            </div>
        </div>
        <div class="fp-profile-id">
            <dl>
                <dt>AgriGOV farmer ID</dt>
                <dd class="fp-registry">{{ $farmer->agri_gov_id }}</dd>
            </dl>
            <p>Use this ID to sign in to your farmer portal.</p>
        </div>
    </header>
    <div class="fp-profile-body">
        <section class="fp-profile-section" aria-labelledby="profile-personal-heading">
            <h3 id="profile-personal-heading">Personal and registry details</h3>
            <dl class="fp-details">
                <div><dt>RSBSA number</dt><dd>{{ $farmer->rsbsa_no ?: 'Not recorded' }}</dd></div>
                <div><dt>FFRS number</dt><dd>{{ $farmer->ffrs ?: 'Not recorded' }}</dd></div>
                <div><dt>Date of birth</dt><dd>{{ $farmer->date_of_birth?->format('F j, Y') ?: 'Not recorded' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $farmer->gender ?: 'Not recorded' }}</dd></div>
                <div><dt>Contact number</dt><dd>{{ $farmer->contact_number ?: 'Not recorded' }}</dd></div>
                <div><dt>Managing office</dt><dd>{{ $farmer->municipality?->name ?: 'Not recorded' }}</dd></div>
            </dl>
        </section>
        <section class="fp-profile-section" aria-labelledby="profile-farm-heading">
            <h3 id="profile-farm-heading">Farm information</h3>
            <dl class="fp-details">
                <div class="fp-profile-wide"><dt>Recorded farm location</dt><dd>{{ $farmer->farm_location ?: 'Not recorded' }}</dd></div>
                <div><dt>Farm municipality</dt><dd>{{ $farmer->farm_municipality ?: 'Not recorded' }}</dd></div>
                <div><dt>Farm province</dt><dd>{{ $farmer->farm_province ?: 'Not recorded' }}</dd></div>
                <div><dt>Declared farm area</dt><dd>{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'Not recorded' }}</dd></div>
                <div><dt>Farm ecosystem</dt><dd>{{ $farmer->ecosystem ?: 'Not recorded' }}</dd></div>
                <div class="fp-profile-wide"><dt>Ecosystem source</dt><dd>{{ $farmer->ecosystem_source ?: 'Not recorded' }}</dd></div>
            </dl>
        </section>
    </div>
    <footer class="fp-profile-footer">
        <div>
            <p><strong>Need to update your details?</strong></p>
            <p>Contact your city or municipal agriculture office for corrections.</p>
        </div>
        <a class="module-button module-button-primary" href="{{ route('farmer-portal.parcels') }}">View my farm and parcels <span aria-hidden="true">→</span></a>
    </footer>
</section>
<p class="fp-small">This is a view of your local registry record. It is not an official RSBSA certificate or proof of land ownership.</p>
<section class="fp-guidance">
    <h2>Keep your account private</h2>
    <p>Sign out after using a shared phone or computer. Contact your agriculture office if you need account recovery.</p>
</section>
@endsection
