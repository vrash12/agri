@extends('layouts.app')

@section('title', 'Edit Admin')

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Edit Admin</h1>
        <p class="p">Update admin info. Leave password blank to keep current password.</p>
      </div>
    </div>

    <div style="padding:16px;">
      <form method="POST" action="{{ route('admins.update', $admin) }}">
        @csrf
        @method('PUT')
        @include('admins._form', ['admin' => $admin, 'buttonText' => 'Update Admin'])
      </form>
    </div>
  </div>
@endsection
