@extends('farmer_portal.layout')
@section('title', 'My seeds & assistance')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p><h1>My seeds &amp; assistance</h1><p>What you received, when it was released, and the recorded planting details.</p></header>
@include('farmer_portal.quantity-totals', ['categoryKey' => 'input_category', 'categoryLabels' => \App\Models\RiceSeedDistribution::INPUT_CATEGORY_LABELS, 'unitLabels' => \App\Models\RiceSeedDistribution::QUANTITY_UNIT_LABELS, 'quantityDecimals' => 2, 'totalsPeriod' => 'All years'])
<div class="fp-section-heading"><h2>Assistance history</h2><span class="fp-small">{{ number_format($releases->total()) }} recorded releases · All years</span></div>
@forelse($releases as $release)
    <article class="fp-panel fp-history-record">
        <header class="fp-section-heading">
            <div><span class="fp-eyebrow">{{ \App\Models\RiceSeedDistribution::INPUT_CATEGORY_LABELS[$release->input_category] ?? 'Type not recorded' }}</span><h2>{{ $release->seed_variety_claimed ?: 'Item / variety not recorded' }}</h2><p class="fp-small">Received {{ $release->date_received?->format('F j, Y') ?: '— date not recorded' }}</p></div>
            <div class="fp-quantity-badge"><span class="fp-small">Quantity received</span><strong>{{ $release->kgs_received !== null ? number_format((float) $release->kgs_received, 2) : 'Not recorded' }} @if($release->kgs_received !== null){{ \App\Models\RiceSeedDistribution::QUANTITY_UNIT_LABELS[$release->quantity_unit] ?? '— unit not recorded' }}@endif</strong></div>
        </header>
        @if($release->batch)
            <p class="fp-batch-label"><strong>{{ $release->batch->title ?: 'Distribution program' }}</strong>@if($release->batch->reference) · {{ $release->batch->reference }}@endif</p>
        @endif
        <details class="fp-record-details"><summary>View release and planting details</summary>
            <dl class="fp-details">
                <div><dt>Seed bags received</dt><dd>{{ $release->seed_bags !== null ? number_format($release->seed_bags) : 'Not recorded' }}</dd></div>
                <div><dt>Seed bag weight</dt><dd>{{ $release->seed_bag_kg !== null ? number_format((float) $release->seed_bag_kg, 2).' kg per bag' : 'Not recorded' }}</dd></div>
                <div><dt>Lot / series</dt><dd>{{ $release->lot_series ?: 'Not recorded' }}</dd></div>
                <div><dt>Planting period</dt><dd>{{ $release->batch?->planting_year ?: 'Year not recorded' }} · {{ \App\Models\HarvestRecord::SEASON_LABELS[$release->batch?->planting_season] ?? 'Season not recorded' }}</dd></div>
                <div><dt>Claimed area</dt><dd>{{ $release->claimed_area_ha !== null ? number_format((float) $release->claimed_area_ha, 2).' ha' : 'Not recorded' }}</dd></div>
                <div><dt>Registered rice area</dt><dd>{{ $release->registered_rice_area_ha !== null ? number_format((float) $release->registered_rice_area_ha, 2).' ha' : 'Not recorded' }}</dd></div>
                <div><dt>Variety planted</dt><dd>{{ $release->seed_variety_planted ?: 'Not recorded' }}</dd></div>
                <div><dt>Seed class</dt><dd>{{ $release->seed_class ?: 'Not recorded' }}</dd></div>
                <div><dt>Crop establishment</dt><dd>{{ $release->crop_establishment ?: 'Not recorded' }}</dd></div>
                <div><dt>Sowing date / period</dt><dd>{{ $release->date_of_sowing_label ?: 'Not recorded' }}</dd></div>
            </dl>
        </details>
    </article>
@empty
    <section class="fp-empty"><h2>{{ $releases->total() ? 'No releases on this page' : 'No assistance releases recorded yet' }}</h2><p>If you received assistance that is missing here, ask your agriculture office to check whether it is linked to your farmer record.</p></section>
@endforelse
@include('farmer_portal.pagination', ['paginator' => $releases, 'recordLabel' => 'releases'])
<p class="fp-small">Quantities keep their recorded units. An assistance release does not guarantee eligibility for future programs. View <a href="{{ route('farmer-portal.harvests') }}">My harvests</a> for recorded production.</p>
@endsection
