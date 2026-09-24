@extends('farmer_portal.layout')
@section('title', 'My profile')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p><h1>My profile</h1><p>Your registered details. Contact your agriculture office for corrections.</p></header>
<section class="fp-panel fp-profile" aria-labelledby="profile-name"><div class="fp-profile-heading"><x-brand compact /><div><span class="fp-eyebrow">Local farmer registry</span><h2 id="profile-name">{{ collect([$farmer->first_name, $farmer->middle_name, $farmer->last_name, $farmer->ext_name])->filter()->implode(' ') ?: $farmer->owner_name }}</h2><p class="fp-registry">{{ $farmer->agri_gov_id }}</p></div></div>
    <div class="fp-profile-section"><h3>Personal and registry details</h3><dl class="fp-details">
        <div><dt>RSBSA number</dt><dd>{{ $farmer->rsbsa_no ?: 'Not recorded' }}</dd></div>
        <div><dt>FFRS number</dt><dd>{{ $farmer->ffrs ?: 'Not recorded' }}</dd></div>
        <div><dt>Date of birth</dt><dd>{{ $farmer->date_of_birth?->format('F j, Y') ?: 'Not recorded' }}</dd></div>
        <div><dt>Contact number</dt><dd>{{ $farmer->contact_number ?: 'Not recorded' }}</dd></div>
        <div><dt>Gender</dt><dd>{{ $farmer->gender ?: 'Not recorded' }}</dd></div>
        <div><dt>Managing office</dt><dd>{{ $farmer->municipality?->name ?: 'Not recorded' }}</dd></div>
    </dl></div>
    <div class="fp-profile-section"><h3>Farm information</h3><dl class="fp-details">
        <div><dt>Recorded farm location</dt><dd>{{ $farmer->farm_location ?: 'Not recorded' }}</dd></div>
        <div><dt>Farm municipality</dt><dd>{{ $farmer->farm_municipality ?: 'Not recorded' }}</dd></div>
        <div><dt>Farm province</dt><dd>{{ $farmer->farm_province ?: 'Not recorded' }}</dd></div>
        <div><dt>Declared farm area</dt><dd>{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'Not recorded' }}</dd></div>
        <div><dt>Farm ecosystem</dt><dd>{{ $farmer->ecosystem ?: 'Not recorded' }}</dd></div>
        <div><dt>Ecosystem source</dt><dd>{{ $farmer->ecosystem_source ?: 'Not recorded' }}</dd></div>
    </dl><p><a href="{{ route('farmer-portal.parcels') }}">View my plotted land and seasonal crops <span aria-hidden="true">→</span></a></p></div>
    <p class="fp-small">This is a view of your local registry record. It is not an official RSBSA certificate or proof of land ownership.</p>
</section>
<section class="fp-guidance"><h2>Keep your account private</h2><p>Your AgriGOV ID is <strong class="fp-break">{{ $account->login_id }}</strong>. Sign out after using a shared phone or computer. Contact your agriculture office if you need account recovery.</p></section>
@endsection
