<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgriculturalMachineryController;
use App\Http\Controllers\AntiRabiesVaccinationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmPlotController;
use App\Http\Controllers\FarmersCooperativeController;
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
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| GUEST ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

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

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'synchronized'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

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

        $cacheKey = 'geocode:' . sha1($query);

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

                if (!$response->ok()) {
                    return response()->json([
                        'error' => 'Geocode failed',
                        'status' => $response->status(),
                    ], 502);
                }

                $results = $response->json();

                if (!is_array($results) || count($results) < 1) {
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
    )->name('farm-plots.import');

    Route::get(
        '/farm-plots/all',
        [FarmPlotController::class, 'all']
    )->name('farm-plots.all');

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

    Route::get('/farmers/create', [FarmerController::class, 'create'])
        ->name('farmers.create');

    Route::post('/farmers', [FarmerController::class, 'store'])
        ->name('farmers.store');

    Route::get('/farmers/import', [FarmerController::class, 'showImport'])
        ->name('farmers.import.form');

    Route::post('/farmers/import', [FarmerController::class, 'import'])
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
    )->name('rice-seed-distributions.import');

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
