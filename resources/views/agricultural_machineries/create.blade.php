@extends('layouts.app')

@section('title', 'Register Machinery')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Asset registration</div><h1>Register machinery</h1><p>Add the equipment identity, responsible farmer or cooperative, operating status, value, and maintenance schedule.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('machinery-inventory.index') }}">Back to inventory</a></div>
  </header>
  <form method="POST" action="{{ route('machinery-inventory.store') }}">@csrf @include('agricultural_machineries._form', ['buttonText' => 'Register machinery'])</form>
</div>
@endsection
