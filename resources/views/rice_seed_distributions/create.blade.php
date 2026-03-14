@extends('layouts.app')

@section('title', 'Add Recipient')

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Add Recipient</h1>
        <p class="p">Fill in the details below. Fields with * are required.</p>
      </div>
    </div>

    <div style="padding:16px;">
      <form method="POST" action="{{ route('rice-seed-distributions.store') }}">
        @csrf
        @include('rice_seed_distributions._form', ['record' => null, 'buttonText' => 'Save Record'])
      </form>
    </div>
  </div>
@endsection
