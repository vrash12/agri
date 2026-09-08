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

class BaguioCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/baguio_reference_boundary.geojson';

    private const SOURCE_CHECKSUM = '103e72b2ce486c5275c9d310ae2cf62f7696fadb0d8305ee151ba0340de69529';

    private const SOURCE_REVISION = '9469f09';

    private const SOURCE_FEATURE_ID = '30758251B18922588133033';

    private const PSGC_CODE = '1430300000';

    private const LEGACY_PSGC_CODE = '141102000';

    private const REFERENCE_AREA_HECTARES = 5808.582396;

    private const BOUNDARY_NAME = 'Baguio City Planning Reference · geoBoundaries 2020';

    public function run(): void
    {
        foreach (['municipalities', 'municipality_boundaries', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table {$table} is missing. Apply the approved project schema first.");
            }
        }

        $geometryService = app(GeoGeometry::class);
        [$geometry, $metadata] = $this->loadBoundary($geometryService);

        $municipalityId = Cache::lock('municipality-boundaries:activation', 120)->block(
            15,
            fn (): int => DB::transaction(function () use ($geometryService, $geometry, $metadata): int {
                $actor = ReferenceBoundaryAccess::actor('Benguet');

                [$municipality, $workspaceCreated] = $this->resolveMunicipality();
                $boundaries = MunicipalityBoundary::query()
                    ->where(fn (Builder $query) => $query
                        ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
                        ->orWhere('municipality_id', $municipality->id))
                    ->lockForUpdate()->get();

                foreach ($boundaries as $existing) {
                    if ((int) $existing->municipality_id === (int) $municipality->id) {
                        if ($existing->isActive() && (
                            $existing->name !== self::BOUNDARY_NAME || $existing->geojson !== $geometry
                        )) {
                            throw new RuntimeException('Baguio already has a different active boundary. Review it before importing; no boundary was replaced.');
                        }

                        continue;
                    }

                    if ($geometryService->overlaps($geometry, $existing->geojson)) {
                        throw new RuntimeException("The Baguio reference conflicts with active boundary #{$existing->id}. Review it before importing.");
                    }
                }

                $matches = $boundaries->filter(fn (MunicipalityBoundary $boundary): bool => (int) $boundary->municipality_id === (int) $municipality->id
                    && $boundary->name === self::BOUNDARY_NAME
                );

                if ($matches->count() > 1) {
                    throw new RuntimeException('Multiple Baguio reference boundaries already exist. Resolve the duplicate records before importing.');
                }

                if ($existing = $matches->first()) {
                    if (! $existing->isActive() || $existing->geojson !== $geometry) {
                        throw new RuntimeException('The existing Baguio reference was changed or deactivated. Review it before importing; no boundary was replaced.');
                    }

                    return (int) $municipality->id;
                }

                $boundary = MunicipalityBoundary::query()->create([
                    'municipality_id' => $municipality->id,
                    'name' => self::BOUNDARY_NAME,
                    'geojson' => $geometry,
                    'color' => '#236344',
                    'status' => MunicipalityBoundary::STATUS_ACTIVE,
                    'area_ha' => $metadata['area_ha'],
                    'centroid_lat' => $metadata['centroid_lat'],
                    'centroid_lng' => $metadata['centroid_lng'],
                    'min_lat' => $metadata['min_lat'],
                    'max_lat' => $metadata['max_lat'],
                    'min_lng' => $metadata['min_lng'],
                    'max_lng' => $metadata['max_lng'],
                    'vertex_count' => $metadata['vertices'],
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->recordAudit($actor, $boundary, $workspaceCreated);

                return (int) $municipality->id;
            }, 3)
        );

        Cache::forget('municipality-boundary:active:v1:'.$municipalityId);
        $this->command?->info('Ready: the Baguio City planning/reference geofence is active.');
        $this->command?->warn('This approximate boundary requires LGU/NAMRIA verification before official use.');
    }

    /** @return array{0:Municipality,1:bool} */
    private function resolveMunicipality(): array
    {
        $matches = Municipality::query()->where(function (Builder $query): void {
            $query->whereIn(DB::raw('LOWER(TRIM(name))'), ['baguio', 'baguio city', 'city of baguio'])
                ->orWhereIn(DB::raw('UPPER(TRIM(code))'), ['BAGUIO', self::PSGC_CODE, self::LEGACY_PSGC_CODE]);
        })->lockForUpdate()->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple Baguio workspaces match the city name or PSGC code. Resolve the ambiguity before importing.');
        }

        if ($municipality = $matches->first()) {
            if (! $municipality->is_active) {
                throw new RuntimeException('The Baguio workspace is inactive. Activate it before importing its boundary.');
            }

            return [$municipality, false];
        }

        // The import audit below explicitly attributes this workspace creation to the Super Admin.
        $municipality = Municipality::withoutEvents(fn (): Municipality => Municipality::query()->create([
            'name' => 'Baguio City',
            'code' => 'BAGUIO',
            // Baguio is an independent HUC; Benguet is the geographic layer convention only.
            'province' => 'Benguet',
            'province_id' => ReferenceBoundaryAccess::provinceId('Benguet'),
            'is_active' => true,
        ]));

        return [$municipality, true];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function loadBoundary(GeoGeometry $geometryService): array
    {
        $path = database_path(self::SOURCE_FILE);
        if (! is_file($path)) {
            throw new RuntimeException('The pinned Baguio boundary source file is missing.');
        }

        $contents = (string) file_get_contents($path);
        if (hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents)) !== self::SOURCE_CHECKSUM) {
            throw new RuntimeException('The pinned Baguio boundary source checksum changed.');
        }

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $source = $document['source'] ?? [];
        $feature = $document['features'][0] ?? [];
        if (($document['type'] ?? null) !== 'FeatureCollection'
            || count($document['features'] ?? []) !== 1
            || ($source['commit'] ?? null) !== self::SOURCE_REVISION
            || ($source['license'] ?? null) !== 'CC BY 3.0 IGO'
            || ($source['psgc_code'] ?? null) !== self::PSGC_CODE
            || ($source['legacy_psgc_code'] ?? null) !== self::LEGACY_PSGC_CODE
            || ($feature['properties']['shapeName'] ?? null) !== 'Baguio City'
            || ($feature['properties']['shapeID'] ?? null) !== self::SOURCE_FEATURE_ID
            || ($feature['properties']['shapeGroup'] ?? null) !== 'PHL'
            || ($feature['properties']['shapeType'] ?? null) !== 'ADM3') {
            throw new RuntimeException('The Baguio boundary identity or attribution does not match the approved pinned source.');
        }

        $geometry = $geometryService->prepare($feature['geometry'] ?? []);
        $metadata = $geometryService->metadata($geometry);
        if (abs($metadata['area_ha'] - self::REFERENCE_AREA_HECTARES) / self::REFERENCE_AREA_HECTARES > 0.03) {
            throw new RuntimeException('The Baguio boundary area differs from the city reference by more than 3%.');
        }

        return [$geometry, $metadata];
    }

    private function recordAudit(User $actor, MunicipalityBoundary $boundary, bool $workspaceCreated): void
    {
        AuditTrail::record('imported', 'Municipality geofences',
            $actor->name.' imported the Baguio City planning/reference boundary.', [
                'actor' => $actor,
                'auditable' => $boundary,
                'municipality_id' => $boundary->municipality_id,
                'new_values' => [
                    'name' => $boundary->name,
                    'status' => $boundary->status,
                    'area_ha' => round($boundary->area_ha, 4),
                    'vertices' => $boundary->vertex_count,
                ],
                'metadata' => [
                    'workspace_created' => $workspaceCreated,
                    'data_classification' => 'planning_reference',
                    'administrative_level' => 'ADM3 city',
                    'city_classification' => 'Highly Urbanized City',
                    'psgc_code' => self::PSGC_CODE,
                    'legacy_psgc_code' => self::LEGACY_PSGC_CODE,
                    'source_dataset' => 'geoBoundaries gbOpen PHL ADM3',
                    'source_revision' => self::SOURCE_REVISION,
                    'source_feature_id' => self::SOURCE_FEATURE_ID,
                    'source_organizations' => ['NAMRIA', 'PSA', 'OCHA Philippines'],
                    'source_license' => 'CC BY 3.0 IGO',
                    'boundary_year' => 2020,
                    'source_checksum' => self::SOURCE_CHECKSUM,
                    'notice' => 'Approximate planning/reference city boundary; not legal, cadastral, or survey-grade.',
                ],
            ]);
    }
}
