@extends('layouts.app')

@section('title', 'Add Farmer')

@section('content')
@include('partials.operations-ui-styles')
<div class="module-page">

  <header class="module-header">
    <div>
      <div class="module-eyebrow">Farmer registry</div>
      <h1>Add farmer profile</h1>
      <p>Enter the farmer's details. Add assistance and parcels after saving.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('farmers.index') }}">
        <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        Back to registry
      </a>
    </div>
  </header>

  <form method="POST" action="{{ route('farmers.store') }}" enctype="multipart/form-data">
    @csrf
    @include('farmers._form', ['record' => $record, 'buttonText' => 'Create farmer'])
  </form>
</div>
@endsection
