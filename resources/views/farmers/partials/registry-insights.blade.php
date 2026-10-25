@php
  $insightTotal = max(0, (int) $totalFarmers);
  $insightGenders = collect($genderStats ?? []);
  $insightLocations = collect($locationStats ?? [])
      ->filter(fn ($count, $label) => trim((string) $label) !== '' && mb_strtolower(trim((string) $label)) !== 'unknown')
      ->sortDesc()->take(10);
  $insightLocationRemainder = max(0, $insightTotal - (int) $insightLocations->sum());
@endphp
<details class="module-more registry-insights" id="farmerInsights">
  <summary>Registry insights <span>Gender and leading farm locations</span></summary>
  <div class="module-more-content registry-insights-content">
    <div class="registry-insights-context">
      <div>
        <p class="registry-insights-eyebrow">Current result set · {{ $workspaceName }}</p>
        <p class="registry-insights-total"><strong>{{ number_format($insightTotal) }}</strong> matching {{ Str::plural('farmer', $insightTotal) }}</p>
        <p class="registry-insights-note">All figures below use the same registry filters. Each bar shows a share of these profiles.</p>
      </div>
      <a class="module-button" href="#farmerDirectory">Review filters</a>
    </div>
    @if($activeFilters->isNotEmpty())
      <ul class="registry-insights-filters" aria-label="Filters applied to these figures">
        @foreach($activeFilters as $filter)<li>{{ $filter['label'] }}: {{ $filter['value'] }}</li>@endforeach
      </ul>
    @endif
    @if($insightTotal === 0)
      <div class="registry-insights-empty"><strong>No matching farmer profiles</strong><p>Change the registry filters to see a breakdown.</p></div>
    @else
      <div class="registry-insights-grid">
        <figure class="registry-insights-figure" aria-labelledby="registryGenderHeading">
          <figcaption><h3 id="registryGenderHeading">Gender breakdown</h3><p>Recorded gender of the matching farmers.</p></figcaption>
          <dl class="registry-insight-bars">
            @forelse($insightGenders as $label => $count)
              @include('farmers.partials.registry-insight-row', ['total' => $insightTotal])
            @empty
              <div class="registry-insights-empty"><p>No gender figures are available for this result set.</p></div>
            @endforelse
          </dl>
        </figure>
        <figure class="registry-insights-figure registry-insights-locations" aria-labelledby="registryLocationHeading">
          <figcaption><h3 id="registryLocationHeading">Leading farm locations</h3><p>Ranked by matching farmers · up to 10 recorded locations.</p></figcaption>
          @if($insightLocations->isEmpty())
            <div class="registry-insights-empty"><p>No recorded farm locations are available for this result set.</p></div>
          @else
            <dl class="registry-insight-bars">
              @foreach($insightLocations->take(5) as $label => $count)
                @include('farmers.partials.registry-insight-row', ['total' => $insightTotal, 'rank' => $loop->iteration])
              @endforeach
            </dl>
            @if($insightLocations->count() > 5)
              <details class="registry-insights-more">
                <summary>{{ $insightLocations->count() === 6 ? 'Location 6' : 'Locations 6–'.$insightLocations->count() }} <span>{{ $insightLocations->count() - 5 }} more</span></summary>
                <dl class="registry-insight-bars">
                  @foreach($insightLocations->slice(5) as $label => $count)
                    @include('farmers.partials.registry-insight-row', ['total' => $insightTotal, 'rank' => $loop->iteration + 5])
                  @endforeach
                </dl>
              </details>
            @endif
          @endif
          <p class="registry-insights-location-note"><strong>{{ number_format($insightLocationRemainder) }}</strong> {{ Str::plural('profile', $insightLocationRemainder) }} in other or unrecorded locations. Locations use saved farm-location text.</p>
        </figure>
      </div>
    @endif
  </div>
</details>
