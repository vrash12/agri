@extends('layouts.app')

@section('title', 'Record Rice Seed Distribution')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Rice program release</div><h1>Record seed distribution</h1><p>Select a registered farmer and capture the seed release. Farmer identity and farm information are copied automatically.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Back to register</a></div></header>
  <form method="POST" action="{{ route('rice-seed-distributions.store') }}">@csrf @include('rice_seed_distributions._form', ['buttonText' => 'Save release', 'farmers' => $farmers, 'farmer_id' => $farmer_id ?? null, 'seedVarietyClaimedOptions' => $seedVarietyClaimedOptions, 'cropEstablishmentOptions' => $cropEstablishmentOptions, 'seedClassOptions' => $seedClassOptions])</form>
</div>
@endsection
