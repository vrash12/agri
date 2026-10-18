@php($metrics = $providerUsage['metrics'] ?? ['enabled' => false, 'periods' => null])
<div class="ops-provider-card ops-application-usage" aria-labelledby="applicationUsageHeading">
  <h3 id="applicationUsageHeading">Application efficiency</h3>
  <p class="ops-provider-help">Best-effort aggregates since tracking began. Response times measure server work, not the browser's complete loading time.</p>
  @if(!$metrics['enabled'])
    <p class="ops-provider-notice">Local efficiency tracking is disabled.</p>
  @elseif($metrics['periods'] === null)
    <p class="ops-provider-notice" role="status">Efficiency metrics are temporarily unavailable.</p>
  @else
    @foreach(['day' => 'Today · UTC', 'month' => 'This month · UTC'] as $period => $label)
      @php($counts = $metrics['periods'][$period])
      <details @if($period === 'day') open @endif>
        <summary>{{ $label }}</summary>
        <p class="ops-provider-help">{{ isset($counts['started_at']) ? 'Tracking since '.$counts['started_at'] : 'No completed tracking batches yet.' }} Current requests appear after completion.</p>
        <div class="module-table-scroll"><table class="module-table">
          <caption>Provider requests — {{ $label }}</caption>
          <thead><tr><th scope="col">Service</th><th scope="col">Attempts</th><th scope="col">Failures</th><th scope="col">Mean response</th></tr></thead>
          <tbody>
          @foreach(['sentinel-statistics' => 'NDVI analysis', 'sentinel-image' => 'Satellite images', 'sentinel-auth' => 'Copernicus sign-in', 'google-static' => 'Google image exports', 'open-meteo' => 'Weather', 'chirps' => 'CHIRPS rainfall', 'openai' => 'AI help generation'] as $key => $name)
            @php($completed = $counts['provider.'.$key.'.completed'] ?? 0)
            <tr><th scope="row">{{ $name }}</th><td>{{ number_format($counts['provider.'.$key.'.attempts'] ?? 0) }}</td><td>{{ number_format($counts['provider.'.$key.'.failed'] ?? 0) }}</td><td>{{ $completed ? number_format(($counts['provider.'.$key.'.ms'] ?? 0) / $completed).' ms' : '—' }}</td></tr>
          @endforeach
          </tbody>
        </table></div>
        <p><strong>AI tokens:</strong> {{ number_format($counts['ai.input_tokens'] ?? 0) }} input · {{ number_format($counts['ai.output_tokens'] ?? 0) }} output from {{ number_format($counts['ai.responses'] ?? 0) }} responses with reported usage. Failed requests may have unreported tokens.</p>
        <div class="module-table-scroll"><table class="module-table">
          <caption>Cache reuse — {{ $label }}</caption>
          <thead><tr><th scope="col">Result</th><th scope="col">Hits</th><th scope="col">Misses</th><th scope="col">Hit rate</th></tr></thead>
          <tbody>
          @foreach(['sentinel-statistics' => 'NDVI analysis', 'sentinel-image' => 'Satellite images', 'google-static' => 'Google image exports', 'weather' => 'Weather', 'chirps' => 'CHIRPS rainfall', 'assistant-help' => 'AI general help'] as $key => $name)
            @php($hits = $counts['cache.'.$key.'.hits'] ?? 0)
            @php($misses = $counts['cache.'.$key.'.misses'] ?? 0)
            <tr><th scope="row">{{ $name }}</th><td>{{ number_format($hits) }}</td><td>{{ number_format($misses) }}</td><td>{{ $hits + $misses ? number_format(100 * $hits / ($hits + $misses), 1).'%' : '—' }}</td></tr>
          @endforeach
          </tbody>
        </table></div>
        <div class="module-table-scroll"><table class="module-table">
          <caption>Server and database work — {{ $label }}</caption>
          <thead><tr><th scope="col">Workflow</th><th scope="col">Requests</th><th scope="col">Mean / longest response</th><th scope="col">Slow responses</th><th scope="col">Queries / slow queries</th><th scope="col">Longest query</th></tr></thead>
          <tbody>
          @foreach(['dashboard' => 'Dashboard', 'farmers' => 'Farmers', 'land' => 'Parcel maps & crops', 'satellite' => 'Satellite', 'climate' => 'Climate', 'assistant' => 'Assistant', 'agriculture' => 'Agriculture', 'other' => 'Other'] as $key => $name)
            @php($requests = $counts['http.'.$key.'.count'] ?? 0)
            <tr><th scope="row">{{ $name }}</th><td>{{ number_format($requests) }}</td><td>{{ $requests ? number_format(($counts['http.'.$key.'.ms'] ?? 0) / $requests).' / '.number_format($counts['http.'.$key.'.max_ms'] ?? 0).' ms' : '—' }}</td><td>{{ number_format($counts['http.'.$key.'.slow'] ?? 0) }}</td><td>{{ number_format($counts['db.'.$key.'.count'] ?? 0) }} / {{ number_format($counts['db.'.$key.'.slow'] ?? 0) }}</td><td>{{ number_format($counts['db.'.$key.'.max_ms'] ?? 0) }} ms</td></tr>
          @endforeach
          </tbody>
        </table></div>
        <p class="ops-provider-help">Slow means at least {{ number_format(config('observability.slow_query_ms', 200)) }} ms per query or {{ number_format(config('observability.slow_response_ms', 1000)) }} ms per server response. Query text and values are never recorded here.</p>
        <div class="module-table-scroll"><table class="module-table">
          <caption>Browser-reported map instances — {{ $label }}</caption>
          <thead><tr><th scope="col">Map</th><th scope="col">Created</th><th scope="col">Reused</th></tr></thead><tbody>
          @foreach(['farmers-3d' => 'Parcel map', 'crop-editor-2d' => 'Crop editor', 'satellite-2d' => 'Satellite reference map'] as $key => $name)
            <tr><th scope="row">{{ $name }}</th><td>{{ number_format($counts['map.'.$key.'.created'] ?? 0) }}</td><td>{{ number_format($counts['map.'.$key.'.reused'] ?? 0) }}</td></tr>
          @endforeach
          </tbody>
        </table></div>
      </details>
    @endforeach
  @endif
  <p class="ops-provider-help">Browser events can be missing or duplicated and are not Google's billable map-load counts. No prices or estimated savings are inferred. <a href="https://console.cloud.google.com/billing" target="_blank" rel="noopener noreferrer">Check Google Cloud Billing ↗</a> for actual charges.</p>
</div>
