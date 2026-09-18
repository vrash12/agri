@extends('layouts.app')

@section('title', 'Record Harvest')

@push('styles')
  @include('partials.operations-ui-styles')
  @include('harvest_records._styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Production recording</div>
      <h1>Record a harvest</h1>
      <p>For any commodity. Rice harvested from an assistance release is recorded on that release instead.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('harvest-records.index') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>Back to harvests
      </a>
    </div>
  </header>

  <form method="POST" action="{{ route('harvest-records.store') }}">
    @csrf
    @include('harvest_records._form', ['buttonText' => 'Record harvest'])
  </form>
</div>
@endsection
