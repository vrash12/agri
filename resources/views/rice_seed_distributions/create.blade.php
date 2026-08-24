@extends('layouts.app')

@section('title', 'Record Agriculture or Fisheries Assistance')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Municipal assistance release</div><h1>Record agriculture or fisheries assistance</h1><p>Select a registered beneficiary, then record crop inputs, tilapia/hito fingerlings, fish feed, fishing gear, or another assistance item.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index', ['assistance_sector' => request('assistance_sector')]) }}">Back to register</a></div></header>
  <form method="POST" action="{{ route('rice-seed-distributions.store') }}">@csrf @include('rice_seed_distributions._form', ['buttonText' => 'Save release', 'farmers' => $farmers, 'farmer_id' => $farmer_id ?? null, 'seedVarietyClaimedOptions' => $seedVarietyClaimedOptions, 'cropEstablishmentOptions' => $cropEstablishmentOptions, 'seedClassOptions' => $seedClassOptions, 'inputCategoryOptions' => $inputCategoryOptions, 'inputSuggestions' => $inputSuggestions, 'quantityUnitOptions' => $quantityUnitOptions, 'preferredUnitsByCategory' => $preferredUnitsByCategory, 'defaultInputCategory' => $defaultInputCategory])</form>
</div>
@endsection
