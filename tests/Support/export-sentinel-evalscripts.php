<?php

use App\Modules\Climate\Application\SatelliteRequestBudget;
use App\Modules\Climate\Application\SentinelImagery;
use App\Modules\Climate\Application\SentinelParcel;
use App\Support\GeoGeometry;

// Export the actual provider scripts for pixel-level Node tests; no framework or credentials needed.
require __DIR__.'/../../app/Support/GeoGeometry.php';
require __DIR__.'/../../app/Modules/Climate/Application/SentinelParcel.php';
require __DIR__.'/../../app/Modules/Climate/Application/SentinelImagery.php';
require __DIR__.'/../../app/Modules/Climate/Application/SatelliteRequestBudget.php';

$service = new SentinelImagery(
    new SentinelParcel(new GeoGeometry),
    new SatelliteRequestBudget
);
echo json_encode(['statistics' => $service->statisticsScript(), 'ndvi' => $service->imageScript('ndvi'),
    'trueColor' => $service->imageScript('true-color')], JSON_THROW_ON_ERROR);
