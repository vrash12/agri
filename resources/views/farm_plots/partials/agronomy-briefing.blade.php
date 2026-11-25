{{-- Local parcel summary (no AI) plus an explicit, optional AI crop-advice request. --}}
@php
  $briefingStatusLabels = ['available' => 'Available', 'attention' => 'Needs attention', 'limited' => 'Limited', 'unavailable' => 'Unavailable'];
@endphp
<section class="module-panel ag-briefing" aria-labelledby="parcelBriefingTitle" data-parcel-briefing>
  <div class="module-panel-head"><div><span class="ag-status" data-status="monitoring">Parcel summary · no AI used</span><h2 id="parcelBriefingTitle">{{ $briefing['headline'] }}</h2><p>{{ $briefing['context'] }}</p></div></div>
  <div class="ag-briefing-next" role="note"><h3>Suggested next step</h3><p><strong>{{ $briefing['next_step']['title'] }}.</strong> {{ $briefing['next_step']['text'] }}</p><p class="ag-meta">From: {{ $briefing['next_step']['source'] }}</p></div>
  <ul class="ag-briefing-list">
    @foreach($briefing['items'] as $item)
      <li data-status="{{ $item['status'] }}"><div class="ag-factor-top"><h3>{{ $item['title'] }}</h3><span class="ag-status" data-status="{{ ['available' => 'monitoring', 'attention' => 'attention', 'limited' => 'waiting', 'unavailable' => 'missing'][$item['status']] ?? 'missing' }}">{{ $briefingStatusLabels[$item['status']] ?? 'Unavailable' }}</span></div>
        <p>{{ $item['text'] }}</p>
        @if(count($item['lines']))<ul class="ag-briefing-lines">@foreach($item['lines'] as $line)<li>{{ $line }}</li>@endforeach</ul>@endif
        <p class="ag-meta">{{ $item['scope'] }}</p>
      </li>
    @endforeach
  </ul>
  <p class="ag-section-note">{{ $briefing['limits'] }}</p>

  <div class="ag-advice" data-crop-advice data-url="{{ route('farm-plots.agronomy.advice', $plot) }}" data-year="{{ $year }}" data-season="{{ $season }}">
    <div class="ag-advice-head"><div><h3 id="cropAdviceTitle">AI crop advice</h3>
      <p class="ag-meta">Optional. Sends only categories — crop, season, growth stage, field condition, water supply, satellite trend and municipality-level rainfall. No names, IDs, places, coordinates or notes leave AgriLGU.</p></div>
      @if($adviceAvailable && ($briefing['categories']['crop'] ?? 'not_recorded') !== 'not_recorded')
        <button type="button" class="module-button module-button-primary" data-crop-advice-button aria-describedby="cropAdviceTitle">Ask AI for crop advice</button>
      @endif
    </div>
    @if(! $adviceAvailable)
      <p class="ag-advice-state" data-state="unavailable" role="status">AI crop advice is not available for this account or office. The parcel summary above still works.</p>
    @elseif(($briefing['categories']['crop'] ?? 'not_recorded') === 'not_recorded')
      <p class="ag-advice-state" data-state="unavailable" role="status">Record this season’s crop, or a current field check, before asking for crop advice.</p>
    @endif
    <div class="ag-advice-result" data-crop-advice-result aria-live="polite" aria-busy="false" hidden></div>
    <noscript><p class="ag-advice-state" data-state="unavailable">Turn on JavaScript to request AI crop advice.</p></noscript>
    <p class="ag-note">Advisory only, not a prescription. It is general guidance from reviewed agriculture references, not a diagnosis, dose or official decision. Verify conditions in the field and with the municipal agriculture office.</p>
  </div>
</section>
@push('scripts')<script src="{{ asset('js/parcel-crop-advice.js') }}?v={{ filemtime(public_path('js/parcel-crop-advice.js')) }}" defer></script>@endpush
