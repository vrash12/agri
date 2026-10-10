<section class="ops-panel ops-provider-usage" aria-labelledby="providerUsageHeading">
  <div class="ops-panel-header">
    <div>
      <span class="ops-panel-kicker">Service monitoring</span>
      <h2 id="providerUsageHeading">Satellite &amp; map usage</h2>
      <p>Shared across AgriLGU offices. These counters show activity recorded by this system.</p>
    </div>
    <span class="ops-provider-time">As of {{ $providerUsage['as_of'] }}<br>Refresh this page to update.</span>
  </div>
  <div class="ops-provider-grid">
    <article class="ops-provider-card" aria-labelledby="ndviUsageHeading">
      <div class="ops-provider-title">
        <div><span class="ops-panel-kicker">Copernicus Sentinel-2</span><h3 id="ndviUsageHeading">NDVI satellite checks</h3></div>
        <span class="ops-provider-status">{{ $providerUsage['satellite']['configured'] ? 'Access configured' : 'Setup needed' }}</span>
      </div>
      <p class="ops-provider-help">NDVI checks and satellite pictures share this local request allowance.</p>
      @if($providerUsage['satellite']['periods'] === null)
        <p class="ops-provider-notice" role="status">Usage is temporarily unavailable. Refresh this page later.</p>
      @else
        @foreach(['day' => 'Today · UTC', 'month' => 'This month · UTC'] as $period => $label)
          @php($usage = $providerUsage['satellite']['periods'][$period])
          <div class="ops-provider-budget">
            <div class="ops-provider-statline"><span>{{ $label }}</span><span><strong>{{ number_format($usage['used']) }}</strong> / {{ number_format($usage['limit']) }} requests</span></div>
            <div class="ops-progress @if($usage['percent'] >= 80) ops-provider-progress-warning @endif" role="progressbar" aria-label="{{ $label }} satellite allowance used" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $usage['percent'] }}" aria-valuetext="{{ $usage['used'] }} of {{ $usage['limit'] }} local requests used">
              <span style="width: {{ $usage['percent'] }}%"></span>
            </div>
            <div class="ops-provider-statline ops-provider-help"><span>{{ number_format($usage['remaining']) }} remaining</span><span>Resets {{ $usage['reset_at'] }}</span></div>
            @if($usage['remaining'] === 0)
              <p class="ops-provider-notice">{{ $usage['limit'] === 0 ? 'New satellite requests are paused by the local settings.' : 'Local allowance reached. New checks wait until the reset or an administrator changes the allowance.' }}</p>
            @elseif($usage['percent'] >= 80)
              <p class="ops-provider-notice">Allowance is nearly used. Reuse existing satellite results when available.</p>
            @endif
          </div>
        @endforeach
      @endif
      <p class="ops-provider-help">Cached results and sign-in requests are excluded. Failed processing attempts use the local allowance. Copernicus processing units and account-wide quotas are available in the provider account.</p>
      <a class="ops-button ops-button-secondary" href="https://documentation.dataspace.copernicus.eu/Quotas.html" target="_blank" rel="noopener noreferrer">Copernicus quota guide <span aria-hidden="true">↗</span><span class="sr-only"> (opens a new tab)</span></a>
    </article>
    <article class="ops-provider-card" aria-labelledby="mapsUsageHeading">
      <div class="ops-provider-title">
        <div><span class="ops-panel-kicker">Google Maps</span><h3 id="mapsUsageHeading">Satellite image exports</h3></div>
        <span class="ops-provider-status">{{ $providerUsage['google']['static_configured'] ? 'Access configured' : 'Setup needed' }}</span>
      </div>
      <p class="ops-provider-help">Server requests to Maps Static API for parcel and municipality images.</p>
      @if($providerUsage['google']['periods'] === null)
        <p class="ops-provider-notice" role="status">Usage is temporarily unavailable. Refresh this page later.</p>
      @else
        <div class="ops-provider-counts">
          @foreach(['day' => 'Today · UTC', 'month' => 'This month · UTC'] as $period => $label)
            @php($usage = $providerUsage['google']['periods'][$period])
            <div><span>{{ $label }}</span><strong>{{ number_format($usage['used']) }}</strong><small>recorded requests</small>
              <small>{{ $usage['started_at'] ? 'First recorded '.$usage['started_at'] : 'No tracked requests yet' }}</small>
            </div>
          @endforeach
        </div>
      @endif
      <div class="ops-provider-notice"><strong>Full Google usage: not connected</strong><p>Interactive map loads, billable usage, charges and quota remaining require your Google Cloud reports. Browser maps: {{ $providerUsage['google']['browser_configured'] ? 'configured' : 'setup needed' }}.</p></div>
      <p class="ops-provider-help">Tracking begins with this update. Cached images are excluded; failed attempts are included. Counts can differ from Google's billing totals.</p>
      <a class="ops-button ops-button-secondary" href="https://console.cloud.google.com/google/maps-apis/overview" target="_blank" rel="noopener noreferrer">Open Google Cloud usage <span aria-hidden="true">↗</span><span class="sr-only"> (opens a new tab)</span></a>
    </article>
  </div>
  <p class="ops-provider-footer">Counters reset at UTC day/month boundaries and when the application cache is cleared. Recording is best-effort; provider reports remain the source for billing and quotas. No farmer details or API keys are shown.</p>
</section>
