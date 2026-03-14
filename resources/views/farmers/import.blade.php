@extends('layouts.app')

@section('title', 'Import Farmers')

@section('content')
<div style="max-width:900px; margin:0; padding:16px;">
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Import Farmers from Excel</h1>
        <p class="p">Upload the RSBSA/FFRS Excel file (.xlsx). The system will insert/update farmers.</p>
      </div>
    </div>

    <div style="padding:16px;">
      <form method="POST" action="{{ route('farmers.import') }}" enctype="multipart/form-data">
        @csrf

        <div style="margin-bottom:12px;">
          <label>Excel File (.xlsx)</label>
          <input class="input" type="file" name="file" accept=".xlsx,.xls" required>
          @error('file')
            <div style="color:#c00; margin-top:6px;">{{ $message }}</div>
          @enderror
        </div>

        <div style="display:flex; gap:10px;">
          <button class="btn" type="submit">Import</button>
          <a class="btn btn-soft" href="{{ route('farmers.index') }}" style="box-shadow:none;">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
