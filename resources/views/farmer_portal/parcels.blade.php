@extends('farmer_portal.layout')
@section('title', 'My farm')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p><h1>My farm</h1><p>Parcels linked to your farmer record. Areas and seasonal crops reflect office records.</p></header>
<form method="GET" class="fp-year-filter"><label for="year">Crop year</label><input class="module-input" id="year" name="year" type="number" min="1900" max="{{ now()->year + 1 }}" value="{{ $cropYear }}" required><button class="module-button module-button-primary" type="submit">Show season records</button></form>
<div class="fp-section-heading"><h2>My plotted land</h2><span class="fp-small">{{ number_format($plots->total()) }} recorded parcels</span></div>
@if($plots->isEmpty())
    <section class="fp-empty"><h2>{{ $plots->total() ? 'No parcels on this page' : 'No farm parcels recorded yet' }}</h2><p>Ask your agriculture office to check or map the parcels linked to your farmer record.</p></section>
@else
    <div class="fp-parcel-list">
        @foreach($plots as $plot)
            <article class="fp-panel fp-parcel"><header><h2>{{ $plot->name ?: 'Unnamed parcel' }}</h2><span>{{ $plot->area_ha !== null ? number_format((float) $plot->area_ha, 4).' ha' : 'Area not recorded' }}</span></header><a class="module-button" href="{{ route('farmer-portal.parcels.map', $plot->id) }}">View parcel map</a><h3>Recorded crops · {{ $cropYear }}</h3>
                @if($plot->seasonalCrops->isEmpty())<p class="fp-muted">No seasonal crop entries recorded for this parcel.</p>@else
                    <ul class="fp-crop-list">@foreach($plot->seasonalCrops as $crop)<li><span>{{ $crop->crop_year }} · {{ \App\Models\ParcelCropSeason::SEASONS[$crop->season] ?? 'Season not recorded' }}</span><strong>{{ \App\Models\ParcelCropSeason::CROPS[$crop->crop] ?? 'Crop not recorded' }}</strong></li>@endforeach</ul>
                @endif
            </article>
        @endforeach
    </div>
@endif
@include('farmer_portal.pagination', ['paginator' => $plots, 'recordLabel' => 'parcels'])
<p><a href="{{ route('farmer-portal.harvests', ['year' => $cropYear]) }}">View harvests recorded for {{ $cropYear }} <span aria-hidden="true">→</span></a></p>
<p class="fp-small">Mapped areas support agriculture-office planning and record keeping. They do not establish legal boundaries or ownership.</p>
@endsection
