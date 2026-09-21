<?php

declare(strict_types=1);

// CLI-only, explicit snapshot/check helper for this release. Never serves database data.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

umask(0077);
set_time_limit(300);
$phase = $argv[1] ?? '';
$base = realpath($argv[2] ?? '');
$recovery = realpath($argv[3] ?? '');
$ownerId = (int) ($argv[4] ?? 0);
if (! in_array($phase, ['snapshot', 'verify'], true) || ! $base || ! $recovery || ! is_file($base.'/artisan') || str_starts_with($recovery, $base.DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Usage: php release-helper.php snapshot|verify APP_ROOT PRIVATE_RECOVERY_DIRECTORY OWNER_ID');
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('production') || ! is_file($base.'/storage/framework/down')) {
    throw new RuntimeException('A production maintenance window is required.');
}

function rowsFingerprint(string $table, ?int $maximumId = null): array
{
    $query = Illuminate\Support\Facades\DB::table($table);
    if ($maximumId !== null) {
        $query->where('id', '<=', $maximumId);
    }
    $rows = $query->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->all();

    return ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
}

$receiptFile = $recovery.'/database-before.json';
if ($phase === 'snapshot') {
    if (is_file($receiptFile)) {
        throw new RuntimeException('Snapshot already exists; use a fresh private release directory.');
    }
    $backup = app(App\Support\DatabaseBackup::class)->create($recovery.'/database', true);
    app(App\Support\DatabaseBackup::class)->verify($backup['path'], $backup['tables']);
    Illuminate\Support\Facades\DB::purge();
    $receipt = ['created_at' => gmdate('c'), 'environment_sha256' => hash_file('sha256', $base.'/.env'), 'backup' => ['bytes' => $backup['bytes'], 'sha256' => $backup['checksum']], 'tables' => [], 'boundaries' => []];
    foreach (array_keys($backup['tables']) as $table) {
        if ($table === 'municipality_boundaries') {
            continue;
        }
        $maximumId = in_array($table, ['audit_logs', 'migrations'], true) ? (int) Illuminate\Support\Facades\DB::table($table)->max('id') : null;
        $receipt['tables'][$table] = ['maximum_id' => $maximumId] + rowsFingerprint($table, $maximumId);
    }
    $receipt['target_ids'] = Illuminate\Support\Facades\DB::table('municipality_boundaries as b')
        ->join('municipalities as m', 'm.id', '=', 'b.municipality_id')->join('provinces as p', 'p.id', '=', 'm.province_id')
        ->whereIn('p.name', ['Batanes', 'Cagayan', 'Isabela', 'Nueva Vizcaya', 'Quirino', 'Santiago City'])->where('b.status', 'active')->pluck('b.id')->all();
    if (count($receipt['target_ids']) !== 93) {
        throw new RuntimeException('Expected 93 active Region II boundaries.');
    }
    foreach (Illuminate\Support\Facades\DB::table('municipality_boundaries')->orderBy('id')->get() as $boundary) {
        $receipt['boundaries'][$boundary->id] = (array) $boundary;
    }
    file_put_contents($receiptFile, json_encode($receipt, JSON_THROW_ON_ERROR));
    echo json_encode(['state' => 'backed_up', 'tables' => count($backup['tables']), 'boundaries' => count($receipt['boundaries']), 'backup' => $receipt['backup']], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

$receipt = json_decode(file_get_contents($receiptFile), true, 512, JSON_THROW_ON_ERROR);
if (! hash_equals($receipt['environment_sha256'], hash_file('sha256', $base.'/.env'))) {
    throw new RuntimeException('Environment file changed.');
}
foreach ($receipt['tables'] as $table => $expected) {
    $actual = rowsFingerprint($table, $expected['maximum_id']);
    if ($actual !== array_intersect_key($expected, $actual)) {
        throw new RuntimeException('Existing rows changed in '.$table.'.');
    }
}
$boundaries = Illuminate\Support\Facades\DB::table('municipality_boundaries')->orderBy('id')->get();
if ($boundaries->count() !== count($receipt['boundaries'])) {
    throw new RuntimeException('Boundary count changed.');
}
foreach ($boundaries as $boundary) {
    $current = (array) $boundary;
    $previous = $receipt['boundaries'][$boundary->id];
    $target = in_array($boundary->id, $receipt['target_ids'], true);
    if ($target && ($boundary->color !== '#FFFFFF' || (float) $boundary->fill_opacity !== .2)) {
        throw new RuntimeException('Region II appearance is incomplete.');
    }
    if (! $target && (float) $boundary->fill_opacity !== .2) {
        throw new RuntimeException('Unexpected non-target opacity.');
    }
    unset($current['fill_opacity']);
    if ($target) {
        foreach (['color', 'updated_at', 'updated_by'] as $field) {
            unset($current[$field], $previous[$field]);
        }
    }
    if ($current !== $previous) {
        throw new RuntimeException('Boundary geometry or non-target properties changed.');
    }
}
$newAudits = Illuminate\Support\Facades\DB::table('audit_logs')->where('id', '>', $receipt['tables']['audit_logs']['maximum_id'])->get();
if ($newAudits->count() !== 93 || $newAudits->contains(fn ($row) => (json_decode($row->metadata, true)['setup'] ?? '') !== 'region_two_white')) {
    throw new RuntimeException('Expected 93 attributed appearance audits.');
}
$newMigrations = Illuminate\Support\Facades\DB::table('migrations')->where('id', '>', $receipt['tables']['migrations']['maximum_id'])->pluck('migration')->all();
if ($newMigrations !== ['2026_09_21_000100_add_fill_opacity_to_municipality_boundaries']) {
    throw new RuntimeException('Unexpected migration set.');
}
$preview = app(App\Support\RegionTwoGeofenceAppearance::class)->run(false, null);
if (array_sum(array_column($preview, 'changed')) !== 0) {
    throw new RuntimeException('Repeat preview proposes more changes.');
}
$owner = App\Models\User::findOrFail($ownerId);
if (! $owner->isSystemOwner() || ! $owner->hasUsableScope()) {
    throw new RuntimeException('Explicit active System Owner required for read-only checks.');
}
Illuminate\Support\Facades\Auth::setUser($owner);
$request = Illuminate\Http\Request::create('/farmers');
$request->setUserResolver(fn () => $owner);
$app->instance('request', $request);
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
$farmers = app(App\Http\Controllers\FarmerController::class)->index($request);
$geofences = app(App\Http\Controllers\MunicipalityBoundaryController::class)->index($request, app(App\Support\BarangayBoundaryReferences::class));
$parcelRows = $farmers->getData()['mapMunicipalityBoundaries']->keyBy('id');
$boundaryRows = $geofences->getData()['boundaries']->keyBy('id');
foreach ($receipt['target_ids'] as $id) {
    foreach (['color', 'fill_opacity', 'label_position'] as $field) {
        if ($parcelRows[$id][$field] !== $boundaryRows[$id][$field] || $parcelRows[$id][$field] === null) {
            throw new RuntimeException('The two maps disagree.');
        }
    }
}
if (! str_contains($farmers->render(), 'geofence-style.js') || ! str_contains($geofences->render(), 'saveGeofenceStyle') || ! str_contains(view('auth.login')->render(), 'name="confidentiality_acknowledged"')) {
    throw new RuntimeException('Rendered controls are incomplete.');
}
$result = ['state' => 'verified', 'verified_at_utc' => gmdate('c'), 'preserved_tables' => count($receipt['tables']), 'preserved_boundary_shapes' => $boundaries->count(), 'matching_region_two_map_styles' => count($receipt['target_ids']), 'new_audits' => $newAudits->count(), 'repeat_changes' => 0, 'environment_unchanged' => true];
file_put_contents($recovery.'/verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
