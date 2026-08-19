@php
  /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
  $label = $label ?? 'result';
  $total = (int) $paginator->total();
  $resultLabel = $total === 1 ? Str::singular($label) : Str::plural($label);

  if (!empty($fragment)) {
      $paginator->fragment($fragment);
  }
@endphp

@if($total > 0)
  <div class="module-pagination app-list-footer" aria-label="Results pagination">
    <div class="app-list-range">
      <span class="app-list-range-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13"></path><circle cx="3.5" cy="6" r=".5"></circle><circle cx="3.5" cy="12" r=".5"></circle><circle cx="3.5" cy="18" r=".5"></circle></svg>
      </span>
      <span>
        Showing <strong>{{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}</strong>
        of <strong>{{ number_format($total) }}</strong> {{ $resultLabel }}
      </span>
    </div>

    @if($paginator->hasPages())
      {{ $paginator->links() }}
    @else
      <span class="app-list-complete">
        <i aria-hidden="true"></i>
        All results shown
      </span>
    @endif
  </div>
@endif
