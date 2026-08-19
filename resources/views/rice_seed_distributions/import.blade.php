@extends('layouts.app')

@section('title', 'Import Rice Seed Distribution')

@push('styles')
  @include('partials.operations-ui-styles')
@endpush

@section('content')
<div class="module-page">
  <header class="module-header"><div><div class="module-eyebrow">Bulk data workflow</div><h1>Import rice releases</h1><p>Upload an NRP Excel workbook to insert new distributions or update matching records.</p></div><div class="module-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Back to register</a></div></header>

  @if($errors->any())<div class="module-alert module-alert-error"><strong>The workbook could not be imported.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('rice-seed-distributions.import') }}" enctype="multipart/form-data">@csrf
    <div class="module-form-shell">
      <div class="module-form-main">
        <section class="module-form-section">
          <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Choose record ownership</h2><p>Every imported row will be assigned to this municipality.</p></div></div>
          <div class="module-form-body">
            @if($canChooseMunicipality ?? false)
              <div class="module-form-field module-form-field-full"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach(($municipalities ?? []) as $municipality)<option value="{{ $municipality->id }}" @selected((string) old('municipality_id') === (string) $municipality->id)>{{ $municipality->name }}{{ $municipality->province ? ', '.$municipality->province : '' }}</option>@endforeach</select></div>
            @else
              <div class="module-preview-item"><span>Municipality</span><strong>{{ auth()->user()->municipality?->name ?? 'Assigned municipal office' }}</strong></div>
            @endif
          </div>
        </section>
        <section class="module-form-section">
          <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Select the Excel workbook</h2><p>Accepted formats are .xlsx and .xls.</p></div></div>
          <div class="module-form-body"><label class="module-dropzone" for="riceImportFile"><span class="module-dropzone-icon"><svg viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"></path></svg></span><span style="min-width:0;flex:1"><strong style="display:block;font-size:11px">Choose an NRP workbook</strong><small style="display:block;margin:3px 0 8px;color:var(--module-muted);font-size:9px">The import validates rows before saving.</small><input id="riceImportFile" type="file" name="file" accept=".xlsx,.xls" required></span></label></div>
          <div class="module-form-actions"><a class="module-button" href="{{ route('rice-seed-distributions.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">Import workbook</button></div>
        </section>
      </div>
      <aside class="module-form-aside"><section class="module-aside-card"><h3>Before uploading</h3><ul><li>Use the expected NRP column layout.</li><li>Confirm the municipality before import.</li><li>Keep farmer identifiers consistent.</li><li>Review validation messages if rows are skipped.</li></ul></section><section class="module-aside-card"><h3>Import behavior</h3><p>Matching farmer records are linked within the selected municipality. Existing matching releases may be updated rather than duplicated.</p></section></aside>
    </div>
  </form>
</div>
@endsection
