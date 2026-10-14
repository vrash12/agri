@extends('layouts.app')
@section('title', 'Record Climate Assessment')
@push('styles')
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/climate-risk.css') }}?v={{ filemtime(public_path('css/climate-risk.css')) }}">
@endpush
@section('content')
@php
  $value = fn($key, $fallback = '') => old($key, $assessment->{$key} ?? $fallback);
  $date = fn($key, $fallback) => old($key, $assessment->{$key}?->toDateString() ?? $fallback);
  $today = \App\Support\LocalTime::now()->toDateString();
@endphp
<div class="module-page climate-risk">
  <header class="module-header"><div><div class="module-eyebrow">Climate readiness · Office assessment</div><h1>{{ $assessment->exists ? 'Update assessment' : 'Record an assessment' }}</h1><p>Record the area, response priority and supporting evidence. Keep source coverage and local observations clear.</p></div><a class="module-button" href="{{ route('climate-risk.index', ['municipality_id' => $selected?->id]) }}">Back to readiness</a></header>
  @if(!$assessment->exists && !$selected)
  <form method="GET" class="module-panel climate-filters"><div><label for="chooseMunicipality">Municipality / city</label><select id="chooseMunicipality" name="municipality_id" class="module-input" required><option value="">Choose an authorized municipality</option>@foreach($choices as $choice)<option value="{{ $choice->id }}">{{ $choice->name }} · {{ $choice->province }}</option>@endforeach</select></div><button type="submit" class="module-button module-button-primary">Continue</button></form>
  @else
  <form method="POST" action="{{ $assessment->exists ? route('climate-risk.update', $assessment) : route('climate-risk.store') }}" id="climateAssessmentForm">
    @csrf @if($assessment->exists) @method('PUT') <input type="hidden" name="_record_version" value="{{ old('_record_version', \App\Support\ConcurrentWrite::version($assessment)) }}"> @endif
    @if($errors->has('_record_version'))<div class="module-alert" role="alert">{{ $errors->first('_record_version') }} <a href="{{ route('climate-risk.edit', $assessment) }}">Reopen the current assessment</a></div>@endif
    <input type="hidden" name="municipality_id" value="{{ $selected->id }}">
    <section class="module-panel"><div class="module-panel-head"><div><h2>1. Area and response level</h2><p>{{ $selected->name }} · {{ $selected->province }}</p></div></div><div class="module-form-grid">
      <x-module.field name="barangay" label="Barangay" hint="Leave blank for a municipality-wide assessment. A barangay assessment does not apply to neighboring barangays.">
        <input id="barangay" name="barangay" list="riskBarangayChoices" class="module-input" maxlength="120" value="{{ $value('barangay') }}" @readonly($assessment->exists)>
        <datalist id="riskBarangayChoices">@foreach($barangayChoices as $feature)<option value="{{ $feature['properties']['name'] }}"></option>@endforeach</datalist>
      </x-module.field>
      <x-module.field name="risk_level" label="Office response priority" :required="true">
        <select id="risk_level" name="risk_level" class="module-input" required><option value="">Choose a level</option>@foreach(\App\Models\ClimateRiskAssessment::LEVELS as $key => $label)<option value="{{ $key }}" @selected($value('risk_level') === $key)>{{ $label }}</option>@endforeach</select>
      </x-module.field>
    </div><div class="climate-panel-body climate-guidance"><span><strong>Normal</strong> · Routine monitoring</span><span><strong>Watch</strong> · Verify dry conditions</span><span><strong>Alert</strong> · Prepare local response</span><span><strong>Critical</strong> · Prioritize field response</span></div></section>
    <section class="module-panel"><div class="module-panel-head"><div><h2>2. Conditions, dates and source</h2><p>Use the bulletin's categories or your office field review. Verify the location and period before saving.</p></div></div><div class="module-form-grid">
      <x-module.field name="drought_status" label="Reported drought condition" :required="true"><select id="drought_status" name="drought_status" class="module-input" required>@foreach(\App\Models\ClimateRiskAssessment::DROUGHT as $key => $label)<option value="{{ $key }}" @selected($value('drought_status','not_recorded') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
      <x-module.field name="rainfall_outlook" label="Source rainfall outlook" :required="true" hint="Use the source's seasonal outlook. A short forecast alone cannot establish below-normal seasonal rainfall."><select id="rainfall_outlook" name="rainfall_outlook" class="module-input" required>@foreach(\App\Models\ClimateRiskAssessment::RAINFALL as $key => $label)<option value="{{ $key }}" @selected($value('rainfall_outlook','not_recorded') === $key)>{{ $label }}</option>@endforeach</select></x-module.field>
      <x-module.field name="observed_on" label="Observation / bulletin date" :required="true"><input type="date" id="observed_on" name="observed_on" class="module-input" value="{{ $date('observed_on', $today) }}" max="{{ $today }}" required></x-module.field>
      <x-module.field name="valid_until" label="Review by" :required="true" hint="Within 31 days of observation. Expired assessments stop determining the current map color."><input type="date" id="valid_until" name="valid_until" class="module-input" value="{{ $date('valid_until', \App\Support\LocalTime::now()->addDays(7)->toDateString()) }}" required></x-module.field>
      <x-module.field name="source_type" label="Evidence type" :required="true"><select id="source_type" name="source_type" class="module-input" required><option value="field" @selected($value('source_type','field') === 'field')>Office field review</option><option value="pagasa" @selected($value('source_type') === 'pagasa')>PAGASA bulletin reviewed by office</option></select></x-module.field>
      <x-module.field name="source_label" label="Source / field review reference" :required="true"><input id="source_label" name="source_label" class="module-input" value="{{ $value('source_label') }}" maxlength="180" required placeholder="Bulletin title or office inspection reference"></x-module.field>
      <x-module.field name="source_url" label="Source link" hint="Direct HTTPS link. Required for a PAGASA bulletin."><input type="url" id="source_url" name="source_url" class="module-input" value="{{ $value('source_url') }}" maxlength="500" placeholder="https://..."></x-module.field>
      <x-module.field name="notes" label="Assessment notes" hint="Describe coverage, field observations or the response needed. Omit personal details."><textarea id="notes" name="notes" class="module-input" rows="3" maxlength="1000">{{ $value('notes') }}</textarea></x-module.field>
    </div></section>
    <section class="module-panel"><div class="module-panel-head"><div><h2>3. Reported affected parcels</h2><p>Optional. Add only parcels your office identified as affected. The map will show these saved boundaries.</p></div></div><div class="climate-panel-body">
      <label for="climateParcelSearch">Find a parcel by its name or number</label><div class="module-actions"><input id="climateParcelSearch" class="module-input" type="search" maxlength="100" placeholder="At least two characters"><button id="searchClimateParcels" type="button" class="module-button">Search parcels</button></div>
      <p id="climateParcelSearchStatus" role="status"></p><div id="climateParcelResults" class="climate-parcel-results"></div>
      <div id="climateSelectedParcels" class="climate-parcel-results" aria-label="Selected affected parcels">@foreach($plots as $plot)<label class="climate-parcel-option"><input type="checkbox" name="plot_ids[]" value="{{ $plot->id }}" checked> {{ $plot->name ?: 'Parcel #'.$plot->id }}</label>@endforeach</div>
      @error('plot_ids')<p class="module-field-error" role="alert">{{ $message }}</p>@enderror
    </div></section>
    <section class="module-panel climate-panel-body"><label class="climate-parcel-option"><input type="checkbox" name="verified" value="1" required @checked(old('verified'))> I checked the source date, geographic coverage and reported parcel ownership.</label>@error('verified')<p class="module-field-error">{{ $message }}</p>@enderror<div class="module-actions"><button type="submit" class="module-button module-button-primary">Save assessment</button><a class="module-button" href="{{ route('climate-risk.index', ['municipality_id' => $selected->id]) }}">Cancel</a></div></section>
  </form>
  @if($assessment->exists)
    <details class="module-panel climate-panel-body"><summary>Withdraw this assessment</summary><p>Withdraw an incorrect assessment from the current register. Reported parcel links are removed from this assessment.</p><form method="POST" action="{{ route('climate-risk.destroy', $assessment) }}" data-climate-withdraw>@csrf @method('DELETE')<input type="hidden" name="_record_version" value="{{ \App\Support\ConcurrentWrite::version($assessment) }}"><button class="module-button" type="submit">Withdraw assessment</button></form></details>
  @endif
  @endif
</div>
@endsection
@push('scripts')
<script>window.__climateRiskConfig = @json($formConfig);</script>
<script src="{{ asset('js/climate-risk.js') }}?v={{ filemtime(public_path('js/climate-risk.js')) }}"></script>
@endpush
