@extends('layouts.app')

@section('title', 'New Rice Seed Distribution Sheet')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page rice-sheet-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Assistance distribution</div>
      <h1>New rice seed distribution sheet</h1>
      <p>Record the information shared by every farmer on the sheet once. Releases are added afterwards.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('rice-distribution-batches.index') }}">Back to sheets</a>
    </div>
  </header>

  @include('partials.form-feedback')

  @include('rice_seed_distributions.batches._form', [
    'seasonOptions' => $seasonOptions,
    'municipalities' => $municipalities,
    'canChooseMunicipality' => $canChooseMunicipality,
    'selectedMunicipalityId' => $selectedMunicipalityId,
  ])
</div>
@endsection
