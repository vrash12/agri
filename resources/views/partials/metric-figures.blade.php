{{--
  Figures table for a DashboardMetrics metric.

  The existing partials.operational-report-figures renders a single series. Dashboard
  metrics routinely carry more than one — releases against unique beneficiaries,
  services against animals covered — and those numbers only mean something side by
  side, so this renders one column per series.

  Expects $metric in the DashboardMetrics contract:
    ['title' => string, 'labels' => string[], 'series' => [...], 'not_recorded' => ...]

  Optional $metricLabel names the row header column (default "Category").
--}}
@php($figureSeries = $metric['series'] ?? [])
@php($figureLabels = $metric['labels'] ?? [])
<div class="module-table-scroll">
  <table class="module-table">
    <caption style="text-align:left;padding:12px 16px;color:var(--module-muted)">
      {{ $metric['title'] }}
      @if(count($figureSeries) === 1)— {{ $figureSeries[0]['name'] }}@endif
    </caption>
    <thead>
      <tr>
        <th scope="col">{{ $metricLabel ?? 'Category' }}</th>
        @foreach($figureSeries as $figureColumn)
          <th scope="col" class="module-numeric">{{ $figureColumn['name'] }} ({{ $figureColumn['unit'] }})</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($figureLabels as $figureIndex => $figureLabel)
        <tr>
          <th scope="row">{{ $figureLabel }}</th>
          @foreach($figureSeries as $figureColumn)
            <td class="module-numeric">{{ isset($figureColumn['values'][$figureIndex]) ? number_format((float) $figureColumn['values'][$figureIndex], $figureColumn['decimals'] ?? 0) : 'Not recorded' }}</td>
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($figureSeries) + 1 }}">Not recorded — no data has been entered for this view yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@if(! empty($metric['not_recorded']))
  {{-- Shown rather than folded into a zero bucket: the records exist, the grouping value does not. --}}
  <p class="module-hint">{{ $metric['not_recorded']['label'] }}: {{ number_format((int) $metric['not_recorded']['count']) }}. These records are excluded from the figures above.</p>
@endif
@if(! empty($metric['undated']))
  <p class="module-hint">{{ $metric['undated']['label'] }}: {{ number_format((int) $metric['undated']['count']) }}. No reporting year can be assigned.</p>
@endif
