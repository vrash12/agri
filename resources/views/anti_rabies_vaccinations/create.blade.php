@extends('layouts.app')

@section('title', 'Record Animal Health Service')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Municipal animal health</div><h1>Record animal-health service</h1><p>Record vaccination, deworming, vitamins, or treatment for an individual animal or livestock group.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Back to register</a></div>
  </header>
  <form method="POST" action="{{ route('anti-rabies-vaccinations.store') }}">@csrf @include('anti_rabies_vaccinations._form', ['record' => $record, 'buttonText' => 'Save service'])</form>
</div>
@endsection
