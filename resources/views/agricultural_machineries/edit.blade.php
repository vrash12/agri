@extends('layouts.app')

@section('title', 'Edit Machinery')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Asset record</div><h1>Edit {{ $record->asset_code }}</h1><p>Update assignment, condition, availability, utilization, acquisition details, and the next maintenance schedule.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('machinery-inventory.index') }}">Back to inventory</a></div>
  </header>
  <form method="POST" action="{{ route('machinery-inventory.update', $record) }}">@csrf @method('PUT') @include('agricultural_machineries._form', ['buttonText' => 'Save machinery record'])</form>
</div>
@endsection
