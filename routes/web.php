<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgriculturalMachineryController;
use App\Http\Controllers\AntiRabiesVaccinationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmersCooperativeController;
use App\Http\Controllers\FarmPlotController;
use App\Http\Controllers\HarvestRecordController;
use App\Http\Controllers\MunicipalityBoundaryController;
use App\Http\Controllers\RiceDistributionBatchController;
use App\Http\Controllers\RiceSeedDistributionController;
use App\Http\Controllers\WeatherAdvisoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (! auth()->check()) {
        return view('welcome');
    }

    if (auth()->user()->isProvincialVeterinaryOffice()) {
        return redirect()->route('anti-rabies-vaccinations.index');
    }

    return auth()->user()->isGisEvaluator()
        ? redirect()->route('municipality-boundaries.index')
        : redirect()->route('dashboard');
})->name('welcome');

/*
|--------------------------------------------------------------------------
| GUEST ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    // Per-address guessing is stopped in AuthController; this caps one host's overall
    // sign-in traffic while staying generous enough for a whole office behind one IP.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:30,1')
        ->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'idle'])
    ->name('logout');

Route::post('/session/heartbeat', function () {
    return response()->noContent();
})
    ->middleware(['auth', 'idle', 'throttle:12,1'])
    ->name('session.heartbeat');

Route::post('/session/timeout', [AuthController::class, 'timeout'])
    ->middleware('auth')
    ->name('session.timeout');

/*
|--------------------------------------------------------------------------
| PUBLIC FARMER LAND VERIFICATION
|--------------------------------------------------------------------------
|
| The unguessable token is printed as a QR code on the farmer ID. The page
| intentionally exposes only a registry summary and read-only parcel map.
|
*/
Route::get('/land/{token}', [FarmerController::class, 'publicLand'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:60,1')
    ->name('farmers.public-land');

// Farmer identities use an independent session guard and never enter office routes.
Route::prefix('farmer-portal')->name('farmer-portal.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\FarmerPortalAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\FarmerPortalAuthController::class, 'login'])->middleware('throttle:30,1')->name('login.attempt');
    Route::get('/activate', [\App\Http\Controllers\FarmerPortalAuthController::class, 'showActivation'])->name('activate');
    Route::post('/activate', [\App\Http\Controllers\FarmerPortalAuthController::class, 'activate'])->middleware('throttle:20,1')->name('activate.submit');
    Route::post('/logout', [\App\Http\Controllers\FarmerPortalAuthController::class, 'logout'])->name('logout');
    Route::middleware(\App\Http\Middleware\EnsureFarmerPortalSession::class)->group(function () {
        Route::get('/', [\App\Http\Controllers\FarmerPortalController::class, 'home'])->name('home');
        Route::get('/profile', [\App\Http\Controllers\FarmerPortalController::class, 'profile'])->name('profile');
        Route::get('/parcels', [\App\Http\Controllers\FarmerPortalController::class, 'parcels'])->name('parcels');
        Route::get('/parcels/geometry', [\App\Http\Controllers\FarmerPortalController::class, 'mapParcels'])->middleware('throttle:30,1')->name('parcels.geometries');
        Route::get('/parcels/{plot}', [\App\Http\Controllers\FarmerPortalController::class, 'map'])->whereNumber('plot')->name('parcels.map');
        Route::get('/parcels/{plot}/geometry', [\App\Http\Controllers\FarmerPortalController::class, 'geometry'])->whereNumber('plot')->middleware('throttle:30,1')->name('parcels.geometry');
        Route::get('/assistance', [\App\Http\Controllers\FarmerPortalController::class, 'assistance'])->name('assistance');
        Route::get('/harvests', [\App\Http\Controllers\FarmerPortalController::class, 'harvests'])->name('harvests');
        Route::post('/heartbeat', fn () => response()->noContent())->middleware('throttle:12,1')->name('heartbeat');
    });
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth',
    'idle',
    'account-scope',
    'provincial-vet-scope',
    'gis-evaluator-scope',
    'synchronized',
])->group(function () {
    Route::get('/evaluation/password', [\App\Http\Controllers\EvaluatorPasswordController::class, 'edit'])->name('evaluation.password');
    Route::post('/evaluation/password', [\App\Http\Controllers\EvaluatorPasswordController::class, 'update'])->middleware('throttle:5,1')->name('evaluation.password.update');
    Route::get('/farmers/{farmer}/portal-account', [\App\Http\Controllers\FarmerPortalAccountController::class, 'show'])->name('farmers.portal-account.show');
    Route::post('/farmers/{farmer}/portal-account/activation', [\App\Http\Controllers\FarmerPortalAccountController::class, 'issue'])->middleware('throttle:10,1')->name('farmers.portal-account.issue');
    Route::post('/farmers/{farmer}/portal-account/disable', [\App\Http\Controllers\FarmerPortalAccountController::class, 'disable'])->middleware('throttle:10,1')->name('farmers.portal-account.disable');
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/assistance-coverage', [\App\Http\Controllers\AssistanceCoverageController::class, 'index'])
        ->name('assistance-coverage.index');
    Route::get('/assistance-coverage/boundaries', [\App\Http\Controllers\AssistanceCoverageController::class, 'boundaries'])
        ->middleware('throttle:60,1')->name('assistance-coverage.boundaries');

    Route::get('/municipality-boundaries', [MunicipalityBoundaryController::class, 'index'])
        ->name('municipality-boundaries.index');
    Route::get('/municipality-boundaries/data', [MunicipalityBoundaryController::class, 'data'])
        ->middleware('throttle:30,1')
        ->name('municipality-boundaries.data');
    Route::get('/municipality-boundaries/barangays', \App\Http\Controllers\BarangayBoundaryController::class)
        ->middleware('throttle:30,1')
        ->name('municipality-boundaries.barangays');
    Route::get('/municipality-boundaries/{boundary}/snapshot-base', [MunicipalityBoundaryController::class, 'snapshotBase'])
        ->middleware('throttle:12,1')
        ->name('municipality-boundaries.snapshot-base');
    Route::post('/municipality-boundaries/{boundary}/snapshot-exported', [MunicipalityBoundaryController::class, 'snapshotExported'])
        ->middleware('throttle:12,1')
        ->name('municipality-boundaries.snapshot-exported');
    Route::post('/municipality-boundaries', [MunicipalityBoundaryController::class, 'store'])
        ->name('municipality-boundaries.store');
    Route::post('/municipality-boundaries/import', [MunicipalityBoundaryController::class, 'import'])
        ->middleware('throttle:6,1')
        ->name('municipality-boundaries.import');
    Route::put('/municipality-boundaries/{boundary}', [MunicipalityBoundaryController::class, 'update'])
        ->name('municipality-boundaries.update');
    Route::patch('/municipality-boundaries/{boundary}/style', [MunicipalityBoundaryController::class, 'style'])
        ->name('municipality-boundaries.style');
    Route::post('/municipality-boundaries/{boundary}/activate', [MunicipalityBoundaryController::class, 'activate'])
        ->name('municipality-boundaries.activate');
    Route::post('/municipality-boundaries/{boundary}/archive', [MunicipalityBoundaryController::class, 'archive'])
        ->name('municipality-boundaries.archive');

    Route::get('/weather-advisories', [WeatherAdvisoryController::class, 'index'])
        ->name('weather.index');

    Route::post('/weather-advisories/refresh', [WeatherAdvisoryController::class, 'refresh'])
        ->middleware('throttle:6,1')
        ->name('weather.refresh');

    Route::get('/farmers/weather-summary', [WeatherAdvisoryController::class, 'summary'])
        ->middleware('throttle:30,1')
        ->name('farmers.weather-summary');

    Route::post('/farmers/weather-summary/refresh', [WeatherAdvisoryController::class, 'refreshSummary'])
        ->middleware('throttle:6,1')
        ->name('farmers.weather-refresh');

    /*
    |--------------------------------------------------------------------------
    | GEOCODING API
    |--------------------------------------------------------------------------
    */
    Route::get('/api/geocode', function (Request $request) {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json([
                'error' => 'Missing q',
            ], 422);
        }

        $cacheKey = 'geocode:'.sha1($query);

        return Cache::remember(
            $cacheKey,
            now()->addHours(12),
            function () use ($query) {
                $response = Http::withHeaders([
                    'User-Agent' => 'AgriMS-Tarlac/1.0',
                    'Accept' => 'application/json',
                ])
                    ->timeout(10)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'format' => 'json',
                        'limit' => 1,
                        'q' => $query,
                    ]);

                if (! $response->ok()) {
                    return response()->json([
                        'error' => 'Geocode failed',
                        'status' => $response->status(),
                    ], 502);
                }

                $results = $response->json();

                if (! is_array($results) || count($results) < 1) {
                    return response()->json([
                        'lat' => null,
                        'lng' => null,
                    ]);
                }

                return response()->json([
                    'lat' => isset($results[0]['lat'])
                        ? (float) $results[0]['lat']
                        : null,
                    'lng' => isset($results[0]['lon'])
                        ? (float) $results[0]['lon']
                        : null,
                ]);
            }
        );
    })
        ->middleware('throttle:30,1')
        ->name('geocode');

    /*
    |--------------------------------------------------------------------------
    | FARM PLOTS AND MAP ENDPOINTS
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/farm-plots/import-kml',
        [FarmPlotController::class, 'importKmlForm']
    )->name('farm-plots.import.form');

    Route::post(
        '/farm-plots/import-kml',
        [FarmPlotController::class, 'importKml']
    )->middleware('throttle:6,1')->name('farm-plots.import');

    Route::get(
        '/farm-plots/all',
        [FarmPlotController::class, 'all']
    )->name('farm-plots.all');

    Route::get('/farm-plots/crop-layer', [\App\Http\Controllers\ParcelCropSeasonController::class, 'layer'])
        ->name('farm-plots.crop-layer');
    Route::get('/farm-plots/{plot}/satellite', [\App\Http\Controllers\ParcelSatelliteController::class, 'show'])
        ->whereNumber('plot')->name('farm-plots.satellite.show');
    Route::post('/farm-plots/{plot}/satellite/observations', [\App\Http\Controllers\ParcelSatelliteController::class, 'analyse'])
        ->whereNumber('plot')->middleware('throttle:6,1')->name('farm-plots.satellite.analyse');
    Route::get('/farm-plots/{plot}/satellite/image', [\App\Http\Controllers\ParcelSatelliteController::class, 'image'])
        ->whereNumber('plot')->middleware('throttle:12,1')->name('farm-plots.satellite.image');
    Route::get('/farm-plots/{plot}/seasonal-crops', [\App\Http\Controllers\ParcelCropSeasonController::class, 'edit'])
        ->name('farm-plots.seasonal-crops.edit');
    Route::post('/farm-plots/{plot}/seasonal-crops', [\App\Http\Controllers\ParcelCropSeasonController::class, 'store'])
        ->name('farm-plots.seasonal-crops.store');

    Route::get(
        '/farm-plots/{plot}/static-map',
        [FarmPlotController::class, 'staticMap']
    )
        ->middleware('throttle:30,1')
        ->name('farm-plots.static-map');

    Route::get(
        '/farmers/{farmer}/plots',
        [FarmPlotController::class, 'index']
    )->name('farmers.plots.index');

    Route::post(
        '/farmers/{farmer}/plots',
        [FarmPlotController::class, 'store']
    )->name('farmers.plots.store');

    Route::get(
        '/farmers/{farmer}/map-card',
        [FarmerController::class, 'mapCard']
    )->name('farmers.map-card');

    Route::put(
        '/farm-plots/{plot}',
        [FarmPlotController::class, 'update']
    )->name('farm-plots.update');

    Route::delete(
        '/farm-plots/{plot}',
        [FarmPlotController::class, 'destroy']
    )->name('farm-plots.destroy');

    /*
    |--------------------------------------------------------------------------
    | FARMERS
    |--------------------------------------------------------------------------
    */
    Route::get('/farmers', [FarmerController::class, 'index'])
        ->name('farmers.index');

    // Declared before /farmers/{farmer} so the wildcard does not swallow it.
    Route::get('/farmers/lookup', [FarmerController::class, 'lookup'])
        ->name('farmers.lookup');

    // The beneficiary picker used by the assistance and harvest forms. Separate
    // from the map's lookup above because the two need different farmer fields.
    Route::get('/farmers/picker', [FarmerController::class, 'picker'])
        ->name('farmers.picker');

    Route::get('/farmers/create', [FarmerController::class, 'create'])
        ->name('farmers.create');

    Route::post('/farmers', [FarmerController::class, 'store'])
        ->name('farmers.store');

    Route::get('/farmers/import', [FarmerController::class, 'showImport'])
        ->name('farmers.import.form');

    Route::post('/farmers/import', [FarmerController::class, 'import'])
        ->middleware('throttle:6,1')
        ->name('farmers.import');

    Route::get(
        '/farmers/{farmer}/records',
        [FarmerController::class, 'records']
    )->name('farmers.records');

    Route::get(
        '/farmers/{farmer}/id-card',
        [FarmerController::class, 'idCard']
    )->name('farmers.id-card');

    Route::get(
        '/farmers/{farmer}/photo',
        [FarmerController::class, 'photo']
    )->name('farmers.photo');

    Route::get('/farmers/{farmer}/edit', [FarmerController::class, 'edit'])
        ->name('farmers.edit');

    Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])
        ->name('farmers.update');

    Route::delete('/farmers/{farmer}', [FarmerController::class, 'destroy'])
        ->name('farmers.destroy');

    /*
    |--------------------------------------------------------------------------
    | FARMERS COOPERATIVES
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/farmers-cooperatives/{farmersCooperative}/export-excel',
        [FarmersCooperativeController::class, 'exportExcel']
    )->name('farmers-cooperatives.export-excel');

    Route::get(
        '/farmers-cooperatives/{farmersCooperative}/assign-farmers',
        [FarmersCooperativeController::class, 'assignFarmers']
    )->name('farmers-cooperatives.assign-farmers');

    Route::put(
        '/farmers-cooperatives/{farmersCooperative}/assign-farmers',
        [FarmersCooperativeController::class, 'saveAssignedFarmers']
    )->name('farmers-cooperatives.save-assigned-farmers');

    Route::resource(
        'farmers-cooperatives',
        FarmersCooperativeController::class
    )->except(['show'])->parameters([
        'farmers-cooperatives' => 'farmersCooperative',
    ]);

    /*
    |--------------------------------------------------------------------------
    | ANTI-RABIES VACCINATIONS
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/anti-rabies-vaccinations/owner-lookup',
        [AntiRabiesVaccinationController::class, 'ownerLookup']
    )->name('anti-rabies-vaccinations.owner-lookup');

    Route::resource(
        'anti-rabies-vaccinations',
        AntiRabiesVaccinationController::class
    )->except(['show'])->parameters([
        'anti-rabies-vaccinations' => 'antiRabiesVaccination',
    ]);

    /*
    |--------------------------------------------------------------------------
    | RICE SEED DISTRIBUTIONS
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/rice-seed-distributions/import',
        [RiceSeedDistributionController::class, 'importForm']
    )->name('rice-seed-distributions.import.form');

    Route::post(
        '/rice-seed-distributions/import',
        [RiceSeedDistributionController::class, 'import']
    )->middleware('throttle:6,1')->name('rice-seed-distributions.import');

    Route::get(
        '/rice-seed-distributions/export',
        [RiceSeedDistributionController::class, 'export']
    )->name('rice-seed-distributions.export');

    Route::resource(
        'rice-seed-distributions',
        RiceSeedDistributionController::class
    )->except(['show'])->parameters([
        'rice-seed-distributions' => 'riceSeedDistribution',
    ]);

    /*
    |--------------------------------------------------------------------------
    | RICE SEED DISTRIBUTION SHEETS
    |--------------------------------------------------------------------------
    |
    | Sheets group existing assistance releases for printing and signature. They
    | keep their own URL prefix so the release resource's {riceSeedDistribution}
    | parameter can never swallow a sheet path.
    |
    */
    Route::get(
        '/rice-distribution-batches/{riceDistributionBatch}/sheet',
        [RiceDistributionBatchController::class, 'sheet']
    )->name('rice-distribution-batches.sheet');

    Route::get(
        '/rice-distribution-batches/{riceDistributionBatch}/sheet/export',
        [RiceDistributionBatchController::class, 'export']
    )->name('rice-distribution-batches.export');

    Route::resource(
        'rice-distribution-batches',
        RiceDistributionBatchController::class
    )->except(['show'])->parameters([
        'rice-distribution-batches' => 'riceDistributionBatch',
    ]);

    /*
    |--------------------------------------------------------------------------
    | AGRICULTURAL MACHINERY INVENTORY
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/machinery-inventory/export',
        [AgriculturalMachineryController::class, 'export']
    )->name('machinery-inventory.export');

    Route::get(
        '/machinery-inventory/holders',
        [AgriculturalMachineryController::class, 'holders']
    )->name('machinery-inventory.holders');

    Route::resource(
        'machinery-inventory',
        AgriculturalMachineryController::class
    )->except(['show'])->parameters([
        'machinery-inventory' => 'machinery',
    ]);

    /*
    |--------------------------------------------------------------------------
    | HARVEST RECORDS
    |--------------------------------------------------------------------------
    |
    | What was actually harvested. Rice arrives on its own from the assistance
    | sheet's production section; every other commodity is entered here.
    |
    | No show route: the list already carries every field a harvest has, so a
    | detail page would be the same row again on its own page.
    */
    Route::get(
        '/harvest-records/export',
        [HarvestRecordController::class, 'export']
    )->name('harvest-records.export');

    Route::resource(
        'harvest-records',
        HarvestRecordController::class
    )->except(['show']);

    /*
    |--------------------------------------------------------------------------
    | BACKUPS
    |--------------------------------------------------------------------------
    */
    Route::get('/backups', [BackupController::class, 'index'])
        ->name('backups.index');

    Route::post('/backups', [BackupController::class, 'store'])
        ->name('backups.store');

    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])
        ->name('backups.destroy');

    Route::get(
        '/backups/{backup}/download',
        [BackupController::class, 'download']
    )->name('backups.download');

    Route::get(
        '/backups/{backup}/preview',
        [BackupController::class, 'preview']
    )->name('backups.preview');

    Route::get(
        '/backups/{backup}/stream',
        [BackupController::class, 'stream']
    )->name('backups.stream');

    Route::post(
        '/backups/{backup}/save',
        [BackupController::class, 'save']
    )->name('backups.save');

    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN AUDIT TRAIL
    |--------------------------------------------------------------------------
    */
    Route::get('/audit-trail/export', [AuditLogController::class, 'export'])
        ->name('audit-logs.export');

    Route::get('/audit-trail', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

    Route::get('/audit-trail/{auditLog}', [AuditLogController::class, 'show'])
        ->whereNumber('auditLog')
        ->name('audit-logs.show');

    /*
    |--------------------------------------------------------------------------
    | USER MANAGEMENT
    |--------------------------------------------------------------------------
    |
    | Super admins manage all accounts. Municipal heads manage only staff
    | accounts assigned to their own municipality.
    |
    */
    Route::prefix('admins')
        ->name('admins.')
        ->group(function () {
            Route::get('/', [AdminController::class, 'index'])
                ->name('index');

            Route::get('/create', [AdminController::class, 'create'])
                ->name('create');

            Route::post('/', [AdminController::class, 'store'])
                ->name('store');

            Route::get('/{admin}/edit', [AdminController::class, 'edit'])
                ->name('edit');

            Route::put('/{admin}', [AdminController::class, 'update'])
                ->name('update');

            Route::delete('/{admin}', [AdminController::class, 'destroy'])
                ->name('destroy');
        });
});
