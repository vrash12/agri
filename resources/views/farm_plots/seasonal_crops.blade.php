@extends('layouts.app')
@section('title', 'Crop areas for this season')
@push('styles')
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/planted-area-editor.css') }}?v={{ @filemtime(public_path('css/planted-area-editor.css')) ?: 1 }}">
@endpush
@php
  $mapUrl = route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id, 'map_farmer' => $plot->farmer_id, 'crop_year' => $year, 'crop_season' => $season]).'#farmersMapModule';
  $canEdit = auth()->user()->can('update', $plot);
  $savedAreas = old('planted_areas', $cropRecord?->planted_areas ?? []);
  if (is_string($savedAreas)) $savedAreas = json_decode($savedAreas, true) ?: [];
  $hasAreas = is_array($savedAreas) && count($savedAreas) > 0;
@endphp
@section('content')
<div class="module-page seasonal-crop-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · Farm parcel</div>
      <h1>{{ $canEdit ? 'Mark the crops on this land' : 'Crops on this land' }}</h1>
      <p>{{ $plot->name ?: 'Parcel #'.$plot->id }} · {{ trim($plot->farmer->first_name.' '.$plot->farmer->last_name) }}</p>
      <div class="crop-context"><span>{{ number_format((float) $plot->area_ha, 2) }} ha parcel</span><span>{{ $year }} · {{ $seasonChoices[$season] }}</span>@unless($canEdit)<span>Read-only</span>@endunless</div>
    </div>
    <a class="module-button" href="{{ $mapUrl }}">← Back to parcel map</a>
  </header>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert">
      <strong>Your changes were not saved. Please check:</strong>
      <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      @if($errors->has('_record_version') || $errors->has('_plot_version'))
        <a href="{{ route('farm-plots.seasonal-crops.edit', ['plot' => $plot->id, 'year' => $year, 'season' => $season]) }}">Reload the latest record before saving</a>
      @endif
    </div>
  @endif

  <section class="module-panel crop-period-panel" aria-labelledby="cropPeriodHeading">
    <div class="crop-period-label"><span class="crop-step" aria-hidden="true">1</span><div><h2 id="cropPeriodHeading">Choose the season</h2><p>Showing {{ $year }} · {{ $seasonChoices[$season] }}</p></div></div>
    <form class="crop-period-actions" method="GET" action="{{ route('farm-plots.seasonal-crops.edit', $plot) }}">
      <x-module.field name="year" label="Year" :required="true">
        <input class="module-input" id="year" name="year" type="number" min="1990" max="{{ \App\Support\LocalTime::now()->year + 1 }}" value="{{ $year }}" required>
      </x-module.field>
      <x-module.field name="season" id="period_season" label="Season" :required="true">
        <select class="module-input" id="period_season" name="season" required>
          @foreach($seasonChoices as $code => $label)<option value="{{ $code }}" @selected($season === $code)>{{ $label }}</option>@endforeach
        </select>
      </x-module.field>
      <button class="module-button" type="submit">Show season</button>
    </form>
  </section>

  <section class="module-panel" aria-labelledby="cropRecordHeading">
    <div class="module-panel-head"><div class="crop-section-heading"><span class="crop-step" aria-hidden="true">2</span><div><h2 id="cropRecordHeading">{{ $canEdit ? 'Mark each planted area' : 'Recorded planted areas' }}</h2><p>{{ $canEdit ? 'Choose a crop, then draw its area inside the white parcel outline.' : 'Choose an area to see its boundary on the map.' }}</p></div></div></div>
    @if($canEdit)
      <form id="seasonalCropForm" method="POST" action="{{ route('farm-plots.seasonal-crops.store', $plot) }}">
        @csrf
        <input type="hidden" name="crop_year" value="{{ $year }}">
        <input type="hidden" name="season" value="{{ $season }}">
        <input type="hidden" name="_record_version" value="{{ old('_record_version', $recordVersion) }}">
        @if($plantedAreasAvailable)
          @include('farm_plots.partials.planted-area-editor', ['canDraw' => true])
        @else
          <p class="module-form-body crop-record-note">Drawing is unavailable. You can still choose a crop for the whole parcel below.</p>
        @endif
        <details class="crop-record-details" @if(!$hasAreas || $errors->has('crop') || $errors->has('notes')) open @endif>
          <summary>Season notes and whole-parcel crop</summary>
          <div class="module-form-body module-form-grid">
            <x-module.field name="crop" label="Crop for the whole parcel" :required="true" :full="true" hint="If you draw areas, this follows their crops automatically. Without drawings, choose the crop here. Not recorded means unknown.">
              <select class="module-input" id="crop" name="crop" required aria-describedby="crop_hint crop_error">
                @foreach($cropChoices as $code => $label)<option value="{{ $code }}" @selected(old('crop', $cropRecord?->crop ?? 'not_recorded') === $code)>{{ $label }}</option>@endforeach
              </select>
            </x-module.field>
            <x-module.field name="notes" label="Notes for this season" :full="true" hint="Optional: field visit date or how the crops were confirmed. Leave out private contact details.">
              <textarea class="module-input" name="notes" id="notes" maxlength="500" rows="3" aria-describedby="notes_hint notes_error">{{ old('notes', $cropRecord?->notes) }}</textarea>
            </x-module.field>
          </div>
        </details>
        <div class="crop-save-bar">
          <div class="crop-section-heading"><span class="crop-step" aria-hidden="true">3</span><div><strong>Save this season</strong><p id="plantedSaveState">{{ $cropRecord ? 'No changes yet.' : 'Nothing saved for this season yet.' }}</p><p>Saved crop icons also appear on the parcel map.</p></div></div>
          <button class="module-button module-button-primary" type="submit">Save season</button>
        </div>
      </form>
    @else
      @if($plantedAreasAvailable) @include('farm_plots.partials.planted-area-editor', ['canDraw' => false]) @endif
      <div class="module-form-body crop-readonly-note">
        <strong>Recorded crop:</strong> {{ $cropChoices[$cropRecord?->crop ?? 'not_recorded'] ?? 'Not recorded' }}
        @if($cropRecord?->notes)<p>{{ $cropRecord->notes }}</p>@endif
        <p>Agricultural staff can update this record. Your account can view it.</p>
      </div>
    @endif
  </section>

  <details class="module-panel crop-history" @if(request()->has('page')) open @endif>
    <summary id="cropHistoryHeading">Earlier season records <span>{{ $history->total() }} {{ $history->total() === 1 ? 'record' : 'records' }}</span></summary>
    <div class="module-table-scroll"><table class="module-table" aria-labelledby="cropHistoryHeading">
      <thead><tr><th scope="col">Season</th><th scope="col">Crop</th><th scope="col">Last updated</th><th scope="col">Action</th></tr></thead>
      <tbody>@forelse($history as $entry)<tr>
        <th scope="row">{{ $entry->crop_year }} · {{ $seasonChoices[$entry->season] ?? $entry->season }}</th>
        <td>{{ $cropChoices[$entry->crop] ?? 'Not recorded' }}</td>
        <td>{{ \App\Support\LocalTime::fromUtc($entry->updated_at)?->format('M d, Y h:i A') }}</td>
        <td><a class="module-button module-button-sm" href="{{ route('farm-plots.seasonal-crops.edit', ['plot' => $plot->id, 'year' => $entry->crop_year, 'season' => $entry->season]) }}">Open season</a></td>
      </tr>@empty<tr><td colspan="4">No seasons saved yet.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $history->links() }}
  </details>
  <p class="crop-record-note crop-footnote">These records describe crops reported for a season, not a live crop survey. Mapped areas are approximate; unmarked land remains unclassified.</p>
</div>
@endsection
