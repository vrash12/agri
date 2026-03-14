{{-- resources/views/anti_rabies_vaccinations/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Add Anti-Rabies Vaccination')

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Add Anti-Rabies Vaccination</h1>
        <p class="p">Create a new vaccination record.</p>
      </div>
      <a class="btn btn-soft" href="{{ route('anti-rabies-vaccinations.index') }}">Back</a>
    </div>

    <div style="padding:16px;">
      <form method="POST" action="{{ route('anti-rabies-vaccinations.store') }}">
        @csrf

        @include('anti_rabies_vaccinations._form', ['record' => $record])

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:14px;">
          <button class="btn" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
@endsection