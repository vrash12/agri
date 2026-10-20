@extends('layouts.app')
@section('title', 'LGU Settings')
@section('content')
@include('partials.operations-ui-styles')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Settings</div><h1>LGU profiles, websites &amp; IDs</h1><p>Maintain the office profile shared by each LGU's public website and farmer digital IDs. Scope: {{ auth()->user()->scopeLabel() }}.</p></div><a class="module-button" href="{{ route('lgu.index') }}">Public LGU directory</a></header>
  @unless($ready)<div class="module-alert" role="status">LGU settings are awaiting the database update. Existing farmer cards remain available.</div>@endunless
  <form method="GET" class="module-panel" style="padding:20px;margin-bottom:20px">
    <div class="module-form-grid">
      <x-module.field name="q" label="Find an LGU or province"><input id="q" name="q" value="{{ request('q') }}" maxlength="100" aria-describedby="q_error"></x-module.field>
      <div class="module-form-field module-actions" style="align-self:end"><button class="module-button module-button-primary">Search</button><a class="module-button" href="{{ route('settings.lgu.index') }}">Clear</a></div>
    </div>
  </form>
  <div class="module-panel module-table-scroll">
    <table class="module-table"><thead><tr><th>Municipality / city</th><th>Supervising province / city scope</th><th>LGU profile</th><th>Public website</th><th>Action</th></tr></thead><tbody>
    @forelse($municipalities as $municipality)
      <tr><td>{{ $municipality->name }}</td><td>{{ $municipality->supervisingProvince->name }}</td><td>{{ $ready && $municipality->profile ? 'Configured' : 'Not configured' }}</td><td>@if($websiteReady && $municipality->website){{ $municipality->website->is_published ? 'Published' : 'Unpublished' }}@elseif($websiteReady)Not configured @else Awaiting setup @endif</td><td><div class="module-actions">@if($ready)<a class="module-button" href="{{ route('settings.lgu.edit', $municipality) }}">Open profile</a>@endif @if($websiteReady)<a class="module-button" href="{{ route('settings.lgu.website.edit', $municipality) }}">Manage website</a>@endif @if(!$ready && !$websiteReady)Awaiting setup @endif</div></td></tr>
    @empty
      <tr><td colspan="5">No active LGUs match your search in this scope.</td></tr>
    @endforelse
    </tbody></table>
  </div>
  {{ $municipalities->links() }}
</div>
@endsection
