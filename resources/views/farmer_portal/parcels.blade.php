@extends('farmer_portal.layout')
@section('title', 'My farm')
@section('content')
<header class="fp-page-heading fp-farm-heading">
    <div>
        <p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p>
        <h1>My farm</h1>
        <p>Your plotted land and the crops recorded by your agriculture office.</p>
    </div>
    <dl class="fp-farm-totals" aria-label="My farm totals">
        <div><dt>Recorded parcels</dt><dd>{{ number_format($totals['parcels']) }}</dd></div>
        <div>
            <dt>Recorded plot area</dt>
            <dd>{{ $totals['area_ha'] !== null ? number_format($totals['area_ha'], 4) : '—' }} <span>ha</span></dd>
            @if($totals['area_recorded'] < $totals['parcels'])
                <small>{{ $totals['parcels'] - $totals['area_recorded'] }} without a recorded area</small>
            @endif
        </div>
    </dl>
</header>
@if($totals['parcels'] > 0)
    <section class="fp-panel fp-map-workspace" aria-labelledby="farm-map-heading"
        data-parcel-map data-collection-url="{{ route('farmer-portal.parcels.geometries') }}"
        data-maps-key="{{ config('services.google_maps.key') }}" data-crop-year="{{ $cropYear }}">
        <div class="fp-map-workspace-heading">
            <div>
                <h2 id="farm-map-heading">My plotted land</h2>
                <p>Select a boundary or choose a parcel to see its records.</p>
            </div>
            <button class="module-button" type="button" data-fit-map disabled>Show all parcels</button>
        </div>
        <div class="fp-map-layout">
            <div class="fp-map-column">
                <div class="fp-map-stage">
                    <div class="fp-map" data-map-canvas aria-label="Map of all your recorded farm parcels" hidden></div>
                    <div class="fp-map-placeholder" data-map-placeholder>
                        <span class="fp-eyebrow">My plotted land</span>
                        <p data-map-placeholder-text>Preparing your map…</p>
                        <noscript>Enable JavaScript to see the map, or open Parcel records below.</noscript>
                    </div>
                </div>
                <div class="fp-map-feedback">
                    <p class="fp-map-status" role="status" aria-live="polite" data-map-status>Loading your parcels…</p>
                    <button class="module-button" type="button" data-map-retry hidden>Try again</button>
                </div>
            </div>
            <aside class="fp-map-details" aria-label="Parcel details">
                <label for="parcel-choice">Choose a parcel</label>
                <select id="parcel-choice" class="module-input" data-map-parcel-select disabled>
                    <option value="">Loading parcels…</option>
                </select>
                <div class="fp-map-detail-card" data-map-detail hidden aria-live="polite" aria-atomic="true">
                    <p class="fp-eyebrow">Selected parcel</p>
                    <h3 data-map-detail-name></h3>
                    <p class="fp-map-area"><strong data-map-detail-area></strong><span>Recorded area</span></p>
                    <p class="fp-map-warning" data-map-detail-warning hidden>This boundary needs office review. The parcel is not shown on the map.</p>
                </div>
                <div class="fp-map-crop-section">
                    <h3>Seasonal crops</h3>
                    <form method="GET" class="fp-map-year-filter" action="{{ route('farmer-portal.parcels') }}">
                        <div>
                            <label for="year">Crop year</label>
                            <input class="module-input" id="year" name="year" type="number" min="1900" max="{{ now()->year + 1 }}" value="{{ $cropYear }}" required>
                        </div>
                        <button class="module-button" type="submit">Apply year</button>
                    </form>
                    <p class="fp-small">Recorded for {{ $cropYear }}</p>
                    <ul class="fp-crop-list" data-map-crops aria-live="polite">
                        <li class="fp-muted">Parcel crop records will appear here.</li>
                    </ul>
                </div>
                <a class="fp-map-harvest-link" href="{{ route('farmer-portal.harvests', ['year' => $cropYear]) }}">View my harvests <span aria-hidden="true">→</span></a>
            </aside>
        </div>
        <p class="fp-map-warning fp-map-review" data-map-warning hidden></p>
    </section>
@else
    <section class="fp-empty">
        <h2>No farm parcels recorded yet</h2>
        <p>Ask your agriculture office to check or map the parcels linked to your farmer record.</p>
    </section>
@endif

@if($totals['parcels'] > 0)
    <details class="fp-record-details fp-map-fallback" data-map-records @if($plots->currentPage() > 1) open @endif>
        <summary>Parcel records <span>Areas and crops in a list</span></summary>
        @forelse($plots as $plot)
            <article class="fp-parcel-record">
                <header>
                    <h2>{{ $plot->name ?: 'Unnamed parcel' }}</h2>
                    <span>{{ $plot->area_ha !== null ? number_format((float) $plot->area_ha, 4).' ha' : 'Area not recorded' }}</span>
                </header>
                @if($plot->seasonalCrops->isEmpty())
                    <p class="fp-muted">No seasonal crop entries recorded for {{ $cropYear }}.</p>
                @else
                    <ul class="fp-crop-list">
                        @foreach($plot->seasonalCrops as $crop)
                            <li><span>{{ $cropYear }} · {{ \App\Models\ParcelCropSeason::SEASONS[$crop->season] ?? 'Season not recorded' }}</span><strong>{{ \App\Models\ParcelCropSeason::CROPS[$crop->crop] ?? 'Crop not recorded' }}</strong></li>
                        @endforeach
                    </ul>
                @endif
                <a href="{{ route('farmer-portal.parcels.map', $plot->id) }}">Open this parcel's map</a>
            </article>
        @empty
            <p>No parcels on this page. <a href="{{ route('farmer-portal.parcels', ['year' => $cropYear]) }}">Return to the first page</a>.</p>
        @endforelse
        @include('farmer_portal.pagination', ['paginator' => $plots, 'recordLabel' => 'parcels'])
    </details>
@endif
<p class="fp-small">Mapped areas support agriculture-office planning and record keeping. They do not establish legal boundaries or ownership.</p>
@endsection
@push('scripts')
    <script src="{{ asset('js/farmer-portal-map.js') }}?v={{ filemtime(public_path('js/farmer-portal-map.js')) }}" defer></script>
@endpush
