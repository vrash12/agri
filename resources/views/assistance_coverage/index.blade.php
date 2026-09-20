@extends('layouts.app')

@section('title', 'Assistance coverage')

@push('styles')
    @include('partials.operations-ui-styles')
    <link rel="stylesheet" href="{{ asset('css/assistance-coverage.css') }}?v={{ filemtime(public_path('css/assistance-coverage.css')) }}">
@endpush

@section('content')
<div class="module-page coverage-page">
    <header class="module-header">
        <div>
            <div class="module-eyebrow">Agriculture &amp; fisheries planning</div>
            <h1>Assistance coverage</h1>
            <p>See where assistance releases were recorded, by municipality.</p>
            <p class="module-scope-note">Viewing: <strong>{{ $provinceName }}</strong>@if($filters['municipality_id']) · {{ $municipalities->firstWhere('id', (int) $filters['municipality_id'])?->name }}@endif</p>
        </div>
        <div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Assistance register</a></div>
    </header>

    @if($provinces->count() > 1)
        <form class="coverage-scope" method="GET" action="{{ route('assistance-coverage.index') }}">
            <x-module.field name="province_id" label="Province / independent city scope">
                <select class="module-input" name="province_id" id="province_id">
                    @foreach($provinces as $province)<option value="{{ $province->id }}" @selected($filters['province_id'] === $province->id)>{{ $province->name }}</option>@endforeach
                </select>
            </x-module.field>
            <button class="module-button" type="submit">Switch scope</button>
        </form>
    @endif

    <section class="module-panel" aria-labelledby="coverage-filters-title">
        <div class="module-panel-head"><div><h2 id="coverage-filters-title">Program and reporting period</h2><p>Filters apply to the map and the municipality figures below.</p></div></div>
        <form method="GET" action="{{ route('assistance-coverage.index') }}" class="coverage-filters">
            <input type="hidden" name="province_id" value="{{ $filters['province_id'] ?: '' }}">
            <div class="coverage-filter-grid">
                <x-module.field name="municipality_id" label="Municipality">
                    <select class="module-input" name="municipality_id" id="municipality_id">
                        <option value="">All authorized municipalities</option>
                        @foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected((int) old('municipality_id', $filters['municipality_id']) === $municipality->id)>{{ $municipality->name }}</option>@endforeach
                    </select>
                </x-module.field>
                <x-module.field name="category" label="Assistance type">
                    <select class="module-input" name="category" id="category">
                        <option value="">All assistance types</option>
                        @foreach($categoryOptions as $value => $label)<option value="{{ $value }}" @selected(old('category', $filters['category']) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </x-module.field>
                <x-module.field name="reference" label="Program / sheet reference" hint="Use the recorded rice-sheet reference. Leave blank for all releases, including those without a sheet.">
                    <input class="module-input" name="reference" id="reference" maxlength="120" list="coverage-references" value="{{ old('reference', $filters['reference']) }}" placeholder="Exact reference, if recorded" aria-describedby="reference_hint reference_error">
                    <datalist id="coverage-references">@foreach($references as $reference)<option value="{{ $reference }}"></option>@endforeach</datalist>
                </x-module.field>
                <x-module.field name="season" label="Planting season">
                    <select class="module-input" name="season" id="season">
                        @foreach(['all' => 'All seasons', 'dry' => 'Dry season', 'wet' => 'Wet season', 'unrecorded' => 'Not recorded'] as $value => $label)<option value="{{ $value }}" @selected(old('season', $filters['season']) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </x-module.field>
                <x-module.field name="year" label="Planting year" hint="Required for Dry or Wet. Blank means all years; Not recorded ignores the year.">
                    <input class="module-input" type="number" min="1990" max="{{ now()->year + 1 }}" step="1" name="year" id="year" value="{{ old('year', $filters['year']) }}" placeholder="All years" aria-describedby="year_hint year_error">
                </x-module.field>
                <div class="coverage-filter-actions"><button class="module-button module-button-primary" type="submit">Apply filters</button><a class="module-button" href="{{ route('assistance-coverage.index', ['province_id' => $filters['province_id'] ?: null]) }}">Reset</a></div>
            </div>
            <p class="coverage-note">Program references and planting periods currently come from rice distribution sheets. Other releases remain visible under All seasons / All years or Not recorded. Receipt dates and harvest seasons are not used to guess planting periods.</p>
            @if($references_truncated)<p class="coverage-note">Showing 200 reference suggestions. You can still enter any exact reference recorded in this scope.</p>@endif
        </form>
    </section>

    @if($truncated)<div class="module-alert" role="status">Showing the first 250 municipalities in this scope. The map and totals cover only these municipalities.</div>@endif
    <p class="coverage-period"><strong>Applied view:</strong> {{ $categoryOptions[$filters['category']] ?? 'All assistance types' }} · {{ $filters['reference'] !== '' ? $filters['reference'] : 'All program references' }} · {{ ['all' => 'All seasons', 'dry' => 'Dry season', 'wet' => 'Wet season', 'unrecorded' => 'Planting period not recorded'][$filters['season']] }} · {{ $filters['year'] ?: 'All years' }}</p>
    <section class="module-kpis coverage-kpis" aria-label="Filtered assistance totals">
        <article class="module-kpi"><span class="module-kpi-label">Recorded releases</span><strong>{{ number_format($summary['releases']) }}</strong><small>Release transactions in this view</small></article>
        <article class="module-kpi"><span class="module-kpi-label">Linked beneficiaries</span><strong>{{ number_format($summary['beneficiaries']) }}</strong><small>Distinct farmer records within their owning municipality</small></article>
        <article class="module-kpi"><span class="module-kpi-label">Municipalities with releases</span><strong>{{ number_format($summary['municipalities_with_releases']) }} / {{ count($rows) }}</strong><small>Recorded activity, not an eligibility or population coverage rate</small></article>
    </section>

    <section class="module-panel coverage-map-panel" aria-labelledby="coverage-map-title">
        <div class="module-panel-head"><div><h2 id="coverage-map-title">Recorded assistance by municipality</h2><p>Darker areas have more matching releases. Gray means no matching releases in these records.</p></div><button type="button" class="module-button module-button-primary" data-load-coverage hidden>Show map</button></div>
        <div class="coverage-legend" aria-label="Map legend"><span><i class="coverage-swatch coverage-zero"></i>No matching releases</span><span><i class="coverage-swatch coverage-low"></i>1–9</span><span><i class="coverage-swatch coverage-medium"></i>10–49</span><span><i class="coverage-swatch coverage-high"></i>50–199</span><span><i class="coverage-swatch coverage-highest"></i>200+ releases</span></div>
        <p class="coverage-map-status" data-coverage-status role="status" aria-live="polite">The table below is available immediately. Open the map to load municipality boundaries.</p>
        <div id="assistance-coverage-map" class="coverage-map" role="region" aria-label="Assistance coverage map" hidden></div>
        <noscript><p class="coverage-note">Enable JavaScript to use the map. All municipality totals are listed below.</p></noscript>
        <p class="coverage-note">{{ number_format($summary['without_boundary']) }} municipality/city area(s) have no single active boundary and appear only in the table. Boundaries are planning references. Releases are assigned to their owning municipality; individual parcels are not colored because releases have no explicit parcel link.</p>
    </section>

    <section class="module-panel" aria-labelledby="coverage-table-title">
        <div class="module-panel-head"><div><h2 id="coverage-table-title">Municipality figures</h2><p>Quantities remain separate by unit. One farmer receiving several releases counts once as a linked beneficiary.</p></div></div>
        <div class="module-table-wrap">
            <table class="module-table coverage-table"><thead><tr><th scope="col">Municipality</th><th scope="col">Releases</th><th scope="col">Linked beneficiaries</th><th scope="col">Recorded quantities</th><th scope="col">Map</th></tr></thead><tbody>
                @forelse($rows as $row)
                    <tr>
                        <th scope="row">{{ $row['name'] }}</th>
                        <td data-label="Releases">{{ number_format($row['releases']) }}</td>
                        <td data-label="Linked beneficiaries">
                            {{ number_format($row['beneficiaries']) }}
                            @if($row['unlinked'])<small class="coverage-cell-note">{{ $row['unlinked'] }} release(s) without a valid farmer link</small>@endif
                        </td>
                        <td data-label="Recorded quantities">
                            @forelse($row['quantities'] as $quantity)
                                <span class="coverage-quantity">{{ number_format($quantity['quantity'], 2) }} {{ $quantity['unit'] }}</span>
                            @empty<span>{{ $row['releases'] ? 'Not recorded' : '—' }}</span>@endforelse
                        </td>
                        <td data-label="Map">
                            @if($row['boundary'] === 'available')
                                <span class="coverage-cell-note">Boundary available</span>
                                <button type="button" class="module-button" data-focus-municipality="{{ $row['id'] }}" hidden>View area</button>
                            @else{{ $row['boundary'] === 'ambiguous' ? 'Boundary needs review' : 'No active boundary' }}@endif
                        </td>
                    </tr>
                @empty<tr><td colspan="5">No active municipalities are available in this scope.</td></tr>@endforelse
            </tbody></table>
        </div>
        @if(! $summary['releases'])<p class="coverage-note">No releases match the applied filters. This does not establish that farmers in these areas received no assistance elsewhere.</p>@endif
    </section>

    <details class="module-more coverage-quality"><summary>Understand these figures and missing information</summary><div>
        <p><strong>{{ number_format($summary['unclassified']) }}</strong> release(s) across all years for the selected area, assistance type, and program have no usable planting period. @if($filters['year'] || in_array($filters['season'], ['dry', 'wet'], true))These are excluded from the selected period.@else These remain in All seasons / All years and Not recorded.@endif</p>
        <p>In this filtered view, <strong>{{ number_format($summary['unlinked']) }}</strong> release(s) lack a valid farmer link in the same municipality. They count as releases but not linked beneficiaries.</p>
        <p><strong>{{ number_format($summary['incomplete_quantities']) }}</strong> release(s) in this view have an incomplete or unsupported quantity/unit. They count as releases but are excluded from quantity totals. A recorded zero quantity remains zero.</p>
        <p>Map colors count release transactions, not hectares, eligible farmers, or production. Program matching uses the sheet reference as recorded; differently named sheets are not automatically combined. Reports reflect current saved records when this page is loaded.</p>
    </div></details>
</div>
@endsection

@push('scripts')
<script>
window.assistanceCoverageSettings = {
    key: @json($googleMapsKey),
    boundariesUrl: @json(route('assistance-coverage.boundaries')),
    rows: @json($rows)
};
</script>
<script src="{{ asset('js/assistance-coverage.js') }}?v={{ filemtime(public_path('js/assistance-coverage.js')) }}" defer></script>
@endpush
