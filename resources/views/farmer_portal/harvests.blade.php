@extends('farmer_portal.layout')
@section('title', 'My harvests')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p><h1>My harvests</h1><p>Recorded production, harvested area and cropping periods for your farm.</p></header>
<form method="GET" class="fp-year-filter">
    <label for="year">Harvest year</label><input class="module-input" id="year" name="year" type="number" min="1900" max="{{ now()->year + 1 }}" value="{{ $harvestYear }}" placeholder="All years" aria-describedby="year-help">
    <button class="module-button module-button-primary" type="submit">Show harvests</button>
    @if($harvestYear !== null)<a class="module-button" href="{{ route('farmer-portal.harvests') }}">All years</a>@endif
    <span class="fp-small" id="year-help">Leave blank to show all recorded years.</span>
</form>
@include('farmer_portal.quantity-totals', ['categoryKey' => 'commodity', 'categoryLabels' => \App\Models\HarvestRecord::COMMODITY_LABELS, 'unitLabels' => \App\Models\HarvestRecord::QUANTITY_UNIT_LABELS, 'quantityDecimals' => 3, 'totalsPeriod' => $harvestYear !== null ? (string) $harvestYear : 'All years'])
<div class="fp-section-heading"><h2>Harvest history</h2><span class="fp-small">{{ number_format($harvests->total()) }} records · {{ $harvestYear ?? 'All years' }}</span></div>
@forelse($harvests as $harvest)
    <article class="fp-panel fp-history-record">
        <header class="fp-section-heading">
            <div><span class="fp-eyebrow">{{ $harvest->periodLabel() }}</span><h2>{{ $harvest->commodityLabel() }}</h2><p class="fp-small">{{ $harvest->variety ?: 'Variety not recorded' }}</p></div>
            <div class="fp-quantity-badge"><span class="fp-small">Recorded production</span><strong>{{ $harvest->quantity !== null ? number_format((float) $harvest->quantity, 3) : 'Not recorded' }} @if($harvest->quantity !== null){{ $harvest->quantityUnitLabel() ?: '— unit not recorded' }}@endif</strong></div>
        </header>
        <dl class="fp-details">
            <div><dt>Date harvested</dt><dd>{{ $harvest->date_harvested?->format('F j, Y') ?: 'Not recorded' }}</dd></div>
            <div><dt>Area harvested</dt><dd>{{ $harvest->area_harvested_ha !== null ? number_format((float) $harvest->area_harvested_ha, 4).' ha' : 'Not recorded' }}</dd></div>
            <div><dt>Farm parcel</dt><dd>@if($harvest->farmPlot)<a href="{{ route('farmer-portal.parcels.map', $harvest->farmPlot->id) }}">{{ $harvest->farmPlot->name ?: 'Unnamed parcel' }} <span aria-hidden="true">→</span></a>@else No parcel linked to your account @endif</dd></div>
            <div><dt>Cropping season</dt><dd>{{ $harvest->seasonLabel() }}</dd></div>
        </dl>
    </article>
@empty
    <section class="fp-empty"><h2>{{ $harvests->total() ? 'No harvests on this page' : 'No harvest records for this period' }}</h2><p>This means no harvest entries are available here, not zero production. Your agriculture office can help record a missing harvest.</p></section>
@endforelse
@include('farmer_portal.pagination', ['paginator' => $harvests, 'recordLabel' => 'harvest records'])
<p class="fp-small">Harvest quantities keep their recorded units. Seasons and years reflect the office record and are not guessed from the harvest date.</p>
@endsection
