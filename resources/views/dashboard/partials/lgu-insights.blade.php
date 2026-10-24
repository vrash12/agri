@php
  // Use the existing scoped aggregates. These visible charts need no additional
  // requests and keep their figures available when chart libraries cannot load.
  $insightRegistered = array_key_exists('total_farmers', $stats) ? (int) $stats['total_farmers'] : null;
  $insightMapped = array_key_exists('mapped_farmers', $stats) ? (int) $stats['mapped_farmers'] : null;
  $insightMappingPercent = $insightRegistered > 0 && $insightMapped !== null
      ? min(100, max(0, round($insightMapped / $insightRegistered * 100, 1))) : null;
  $insightAssistance = $coverageStats['assistance'] ?? null;
  $insightReachPercent = $insightAssistance['reach_percent'] ?? null;
  $insightMonths = collect($charts['months'] ?? [])->take(12)->values();
  $insightWeights = collect($charts['rice_monthly'] ?? [])->take(12)->values();
  $insightWeightAvailable = $insightMonths->count() === 12 && $insightWeights->count() === 12;
  $insightWeightTotal = $insightWeightAvailable ? (float) $insightWeights->sum() : null;
  $insightWeightMaximum = max(1, (float) ($insightWeights->max() ?? 0));
@endphp
<section class="ops-insights" id="lguInsights" aria-labelledby="lguInsightsHeading">
  <div class="ops-section-heading ops-insights-heading">
    <div>
      <span class="ops-panel-kicker">{{ $scopeLabel }}</span>
      <h2 id="lguInsightsHeading">Your LGU at a glance</h2>
      <p>Saved records in your access scope. Each chart states its reporting period.</p>
    </div>
    <a class="ops-text-link" href="#dashboardReports" data-open-dashboard-section="dashboardReports">More graphs and reporting year <span aria-hidden="true">↓</span></a>
  </div>
  <div class="ops-insight-grid">
    <article class="ops-insight-card" aria-labelledby="mappingInsightHeading">
      <div><span class="ops-period">Current registry</span><h3 id="mappingInsightHeading">Who has a mapped farm?</h3></div>
      <p class="ops-insight-value">{{ $insightMappingPercent !== null ? number_format($insightMappingPercent, 1).'%' : 'Not available' }}</p>
      @if($insightMappingPercent !== null)
        <div class="ops-insight-bar" aria-hidden="true"><span style="width: {{ $insightMappingPercent }}%"></span></div>
        <dl class="ops-insight-legend">
          <div><dt>With a saved parcel</dt><dd>{{ number_format($insightMapped) }}</dd></div>
          <div><dt>Without a saved parcel</dt><dd>{{ number_format(max(0, $insightRegistered - $insightMapped)) }}</dd></div>
        </dl>
        <p class="ops-insight-note">Share of {{ number_format($insightRegistered) }} registered farmers. This counts farmers, not unmapped land or survey accuracy.</p>
      @else
        <p class="ops-insight-note">{{ $insightRegistered === 0 ? 'No registered farmers yet. Mapping coverage will appear when the registry has farmers.' : 'Mapping figures are unavailable. Open the farmer directory to review saved records.' }}</p>
      @endif
      <a class="ops-text-link" href="{{ route('farmers.index', ['mapping' => 'unmapped']) }}">Review farmers without parcels <span aria-hidden="true">→</span></a>
    </article>

    <article class="ops-insight-card" aria-labelledby="assistanceInsightHeading">
      <div><span class="ops-period">Assistance · {{ $reportYear }}</span><h3 id="assistanceInsightHeading">Who received assistance?</h3></div>
      <p class="ops-insight-value">{{ $insightReachPercent !== null ? number_format($insightReachPercent, 1).'%' : 'Not available' }}</p>
      @if($insightAssistance !== null)
        @if($insightReachPercent !== null)
          <div class="ops-insight-bar" aria-hidden="true"><span style="width: {{ min(100, max(0, $insightReachPercent)) }}%"></span></div>
        @endif
        <dl class="ops-insight-legend">
          <div><dt>Farmers with a linked release</dt><dd>{{ number_format((int) $insightAssistance['beneficiaries']) }}</dd></div>
          <div><dt>Without a linked release</dt><dd>{{ number_format((int) $insightAssistance['without_release']) }}</dd></div>
        </dl>
        <p class="ops-insight-note">Each farmer counts once against the current registry. No linked release does not establish eligibility or unmet need.</p>
        @if((int) $insightAssistance['unlinked_releases'] > 0 || (int) $insightAssistance['undated_releases'] > 0)
          <p class="ops-insight-notice">Release records needing a valid farmer link in {{ $reportYear }}: {{ number_format((int) $insightAssistance['unlinked_releases']) }}. Undated records excluded from annual totals: {{ number_format((int) $insightAssistance['undated_releases']) }}.</p>
        @endif
        @if((int) $insightAssistance['registered'] === 0)
          <p class="ops-insight-note">No registered farmers yet; a reach percentage cannot be calculated.</p>
        @endif
      @else
        <p class="ops-insight-note">Assistance coverage is unavailable. Review dated release records before using this figure.</p>
      @endif
      <a class="ops-text-link" href="{{ route('rice-seed-distributions.index', ['received_from' => $reportYear.'-01-01', 'received_to' => $reportYear.'-12-31']) }}">Review {{ $reportYear }} releases <span aria-hidden="true">→</span></a>
    </article>

    <article class="ops-insight-card" aria-labelledby="releaseInsightHeading">
      <div><span class="ops-period">Monthly input weight · {{ $currentYear }}</span><h3 id="releaseInsightHeading">When were inputs released?</h3></div>
      <p class="ops-insight-value">{{ $insightWeightTotal !== null ? number_format($insightWeightTotal, 2) : 'Not available' }}@if($insightWeightTotal !== null) <small>kg</small>@endif</p>
      @if($insightWeightAvailable)
        <figure class="ops-insight-trend">
          <div class="ops-insight-columns" aria-hidden="true">
            @foreach($insightMonths as $index => $month)
              <div class="ops-insight-column"><div><span style="height: {{ min(100, max(0, (float) $insightWeights[$index] / $insightWeightMaximum * 100)) }}%"></span></div><span>{{ $month }}</span></div>
            @endforeach
          </div>
          <figcaption class="ops-insight-note">Monthly weight-based releases in {{ $currentYear }}. Pieces, sacks and other units are excluded. These are inputs released, not harvest or yield.</figcaption>
        </figure>
        <details class="ops-insight-figures">
          <summary>Read monthly amounts</summary>
          <table><caption>Input releases · {{ $currentYear }} · kilograms</caption><thead><tr><th scope="col">Month</th><th scope="col">Weight (kg)</th></tr></thead><tbody>
            @foreach($insightMonths as $index => $month)
              <tr><th scope="row">{{ $month }}</th><td>{{ number_format((float) $insightWeights[$index], 2) }}</td></tr>
            @endforeach
          </tbody></table>
        </details>
        <p class="ops-insight-note">Zero means no weight recorded for that month. Undated releases are excluded.</p>
      @else
        <p class="ops-insight-note">Monthly weight figures are unavailable. Open the reports to review dated releases.</p>
      @endif
      <a class="ops-text-link" href="{{ route('rice-seed-distributions.index', ['received_from' => $currentYear.'-01-01', 'received_to' => $currentYear.'-12-31']) }}">Review {{ $currentYear }} input records <span aria-hidden="true">→</span></a>
    </article>
  </div>
</section>
