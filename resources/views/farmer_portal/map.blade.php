@extends('farmer_portal.layout')
@section('title', 'My parcel map')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">My farm</p><h1>{{ $parcel->name ?: 'My parcel map' }}</h1><p>{{ $parcel->area_ha !== null ? number_format($parcel->area_ha, 4).' ha recorded' : 'Area not recorded' }}</p><a href="{{ route('farmer-portal.parcels') }}">Back to my farm</a></header>
<section class="fp-panel" data-parcel-map data-geometry-url="{{ route('farmer-portal.parcels.geometry', $parcel->id) }}" data-maps-key="{{ config('services.google_maps.key') }}">
    <h2>Recorded parcel boundary</h2><p>Load the map to see this parcel over satellite imagery.</p>
    <button class="module-button module-button-primary" type="button" data-load-map>Load parcel map</button>
    <p role="status" aria-live="polite" data-map-status></p>
    <div class="fp-map" data-map-canvas aria-label="Your recorded parcel map" hidden></div>
</section>
<p class="fp-small">For agriculture-office planning. This map does not establish legal boundaries or land ownership.</p>
@endsection
@push('scripts')<script src="{{ asset('js/farmer-portal-map.js') }}?v={{ filemtime(public_path('js/farmer-portal-map.js')) }}" defer></script>@endpush
