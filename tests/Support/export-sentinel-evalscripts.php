<?php

use App\Support\GeoGeometry;
use App\Support\SatelliteRequestBudget;
use App\Support\SentinelImagery;
use App\Support\SentinelParcel;

// Export the actual provider scripts for pixel-level Node tests; no framework or credentials needed.
require __DIR__.'/../../app/Support/GeoGeometry.php';
require __DIR__.'/../../app/Support/SentinelParcel.php';
require __DIR__.'/../../app/Support/SentinelImagery.php';
require __DIR__.'/../../app/Support/SatelliteRequestBudget.php';

$service = new SentinelImagery(
    new SentinelParcel(new GeoGeometry),
    new SatelliteRequestBudget
);
echo json_encode(['statistics' => $service->statisticsScript(), 'ndvi' => $service->imageScript('ndvi'),
    'trueColor' => $service->imageScript('true-color')], JSON_THROW_ON_ERROR);
