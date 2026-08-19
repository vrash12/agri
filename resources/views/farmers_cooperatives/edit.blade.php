@extends('layouts.app')

@section('title', 'Edit Farmers Cooperative')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Cooperative profile</div><h1>Edit {{ $record->name }}</h1><p>Keep the organization, contact, and office information accurate for membership reports.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('farmers-cooperatives.assign-farmers', $record) }}">Manage members</a><a class="module-button" href="{{ route('farmers-cooperatives.index') }}">Back to directory</a></div>
  </header>
  <form method="POST" action="{{ route('farmers-cooperatives.update', $record) }}">@csrf @method('PUT') @include('farmers_cooperatives._form', ['record' => $record, 'buttonText' => 'Save changes'])</form>
</div>
@endsection
