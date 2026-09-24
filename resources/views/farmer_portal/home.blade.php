@extends('farmer_portal.layout')
@section('title', 'My farmer account')
@section('content')
<header class="fp-page-heading">
    <p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p>
    <h1>Welcome, {{ $farmer->first_name ?: 'farmer' }}.</h1>
    <p>Your farm, assistance and harvest records in one place.</p>
</header>
<section class="fp-overview" aria-labelledby="overview-title">
    <div><h2 id="overview-title">My agriculture records</h2><p>As recorded by your agriculture office.</p><span class="fp-registry">{{ $farmer->agri_gov_id }}</span></div>
    <a class="module-button" href="{{ route('farmer-portal.profile') }}">View my profile <span aria-hidden="true">→</span></a>
</section>
<p class="fp-period-label">Your recorded totals · All years</p>
<div class="fp-home-links">
    <a class="fp-record-link" href="{{ route('farmer-portal.parcels') }}">
        <span class="fp-record-count">{{ number_format($overview['parcels']) }}</span><h2>Farm parcels</h2>
        <p>{{ $overview['area_ha'] !== null ? number_format($overview['area_ha'], 4).' ha of recorded parcel area' : 'Parcel area not recorded' }}</p>
        @if($overview['area_recorded'] < $overview['parcels'])<p class="fp-small">{{ number_format($overview['parcels'] - $overview['area_recorded']) }} with no recorded area</p>@endif
        <span class="fp-link-label">View land and crops <span aria-hidden="true">→</span></span>
    </a>
    <a class="fp-record-link" href="{{ route('farmer-portal.assistance') }}">
        <span class="fp-record-count">{{ number_format($overview['assistance']) }}</span><h2>Assistance releases</h2>
        <p>Seeds, farm inputs and other assistance received</p><span class="fp-link-label">View my assistance <span aria-hidden="true">→</span></span>
    </a>
    <a class="fp-record-link" href="{{ route('farmer-portal.harvests') }}">
        <span class="fp-record-count">{{ number_format($overview['harvests']) }}</span><h2>Harvest records</h2>
        <p>Production, harvested area and cropping periods</p><span class="fp-link-label">View my harvests <span aria-hidden="true">→</span></span>
    </a>
</div>
<section class="fp-panel" aria-labelledby="my-parcels-title">
    <div class="fp-section-heading"><div><h2 id="my-parcels-title">My plotted land</h2><p class="fp-small">Crops recorded for {{ $cropYear }}</p></div><a href="{{ route('farmer-portal.parcels') }}">View all parcels <span aria-hidden="true">→</span></a></div>
    @if($recent['parcels']->isEmpty())
        <p class="fp-muted">No farm parcels recorded yet. Your agriculture office can help link or map your land.</p>
    @else
        <div class="fp-parcel-preview">
            @foreach($recent['parcels'] as $parcel)
                <article>
                    <span class="fp-eyebrow">Farm parcel</span><h3>{{ $parcel->name ?: 'Unnamed parcel' }}</h3>
                    <p>{{ $parcel->area_ha !== null ? number_format($parcel->area_ha, 4).' ha' : 'Area not recorded' }}</p>
                    @forelse($crops->get($parcel->id, collect()) as $crop)
                        <p class="fp-small">{{ \App\Models\ParcelCropSeason::SEASONS[$crop->season] ?? 'Season not recorded' }}: <strong>{{ \App\Models\ParcelCropSeason::CROPS[$crop->crop] ?? 'Crop not recorded' }}</strong></p>
                    @empty
                        <p class="fp-small">No seasonal crops recorded for {{ $cropYear }}.</p>
                    @endforelse
                    <a class="module-button" href="{{ route('farmer-portal.parcels.map', $parcel->id) }}" aria-label="View parcel map: {{ $parcel->name ?: 'Unnamed parcel' }}">View parcel map <span aria-hidden="true">→</span></a>
                </article>
            @endforeach
        </div>
    @endif
</section>
<div class="fp-activity-grid">
    <section class="fp-panel" aria-labelledby="recent-assistance-title">
        <div class="fp-section-heading"><h2 id="recent-assistance-title">Recent seeds &amp; assistance</h2><a href="{{ route('farmer-portal.assistance') }}">View all</a></div>
        <ul class="fp-activity-list">
            @forelse($recent['assistance'] as $release)
                <li><div><span class="fp-small">{{ $release->date_received?->format('M j, Y') ?: 'Date not recorded' }}</span><h3>{{ $release->seed_variety_claimed ?: 'Item not recorded' }}</h3><p>{{ \App\Models\RiceSeedDistribution::INPUT_CATEGORY_LABELS[$release->input_category] ?? 'Type not recorded' }}</p></div><strong class="fp-quantity">{{ $release->kgs_received !== null ? number_format((float) $release->kgs_received, 2) : 'Quantity not recorded' }} @if($release->kgs_received !== null){{ \App\Models\RiceSeedDistribution::QUANTITY_UNIT_LABELS[$release->quantity_unit] ?? '— unit not recorded' }}@endif</strong></li>
            @empty
                <li><p class="fp-muted">No assistance releases recorded yet. Contact your agriculture office if a release is missing.</p></li>
            @endforelse
        </ul>
    </section>
    <section class="fp-panel" aria-labelledby="recent-harvests-title">
        <div class="fp-section-heading"><h2 id="recent-harvests-title">Recent harvests</h2><a href="{{ route('farmer-portal.harvests') }}">View all</a></div>
        <ul class="fp-activity-list">
            @forelse($recent['harvests'] as $harvest)
                <li><div><span class="fp-small">{{ $harvest->date_harvested?->format('M j, Y') ?: 'Date not recorded' }}</span><h3>{{ $harvest->commodityLabel() }}</h3><p>{{ $harvest->variety ?: 'Variety not recorded' }} · {{ $harvest->periodLabel() }}</p></div><strong class="fp-quantity">{{ $harvest->quantity !== null ? number_format((float) $harvest->quantity, 3) : 'Quantity not recorded' }} @if($harvest->quantity !== null){{ $harvest->quantityUnitLabel() ?: '— unit not recorded' }}@endif</strong></li>
            @empty
                <li><p class="fp-muted">No harvest records yet. Your office can record your harvest even when you used your own seed.</p></li>
            @endforelse
        </ul>
    </section>
</div>
<section class="fp-guidance" aria-labelledby="help-title"><h2 id="help-title">Something missing or incorrect?</h2><p>Contact your city or municipal agriculture office and give them your AgriGOV ID. Staff can check your record and make corrections.</p><p class="fp-small">These are recorded transactions and planning maps. They do not establish land ownership or eligibility for future assistance.</p></section>
@endsection
