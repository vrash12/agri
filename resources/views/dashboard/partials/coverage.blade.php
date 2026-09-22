@php
  $assistanceReach = $coverageStats['assistance'] ?? [];
  $geofenceReach = $coverageStats['geofences'] ?? [];
@endphp
<section class="ops-coverage" aria-labelledby="coverageHeading">
  <div class="ops-coverage-heading">
    <h3 id="coverageHeading">Assistance reach and map readiness</h3>
    <p>Use these gaps to plan follow-up in {{ $scopeLabel }}.</p>
  </div>
  <dl class="ops-coverage-grid">
    <div>
      <dt>Farmers reached · {{ $reportYear }}</dt>
      <dd>{{ number_format((int) ($assistanceReach['beneficiaries'] ?? 0)) }}<p>{{ number_format((int) ($assistanceReach['releases'] ?? 0)) }} release records; each linked farmer counts once.</p></dd>
    </div>
    <div>
      <dt>Registered farmers without a release · {{ $reportYear }}</dt>
      <dd>{{ number_format((int) ($assistanceReach['without_release'] ?? 0)) }}<p>No valid linked release recorded for the year.</p></dd>
    </div>
    <div>
      <dt>Assistance reach · {{ $reportYear }}</dt>
      <dd>{{ isset($assistanceReach['reach_percent']) ? number_format($assistanceReach['reach_percent'], 1).'%' : 'Not available' }}<p>Share of {{ number_format((int) ($assistanceReach['registered'] ?? 0)) }} farmers currently registered.</p></dd>
    </div>
    <div>
      <dt>Releases needing a farmer link · {{ $reportYear }}</dt>
      <dd>{{ number_format((int) ($assistanceReach['unlinked_releases'] ?? 0)) }}<p>A valid link must match the release municipality.</p></dd>
    </div>
    @if($geofenceReach['available'] ?? false)
    <div>
      <dt>Municipalities with one active geofence</dt>
      <dd>{{ number_format((int) ($geofenceReach['covered'] ?? 0)) }} <small>of {{ number_format((int) ($geofenceReach['municipalities'] ?? 0)) }}</small><p>{{ isset($geofenceReach['coverage_percent']) ? number_format($geofenceReach['coverage_percent'], 1).'% of active municipalities' : 'No active municipalities in scope' }}. Current reference coverage.</p></dd>
    </div>
    <div>
      <dt>Municipalities without an active geofence</dt>
      <dd>{{ number_format((int) ($geofenceReach['missing'] ?? 0)) }}<p>{{ number_format((int) ($geofenceReach['ambiguous'] ?? 0)) }} {{ Str::plural('additional municipality', (int) ($geofenceReach['ambiguous'] ?? 0)) }} with multiple active versions to review.</p></dd>
    </div>
    @else
    <div><dt>Municipality geofence coverage</dt><dd>Not available<p>Geofence reporting is unavailable until the boundary module is installed.</p></dd></div>
    @endif
  </dl>
  <div class="ops-coverage-notes">
    <p>Reach uses release dates and the current farmer registry. A missing linked release does not establish eligibility or unmet need. {{ number_format((int) ($assistanceReach['undated_releases'] ?? 0)) }} undated release records are excluded from year totals.</p>
    <div class="ops-coverage-links">
      <a class="ops-text-link" href="{{ route('rice-seed-distributions.index', ['received_from' => $reportYear.'-01-01', 'received_to' => $reportYear.'-12-31']) }}">Review {{ $reportYear }} assistance</a>
      <a class="ops-text-link" href="{{ route('municipality-boundaries.index') }}">Review municipality geofences</a>
    </div>
  </div>
</section>
