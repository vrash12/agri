@extends('layouts.app')
@section('title', 'Set your evaluation password')
@section('content')
@include('partials.operations-ui-styles')
<section class="module-panel">
  <h1>Set your evaluation password</h1>
  <p>Replace your temporary password before opening the boundary map. Use at least 15 characters.</p>
  <form method="POST" action="{{ route('evaluation.password.update') }}">
    @csrf
    <div class="module-field"><label for="current_password">Current password</label><input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
    <div class="module-field"><label for="password">New password</label><input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="15" maxlength="72" required></div>
    <div class="module-field"><label for="password_confirmation">Confirm new password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="15" maxlength="72" required></div>
    <button class="btn" type="submit">Save password</button>
  </form>
</section>
@endsection
