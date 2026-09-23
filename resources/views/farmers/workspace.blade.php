@extends('layouts.app')

@section('title', 'Farmers — Choose municipality')

@section('content')
@include('partials.operations-ui-styles')
<div class="module-page farmers-registry-page">
  @if(session('success'))<div class="module-alert" role="status">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error" role="alert">{{ session('error') }}</div>@endif
  <header class="module-header">
    <div><h1>Farmers</h1><p>Find farmer records and farm parcels by location.</p></div>
  </header>
  @include('farmers.partials.workspace-nav')
</div>
@endsection
