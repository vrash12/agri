@extends('layouts.app')

@section('title', 'Edit Recipient')

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Edit Recipient</h1>
        <p class="p">Update the information then click Update.</p>
      </div>
    </div>

    <div style="padding:16px;">
      <form method="POST" action="{{ route('rice-seed-distributions.update', $record) }}">
        @csrf
        @method('PUT')
        @include('rice_seed_distributions._form', ['record' => $record, 'buttonText' => 'Update Record'])
      </form>
    </div>
  </div>
@endsection
