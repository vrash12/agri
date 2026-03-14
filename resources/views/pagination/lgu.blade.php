@if ($paginator->hasPages())
  <nav role="navigation" aria-label="Pagination Navigation"
       style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding-top:10px;">

    {{-- Previous Page Link --}}
    @if ($paginator->onFirstPage())
      <span class="btn btn-soft" style="opacity:.55; pointer-events:none;">« Previous</span>
    @else
      <a class="btn btn-soft" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Previous</a>
    @endif

    {{-- Pagination Elements --}}
    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
      @foreach ($elements as $element)
        {{-- "Three Dots" Separator --}}
        @if (is_string($element))
          <span style="padding:8px 10px; color:var(--muted); font-weight:900;">{{ $element }}</span>
        @endif

        {{-- Array Of Links --}}
        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="btn"
                    style="cursor:default; border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.18);">
                {{ $page }}
              </span>
            @else
              <a class="btn btn-soft" href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach
    </div>

    {{-- Next Page Link --}}
    @if ($paginator->hasMorePages())
      <a class="btn btn-soft" href="{{ $paginator->nextPageUrl() }}" rel="next">Next »</a>
    @else
      <span class="btn btn-soft" style="opacity:.55; pointer-events:none;">Next »</span>
    @endif
  </nav>
@endif
