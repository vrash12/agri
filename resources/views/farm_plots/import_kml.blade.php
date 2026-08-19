@extends('layouts.app')

@section('title', 'Import KML Plots')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h1 class="h1" style="margin:0;">Import Ramos KML</h1>
            <p class="p" style="margin-top:8px;">
                This imports KML parcel polygons into <strong>farm_plots</strong> and tries to match each placemark to a farmer using
                <strong>rsbsa_no</strong>, <strong>ffrs</strong>, or owner name.
            </p>
        </div>
    </div>

    <div style="padding:16px;">
        @if(session('success'))
            <div class="successbox">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="errorbox">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="errorbox">
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('farm-plots.import') }}" enctype="multipart/form-data">
            @csrf

            @if($canChooseMunicipality ?? false)
                <div style="max-width:520px; margin-bottom:14px;">
                    <label for="municipality_id">Municipality</label>
                    <select class="input" id="municipality_id" name="municipality_id" required>
                        <option value="">— Select Municipality —</option>
                        @foreach(($municipalities ?? []) as $municipality)
                            <option value="{{ $municipality->id }}" @selected((string) old('municipality_id') === (string) $municipality->id)>
                                {{ $municipality->name }}{{ $municipality->province ? ', ' . $municipality->province : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="p" style="margin-top:6px;">Only farmers from this municipality will be considered during matching.</div>
                </div>
            @endif

            <div style="max-width:520px;">
                <label for="file">KML File</label>
                <input class="input" type="file" id="file" name="file" accept=".kml,.xml" required>
            </div>

            <div style="margin-top:16px;">
                <button class="btn" type="submit">Import KML</button>
            </div>
        </form>
    </div>
</div>
@endsection
