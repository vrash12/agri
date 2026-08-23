@extends('layouts.app')

@section('title', 'Add Farmer')

@section('content')
@include('partials.operations-ui-styles')
<div class="module-page">
  @include('farmers.partials.workspace-nav')

  <header class="module-header">
    <div>
      <div class="module-eyebrow">Farmer registry</div>
      <h1>Add farmer profile</h1>
      <p>Create the farmer's core registry record first. Distribution history and mapped farm boundaries can be added after the profile is saved.</p>
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
