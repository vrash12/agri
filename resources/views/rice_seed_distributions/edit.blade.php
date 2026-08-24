@extends('layouts.app')

@section('title', 'Edit Agriculture or Fisheries Assistance')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Distribution record</div><h1>Edit agriculture or fisheries assistance</h1><p>Correct the beneficiary, assistance category, item/species, quantity, batch, or applicable crop-monitoring information.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index', ['assistance_sector' => $record->isFisheriesInput() ? 'fisheries' : 'agriculture']) }}">Back to register</a></div></header>
  <form method="POST" action="{{ route('rice-seed-distributions.update', $record) }}">@csrf @method('PUT') @include('rice_seed_distributions._form', ['record' => $record, 'buttonText' => 'Save changes', 'farmers' => $farmers, 'farmer_id' => $farmer_id ?? $record->farmer_id, 'seedVarietyClaimedOptions' => $seedVarietyClaimedOptions, 'cropEstablishmentOptions' => $cropEstablishmentOptions, 'seedClassOptions' => $seedClassOptions, 'inputCategoryOptions' => $inputCategoryOptions, 'inputSuggestions' => $inputSuggestions, 'quantityUnitOptions' => $quantityUnitOptions, 'preferredUnitsByCategory' => $preferredUnitsByCategory])</form>
</div>
@endsection
