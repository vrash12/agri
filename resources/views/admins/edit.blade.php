@extends('layouts.app')

@section('title', ($isMunicipalHeadManager ?? false) ? 'Edit Municipal Staff' : 'Edit User')

@section('content')
<div class="user-editor-page">
  <section class="user-editor-hero">
    <div>
      <span class="user-editor-eyebrow">
        {{ $isMunicipalHeadManager ? 'Municipal Administration' : 'Provincial Administration' }}
      </span>
      <h1>{{ $isMunicipalHeadManager ? 'Edit Municipal Staff' : 'Edit User Account' }}</h1>
      <p>Update {{ $account->name }}'s profile, access assignment, status, or password.</p>
    </div>
    <a class="btn btn-soft" href="{{ route('admins.index') }}">Back to Users</a>
  </section>

  <form method="POST" action="{{ route('admins.update', $account) }}">
    @csrf
    @method('PUT')
    @include('admins._form')
  </form>
</div>
@endsection

@push('styles')
<style>
  .user-editor-page{display:flex;flex-direction:column;gap:16px;max-width:1050px;margin:0 auto;}
  .user-editor-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;padding:22px;border:1px solid var(--border);border-radius:22px;background:linear-gradient(145deg,#052e16,#166534);color:#fff;box-shadow:0 18px 40px rgba(15,23,42,.1);}
  .user-editor-eyebrow{display:inline-flex;padding:6px 10px;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.1);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.45px;}
  .user-editor-hero h1{margin:12px 0 6px;font-size:28px;font-weight:900;}
  .user-editor-hero p{max-width:710px;margin:0;color:rgba(255,255,255,.78);font-size:13px;line-height:1.55;}
  @media(max-width:700px){.user-editor-hero{flex-direction:column}.user-editor-hero .btn{width:100%;}}
</style>
@endpush
