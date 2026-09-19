@extends('farmer_portal.layout')
@section('title', 'My assistance')
@section('content')
<header class="fp-page-heading"><p class="fp-eyebrow">{{ $farmer->municipality?->name ?: 'Agriculture office' }}</p><h1>My assistance</h1><p>Recorded releases linked to your farmer record. Quantities retain their recorded units.</p></header>
@if($releases->isEmpty())
    <section class="fp-empty"><h2>No assistance releases recorded yet</h2><p>If you received assistance that is missing here, ask your agriculture office to check whether the release is linked to your farmer record.</p></section>
@else
    <div class="fp-panel fp-table-wrap"><table class="fp-table"><caption class="fp-sr-only">Assistance releases recorded by your agriculture office</caption><thead><tr><th scope="col">Date received</th><th scope="col">Assistance</th><th scope="col">Item / variety</th><th scope="col">Quantity</th></tr></thead><tbody>
        @foreach($releases as $release)
            <tr><td data-label="Date received">{{ $release->date_received?->format('M j, Y') ?: 'Not recorded' }}</td><td data-label="Assistance">{{ \App\Models\RiceSeedDistribution::INPUT_CATEGORY_LABELS[$release->input_category] ?? 'Type not recorded' }}</td><td data-label="Item / variety">{{ $release->seed_variety_claimed ?: 'Not recorded' }}</td><td data-label="Quantity">@if($release->kgs_received === null)Not recorded @else {{ number_format((float) $release->kgs_received, 2) }} {{ \App\Models\RiceSeedDistribution::QUANTITY_UNIT_LABELS[$release->quantity_unit] ?? '— unit not recorded' }} @endif</td></tr>
        @endforeach
    </tbody></table></div>
    @include('farmer_portal.pagination', ['paginator' => $releases, 'recordLabel' => 'releases'])
@endif
<p class="fp-small">A release in this list is a recorded transaction. It does not guarantee eligibility for future programs.</p>
@endsection
