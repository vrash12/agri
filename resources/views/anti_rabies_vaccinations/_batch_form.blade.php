@php
  $serviceSuggestions = \App\Support\AnimalHealthFormOptions::SERVICE_SUGGESTIONS;
  $breedsByType = \App\Support\AnimalHealthFormOptions::BREEDS_BY_TYPE;
  $today = \App\Support\LocalTime::now()->toDateString();
  $rows = old('animals', [['animal_count' => 1, 'service_type' => $defaultServiceType ?? 'vaccination', 'vaccination_date' => $today]]);
  $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
  $rows = array_slice($rows ?: [[]], 0, \App\Http\Requests\StoreAnimalHealthServicesRequest::MAX_ANIMAL_ROWS);
@endphp
@push('styles')
<style>
  #animalServicesForm .animal-batch-row{min-width:0;margin:0 0 16px;padding:16px;border:1px solid var(--ui-control-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface)}
  #animalServicesForm .animal-batch-row legend{padding:0 6px;font-weight:600;color:var(--ui-text)}
  #animalServicesForm .animal-row-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
  #animalServicesForm .animal-row-head p{margin:0;color:var(--ui-text-muted);font-size:14px}
  #animalServicesForm .animal-row-head button{flex:none;min-height:44px}
  #animalServicesForm .animal-batch-error{color:var(--ui-danger);font-size:13px}
  #animalServicesForm [hidden]{display:none!important}
  #animalServicesForm .animal-batch-lookup{padding:14px;margin-top:14px;border:1px solid var(--ui-border);border-radius:var(--ui-radius-panel);background:var(--ui-surface-subtle)}
  #animalServicesForm .animal-batch-lookup .module-actions{margin-top:10px}
  @media(max-width:520px){#animalServicesForm .animal-row-head{flex-direction:column}}
</style>
@endpush
@if($errors->any())<div class="module-alert module-alert-error" role="alert"><strong>Review the highlighted information. Nothing was saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="module-form-shell">
  <div class="module-form-main">
    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Owner and municipality</h2><p>All rows below belong to this owner and municipality.</p></div></div>
      <div class="module-form-body"><div class="module-form-grid">
        @if($canChooseMunicipality ?? false)
        <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>{{ $municipality->name }}, {{ $municipality->province }}</option>@endforeach</select></div>
        @endif
        <div class="module-form-field module-form-field-full"><label for="owner_name">Owner / raiser name <span class="module-required">*</span></label><input class="module-input" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" maxlength="120" list="ownerNameList" autocomplete="off" required><datalist id="ownerNameList">@foreach(($ownerNameOptions ?? []) as $name)<option value="{{ $name }}"></option>@endforeach</datalist></div>
        <div class="module-form-field"><label for="barangay">Barangay <span class="module-required">*</span></label><input class="module-input" id="barangay" name="barangay" value="{{ old('barangay') }}" maxlength="120" required></div>
        <div class="module-form-field"><label for="birthday">Owner birthday <span class="module-hint">Optional</span></label><input class="module-input" type="date" id="birthday" name="birthday" value="{{ old('birthday') }}" max="{{ $today }}"></div>
      </div>
      <p class="module-hint" id="animalLookupStatus" role="status" aria-live="polite">Type an existing owner's full name to find previously served animals.</p>
      <div class="animal-batch-lookup" id="animalLookupPanel" hidden>
        <label for="animalPriorSelect">Previously served animal or group</label><select class="module-input" id="animalPriorSelect"><option value="">Select an animal or group</option></select>
        <div class="module-actions"><button class="module-button" type="button" id="animalUsePrior">Add selected animal</button></div>
        <p class="module-hint">Reuses animal details only. Enter the product and service for today's visit.</p>
      </div></div>
    </section>
    <section class="module-form-section">
      <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Animals served</h2><p>For example: add a Dog row with 2 served, then a Cattle / Cow row with 1 served. Only include animals receiving a service.</p></div></div>
      <div class="module-form-body">
        <div id="animalRows">@foreach($rows as $index => $row) @include('anti_rabies_vaccinations._animal_row') @endforeach</div>
        <button class="module-button" type="button" id="animalAddRow" hidden>Add another animal or group</button>
        <p id="animalRowStatus" class="module-hint" role="status" aria-live="polite"></p>
        <noscript><p class="module-hint">Save one animal or group at a time with JavaScript off. Enable JavaScript to add more rows.</p></noscript>
      </div>
      <div class="module-form-actions"><a class="module-button" href="{{ route('anti-rabies-vaccinations.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">Save services</button></div>
    </section>
  </div>
  <aside class="module-form-aside"><section class="module-aside-card"><h3>One owner, several animals</h3><p>Each row saves a separate service record. Two dogs and one cow total three animals served. This records services, not a permanent animal inventory.</p><p>Choose the actual medicine or treatment for each row. It is never copied automatically to a different species.</p><p>Up to 20 rows can be saved together. If any row needs correction, none of the rows are saved. Edit saved records individually in the register.</p></section><section class="module-aside-card"><h3>Municipality ownership</h3><p>@if($canChooseMunicipality ?? false)All rows use the selected municipality.@else Assigned to <strong>{{ auth()->user()->municipality?->name ?? 'your municipal office' }}</strong>.@endif</p></section></aside>
</div>
<template id="animalRowTemplate" data-service-suggestions="{{ json_encode($serviceSuggestions) }}" data-breed-suggestions="{{ json_encode($breedsByType) }}">@include('anti_rabies_vaccinations._animal_row', ['index' => '__ROW__', 'row' => []])</template>
@push('scripts')<script src="{{ asset('js/animal-health-services.js') }}?v={{ filemtime(public_path('js/animal-health-services.js')) }}" defer></script>@endpush
