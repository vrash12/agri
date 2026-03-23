<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AntiRabiesVaccinationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmPlotController;
use App\Http\Controllers\RiceSeedDistributionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;

use App\Http\Controllers\DashboardController;

Route::middleware('auth')->get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');


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
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | GEOCODE (for map)
    |--------------------------------------------------------------------------
    | GET /api/geocode?q=Some+Address
    | Returns: { lat: <float|null>, lng: <float|null> }
    |
    | NOTE: Replace the User-Agent with your real app name + contact.
    | Also: caching prevents repeated calls and helps avoid rate limits.
    */
    Route::get('/api/geocode', function (Request $request) {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['error' => 'Missing q'], 422);
        }

        $cacheKey = 'geocode:' . sha1($q);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($q) {
            $resp = Http::withHeaders([
                // IMPORTANT: Put your real app name + a contact email/domain here
                'User-Agent' => 'FarmApp/1.0 (contact: you@yourdomain.com)',
                'Accept' => 'application/json',
            ])->timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'json',
                'limit'  => 1,
                'q'      => $q,
            ]);

            if (!$resp->ok()) {
                return response()->json([
                    'error'  => 'Geocode failed',
                    'status' => $resp->status(),
                ], 502);
            }

            $arr = $resp->json();
            if (!is_array($arr) || count($arr) < 1) {
                return response()->json(['lat' => null, 'lng' => null]);
            }

            return response()->json([
                'lat' => isset($arr[0]['lat']) ? (float) $arr[0]['lat'] : null,
                'lng' => isset($arr[0]['lon']) ? (float) $arr[0]['lon'] : null,
            ]);
        });
    })
        ->name('geocode')
        ->middleware('throttle:30,1');

    /*
    |--------------------------------------------------------------------------
    | FARM PLOTS (JSON endpoints)
    |--------------------------------------------------------------------------
    */
    Route::get('/farmers/{farmer}/plots', [FarmPlotController::class, 'index'])->name('farmers.plots.index');
    Route::post('/farmers/{farmer}/plots', [FarmPlotController::class, 'store'])->name('farmers.plots.store');
    Route::delete('/farm-plots/{plot}', [FarmPlotController::class, 'destroy'])->name('farm-plots.destroy');

    /*
    |--------------------------------------------------------------------------
    | FARMERS
    |--------------------------------------------------------------------------
    */
    Route::get('/farmers', [FarmerController::class, 'index'])->name('farmers.index');
    Route::get('/farmers/{farmer}/records', [FarmerController::class, 'records'])->name('farmers.records');

    Route::get('/farmers/import', [FarmerController::class, 'showImport'])->name('farmers.import.form');
    Route::post('/farmers/import', [FarmerController::class, 'import'])->name('farmers.import');

    /*
    |--------------------------------------------------------------------------
    | ANTI-RABIES VACCINATIONS
    |--------------------------------------------------------------------------
    */
    Route::resource('anti-rabies-vaccinations', AntiRabiesVaccinationController::class)
        ->except(['show']);
        Route::get('/anti-rabies-vaccinations/owner-lookup', [AntiRabiesVaccinationController::class, 'ownerLookup'])
    ->name('anti-rabies-vaccinations.owner-lookup');

    /*
    |--------------------------------------------------------------------------
    | RICE SEED DISTRIBUTIONS (IMPORT/EXPORT MUST BE ABOVE RESOURCE)
    |--------------------------------------------------------------------------
    */
    Route::get('/rice-seed-distributions/import', [RiceSeedDistributionController::class, 'importForm'])
        ->name('rice-seed-distributions.import.form');

    Route::post('/rice-seed-distributions/import', [RiceSeedDistributionController::class, 'import'])
        ->name('rice-seed-distributions.import');

    Route::get('/rice-seed-distributions/export', [RiceSeedDistributionController::class, 'export'])
        ->name('rice-seed-distributions.export');

    Route::resource('rice-seed-distributions', RiceSeedDistributionController::class)
        ->except(['show']);

Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');

/** NEW: preview + stream + save */
Route::get('/backups/{backup}/preview', [BackupController::class, 'preview'])->name('backups.preview');
Route::get('/backups/{backup}/stream', [BackupController::class, 'stream'])->name('backups.stream');
Route::post('/backups/{backup}/save', [BackupController::class, 'save'])->name('backups.save');
    /*
    |--------------------------------------------------------------------------
    | HEAD ADMIN ONLY: Admin CRUD
    |--------------------------------------------------------------------------
    */
    Route::middleware('head_admin')
        ->prefix('admins')
        ->name('admins.')
        ->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::get('/create', [AdminController::class, 'create'])->name('create');
            Route::post('/', [AdminController::class, 'store'])->name('store');
            Route::get('/{admin}/edit', [AdminController::class, 'edit'])->name('edit');
            Route::put('/{admin}', [AdminController::class, 'update'])->name('update');
            Route::delete('/{admin}', [AdminController::class, 'destroy'])->name('destroy');
        });
});