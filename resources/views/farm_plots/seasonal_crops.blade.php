@extends('layouts.app')
@section('title', 'Seasonal parcel crops')
@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .seasonal-crop-page { max-width: 1050px; margin: 0 auto; }
    .seasonal-crop-page .module-panel { margin-bottom: 20px; }
    .seasonal-crop-page .crop-period-actions { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; }
    .seasonal-crop-page .crop-period-actions .module-form-field { flex: 1 1 150px; }
    .seasonal-crop-page .crop-record-note { color: var(--module-muted); line-height: 1.6; }
  </style>
@endpush
@php
  $mapUrl = route('farmers.index', ['municipality_id' => $plot->farmer->municipality_id]).'#farmersMapModule';
  $canEdit = auth()->user()->can('update', $plot);
@endphp
@section('content')
<div class="module-page seasonal-crop-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">{{ $plot->farmer->municipality?->name }} · Parcel crops</div>
      <h1>Seasonal crops</h1>
      <p>{{ $plot->name ?: 'Parcel #'.$plot->id }} · {{ trim($plot->farmer->first_name.' '.$plot->farmer->last_name) }} · {{ number_format((float) $plot->area_ha, 2) }} ha parcel area</p>
    </div>
    <a class="module-button" href="{{ $mapUrl }}">Back to parcel map</a>
  </header>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert">
      <strong>Check the seasonal crop record.</strong>
      <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      @if($errors->has('_record_version'))
        <a href="{{ route('farm-plots.seasonal-crops.edit', ['plot' => $plot->id, 'year' => $year, 'season' => $season]) }}">Reload the latest record before saving</a>
      @endif
    </div>
  @endif

  <section class="module-panel" aria-labelledby="cropPeriodHeading">
    <div class="module-panel-head"><div><h2 id="cropPeriodHeading">Choose the reporting period</h2><p>Choose a year and season, then load its record.</p></div></div>
    <form class="module-form-body crop-period-actions" method="GET" action="{{ route('farm-plots.seasonal-crops.edit', $plot) }}">
      <x-module.field name="year" label="Year" :required="true">
        <input class="module-input" id="year" name="year" type="number" min="1990" max="{{ \App\Support\LocalTime::now()->year + 1 }}" value="{{ $year }}" required>
      </x-module.field>
      <x-module.field name="season" id="period_season" label="Season" :required="true">
        <select class="module-input" id="period_season" name="season" required>
          @foreach($seasonChoices as $code => $label)<option value="{{ $code }}" @selected($season === $code)>{{ $label }}</option>@endforeach
        </select>
      </x-module.field>
      <button class="module-button" type="submit">Load season</button>
    </form>
  </section>

  <section class="module-panel" aria-labelledby="cropRecordHeading">
    <div class="module-panel-head"><div><h2 id="cropRecordHeading">{{ $year }} · {{ $seasonChoices[$season] }}</h2><p>Record the crop confirmed for this parcel and season. This classification does not establish the crop currently growing.</p></div></div>
    @if($canEdit)
      <form method="POST" action="{{ route('farm-plots.seasonal-crops.store', $plot) }}">
        @csrf
        <input type="hidden" name="crop_year" value="{{ $year }}">
        <input type="hidden" name="season" value="{{ $season }}">
        <input type="hidden" name="_record_version" value="{{ old('_record_version', $recordVersion) }}">
        <div class="module-form-body module-form-grid">
          <x-module.field name="crop" label="Recorded crop" :required="true" :full="true" hint="For intercropping or several crops in the same season, choose Mixed crops and describe them below. Not recorded means unknown, not fallow land.">
            <select class="module-input" id="crop" name="crop" required aria-describedby="crop_hint crop_error">
              @foreach($cropChoices as $code => $label)<option value="{{ $code }}" @selected(old('crop', $cropRecord?->crop ?? 'not_recorded') === $code)>{{ $label }}</option>@endforeach
            </select>
          </x-module.field>
          <x-module.field name="notes" label="Crop details or source" :full="true" hint="Optional: variety, crops grown together, or date of the field visit. Do not include private contact details.">
            <textarea class="module-input" name="notes" id="notes" maxlength="500" rows="3" aria-describedby="notes_hint notes_error">{{ old('notes', $cropRecord?->notes) }}</textarea>
          </x-module.field>
        </div>
        <div class="module-form-actions"><button class="module-button module-button-primary" type="submit">Save seasonal crop</button></div>
      </form>
    @else
      <div class="module-form-body">
        <p><strong>Recorded crop:</strong> {{ $cropChoices[$cropRecord?->crop ?? 'not_recorded'] ?? 'Not recorded' }}</p>
        @if($cropRecord?->notes)<p>{{ $cropRecord->notes }}</p>@endif
        <p class="crop-record-note">Your account has read-only oversight. Agricultural staff can record seasonal crops.</p>
      </div>
    @endif
  </section>

  <section class="module-panel" aria-labelledby="cropHistoryHeading">
    <div class="module-panel-head"><div><h2 id="cropHistoryHeading">Recorded seasons</h2><p>Each row is the latest classification for one season. Crop colors do not measure planted area or harvest.</p></div></div>
    <div class="module-table-scroll"><table class="module-table">
      <thead><tr><th scope="col">Period</th><th scope="col">Crop</th><th scope="col">Last updated</th><th scope="col">Action</th></tr></thead>
      <tbody>@forelse($history as $entry)<tr>
        <th scope="row">{{ $entry->crop_year }} · {{ $seasonChoices[$entry->season] ?? $entry->season }}</th>
        <td>{{ $cropChoices[$entry->crop] ?? 'Not recorded' }}</td>
        <td>{{ \App\Support\LocalTime::fromUtc($entry->updated_at)?->format('M d, Y h:i A') }}</td>
        <td><a class="module-button module-button-sm" href="{{ route('farm-plots.seasonal-crops.edit', ['plot' => $plot->id, 'year' => $entry->crop_year, 'season' => $entry->season]) }}">Open season</a></td>
      </tr>@empty<tr><td colspan="4">No seasonal crop records yet. This parcel will appear as Not recorded.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $history->links() }}
  </section>
</div>
@endsection
