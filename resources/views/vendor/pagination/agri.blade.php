@if ($paginator->hasPages())
  @php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $visiblePages = collect([
        1,
        $currentPage - 1,
        $currentPage,
        $currentPage + 1,
        $lastPage,
    ])->filter(fn ($page) => $page >= 1 && $page <= $lastPage)
      ->unique()
      ->sort()
      ->values();
    $previousVisiblePage = null;
  @endphp

  <nav class="agri-pagination" role="navigation" aria-label="Pagination">
    <div class="agri-pagination-mobile">
      @if ($paginator->onFirstPage())
        <span class="agri-page-control is-disabled" aria-disabled="true" aria-label="Previous page">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
        </span>
      @else
        <a class="agri-page-control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
        </a>
      @endif

      <span class="agri-page-position">
        Page <strong>{{ number_format($currentPage) }}</strong> of {{ number_format($lastPage) }}
      </span>

      @if ($paginator->hasMorePages())
        <a class="agri-page-control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        </a>
      @else
        <span class="agri-page-control is-disabled" aria-disabled="true" aria-label="Next page">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        </span>
      @endif
    </div>

    <div class="agri-pagination-desktop">
      @if ($paginator->onFirstPage())
        <span class="agri-page-control agri-page-control-wide is-disabled" aria-disabled="true">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
          <span>Previous</span>
        </span>
      @else
        <a class="agri-page-control agri-page-control-wide" href="{{ $paginator->previousPageUrl() }}" rel="prev">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
          <span>Previous</span>
        </a>
      @endif

      <div class="agri-page-list" aria-label="Page numbers">
        @foreach($visiblePages as $page)
          @if($previousVisiblePage !== null && $page - $previousVisiblePage > 1)
            <span class="agri-page-ellipsis" aria-hidden="true">…</span>
          @endif

          @if($page === $currentPage)
            <span class="agri-page-link is-current" aria-current="page" aria-label="Current page, page {{ $page }}">{{ $page }}</span>
          @else
            <a class="agri-page-link" href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
          @endif

          @php($previousVisiblePage = $page)
        @endforeach
      </div>

      @if ($paginator->hasMorePages())
        <a class="agri-page-control agri-page-control-wide" href="{{ $paginator->nextPageUrl() }}" rel="next">
          <span>Next</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        </a>
      @else
        <span class="agri-page-control agri-page-control-wide is-disabled" aria-disabled="true">
          <span>Next</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        </span>
      @endif
    </div>
  </nav>
@endif
