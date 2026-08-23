@extends('layouts.app')

@section('title', 'Edit Farmer')

@section('content')
@include('partials.operations-ui-styles')
@php
  $fullName = trim(collect([$record->first_name, $record->middle_name, $record->last_name, $record->ext_name])->filter()->implode(' '));
@endphp
<div class="module-page">
  @include('farmers.partials.workspace-nav', ['workspaceMunicipality' => $record->municipality])

  <header class="module-header">
    <div>
      <div class="module-eyebrow">Farmer registry</div>
      <h1>Edit farmer profile</h1>
      <p>Update {{ $fullName ?: 'this farmer' }}'s registry, contact, and farm details. Existing distributions and plotted boundaries remain linked.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('farmers.id-card', $record) }}">View digital ID</a>
      <a class="module-button" href="{{ route('farmers.records', $record) }}">View history</a>
      <a class="module-button" href="{{ route('farmers.index') }}">
        <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        Back to registry
      </a>
    </div>
  </header>

  <form method="POST" action="{{ route('farmers.update', $record) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('farmers._form', ['record' => $record, 'buttonText' => 'Save changes'])
  </form>
</div>
@endsection
