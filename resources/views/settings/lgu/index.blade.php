@extends('layouts.app')
@section('title', 'LGU Settings')
@section('content')
@include('partials.operations-ui-styles')
@include('settings.lgu.partials.styles')
<div class="module-page lgu-set">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Settings</div>
      <h1>LGU profiles, websites &amp; IDs</h1>
      <p>Maintain each office profile shared by its public LGU website and farmer digital IDs.</p>
      <div class="module-scope-note"><span>Scope:</span><strong>{{ auth()->user()->scopeLabel() }}</strong></div>
    </div>
    <div class="module-header-actions"><a class="module-button" href="{{ route('lgu.index') }}" target="_blank" rel="noopener">@include('public-lgu.partials.icon', ['name' => 'external', 'size' => 18])Public LGU directory</a></div>
  </header>
  @unless($ready)<div class="module-alert lgu-set-alert-warning" role="status">LGU settings are awaiting the database update. Existing farmer cards remain available.</div>@endunless

  <section class="module-panel" aria-labelledby="lguDirectoryHeading">
    <div class="module-panel-head">
      <div><h2 id="lguDirectoryHeading">Municipalities &amp; cities</h2><p>Open an office profile to set its logo, contacts and ID signatory, or manage its public website.</p></div>
    </div>
    <form method="GET" class="lgu-set-search" role="search">
      <x-module.field name="q" label="Find an LGU or province">
        <input id="q" name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="e.g. Ramos or Tarlac" aria-describedby="q_error">
      </x-module.field>
      <div class="module-actions"><button class="module-button module-button-primary" type="submit">@include('public-lgu.partials.icon', ['name' => 'search', 'size' => 18])Search</button>@if(request()->filled('q'))<a class="module-button" href="{{ route('settings.lgu.index') }}">Clear search</a>@endif</div>
    </form>
    <div class="module-table-scroll">
      <table class="module-table lgu-set-table" aria-labelledby="lguDirectoryHeading">
        <thead><tr><th scope="col">Municipality / city</th><th scope="col">LGU profile</th><th scope="col">Public website</th><th scope="col"><span class="lgu-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @forelse($municipalities as $municipality)
          <tr>
            <td><div class="lgu-set-lgu"><strong>{{ $municipality->name }}</strong><span>{{ $municipality->supervisingProvince->name }}</span></div></td>
            <td>@if($ready && $municipality->profile)<span class="lgu-badge lgu-badge-green">Configured</span>@elseif($ready)<span class="lgu-badge">Not configured</span>@else<span class="lgu-badge">Awaiting setup</span>@endif</td>
            <td>
              @if($websiteReady && $municipality->website)
                @if($municipality->website->is_published)<span class="lgu-badge lgu-badge-green">Published</span>@else<span class="lgu-badge lgu-badge-amber">Unpublished</span>@endif
              @elseif($websiteReady)<span class="lgu-badge">Not configured</span>
              @else<span class="lgu-badge">Awaiting setup</span>@endif
            </td>
            <td>
              <div class="lgu-set-row-actions">
                @if($ready)<a class="module-button module-button-small" href="{{ route('settings.lgu.edit', $municipality) }}" aria-label="Open {{ $municipality->name }} profile">Open profile</a>@endif
                @if($websiteReady)<a class="module-button module-button-small" href="{{ route('settings.lgu.website.edit', $municipality) }}" aria-label="Manage {{ $municipality->name }} website">Manage website</a>@endif
                @if(!$ready && !$websiteReady)<span class="module-hint">Awaiting setup</span>@endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="4"><div class="module-empty"><strong>No active LGUs match your search in this scope.</strong>@if(request()->filled('q'))<p><a href="{{ route('settings.lgu.index') }}">Clear the search</a> to see every LGU you can access.</p>@endif</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @if($municipalities->total())
      <div class="module-panel-head" style="border-top:1px solid var(--ui-border);border-bottom:0"><p class="lgu-set-count">Showing {{ number_format($municipalities->firstItem()) }}–{{ number_format($municipalities->lastItem()) }} of {{ number_format($municipalities->total()) }} {{ $municipalities->total() === 1 ? 'LGU' : 'LGUs' }}</p></div>
    @endif
  </section>
  {{ $municipalities->links() }}
</div>
@endsection
