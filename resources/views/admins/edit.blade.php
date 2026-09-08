@extends('layouts.app')

@section('title', ($isMunicipalHeadManager ?? false) ? 'Edit Municipal Staff' : 'Edit User')

@section('content')
<div class="user-editor-page">
  <section class="user-editor-hero">
    <div>
      <span class="user-editor-eyebrow">
        {{ $manager->isSystemOwner() ? 'System Administration' : ($isMunicipalHeadManager ? 'Municipal Administration' : $manager->province?->name . ' Administration') }}
      </span>
      <h1>{{ $isMunicipalHeadManager ? 'Edit Municipal Staff' : 'Edit User Account' }}</h1>
      <p>{{ $isOwnAccount ? 'Update your profile details or password.' : "Update {$account->name}'s profile, access assignment, status, or password." }}</p>
    </div>
    <a class="module-button" href="{{ route('admins.index') }}">Back to users</a>
  </section>

  <form method="POST" action="{{ route('admins.update', $account) }}">
    @csrf
    @method('PUT')
    @include('admins._form')
  </form>
</div>
@endsection

@push('styles')
  @include('partials.operations-ui-styles')
  @include('admins.partials.styles')
@endpush
