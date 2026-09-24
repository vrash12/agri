<?php

// Export the actual provider scripts for pixel-level Node tests; no framework or credentials needed.
require __DIR__.'/../../app/Support/GeoGeometry.php';
require __DIR__.'/../../app/Support/SentinelParcel.php';
require __DIR__.'/../../app/Support/SentinelImagery.php';

$service = new App\Support\SentinelImagery(new App\Support\SentinelParcel(new App\Support\GeoGeometry));
echo json_encode(['statistics' => $service->statisticsScript(), 'ndvi' => $service->imageScript('ndvi'),
    'trueColor' => $service->imageScript('true-color')], JSON_THROW_ON_ERROR);
