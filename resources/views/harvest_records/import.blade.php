@extends('layouts.app')

@section('title', 'Import Harvest Records')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header">
    <div>
      <div class="module-eyebrow">Bulk data workflow</div>
      <h1>Import harvests from Excel</h1>
      <p>Upload a filled-in harvest template to record many harvests at once, for any commodity.</p>
    </div>
    <div class="module-actions">
      <a class="module-button" href="{{ route('harvest-records.import.template') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 19h16"></path></svg>
        Download template
      </a>
      <a class="module-button" href="{{ route('harvest-records.index') }}">Back to harvest records</a>
    </div>
  </header>

  @if($errors->any())
    <div class="module-alert module-alert-error" role="alert">
      <strong>The workbook could not be imported.</strong>
      <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif
  @if(session('error'))<div class="module-alert module-alert-error" role="alert">{{ session('error') }}</div>@endif

  <form method="POST" action="{{ route('harvest-records.import') }}" enctype="multipart/form-data">@csrf
    <div class="module-form-shell">
      <div class="module-form-main">
        <section class="module-form-section">
          <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Choose record ownership</h2><p>Every imported harvest is recorded for this municipality, and any Farmer ID must belong to a farmer registered there.</p></div></div>
          <div class="module-form-body">
            @if($canChooseMunicipality)
              <div class="module-form-field module-form-field-full">
                <label for="municipality_id">Municipality <span class="module-required">*</span></label>
                <select class="module-input" id="municipality_id" name="municipality_id" required>
                  <option value="">Select municipality</option>
                  @foreach($municipalities as $municipality)
                    <option value="{{ $municipality->id }}" @selected((string) old('municipality_id') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>
                  @endforeach
                </select>
              </div>
            @else
              <div class="module-preview-item"><span>Municipality</span><strong>{{ auth()->user()->municipality?->name ?? 'Assigned municipal office' }}</strong></div>
            @endif
          </div>
        </section>
        <section class="module-form-section">
          <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Select the Excel workbook</h2><p>Accepted formats are .xlsx and .xls, up to 10 MB and {{ number_format($maxRows) }} harvest rows.</p></div></div>
          <div class="module-form-body">
            <label class="module-dropzone" for="harvestImportFile">
              <span class="module-dropzone-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"></path></svg></span>
              <span style="min-width:0;flex:1">
                <strong style="display:block;font-size:12px">Choose a harvest workbook</strong>
                <small style="display:block;margin:3px 0 8px;color:var(--module-muted);font-size:12px">Every row is checked before anything is saved.</small>
                <input id="harvestImportFile" type="file" name="file" accept=".xlsx,.xls" required>
              </span>
            </label>
          </div>
          <div class="module-form-actions"><a class="module-button" href="{{ route('harvest-records.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">Import workbook</button></div>
        </section>
      </div>
      <aside class="module-form-aside">
        <section class="module-aside-card">
          <h3>Before uploading</h3>
          <ul>
            <li><a href="{{ route('harvest-records.import.template') }}">Download the template</a> and keep its headings in row 1 of the Harvests sheet.</li>
            <li>Commodity, Harvest Year, Quantity and Unit are required. The other columns may be left blank.</li>
            <li>Commodity, Season and Unit accept the code or the label from the template's Allowed values sheet.</li>
            <li>Farmer ID accepts an AgriLGU ID, FFRS or RSBSA number. Leave it blank for a municipality-wide figure.</li>
            <li>Date Harvested must be an Excel date or YYYY-MM-DD, within one year of the Harvest Year.</li>
          </ul>
        </section>
        <section class="module-aside-card">
          <h3>Import behavior</h3>
          <p>If any row has a problem, nothing is saved and the rows to fix are listed. Rows that exactly match a harvest already recorded are skipped, so uploading the same file twice adds nothing. Imported harvests are not linked to parcels or planted sections.</p>
        </section>
      </aside>
    </div>
  </form>
</div>
@endsection
