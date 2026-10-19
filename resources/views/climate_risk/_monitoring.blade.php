@php
  $forecast = $monitoring['sources']['open_meteo'];
  $rain = $monitoring['sources']['chirps'];
  $dateLabel = fn($value) => $value ? \Carbon\CarbonImmutable::parse($value)->format('M j, Y') : '—';
  $timeLabel = fn($value) => $value ? \Carbon\CarbonImmutable::parse($value)->setTimezone('Asia/Manila')->format('M j, g:i A') : null;
@endphp
<section class="module-panel climate-monitor climate-monitor-{{ $monitoring['state'] }}" aria-labelledby="monitorTitle">
  <div class="climate-monitor-heading">
    <div><span class="climate-source-kicker">{{ $selected->name }} · Automatic monitoring</span><h2 id="monitorTitle">{{ $monitoring['label'] }}</h2><p>{{ $monitoring['summary'] }}</p></div>
    <span class="climate-source-note">{{ $monitoring['coverage'] }}</span>
  </div>
  @if($monitoring['signals'])
    <ul class="climate-monitor-signals">
      @foreach($monitoring['signals'] as $signal)
        <li><strong>{{ $signal['title'] }}</strong><p>{{ $signal['detail'] }}</p><small>{{ $signal['source'] }} · period ends {{ $dateLabel($signal['date']) }}</small></li>
      @endforeach
    </ul>
  @endif
  <div class="climate-source-grid">
    <article class="climate-source-card">
      <span class="climate-source-kicker">Open-Meteo · 7-day forecast</span><h3>Rain and temperature</h3>
      @if($forecast['usable'])
        <strong>{{ number_format($forecast['reading']['value'], 1) }} <span>mm rain</span></strong>
        @if(($forecast['reading']['metadata']['temperature_days'] ?? 0) === 7 && is_numeric($forecast['reading']['metadata']['temperature_max_c'] ?? null))
          <p>Highest temperature: {{ number_format($forecast['reading']['metadata']['temperature_max_c'], 1) }}°C</p>
        @endif
        <small>{{ $dateLabel($forecast['reading']['period_start']) }}–{{ $dateLabel($forecast['reading']['period_end']) }}<br>Updated {{ $timeLabel($forecast['reading']['fetched_at']) }}</small>
      @else
        <strong class="climate-source-empty">{{ $forecast['label'] }}</strong><p>The automatic refresh will add a complete forecast when available.</p>
        @if($forecast['reading'])<small>Last received {{ $timeLabel($forecast['reading']['fetched_at'] ?? null) ?? '—' }}</small>@endif
      @endif
      <details class="climate-forecast-details">
        <summary>Daily forecast</summary>
        <button type="button" class="module-button" id="loadClimateRainfall">Load daily forecast</button>
        <div id="climateRainfall" role="status">Open the daily forecast for rainfall amounts and dates.</div>
      </details>
    </article>
    <article class="climate-source-card">
      <span class="climate-source-kicker">CHIRPS · complete 30-day period</span><h3>Recent rain</h3>
      @if($rain['usable'])
        <strong>{{ number_format($rain['reading']['value'], 1) }} <span>mm estimated rain</span></strong>
        @php($reference = $rain['reading']['baseline_value'] ?? null)
        @if(is_numeric($reference) && $reference > 0 && ($rain['reading']['metadata']['baseline_years'] ?? 0) >= 3 && ($rain['reading']['metadata']['baseline_years'] ?? 0) === ($rain['reading']['metadata']['baseline_years_requested'] ?? -1))
          @php($difference = 100 * (($rain['reading']['value'] - $reference) / $reference))
          <p>{{ number_format(abs($difference), 0) }}% {{ $difference < 0 ? 'below' : 'above' }} the {{ $rain['reading']['metadata']['baseline_years'] }}-year reference.</p>
        @else<p>A complete rainfall reference is not available yet.</p>@endif
        <small>{{ $dateLabel($rain['reading']['period_start']) }}–{{ $dateLabel($rain['reading']['period_end']) }}<br>Updated {{ $timeLabel($rain['reading']['fetched_at']) }}</small>
      @else
        <strong class="climate-source-empty">{{ $rain['label'] }}</strong><p>Rainfall estimates arrive after a reporting delay. A complete period is required.</p>
        @if($rain['reading'])<small>Last received {{ $timeLabel($rain['reading']['fetched_at'] ?? null) ?? '—' }}</small>@endif
      @endif
      <small>Reference-point area estimate; rainfall can differ across farms.</small>
    </article>
    <article class="climate-source-card">
      <span class="climate-source-kicker">Sentinel-2 · parcel checks</span><h3>Inspect vegetation</h3>
      <p>Use <strong class="climate-inline-label">NDMI</strong> to investigate possible water stress, and <strong class="climate-inline-label">NDVI</strong> to compare greenness.</p>
      <p>Check the picture date and cloud coverage, then confirm conditions on the farm.</p>
      <a class="module-button" href="{{ route('farmers.index', ['municipality_id' => $selected->id]) }}#farmersMapModule">Choose a parcel</a>
      <small>{{ config('climate.sources.sentinel2.enabled', false) ? 'Limited scheduled satellite checks are enabled. Existing processing limits apply.' : 'Satellite checks run when requested. This protects the processing allowance.' }}</small>
    </article>
  </div>
  <div class="climate-monitor-footer">
    <p>{{ $monitoring['limits'] }}</p>
    <details><summary>How automatic checks work</summary>
      <p>Weather refreshes every {{ config('climate.sources.open_meteo.refresh_hours', 6) }} hours; rainfall is checked daily. Readings are reused between refreshes. Opening this page does not start a satellite check.</p>
      <p>A field-check prompt appears for a forecast high of at least {{ $monitoring['thresholds']['temperature'] }}°C, less than {{ $monitoring['thresholds']['forecast_rain'] }} mm forecast rain in seven days, or at least {{ $monitoring['thresholds']['rainfall_deficit'] }}% less rain than a complete reference. These planning thresholds are not official drought categories.</p>
      @foreach(['open_meteo' => 'Weather', 'chirps' => 'Rainfall'] as $provider => $label)
        <small>{{ $label }} refresh: {{ $timeLabel($refreshStatus[$provider]['finished_at'] ?? null) ?? 'No completed run recorded yet' }}
          @if(($refreshStatus[$provider]['state'] ?? null) === 'needs_review')
            · some readings need another check
          @endif
        </small>
      @endforeach
      <p>Automatic refresh requires the hosting scheduler. Missing readings stay marked as unavailable. Office assessments below are verified by authorized staff.</p>
      <div class="module-actions"><a href="https://open-meteo.com/en/docs" target="_blank" rel="noopener noreferrer">Forecast source</a><a href="https://climateserv.servirglobal.net/" target="_blank" rel="noopener noreferrer">Rainfall source</a></div>
    </details>
  </div>
</section>
