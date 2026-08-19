@extends('layouts.app')

@section('title', 'Record Anti-Rabies Vaccination')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Animal health service</div><h1>Record vaccination</h1><p>Find the owner, reuse an existing animal when possible, and record today’s anti-rabies service.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Back to register</a></div>
  </header>
  <form method="POST" action="{{ route('anti-rabies-vaccinations.store') }}">@csrf @include('anti_rabies_vaccinations._form', ['record' => $record, 'buttonText' => 'Save vaccination'])</form>
</div>
@endsection
