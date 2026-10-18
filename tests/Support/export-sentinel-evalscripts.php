<?php

use App\Modules\Climate\Application\SatelliteIndex;
use App\Modules\Climate\Application\SatelliteRequestBudget;
use App\Modules\Climate\Application\SentinelImagery;
use App\Modules\Climate\Application\SentinelParcel;
use App\Support\GeoGeometry;

// Export the actual provider scripts for pixel-level Node tests; no framework or credentials needed.
require __DIR__.'/../../app/Support/GeoGeometry.php';
require __DIR__.'/../../app/Modules/Climate/Application/SentinelParcel.php';
require __DIR__.'/../../app/Modules/Climate/Application/SentinelImagery.php';
require __DIR__.'/../../app/Modules/Climate/Application/SatelliteRequestBudget.php';
require __DIR__.'/../../app/Modules/Climate/Application/SatelliteIndex.php';

$service = new SentinelImagery(
    new SentinelParcel(new GeoGeometry),
    new SatelliteRequestBudget
);
$scripts = ['statistics' => $service->statisticsScript(), 'ndvi' => $service->imageScript('ndvi'),
    'trueColor' => $service->imageScript('true-color'), 'definitions' => SatelliteIndex::definitions()];
foreach (SatelliteIndex::IDS as $index) {
    $scripts['indexes'][$index] = ['statistics' => $service->statisticsScript($index), 'image' => $service->imageScript($index)];
}
echo json_encode($scripts, JSON_THROW_ON_ERROR);
