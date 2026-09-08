<div class="module-table-scroll">
  <table class="module-table">
    <caption style="text-align:left;padding:12px 16px;color:var(--module-muted)">{{ $reportTitle }} — {{ $reportUnit }}</caption>
    <thead><tr><th scope="col">{{ $reportLabel ?? 'Category' }}</th><th scope="col" class="module-numeric">{{ $reportUnit }}</th></tr></thead>
    <tbody>
      @forelse($reportLabels as $index => $label)
        <tr><th scope="row">{{ $label }}</th><td class="module-numeric">{{ number_format((float) ($reportValues[$index] ?? 0), $reportDecimals ?? 0) }}</td></tr>
      @empty
        <tr><td colspan="2">No figures for the current filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
