@extends('public-lgu.layout')
@section('title', 'Find your LGU')
@section('navigation')<a href="#directory">LGU directory</a><a href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('farmer-portal.login') }}">Farmer sign in</a>@endsection
@section('content')
<section class="lgu-directory-hero">
  <div class="lgu-container lgu-directory-intro">
    <div>
      <p class="lgu-eyebrow">Your community. Your local services.</p>
      <h1>One place to find<br>your agriculture office.</h1>
      <p class="lgu-lead">Connect with your municipality or city. Explore local services, read announcements, and find the right office to help.</p>
      <div class="lgu-actions"><a class="lgu-button" href="#directory">Find your LGU @include('public-lgu.partials.icon', ['name' => 'arrow-down', 'size' => 18])</a></div>
    </div>
    <aside class="lgu-directory-guide" aria-labelledby="guide-title">
      <span class="lgu-eyebrow" id="guide-title">Start here</span>
      <ol>
        <li><span aria-hidden="true">01</span><div><strong>Choose your LGU</strong><p>Search by municipality, city or province.</p></div></li>
        <li><span aria-hidden="true">02</span><div><strong>Explore local services</strong><p>Check information and office requirements.</p></div></li>
        <li><span aria-hidden="true">03</span><div><strong>Connect with your office</strong><p>Find contact details or sign in to AgriLGU.</p></div></li>
      </ol>
    </aside>
  </div>
</section>

<section class="lgu-section lgu-container" id="directory" aria-labelledby="directory-title">
  <div class="lgu-section-heading"><div><p class="lgu-eyebrow">Local government directory</p><h2 id="directory-title">Find your LGU</h2></div><p>Websites published by participating municipal and city agriculture offices.</p></div>
  <form class="lgu-search" method="GET" action="{{ route('lgu.index', [], false) }}#directory" role="search">
    <div>
      <label for="q">Municipality, city or province</label>
      <div class="lgu-search-field">@include('public-lgu.partials.icon', ['name' => 'search', 'size' => 18])<input id="q" type="search" name="q" maxlength="100" value="{{ $search }}" placeholder="e.g. Ramos or Tarlac" aria-describedby="search_error" @error('q') aria-invalid="true" @enderror></div>
      @error('q')<p id="search_error" role="alert">{{ $message }}</p>@enderror
    </div>
    <button class="lgu-button" type="submit">Search directory</button>
    @if($search)<a href="{{ route('lgu.index', [], false) }}#directory">Clear search</a>@endif
  </form>
  <p class="lgu-result-count" role="status">{{ number_format($sites->total()) }} {{ $sites->total() === 1 ? 'website' : 'websites' }}{{ $search ? ' matching “'.$search.'”' : ' available' }}</p>
  <div class="lgu-directory-grid">
    @forelse($sites as $site)
      <article class="lgu-directory-card">
        <div class="lgu-card-top">
          <span class="lgu-card-mark">@if($site->municipality->profile->logo_path)<img src="{{ route('lgu.media', [$site->slug, 'logo'], false) }}" alt="" loading="lazy" width="64" height="64">@else<x-brand compact />@endif</span>
          <span class="lgu-chip">{{ $site->municipality->supervisingProvince->name }}</span>
        </div>
        <h3>{{ $site->municipality->name }}</h3>
        <p>{{ $site->tagline ?: 'Local agriculture information, office contacts and services.' }}</p>
        <a class="lgu-link" href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::publicUrl($site) }}">Visit {{ $site->municipality->name }} @include('public-lgu.partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
      </article>
    @empty
      <div class="lgu-empty">
        @include('public-lgu.partials.icon', ['name' => $search ? 'search' : 'building'])
        <h3>{{ $search ? 'No LGU websites match your search.' : 'LGU websites are being prepared.' }}</h3>
        <p>{{ $search ? 'Try another name or clear your search.' : 'Participating offices will appear here when their websites are published.' }}</p>
        @if($search)<a class="lgu-link" href="{{ route('lgu.index', [], false) }}#directory">Clear search @include('public-lgu.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>@else<a class="lgu-link" href="{{ \App\Modules\LocalGovernment\Application\LguWebsiteAddress::centralUrl('welcome') }}">Explore AgriLGU services @include('public-lgu.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>@endif
      </div>
    @endforelse
  </div>
  @if($sites->hasPages())<nav class="lgu-pagination" aria-label="Directory pages">@if($sites->previousPageUrl())<a class="lgu-button lgu-button-outline lgu-button-sm" href="{{ $sites->previousPageUrl() }}#directory">Previous</a>@endif<span>Page {{ $sites->currentPage() }} of {{ $sites->lastPage() }}</span>@if($sites->nextPageUrl())<a class="lgu-button lgu-button-outline lgu-button-sm" href="{{ $sites->nextPageUrl() }}#directory">Next</a>@endif</nav>@endif
</section>
@endsection
