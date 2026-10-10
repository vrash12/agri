<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Satellite field check | AgriLGU</title>
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/parcel-satellite.css') }}?v={{ @filemtime(public_path('css/parcel-satellite.css')) ?: 1 }}">
  <style>
    html, body { margin: 0; min-height: 100%; background: var(--ui-bg); }
    body { padding: 18px; box-sizing: border-box; font-family:var(--ui-font); }
    .satellite-page { max-width: none; }
    @media(max-width:600px) { body { padding:12px; } }
  </style>
</head>
<body>
  @include('farm_plots._satellite_content', ['modal' => true])
</body>
</html>
