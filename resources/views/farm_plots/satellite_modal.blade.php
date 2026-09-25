<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Parcel health view | AgriGOV</title>
  @include('partials.operations-ui-styles')
  <link rel="stylesheet" href="{{ asset('css/parcel-satellite.css') }}?v={{ @filemtime(public_path('css/parcel-satellite.css')) ?: 1 }}">
  <style>
    html, body { margin: 0; min-height: 100%; background: #f7faf8; }
    body { padding: 18px; box-sizing: border-box; }
    .satellite-page { max-width: none; }
    .satellite-page .module-header { position: sticky; top: 0; z-index: 2; }
  </style>
</head>
<body>
  @include('farm_plots._satellite_content', ['modal' => true])
</body>
</html>
