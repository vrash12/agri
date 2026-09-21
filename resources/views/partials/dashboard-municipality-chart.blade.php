<div class="ops-paged-chart module-chart-body" data-chart-pages="{{ $metricId }}" hidden>
  <div class="ops-chart-controls" data-chart-controls hidden>
    <label class="ops-chart-search" for="{{ $metricId }}Search">
      <span>Search municipality</span>
      <input type="search" id="{{ $metricId }}Search" data-chart-search autocomplete="off" aria-controls="{{ $metricId }}" placeholder="Municipality name">
    </label>
    <div class="ops-chart-pagination" role="group" aria-label="{{ $metric['title'] }} pages">
      <button type="button" class="ops-button ops-button-secondary" data-chart-previous aria-controls="{{ $metricId }}">Previous</button>
      <button type="button" class="ops-button ops-button-secondary" data-chart-next aria-controls="{{ $metricId }}">Next</button>
    </div>
    <p class="ops-chart-range" data-chart-range role="status" aria-live="polite" aria-atomic="true"></p>
  </div>
  <div class="ops-metric-canvas" data-chart-canvas>
    <canvas id="{{ $metricId }}" role="img" aria-label="{{ $metric['title'] }}"></canvas>
  </div>
</div>
