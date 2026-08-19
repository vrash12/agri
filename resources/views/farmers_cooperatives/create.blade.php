@extends('layouts.app')

@section('title', 'New Farmers Cooperative')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Cooperative setup</div><h1>New farmers cooperative</h1><p>Create the organization profile first. You will assign farmers immediately after saving.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('farmers-cooperatives.index') }}">Back to directory</a></div>
  </header>
  <form method="POST" action="{{ route('farmers-cooperatives.store') }}">@csrf @include('farmers_cooperatives._form', ['record' => $record, 'buttonText' => 'Create and assign farmers'])</form>
</div>
@endsection
