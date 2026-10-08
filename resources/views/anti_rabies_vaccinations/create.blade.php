@extends('layouts.app')

@section('title', 'Record Animal Health Service')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Municipal animal health</div><h1>Record animal-health services</h1><p>Enter the owner once, then add the animals or groups served.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Back to register</a></div>
  </header>
  <form method="POST" action="{{ route('anti-rabies-vaccinations.store') }}" id="animalServicesForm" data-lookup-url="{{ route('anti-rabies-vaccinations.owner-lookup') }}">@csrf @include('anti_rabies_vaccinations._batch_form')</form>
</div>
@endsection
