@if ($paginator->hasPages())
  <nav class="agri-pagination" role="navigation" aria-label="Pagination">
    <div class="agri-pagination-simple">
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

      <span class="agri-page-position">Page <strong>{{ number_format($paginator->currentPage()) }}</strong></span>

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
