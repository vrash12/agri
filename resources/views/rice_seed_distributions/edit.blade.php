@extends('layouts.app')

@section('title', 'Edit Seed or Farm Input Distribution')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Distribution record</div><h1>Edit seed or farm-input release</h1><p>Correct the recipient, item category, product, quantity, or applicable seed-monitoring information.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Back to register</a></div></header>
  <form method="POST" action="{{ route('rice-seed-distributions.update', $record) }}">@csrf @method('PUT') @include('rice_seed_distributions._form', ['record' => $record, 'buttonText' => 'Save changes', 'farmers' => $farmers, 'farmer_id' => $farmer_id ?? $record->farmer_id, 'seedVarietyClaimedOptions' => $seedVarietyClaimedOptions, 'cropEstablishmentOptions' => $cropEstablishmentOptions, 'seedClassOptions' => $seedClassOptions, 'inputCategoryOptions' => $inputCategoryOptions, 'inputSuggestions' => $inputSuggestions, 'quantityUnitOptions' => $quantityUnitOptions])</form>
</div>
@endsection
