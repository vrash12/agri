@extends('layouts.app')

@section('title', 'Edit Rice Seed Distribution')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Distribution record</div><h1>Edit seed release</h1><p>Correct recipient, release, claim, or production-monitoring information for this transaction.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Back to register</a></div></header>
  <form method="POST" action="{{ route('rice-seed-distributions.update', $record) }}">@csrf @method('PUT') @include('rice_seed_distributions._form', ['record' => $record, 'buttonText' => 'Save changes', 'farmers' => $farmers, 'farmer_id' => $farmer_id ?? $record->farmer_id, 'seedVarietyClaimedOptions' => $seedVarietyClaimedOptions, 'cropEstablishmentOptions' => $cropEstablishmentOptions, 'seedClassOptions' => $seedClassOptions])</form>
</div>
@endsection
