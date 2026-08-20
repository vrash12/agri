@extends('layouts.app')

@section('title', 'Record Seed or Farm Input Distribution')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Agricultural assistance release</div><h1>Record seed or farm input</h1><p>Select a registered farmer, then record rice seed, another seed variety, fertilizer (abono), or another agricultural input.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Back to register</a></div></header>
  <form method="POST" action="{{ route('rice-seed-distributions.store') }}">@csrf @include('rice_seed_distributions._form', ['buttonText' => 'Save release', 'farmers' => $farmers, 'farmer_id' => $farmer_id ?? null, 'seedVarietyClaimedOptions' => $seedVarietyClaimedOptions, 'cropEstablishmentOptions' => $cropEstablishmentOptions, 'seedClassOptions' => $seedClassOptions, 'inputCategoryOptions' => $inputCategoryOptions, 'inputSuggestions' => $inputSuggestions, 'quantityUnitOptions' => $quantityUnitOptions])</form>
</div>
@endsection
