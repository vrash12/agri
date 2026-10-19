@extends('layouts.app')
@section('title', 'Crop checks and suitability')
@push('styles')
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/parcel-agronomy.css') }}?v={{ filemtime(public_path('css/parcel-agronomy.css')) }}">
@endpush
@php
  $dateLabel = fn($date) => $date ? \Carbon\CarbonImmutable::parse($date)->format('M j, Y') : 'Not recorded';
  $cropLabels = ['rice' => 'Rice / Palay', 'corn' => 'Corn', 'mungbean' => 'Mungbean / Munggo'];
  $stageLabels = ['unknown' => 'Not checked', 'not_planted' => 'Not yet planted', 'establishment' => 'Newly planted', 'vegetative' => 'Growing leaves', 'flowering' => 'Flowering', 'grain_filling' => 'Grain / pod filling', 'mature' => 'Ready for harvest', 'harvested' => 'Already harvested'];
  $conditionLabels = ['unknown' => 'Not checked', 'normal' => 'No visible problem observed', 'wilting' => 'Wilting / leaves curling', 'yellowing' => 'Yellowing leaves', 'waterlogged' => 'Too much standing water'];
  $waterLabels = ['unknown' => 'Not verified', 'reliable' => 'Water is reliably available', 'limited' => 'Water is limited', 'unavailable' => 'Water is unavailable'];
  $factorLabels = ['matches' => 'Matches guide', 'review' => 'Needs review', 'missing' => 'Evidence needed'];
  $currentEvidence = ($assessment['context_current'] ?? false) ? $assessment : [];
  $value = fn($key, $fallback = '') => old($key, $currentEvidence[$key] ?? $fallback);
  $mapUrl = route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id, 'map_farmer' => $plot->farmer_id, 'crop_year' => $year, 'crop_season' => $season]).'#farmersMapModule';
  $satelliteUrl = route('farm-plots.satellite.show', $plot);
  $profiles = $suitabilityProfiles ?? [$suitability];
  $followUp = $stress['follow_up'] ?? [];
