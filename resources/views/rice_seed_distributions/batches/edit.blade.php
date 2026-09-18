@extends('layouts.app')

@section('title', 'Edit Rice Seed Distribution Sheet')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page rice-sheet-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Assistance distribution</div>
      <h1>Edit sheet</h1>
      <p>{{ $batch->reference }} &middot; {{ $batch->plantingSeasonHeading() ?: 'Planting season not set' }}</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('rice-distribution-batches.sheet', $batch) }}">Open sheet</a>
      <a class="module-button" href="{{ route('rice-distribution-batches.index') }}">Back to sheets</a>
    </div>
  </header>

  @include('partials.form-feedback')

  {{-- The office cannot move to another municipality once releases are attached,
       so the field is not offered on an existing sheet. --}}
  @include('rice_seed_distributions.batches._form', [
    'batch' => $batch,
    'seasonOptions' => $seasonOptions,
    'municipalities' => $municipalities,
    'canChooseMunicipality' => $canChooseMunicipality,
    'selectedMunicipalityId' => $selectedMunicipalityId,
  ])
</div>
@endsection
