@extends('layouts.app')

@section('title', 'Add Admin')

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Add Admin</h1>
        <p class="p">Create a new admin account. Password is required.</p>
      </div>
    </div>

    <div style="padding:16px;">
      <form method="POST" action="{{ route('admins.store') }}">
        @csrf
        @include('admins._form', ['admin' => null, 'buttonText' => 'Create Admin'])
      </form>
    </div>
  </div>
@endsection
