{{--
  One dashboard indicator: a heading, an optional chart, and the figures behind it.

  The figures table is always rendered and the canvas starts hidden. The shared
  report loader reveals canvases once Chart.js has loaded and re-hides them if it
  never arrives, so an office on a slow or filtered connection still reads every
  number — the chart is the enhancement, the table is the report.

  Expects:
    $metric      DashboardMetrics contract array
    $metricId    canvas element id (omit with $metricChart = false)
  Optional:
    $metricNote  one line under the heading explaining how to read it
    $metricLabel row-header name for the figures table (default "Category")
    $metricChart false to render figures only, for measures that must not share an axis
--}}
@php($metricHasData = collect($metric['series'] ?? [])
    ->contains(fn ($metricColumn) => collect($metricColumn['values'] ?? [])
        ->contains(fn ($metricValue) => (float) $metricValue !== 0.0)))
<article class="ops-metric">
  <div class="ops-metric-head">
    <h3>{{ $metric['title'] }}</h3>
    @isset($metricNote)
      <p>{{ $metricNote }}</p>
    @endisset
  </div>

  @if(($metricChart ?? true) && $metricHasData)
    <div class="ops-metric-canvas module-chart-body" hidden>
      <canvas id="{{ $metricId }}" role="img" aria-label="{{ $metric['title'] }}"></canvas>
    </div>
  @endif

  @unless($metricHasData)
    <p class="ops-metric-empty">Not recorded — nothing has been entered for this view yet.</p>
  @endunless

  <details class="ops-metric-figures">
    <summary>Figures</summary>
    @include('partials.metric-figures', [
      'metric' => $metric,
      'metricLabel' => $metricLabel ?? 'Category',
    ])
  </details>
</article>
