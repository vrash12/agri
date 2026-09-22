@extends('layouts.app')

@section('title', 'Farmers — Choose workspace')

@section('content')
@include('partials.operations-ui-styles')
<div class="module-page farmers-registry-page">
  @if(session('success'))<div class="module-alert" role="status">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="module-alert module-alert-error" role="alert">{{ session('error') }}</div>@endif
  <header class="module-header">
    <div><div class="module-eyebrow">Registry and land management</div><h1>Farmers</h1><p>Open one municipality to work with its farmer records and mapped parcels.</p></div>
  </header>
  @include('farmers.partials.workspace-nav')
</div>
@endsection