@endphp
@section('content')
<div class="module-page parcel-agronomy">
  <header class="module-header">
    <div><div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · Parcel care</div><h1>Crop checks &amp; suitability</h1><p>{{ $plot->name ?: 'Parcel #'.$plot->id }} · {{ trim($plot->farmer->first_name.' '.$plot->farmer->last_name) }}</p>
      <div class="ag-context">@if(is_numeric($plot->area_ha))<span>{{ number_format((float) $plot->area_ha, 2) }} ha mapped parcel</span>@endif<span>{{ $year }} · {{ $season === 'wet' ? 'Wet' : 'Dry' }} season</span>@unless($canEdit)<span class="ag-readonly">View only</span>@endunless</div>
    </div><a class="module-button" href="{{ $mapUrl }}">← Back to parcel map</a>
  </header>

  <form method="GET" action="{{ route('farm-plots.agronomy.show', $plot) }}" class="ag-period module-panel" aria-label="Choose the crop season">
    <div><label for="agronomyPeriodYear">Crop year</label><input class="module-input" id="agronomyPeriodYear" name="year" type="number" min="1990" max="{{ \App\Support\LocalTime::now()->year + 1 }}" value="{{ $year }}" required></div>
    <div><label for="agronomyPeriodSeason">Season</label><select class="module-input" id="agronomyPeriodSeason" name="season" required><option value="dry" @selected($season === 'dry')>Dry season</option><option value="wet" @selected($season === 'wet')>Wet season</option></select></div>
    <button class="module-button" type="submit">Show season</button><p>Field checks and crop guidance below use this season.</p>
  </form>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert"><strong>The field check was not saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      @if($errors->has('_record_version') || $errors->has('_plot_version') || $errors->has('_context_fingerprint'))<p><a href="{{ route('farm-plots.agronomy.show', $plot) }}">Reopen the latest parcel before saving again</a></p>@endif
    </div>
  @endif
  @if($assessment && !($assessment['context_current'] ?? false))<div class="ag-missing" role="status"><strong>This parcel has changed since the last field check.</strong> Earlier checks remain in the history. Record a new check for the current land and ownership.</div>@endif

  <section class="ag-lead" data-status="{{ $stress['status'] }}" aria-labelledby="agronomyStatusTitle">
    <div class="ag-lead-main"><span class="ag-status" data-status="{{ $stress['status'] }}">Field-check guidance</span><h2 id="agronomyStatusTitle">{{ $stress['label'] }}</h2><p>{{ $stress['summary'] }}</p>
      <p class="ag-meta">@if($stress['observed_on'] ?? null)Field checked {{ $dateLabel($stress['observed_on']) }} · {{ $stress['stage_label'] ?? 'Stage not recorded' }}@else No current field check recorded for this parcel.@endif</p>
    </div>
    <div class="ag-lead-side"><h3>What to do next</h3>
      @if(($followUp['due_on'] ?? null) && ($followUp['status'] ?? null) !== 'completed')<p>{{ ($followUp['overdue'] ?? false) ? 'Inspection due: ' : 'Next inspection: ' }}{{ $dateLabel($followUp['due_on']) }}</p><p class="ag-meta">Check conditions on the farm and record the outcome.</p>@else<p>{{ $followUp['label'] ?? 'Record what you see on the farm, then review the evidence below.' }}</p>@endif
      <div class="ag-actions">@if($canEdit)<a class="module-button module-button-primary" href="#recordFieldCheck">Record field check</a>@endif<a class="module-button" href="{{ $satelliteUrl }}">Satellite check</a></div>
    </div>
  </section>

  <section class="module-panel" aria-labelledby="agronomyEvidenceTitle">
    <div class="module-panel-head"><div><h2 id="agronomyEvidenceTitle">What supports this check?</h2><p>Read the date and coverage of each piece of evidence.</p></div></div>
    @if(count($stress['signals'] ?? []))<ul class="ag-evidence-list">@foreach($stress['signals'] as $signal)<li><h3>{{ $signal['title'] }}</h3><p>{{ $signal['detail'] }}</p><p class="ag-meta">{{ $signal['scope'] ?? 'Parcel field check' }}@if($signal['date'] ?? null) · {{ $dateLabel($signal['date']) }}@endif</p></li>@endforeach</ul>@endif
    <div class="ag-body">
      @if(count($stress['missing'] ?? []))<div class="ag-missing"><h3>Still needed</h3><ul>@foreach($stress['missing'] as $missing)<li>{{ $missing }}</li>@endforeach</ul></div>@endif
      <details class="ag-climate-details"><summary>Municipality weather and rainfall reference</summary><div class="ag-climate-grid">
        @foreach(['open_meteo' => '7-day rain forecast', 'chirps' => 'Recent rainfall estimate'] as $provider => $sourceLabel)
          @php
            $source = $climateMonitoring['sources'][$provider] ?? [];
          @endphp
          <article><h3>{{ $sourceLabel }}</h3>
            @if(($source['usable'] ?? false) && is_numeric($source['reading']['value'] ?? null))<strong class="ag-climate-value">{{ number_format((float) $source['reading']['value'], 1) }} <small>mm</small></strong><p class="ag-meta">{{ $dateLabel($source['reading']['period_start'] ?? null) }}–{{ $dateLabel($source['reading']['period_end'] ?? null) }}</p>@else<p>{{ $source['label'] ?? 'No current complete reading' }}</p>@endif
            <p class="ag-note">{{ $provider === 'open_meteo' ? 'Open-Meteo forecast at the municipality reference point.' : 'CHIRPS rainfall estimate around the municipality reference point.' }} Rain can differ at this parcel.</p>
          </article>
        @endforeach
      </div></details>
    </div><p class="ag-section-note">{{ $stress['limits'] }}</p>
  </section>

  <section class="module-panel" aria-labelledby="agronomySatelliteTitle">
    <div class="module-panel-head"><div><h2 id="agronomySatelliteTitle">Changes seen from space</h2><p>Saved checks for this parcel. Compare these with the crop stage and a field visit.</p></div><a class="module-button module-button-small" href="{{ $satelliteUrl }}">Find a picture</a></div>
    <div class="ag-satellite-grid">
      @forelse($stress['satellite'] ?? [] as $series)
        @php
          $points = collect($series['observations'] ?? [])->filter(fn($point) => is_numeric($point['mean'] ?? null) && is_finite((float) $point['mean']) && !empty($point['date']))->sortBy('date')->values()->all();
          $chartMin = min(array_merge([-1], array_column($points, 'mean')));
          $chartMax = max(array_merge([1], array_column($points, 'mean')));
          $firstTime = $points ? strtotime($points[0]['date']) : 0;
          $lastTime = $points ? strtotime($points[count($points) - 1]['date']) : 0;
        @endphp
        <article class="ag-satellite-card"><h3>{{ $series['label'] }}</h3><span class="ag-meta">{{ $series['label_status'] }}</span><p>{{ $series['detail'] }}</p>
          @if(count($points))
            <figure class="ag-chart"><svg viewBox="0 0 560 185" role="img" aria-label="{{ strtoupper($series['index']) }} saved readings; exact dates and values in the table below">
              @foreach([$chartMax, ($chartMin + $chartMax) / 2, $chartMin] as $tickIndex => $tick)<line class="ag-grid-line" x1="43" x2="540" y1="{{ 18 + $tickIndex * 65 }}" y2="{{ 18 + $tickIndex * 65 }}"/><text x="33" y="{{ 22 + $tickIndex * 65 }}" text-anchor="end">{{ number_format((float) $tick, 1) }}</text>@endforeach
              @foreach($points as $point)
                @php
                  $pointX = $lastTime > $firstTime ? 48 + (strtotime($point['date']) - $firstTime) / ($lastTime - $firstTime) * 484 : 290;
                  $pointY = 148 - ((float) $point['mean'] - $chartMin) / ($chartMax - $chartMin) * 130;
                @endphp
                <circle class="ag-dot" cx="{{ number_format($pointX, 2, '.', '') }}" cy="{{ number_format($pointY, 2, '.', '') }}" r="5"><title>{{ $dateLabel($point['date']) }}: {{ number_format((float) $point['mean'], 3) }}</title></circle>
              @endforeach
              <text x="43" y="175">{{ $dateLabel($points[0]['date']) }}</text>@if(count($points) > 1)<text x="540" y="175" text-anchor="end">{{ $dateLabel($points[count($points) - 1]['date']) }}</text>@endif
            </svg><figcaption>Each dot is one saved reading. Missing days have no reading.</figcaption></figure>
            <details><summary>Exact dates and values</summary><div class="module-table-scroll"><table class="module-table"><thead><tr><th scope="col">Picture date</th><th scope="col">Average {{ strtoupper($series['index']) }}</th><th scope="col">Usable pixels</th></tr></thead><tbody>@foreach($points as $point)<tr><th scope="row">{{ $dateLabel($point['date']) }}</th><td>{{ number_format((float) $point['mean'], 3) }}</td><td>{{ isset($point['valid_pixels']) ? number_format((int) $point['valid_pixels']) : 'Not recorded' }}</td></tr>@endforeach</tbody></table></div></details>
          @endif
        </article>
      @empty<div class="ag-empty"><strong>No saved satellite readings yet</strong>Open Satellite check and choose plant greenness (NDVI) or vegetation water (NDMI).</div>@endforelse
    </div><p class="ag-section-note">NDVI shows greenness. NDMI indicates water in vegetation. Neither number is a drought diagnosis, and clouds or small parcels can limit the reading.</p>
  </section>

  <section class="module-panel" aria-labelledby="agronomySuitabilityTitle">
    <div class="module-panel-head"><div><h2 id="agronomySuitabilityTitle">Compare crop options</h2><p>Does the recorded soil, slope, climate and water match each crop’s broad growing guide?</p></div></div>
    <div class="ag-profile-grid">
      @foreach($profiles as $profile)
        <article class="ag-profile-card"><h3>{{ $profile['crop_label'] }}</h3><span class="ag-status" data-status="{{ $profile['status'] }}">{{ $profile['label'] }}</span><p>{{ $profile['summary'] }}</p>
          <details><summary>View the six checks</summary><div class="ag-profile-factors">@foreach($profile['factors'] as $factor)
            <div class="ag-profile-factor"><div class="ag-factor-top"><h4>{{ $factor['label'] }}</h4><span class="ag-status" data-status="{{ $factor['status'] }}">{{ $factorLabels[$factor['status']] ?? 'Evidence needed' }}</span></div>
              <p class="ag-factor-reading">{{ $factor['value'] === null ? 'Not available' : $factor['value'] }}@if($factor['value'] !== null && ($factor['unit'] ?? '') !== '') {{ $factor['unit'] }}@endif</p><p>{{ $factor['detail'] }}</p>
              @if($factor['observed_on'] ?? null)<p class="ag-meta">Evidence: {{ $factor['evidence_source'] ?? 'Recorded field evidence' }} · {{ $dateLabel($factor['observed_on']) }}</p>@endif
              @if($factor['source'] ?? null)<p class="ag-meta"><a href="{{ $factor['source'] }}" target="_blank" rel="noopener noreferrer">Growing guide reference</a></p>@endif
            </div>
          @endforeach</div></details>
        </article>
      @endforeach
    </div><div class="ag-body ag-profile-context">
      @if(is_numeric($suitability['rainfall_context']['value_mm'] ?? null))<p><strong>Recorded growing-period rainfall:</strong> {{ number_format((float) $suitability['rainfall_context']['value_mm'], 1) }} mm · {{ $dateLabel($suitability['rainfall_context']['period_start'] ?? null) }}–{{ $dateLabel($suitability['rainfall_context']['period_end'] ?? null) }}. {{ $suitability['rainfall_context']['detail'] }}</p>@else<p><strong>Growing-period rainfall:</strong> {{ $suitability['rainfall_context']['detail'] ?? 'No verified reference recorded.' }} Water adequacy still needs local review.</p>@endif
      <p class="ag-note">{{ $suitability['limits'] }}</p>
    </div>
  </section>

  @if($canEdit)
  <section class="module-panel" id="recordFieldCheck" aria-labelledby="recordFieldCheckTitle">
    <div class="module-panel-head"><div><h2 id="recordFieldCheckTitle">Record a field check</h2><p>Save what was observed, the evidence used and the next visit. Every save adds a dated history entry.</p></div></div>
    <form method="POST" action="{{ route('farm-plots.agronomy.store', $plot) }}">
      @csrf
      <input type="hidden" name="_record_version" value="{{ old('_record_version', $recordVersion) }}"><input type="hidden" name="_plot_version" value="{{ old('_plot_version', $plotVersion) }}"><input type="hidden" name="_context_fingerprint" value="{{ old('_context_fingerprint', $contextFingerprint) }}"><input type="hidden" name="crop_year" value="{{ $year }}"><input type="hidden" name="season" value="{{ $season }}">
      <div class="module-form-grid">
        <x-module.field name="observed_on" label="Date you checked the field" :required="true"><input class="module-input" type="date" id="observed_on" name="observed_on" value="{{ old('observed_on', $today) }}" max="{{ $today }}" required aria-describedby="observed_on_error"></x-module.field>
        <x-module.field name="crop" label="Crop being checked" :required="true"><select class="module-input" id="crop" name="crop" required aria-describedby="crop_error"><option value="">Choose a crop</option>@foreach($cropLabels as $key => $label)<option value="{{ $key }}" @selected($value('crop') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
        <x-module.field name="crop_stage" label="What stage is the crop at?" :required="true"><select class="module-input" id="crop_stage" name="crop_stage" required aria-describedby="crop_stage_error">@foreach($stageLabels as $key => $label)<option value="{{ $key }}" @selected(old('crop_stage', 'unknown') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
        <x-module.field name="planted_on" label="Planting date" hint="Optional, if known."><input class="module-input" type="date" id="planted_on" name="planted_on" value="{{ $value('planted_on') }}" max="{{ $today }}" aria-describedby="planted_on_hint planted_on_error"></x-module.field>
        <x-module.field name="field_condition" label="What do you see in the field?" :required="true" :full="true"><select class="module-input" id="field_condition" name="field_condition" required aria-describedby="field_condition_error">@foreach($conditionLabels as $key => $label)<option value="{{ $key }}" @selected(old('field_condition', 'unknown') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
      </div>
      <fieldset class="ag-fieldset"><legend>Water for this crop</legend><p class="ag-fieldset-note">Verify the available supply, including rainfall or irrigation. A rain forecast alone does not confirm water at the farm.</p><div class="module-form-grid">
        <x-module.field name="water_availability" label="Is enough water available?" :required="true"><select class="module-input" id="water_availability" name="water_availability" required aria-describedby="water_availability_error">@foreach($waterLabels as $key => $label)<option value="{{ $key }}" @selected($value('water_availability', 'unknown') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
        <x-module.field name="water_observed_on" label="Water supply checked on" hint="Required when water availability is verified."><input class="module-input" type="date" id="water_observed_on" name="water_observed_on" value="{{ $value('water_observed_on') }}" max="{{ $today }}" aria-describedby="water_observed_on_hint water_observed_on_error"></x-module.field>
        <x-module.field name="water_source" label="Water source and evidence" :full="true" hint="For example, canal supply confirmed during a field visit, or a verified pump schedule."><input class="module-input" id="water_source" name="water_source" maxlength="180" value="{{ $value('water_source') }}" aria-describedby="water_source_hint water_source_error"></x-module.field>
      </div></fieldset>
      <details class="ag-optional" @if($errors->hasAny(['soil_ph','soil_texture','drainage','soil_source','soil_observed_on','slope_percent','terrain_source','terrain_observed_on'])) open @endif><summary>Add soil and slope evidence <small>Optional · needed for crop suitability</small></summary>
        <p class="ag-fieldset-note">Use a soil test, survey or verified field reference. Keep its original measurement date.</p><div class="module-form-grid">
          <x-module.field name="soil_ph" label="Soil pH" hint="Enter the measured number, from 0 to 14."><input class="module-input" type="number" step="0.01" min="0" max="14" id="soil_ph" name="soil_ph" value="{{ $value('soil_ph') }}" aria-describedby="soil_ph_hint soil_ph_error"></x-module.field>
          <x-module.field name="soil_texture" label="Soil texture"><select class="module-input" id="soil_texture" name="soil_texture" aria-describedby="soil_texture_error"><option value="">Not recorded</option>@foreach(['sandy' => 'Sandy', 'loamy' => 'Loamy', 'clayey' => 'Clayey'] as $key => $label)<option value="{{ $key }}" @selected($value('soil_texture') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
          <x-module.field name="drainage" label="How easily does water drain?"><select class="module-input" id="drainage" name="drainage" aria-describedby="drainage_error"><option value="">Not recorded</option>@foreach(['good' => 'Well drained', 'moderate' => 'Moderately drained', 'poor' => 'Poorly drained / holds water'] as $key => $label)<option value="{{ $key }}" @selected($value('drainage') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
          <x-module.field name="soil_observed_on" label="Soil evidence date" hint="Required when soil or drainage values are entered."><input class="module-input" type="date" id="soil_observed_on" name="soil_observed_on" value="{{ $value('soil_observed_on') }}" max="{{ $today }}" aria-describedby="soil_observed_on_hint soil_observed_on_error"></x-module.field>
          <x-module.field name="soil_source" label="Soil evidence source" :full="true"><input class="module-input" id="soil_source" name="soil_source" maxlength="180" value="{{ $value('soil_source') }}" aria-describedby="soil_source_error"></x-module.field>
          <x-module.field name="slope_percent" label="Slope (%)" hint="0 means flat. Enter percent slope, not degrees."><input class="module-input" type="number" step="0.01" min="0" max="100" id="slope_percent" name="slope_percent" value="{{ $value('slope_percent') }}" aria-describedby="slope_percent_hint slope_percent_error"></x-module.field>
          <x-module.field name="terrain_observed_on" label="Slope evidence date" hint="Required when a slope value is entered."><input class="module-input" type="date" id="terrain_observed_on" name="terrain_observed_on" value="{{ $value('terrain_observed_on') }}" max="{{ $today }}" aria-describedby="terrain_observed_on_hint terrain_observed_on_error"></x-module.field>
          <x-module.field name="terrain_source" label="Slope evidence source" :full="true" hint="Name the survey or terrain dataset and its coverage."><input class="module-input" id="terrain_source" name="terrain_source" maxlength="180" value="{{ $value('terrain_source') }}" aria-describedby="terrain_source_hint terrain_source_error"></x-module.field>
        </div>
      </details>
      <details class="ag-optional" @if($errors->hasAny(['growing_temperature_c','growing_rainfall_mm','climate_source','climate_period_start','climate_period_end'])) open @endif><summary>Add a growing-period climate reference <small>Optional · use a verified period of 30–366 days</small></summary>
        <p class="ag-fieldset-note">Use a completed reference period relevant to the crop. Keep short-term forecasts in the weather panel above.</p><div class="module-form-grid">
          <x-module.field name="growing_temperature_c" label="Average temperature (°C)"><input class="module-input" type="number" step="0.1" min="-10" max="60" id="growing_temperature_c" name="growing_temperature_c" value="{{ $value('growing_temperature_c') }}" aria-describedby="growing_temperature_c_error"></x-module.field>
          <x-module.field name="growing_rainfall_mm" label="Total rainfall (mm)" hint="Context only. Seasonal rain alone cannot confirm adequate water."><input class="module-input" type="number" step="0.1" min="0" max="10000" id="growing_rainfall_mm" name="growing_rainfall_mm" value="{{ $value('growing_rainfall_mm') }}" aria-describedby="growing_rainfall_mm_hint growing_rainfall_mm_error"></x-module.field>
          <x-module.field name="climate_period_start" label="Reference period starts"><input class="module-input" type="date" id="climate_period_start" name="climate_period_start" value="{{ $value('climate_period_start') }}" max="{{ $today }}" aria-describedby="climate_period_start_error"></x-module.field>
          <x-module.field name="climate_period_end" label="Reference period ends"><input class="module-input" type="date" id="climate_period_end" name="climate_period_end" value="{{ $value('climate_period_end') }}" max="{{ $today }}" aria-describedby="climate_period_end_error"></x-module.field>
          <x-module.field name="climate_source" label="Climate reference source" :full="true" hint="Name the dataset, station or report. Required with either climate value."><input class="module-input" id="climate_source" name="climate_source" maxlength="180" value="{{ $value('climate_source') }}" aria-describedby="climate_source_hint climate_source_error"></x-module.field>
        </div>
      </details>
      <fieldset class="ag-fieldset"><legend>Plan the follow-up</legend><div class="module-form-grid">
        <x-module.field name="follow_up_status" label="Field follow-up" :required="true"><select class="module-input" id="follow_up_status" name="follow_up_status" required aria-describedby="follow_up_status_error"><option value="open" @selected(old('follow_up_status', 'open') === 'open')>Open — follow-up needed</option><option value="completed" @selected(old('follow_up_status', 'open') === 'completed')>Completed — outcome recorded</option></select></x-module.field>
        <x-module.field name="next_inspection_on" label="Next inspection date" hint="Optional. Choose the next visit your office plans to make."><input class="module-input" type="date" id="next_inspection_on" name="next_inspection_on" value="{{ old('next_inspection_on') }}" aria-describedby="next_inspection_on_hint next_inspection_on_error"></x-module.field>
        <x-module.field name="follow_up_notes" label="What was done, or what should happen next?" :full="true" hint="Required for a completed follow-up. Keep personal contact details out."><textarea class="module-input" id="follow_up_notes" name="follow_up_notes" rows="3" maxlength="1000" aria-describedby="follow_up_notes_hint follow_up_notes_error">{{ old('follow_up_notes') }}</textarea></x-module.field>
      </div></fieldset>
      <div class="ag-save"><p>Saving updates this parcel’s crop-check guidance and suitability screening. Earlier field checks remain in the history.</p><button class="module-button module-button-primary" type="submit">Save field check</button></div>
    </form>
  </section>
  @endif

  <details class="module-panel ag-details" @if(request()->has('page')) open @endif><summary id="agronomyHistoryTitle">Field-check history · {{ $history->total() }} {{ $history->total() === 1 ? 'entry' : 'entries' }}</summary>
    <ol class="ag-history-list" aria-labelledby="agronomyHistoryTitle">@forelse($history as $entry)<li class="ag-history-entry"><div class="ag-history-top"><div><h3>{{ $dateLabel($entry['observed_on']) }} · {{ $cropLabels[$entry['crop']] ?? 'Crop not recorded' }}</h3><p class="ag-meta">{{ $entry['crop_year'] }} · {{ $entry['season'] === 'wet' ? 'Wet' : 'Dry' }} season @if(!($entry['context_current'] ?? false)) · Earlier parcel context @endif</p></div><span class="ag-status" data-status="{{ $entry['follow_up_status'] === 'completed' ? 'monitoring' : 'waiting' }}">{{ $entry['follow_up_status'] === 'completed' ? 'Follow-up completed' : 'Follow-up open' }}</span></div>
      <dl><div><dt>Crop stage</dt><dd>{{ $stageLabels[$entry['crop_stage']] ?? 'Not checked' }}</dd></div><div><dt>Field condition</dt><dd>{{ $conditionLabels[$entry['field_condition']] ?? 'Not checked' }}</dd></div><div><dt>Water supply</dt><dd>{{ $waterLabels[$entry['water_availability']] ?? 'Not verified' }}</dd></div>@if($entry['next_inspection_on'] ?? null)<div><dt>Planned inspection</dt><dd>{{ $dateLabel($entry['next_inspection_on']) }}</dd></div>@endif</dl>
      @if($entry['follow_up_notes'] ?? null)<p class="ag-history-note">{{ $entry['follow_up_notes'] }}</p>@endif
      <details class="ag-history-evidence"><summary>Evidence saved with this check</summary><dl>
        <div><dt>Soil pH</dt><dd>{{ is_numeric($entry['soil_ph'] ?? null) ? $entry['soil_ph'] : 'Not recorded' }}</dd></div>
        <div><dt>Soil texture</dt><dd>{{ ['sandy' => 'Sandy', 'loamy' => 'Loamy', 'clayey' => 'Clayey'][$entry['soil_texture'] ?? ''] ?? 'Not recorded' }}</dd></div>
        <div><dt>Drainage</dt><dd>{{ ['good' => 'Well drained', 'moderate' => 'Moderately drained', 'poor' => 'Poorly drained'][$entry['drainage'] ?? ''] ?? 'Not recorded' }}</dd></div>
        <div><dt>Slope</dt><dd>{{ is_numeric($entry['slope_percent'] ?? null) ? $entry['slope_percent'].'%' : 'Not recorded' }}</dd></div>
        <div><dt>Average temperature</dt><dd>{{ is_numeric($entry['growing_temperature_c'] ?? null) ? $entry['growing_temperature_c'].'°C' : 'Not recorded' }}</dd></div>
        <div><dt>Growing-period rainfall</dt><dd>{{ is_numeric($entry['growing_rainfall_mm'] ?? null) ? $entry['growing_rainfall_mm'].' mm' : 'Not recorded' }}</dd></div>
      </dl><ul class="ag-history-sources">
        @foreach(['soil' => 'Soil', 'terrain' => 'Slope', 'water' => 'Water'] as $group => $label)
          @if($entry[$group.'_source'] ?? null)<li><strong>{{ $label }}:</strong> {{ $entry[$group.'_source'] }} · {{ $dateLabel($entry[$group.'_observed_on'] ?? null) }}</li>@endif
        @endforeach
        @if($entry['climate_source'] ?? null)<li><strong>Climate:</strong> {{ $entry['climate_source'] }} · {{ $dateLabel($entry['climate_period_start'] ?? null) }}–{{ $dateLabel($entry['climate_period_end'] ?? null) }}</li>@endif
      </ul></details>
    </li>@empty<li class="ag-history-entry ag-note">No field checks have been saved for this parcel yet.</li>@endforelse</ol><div class="ag-history-pagination">{{ $history->links() }}</div>
  </details>
  <details class="module-panel ag-details ag-method"><summary>How to use these checks</summary><div class="ag-body"><p>Crop suitability compares recorded evidence with broad crop requirements. It is an initial screening for an agricultural officer to review before planting decisions.</p><p>Rice means an irrigated lowland rice system in this guide. A crop may need a different variety, management method or local assessment. Unknown evidence stays unknown.</p><p>Field-check guidance combines dated field observations with available rainfall and satellite evidence. Harvest, growth stage, irrigation, clouds and other field conditions can explain a change in greenness or vegetation water.</p><ul>@foreach($suitability['sources'] ?? [] as $source)<li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['label'] }}</a></li>@endforeach</ul><p class="ag-note">Profile reference: {{ $suitability['profile_version'] }}. Mapped parcel size does not measure planted crop area.</p></div></details>
</div>
@endsection
