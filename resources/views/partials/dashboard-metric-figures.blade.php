<details class="ops-metric-figures">
  <summary>Figures <span>({{ count($metric['labels'] ?? []) }} {{ ($metricLabel ?? '') === 'Municipality' ? 'municipalities' : 'categories' }})</span></summary>
  <div class="ops-figure-scroll" role="region" tabindex="0" aria-label="{{ $metric['title'] }} — all figures">
    @include('partials.metric-figures', ['metric' => $metric, 'metricLabel' => $metricLabel ?? 'Category'])
  </div>
</details>
