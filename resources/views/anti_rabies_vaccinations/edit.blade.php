@extends('layouts.app')

@section('title', 'Edit Animal Health Service')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Animal-health record</div><h1>Edit {{ $record->pet_name ?: $record->animalTypeLabel() }}</h1><p>Correct the owner, animal coverage, product, dosage, treatment, or follow-up information.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Back to register</a></div>
  </header>
  <form method="POST" action="{{ route('anti-rabies-vaccinations.update', $record) }}">@csrf @method('PUT') @include('anti_rabies_vaccinations._form', ['record' => $record, 'buttonText' => 'Save changes'])</form>
</div>
@endsection
