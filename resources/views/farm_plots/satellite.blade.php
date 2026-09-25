@extends('layouts.app')
@section('title', 'Satellite observations')
@push('styles')
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/parcel-satellite.css') }}?v={{ @filemtime(public_path('css/parcel-satellite.css')) ?: 1 }}">
@endpush
@section('content')
  @include('farm_plots._satellite_content', ['modal' => false])
@endsection
