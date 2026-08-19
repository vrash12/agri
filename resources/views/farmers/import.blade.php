@extends('layouts.app')

@section('title', 'Import Farmers')

@section('content')
@include('partials.operations-ui-styles')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Farmer registry</div><h1>Import farmer workbook</h1><p>Load the Ramos RSBSA / FFRS workbook into one municipality. Existing profiles are updated when their registry identifier matches.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('farmers.index') }}">Back to registry</a></div>
  </header>

  @if($errors->any())<div class="module-alert module-alert-error"><strong>The workbook could not be imported.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <form class="module-form-shell" method="POST" action="{{ route('farmers.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="module-form-main">
      <section class="module-form-section">
        <div class="module-form-section-head"><span class="module-step">1</span><div><h2>Select workbook</h2><p>The importer reads PARCEL LISTING and OUTSIDE LGU sheets.</p></div></div>
        <div class="module-form-body"><label class="module-dropzone"><span class="module-dropzone-icon"><svg viewBox="0 0 24 24"><path d="M12 3v12m0-12-4 4m4-4 4 4M5 15v4h14v-4"/></svg></span><span><strong>Official Excel workbook</strong><small>.xlsx or .xls only</small><input type="file" name="file" accept=".xlsx,.xls" required></span></label></div>
      </section>
      @if($canChooseMunicipality ?? false)
        <section class="module-form-section">
          <div class="module-form-section-head"><span class="module-step">2</span><div><h2>Choose municipality</h2><p>Every imported farmer will be owned by this municipality.</p></div></div>
          <div class="module-form-body"><div class="module-form-field"><label for="municipality_id">Municipality <span class="module-required">*</span></label><select class="module-input" id="municipality_id" name="municipality_id" required><option value="">Select municipality</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected((string)old('municipality_id') === (string)$municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div></div>
        </section>
      @endif
    </div>
    <aside class="module-form-aside">
      <section class="module-aside-card"><h3>Import behavior</h3><ul><li>Rows with matching registry identifiers are updated.</li><li>New valid rows create farmer profiles.</li><li>Blank and invalid rows are skipped safely.</li></ul></section>
      <section class="farmer-import-submit"><button class="module-button module-button-primary" type="submit">Import workbook</button><a class="module-button" href="{{ route('farmers.index') }}">Cancel</a></section>
    </aside>
  </form>
</div>
@endsection

@push('styles')
<style>.module-dropzone>span:last-child{display:grid;gap:3px;width:100%}.module-dropzone strong{color:var(--module-ink);font-size:11px}.module-dropzone small{color:var(--module-muted);font-size:9px}.module-dropzone input{margin-top:7px}.farmer-import-submit{display:grid;gap:7px;padding:12px;border:1px solid var(--module-border);border-radius:10px;background:#fff}.farmer-import-submit .module-button{width:100%}</style>
@endpush
