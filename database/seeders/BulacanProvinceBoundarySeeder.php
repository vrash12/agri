<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\GeoGeometry;
use App\Support\ReferenceBoundaryAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BulacanProvinceBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/bulacan_reference_boundary.geojson';

    private const SOURCE_CHECKSUM = '41c71c536b79c4ff7d85b88efa9a5cef41bf732926c6cf30b2f09f9025122d33';

    private const SOURCE_REVISION = '41af8f1';

    private const SOURCE_FEATURE_ID = '2640588B70472166839855';

    private const PSGC_CODE = '0301400000';

    private const REFERENCE_AREA_HECTARES = 278369.0;

    private const BOUNDARY_NAME = 'Bulacan Province Planning Reference · geoBoundaries 2020';

    public function run(): void
    {
        $this->assertRequiredSchema();

        $geometryService = app(GeoGeometry::class);
        [$boundaryGeometry, $metadata] = $this->loadBoundary($geometryService);
        $audit = null;
        $actor = null;
        $municipality = null;

        Cache::lock('municipality-boundaries:activation', 120)->block(15, function () use (
            &$actor,
            &$municipality,
            $boundaryGeometry,
            $metadata,
            $geometryService,
            &$audit
        ): void {
            DB::transaction(function () use (
                &$actor,
                &$municipality,
                $boundaryGeometry,
                $metadata,
                $geometryService,
                &$audit
            ): void {
                $actor = ReferenceBoundaryAccess::actor('Bulacan');
                $municipality = $this->resolveMunicipality();
                $boundaries = MunicipalityBoundary::query()->lockForUpdate()->get();

                foreach ($boundaries->where('status', MunicipalityBoundary::STATUS_ACTIVE) as $existing) {
                    if ((int) $existing->municipality_id === (int) $municipality->id) {
                        continue;
                    }

                    if ($geometryService->overlaps($boundaryGeometry, $existing->geojson)) {
                        throw new RuntimeException(
                            "The Bulacan reference boundary conflicts with active boundary #{$existing->id} ({$existing->name}). Review it before importing."
                        );
                    }
                }

                foreach ($boundaries->where('municipality_id', $municipality->id) as $existing) {
                    if ($existing->status !== MunicipalityBoundary::STATUS_ACTIVE || $existing->name === self::BOUNDARY_NAME) {
                        continue;
                    }

                    $existing->forceFill([
                        'status' => MunicipalityBoundary::STATUS_ARCHIVED,
                        'archived_at' => now(),
                        'updated_by' => $actor->id,
                    ])->save();
                }

                $record = $boundaries->first(
                    fn (MunicipalityBoundary $boundary): bool => (int) $boundary->municipality_id === (int) $municipality->id
                        && $boundary->name === self::BOUNDARY_NAME
                ) ?? new MunicipalityBoundary();
                // Repeated imports preserve the saved styling and database-rounded metadata.
                if ($record->exists && $record->isActive() && $record->geojson === $boundaryGeometry) {
                    return;
                }
                $before = $record->exists ? $this->snapshot($record) : null;

                $record->fill([
                    'municipality_id' => $municipality->id,
                    'name' => self::BOUNDARY_NAME,
                    'geojson' => $boundaryGeometry,
                    'color' => '#1D4ED8',
                    'status' => MunicipalityBoundary::STATUS_ACTIVE,
                    'area_ha' => $metadata['area_ha'],
                    'centroid_lat' => $metadata['centroid_lat'],
                    'centroid_lng' => $metadata['centroid_lng'],
                    'min_lat' => $metadata['min_lat'],
                    'max_lat' => $metadata['max_lat'],
                    'min_lng' => $metadata['min_lng'],
                    'max_lng' => $metadata['max_lng'],
                    'vertex_count' => $metadata['vertices'],
                    'created_by' => $record->created_by ?: $actor->id,
                    'updated_by' => $actor->id,
                    'archived_at' => null,
                ]);

                if (! $record->exists || $record->isDirty()) {
                    $event = $record->exists ? 'updated' : 'imported';
                    $record->save();
                    $audit = [
                        'event' => $event,
                        'boundary' => $record,
                        'before' => $before,
                        'after' => $this->snapshot($record),
                    ];
                }
            }, 3);
        });

        Cache::forget('municipality-boundary:active:v1:'.$municipality->id);

        if ($audit) {
            $this->recordAudit($actor, $municipality, $audit);
        }

        $this->command?->info('Ready: the Bulacan province planning/reference geofence is active.');
        $this->command?->warn('This province boundary is approximate and requires Bulacan LGU/NAMRIA verification before official use.');
    }

    private function resolveMunicipality(): Municipality
    {
        $provinceId = ReferenceBoundaryAccess::provinceId('Bulacan');
        // Production may retain the original BUL code; preserve its record and ownership.
        $matches = Municipality::query()->where(function (Builder $query): void {
            $query->whereIn(DB::raw('UPPER(TRIM(code))'), ['BUL', 'BULACAN', self::PSGC_CODE])
                ->orWhereIn(DB::raw('LOWER(TRIM(name))'), ['bulacan', 'bulacan province']);
        })->lockForUpdate()->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple Bulacan workspaces match the name or code. Resolve the ambiguity before importing.');
        }
        if ($municipality = $matches->first()) {
            if ($municipality->province_id !== $provinceId || mb_strtolower(trim((string) $municipality->province)) !== 'bulacan') {
                throw new RuntimeException('The Bulacan workspace is assigned to a different province. Review its identity before importing.');
            }
            if (! $municipality->is_active) {
                throw new RuntimeException('The Bulacan workspace exists but is inactive. Activate it before importing its boundary.');
            }

            return $municipality;
        }

        return Municipality::withoutEvents(fn (): Municipality => Municipality::query()->create([
            'code' => 'BULACAN', 'name' => 'Bulacan', 'province' => 'Bulacan',
            'province_id' => $provinceId, 'is_active' => true,
        ]));
    }

    private function assertRequiredSchema(): void
    {
        foreach (['municipalities', 'municipality_boundaries', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table {$table} is missing. Apply the approved project schema first.");
            }
        }
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function loadBoundary(GeoGeometry $geometryService): array
    {
        $path = database_path(self::SOURCE_FILE);
        if (! is_file($path)) {
            throw new RuntimeException('The pinned Bulacan boundary source file is missing.');
        }

        $contents = (string) file_get_contents($path);
        $checksum = hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
        if ($checksum !== self::SOURCE_CHECKSUM) {
            throw new RuntimeException('The pinned Bulacan boundary source checksum changed.');
        }

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (
            ($document['type'] ?? null) !== 'FeatureCollection'
            || ($document['source']['commit'] ?? null) !== self::SOURCE_REVISION
            || ($document['source']['license'] ?? null) !== 'CC BY 3.0 IGO'
        ) {
            throw new RuntimeException('The Bulacan boundary attribution metadata does not match the approved pinned source.');
        }

        $feature = collect($document['features'] ?? [])->first(
            fn (array $item): bool => ($item['properties']['shapeName'] ?? null) === 'Bulacan'
        );
        if (! is_array($feature) || ($feature['properties']['shapeID'] ?? null) !== self::SOURCE_FEATURE_ID) {
            throw new RuntimeException('The approved Bulacan province feature is missing from the pinned source.');
        }

        if (($feature['properties']['shapeType'] ?? null) !== 'ADM2') {
            throw new RuntimeException('The Bulacan reference must be a province-level ADM2 geometry.');
        }

        $geometry = $geometryService->prepare($feature['geometry'] ?? []);
        $metadata = $geometryService->metadata($geometry);
        $areaDifference = abs($metadata['area_ha'] - self::REFERENCE_AREA_HECTARES)
            / self::REFERENCE_AREA_HECTARES;

        if ($areaDifference > 0.03) {
            throw new RuntimeException('The Bulacan boundary area differs from the provincial reference by more than 3%.');
        }

        return [$geometry, $metadata];
    }

    /** @return array<string,mixed> */
    private function snapshot(MunicipalityBoundary $boundary): array
    {
        return [
            'municipality_id' => $boundary->municipality_id,
            'name' => $boundary->name,
            'status' => $boundary->status,
            'color' => $boundary->color,
            'area_ha' => round((float) $boundary->area_ha, 4),
            'centroid_lat' => (float) $boundary->centroid_lat,
            'centroid_lng' => (float) $boundary->centroid_lng,
            'vertices' => (int) $boundary->vertex_count,
        ];
    }

    /** @param  array{event:string,boundary:MunicipalityBoundary,before:?array,after:array}  $audit */
    private function recordAudit(User $actor, Municipality $municipality, array $audit): void
    {
        AuditTrail::record(
            $audit['event'],
            'Municipality geofences',
            sprintf('%s %s the Bulacan province planning/reference boundary.', $actor->name, $audit['event']),
            [
                'actor' => $actor,
                'auditable' => $audit['boundary'],
                'municipality_id' => $municipality->id,
                'old_values' => $audit['before'],
                'new_values' => $audit['after'],
                'metadata' => [
                    'data_classification' => 'planning_reference',
                    'administrative_level' => 'ADM2 province',
                    'psgc_code' => self::PSGC_CODE,
                    'source_dataset' => 'geoBoundaries gbOpen PHL ADM2',
                    'source_revision' => self::SOURCE_REVISION,
                    'source_feature_id' => self::SOURCE_FEATURE_ID,
                    'source_organizations' => ['NAMRIA', 'PSA', 'OCHA Philippines'],
                    'source_license' => 'CC BY 3.0 IGO',
                    'boundary_year' => 2020,
                    'source_checksum' => self::SOURCE_CHECKSUM,
                    'notice' => 'Approximate planning/reference province boundary; not legal, cadastral, or survey-grade.',
                ],
            ]
        );
    }
}
