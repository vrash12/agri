@extends('layouts.app')

@section('title', ($isMunicipalHeadManager ?? false) ? 'Create Municipal Staff' : 'Create User')

@section('content')
<div class="user-editor-page">
  <section class="user-editor-hero">
    <div>
      <span class="user-editor-eyebrow">
        {{ $manager->isSystemOwner() ? 'System Administration' : ($isMunicipalHeadManager ? 'Municipal Administration' : $manager->scopeLabel() . ' Administration') }}
      </span>
      <h1>{{ $isMunicipalHeadManager ? 'Create Municipal Staff' : 'Create User Account' }}</h1>
      <p>
        {{ $isMunicipalHeadManager
            ? 'Add a municipal-staff account for your municipality.'
            : ($manager->isSystemOwner() ? 'Add a regional head, provincial head or staff account and assign its office scope.' : 'Add permitted accounts within your assigned area and select their office scope.') }}
      </p>
    </div>
    <a class="module-button" href="{{ route('admins.index') }}">Back to users</a>
  </section>

  <form method="POST" action="{{ route('admins.store') }}">
    @csrf
    @include('admins._form')
  </form>
</div>
@endsection

@push('styles')
  @include('partials.operations-ui-styles')
  @include('admins.partials.styles')
@endpush
