@extends('farmer_portal.layout')
@section('title', 'My farmer account')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p><h1>Welcome, {{ $farmer->first_name ?: 'farmer' }}.</h1><p>Your records, as entered by your agriculture office.</p></header>
<section class="fp-overview" aria-labelledby="overview-title"><div><h2 id="overview-title">My agriculture records</h2><p>View your information and keep track of recorded assistance.</p><span class="fp-registry">{{ $farmer->registry_id }}</span></div><a class="module-button" href="{{ route('farmer-portal.profile') }}">View my profile</a></section>
<div class="fp-home-links">
    <a class="fp-record-link" href="{{ route('farmer-portal.parcels') }}"><span class="fp-record-count">{{ number_format($parcelCount) }}</span><h2>My farm</h2><p>Recorded parcels and crops by season</p><span class="fp-link-label">View farm records <span aria-hidden="true">→</span></span></a>
    <a class="fp-record-link" href="{{ route('farmer-portal.assistance') }}"><span class="fp-record-count">{{ number_format($assistanceCount) }}</span><h2>My assistance</h2><p>Releases linked to your farmer record</p><span class="fp-link-label">View assistance history <span aria-hidden="true">→</span></span></a>
</div>
<section class="fp-guidance" aria-labelledby="help-title"><h2 id="help-title">Something missing or incorrect?</h2><p>Contact your city or municipal agriculture office and give them your registry ID. Staff can check your record and make corrections.</p><p class="fp-small">This portal displays recorded information. It does not confirm land ownership, program eligibility, or approval of an assistance application.</p></section>
@endsection
