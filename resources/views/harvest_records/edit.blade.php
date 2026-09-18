@extends('layouts.app')

@section('title', 'Edit Harvest')

@push('styles')
  @include('partials.operations-ui-styles')
  @include('harvest_records._styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Production recording</div>
      <h1>Edit harvest</h1>
      <p>{{ $record->commodityLabel() }} · {{ $record->periodLabel() }}</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('harvest-records.index') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>Back to harvests
      </a>
    </div>
  </header>

  <form method="POST" action="{{ route('harvest-records.update', $record) }}">
    @csrf
    @method('PUT')
    @include('harvest_records._form', ['buttonText' => 'Save harvest'])
  </form>
</div>
@endsection
