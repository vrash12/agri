<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ReferenceMunicipalityBoundaryImporter
{
    public function __construct(private GeoGeometry $geometry)
    {
    }

    /**
     * Apply a trusted, pinned municipality dataset as one atomic import.
     *
     * @param  array<string,array{code:string,aliases:array<int,string>,psgc_code:string,legacy_psgc_code:string,shape_id:string,reference_area_ha:float}>  $identities
     * @return array<int,int> Municipality IDs whose active-boundary caches were invalidated.
     */
    public function import(string $province, string $sourceFile, string $checksum, string $revision, array $identities): array
    {
        foreach (['municipalities', 'municipality_boundaries', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table {$table} is missing. Apply the approved project schema first.");
            }
        }

        $references = $this->loadBoundaries($province, $sourceFile, $checksum, $revision, $identities);

        $municipalityIds = Cache::lock('municipality-boundaries:activation', 120)->block(
            15,
            fn (): array => DB::transaction(function () use ($province, $references, $identities, $checksum, $revision): array {
                $actor = ReferenceBoundaryAccess::actor($province);

                $ids = [];
                foreach ($identities as $name => $identity) {
                    [$municipality, $workspaceCreated] = $this->resolveMunicipality($province, $name, $identity);
                    $this->activateReference($municipality, $identity, $references[$name], $actor, $workspaceCreated, $checksum, $revision);
                    $ids[] = (int) $municipality->id;
                }

                return $ids;
            }, 3)
        );

        foreach ($municipalityIds as $municipalityId) {
            Cache::forget('municipality-boundary:active:v1:'.$municipalityId);
        }

        return $municipalityIds;
    }

    /**
     * @param  array<string,mixed>  $identity
     * @return array{0:Municipality,1:bool}
     */
    private function resolveMunicipality(string $province, string $name, array $identity): array
    {
        $provinceId = ReferenceBoundaryAccess::provinceId($province);
        $names = [mb_strtolower($name), 'municipality of '.mb_strtolower($name)];
        $codes = array_merge($identity['aliases'], [$identity['psgc_code'], $identity['legacy_psgc_code']]);
        $matches = Municipality::query()->where(function (Builder $query) use ($names, $codes): void {
            $query->whereIn(DB::raw('LOWER(TRIM(name))'), $names)
                ->orWhereIn(DB::raw('UPPER(TRIM(code))'), $codes);
        })->lockForUpdate()->get();

        if ($matches->count() > 1) {
            throw new RuntimeException("Multiple {$name} workspaces match the name or PSGC code. Resolve the ambiguity before importing.");
        }
        if ($municipality = $matches->first()) {
            if (mb_strtolower(trim((string) $municipality->province)) !== mb_strtolower($province)) {
                throw new RuntimeException("The {$name} workspace is assigned to a different province. Review its identity before importing.");
            }
            if ($municipality->province_id !== $provinceId) {
                throw new RuntimeException("The {$name} supervising province does not match. Review its assignment before importing.");
            }
            if (! $municipality->is_active) {
                throw new RuntimeException("The {$name} workspace is inactive. Activate it before importing its boundary.");
            }

            return [$municipality, false];
        }

        // Each import audit explicitly attributes any workspace creation to the Super Admin.
        $municipality = Municipality::withoutEvents(fn (): Municipality => Municipality::query()->create([
            'name' => $name,
            'code' => $identity['code'],
            'province' => $province,
            'province_id' => $provinceId,
            'is_active' => true,
        ]));

        return [$municipality, true];
    }

    /**
     * @param  array<string,mixed>  $identity
     * @param  array{name:string,geometry:array<string,mixed>,metadata:array<string,mixed>}  $reference
     */
    private function activateReference(
        Municipality $municipality,
        array $identity,
        array $reference,
        User $actor,
        bool $workspaceCreated,
        string $checksum,
        string $revision
    ): void {
        $name = $reference['name'].' Planning Reference · geoBoundaries 2020';
        $geometry = $reference['geometry'];
        $metadata = $reference['metadata'];
        $boundaries = MunicipalityBoundary::query()->where(function (Builder $query) use ($municipality, $metadata): void {
            $query->where('municipality_id', $municipality->id)
                ->orWhere(fn (Builder $candidate) => $candidate->active()
                    ->where('min_lat', '<=', $metadata['max_lat'])->where('max_lat', '>=', $metadata['min_lat'])
                    ->where('min_lng', '<=', $metadata['max_lng'])->where('max_lng', '>=', $metadata['min_lng']));
        })->lockForUpdate()->get();

        foreach ($boundaries as $existing) {
            if ((int) $existing->municipality_id === (int) $municipality->id) {
                if ($existing->isActive() && ($existing->name !== $name || $existing->geojson !== $geometry)) {
                    throw new RuntimeException("{$reference['name']} already has a different active boundary. Review it before importing; no boundary was replaced.");
                }
            } elseif ($this->geometry->overlaps($geometry, $existing->geojson)) {
                throw new RuntimeException("The {$reference['name']} reference conflicts with active boundary #{$existing->id}. Review it before importing.");
            }
        }

        $matches = $boundaries->filter(fn (MunicipalityBoundary $boundary): bool => (int) $boundary->municipality_id === (int) $municipality->id && $boundary->name === $name);
        if ($matches->count() > 1) {
            throw new RuntimeException("Multiple {$reference['name']} reference boundaries already exist. Resolve the duplicate records before importing.");
        }
        if ($existing = $matches->first()) {
            if (! $existing->isActive() || $existing->geojson !== $geometry) {
                throw new RuntimeException("The existing {$reference['name']} reference was changed or deactivated. Review it before importing; no boundary was replaced.");
            }

            return;
        }

        $boundary = MunicipalityBoundary::query()->create([
            'municipality_id' => $municipality->id,
            'name' => $name,
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

        AuditTrail::record('imported', 'Municipality geofences',
            $actor->name.' imported the '.$reference['name'].' planning/reference boundary.', [
                'actor' => $actor,
                'auditable' => $boundary,
                'municipality_id' => $municipality->id,
                'new_values' => ['name' => $name, 'status' => $boundary->status, 'area_ha' => $boundary->area_ha, 'vertices' => $boundary->vertex_count],
                'metadata' => [
                    'workspace_created' => $workspaceCreated,
                    'data_classification' => 'planning_reference',
                    'administrative_level' => 'ADM3 municipality',
                    'psgc_code' => $identity['psgc_code'],
                    'legacy_psgc_code' => $identity['legacy_psgc_code'],
                    'source_dataset' => 'geoBoundaries gbOpen PHL ADM3',
                    'source_revision' => $revision,
                    'source_feature_id' => $identity['shape_id'],
                    'source_organizations' => ['NAMRIA', 'PSA', 'OCHA Philippines'],
                    'source_license' => 'CC BY 3.0 IGO',
                    'source_checksum' => $checksum,
                    'boundary_year' => 2020,
                    'notice' => 'Approximate planning/reference municipality boundary; not legal, cadastral, or survey-grade.',
                ],
            ]);
    }

    /**
     * @param  array<string,array<string,mixed>>  $identities
     * @return array<string,array{name:string,geometry:array<string,mixed>,metadata:array<string,mixed>}>
     */
    private function loadBoundaries(string $province, string $sourceFile, string $checksum, string $revision, array $identities): array
    {
        $path = database_path($sourceFile);
        if (! is_file($path)) {
            throw new RuntimeException("The pinned {$province} boundary source file is missing.");
        }
        $contents = (string) file_get_contents($path);
        if (hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents)) !== $checksum) {
            throw new RuntimeException("The pinned {$province} boundary source checksum changed.");
        }
        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (($document['type'] ?? null) !== 'FeatureCollection'
            || ($document['source']['commit'] ?? null) !== $revision
            || ($document['source']['license'] ?? null) !== 'CC BY 3.0 IGO'
            || count($document['features'] ?? []) !== count($identities)) {
            throw new RuntimeException("The {$province} boundary attribution or feature count does not match the approved pinned source.");
        }

        $references = [];
        foreach ($document['features'] as $feature) {
            $properties = $feature['properties'] ?? [];
            $name = $properties['shapeName'] ?? '';
            $identity = $identities[$name] ?? null;
            if (! $identity || isset($references[$name])
                || ($properties['shapeID'] ?? null) !== $identity['shape_id']
                || ($properties['psgc_code'] ?? null) !== $identity['psgc_code']
                || ($properties['legacy_psgc_code'] ?? null) !== $identity['legacy_psgc_code']
                || ($properties['province'] ?? null) !== $province
                || ($properties['shapeGroup'] ?? null) !== 'PHL'
                || ($properties['shapeType'] ?? null) !== 'ADM3') {
                throw new RuntimeException("A {$province} municipality identity does not match the approved pinned source.");
            }
            $geometry = $this->geometry->prepare($feature['geometry'] ?? []);
            $metadata = $this->geometry->metadata($geometry);
            if (abs($metadata['area_ha'] - $identity['reference_area_ha']) / $identity['reference_area_ha'] > 0.03) {
                throw new RuntimeException("The {$name} boundary area differs from the municipality reference by more than 3%.");
            }
            $references[$name] = ['name' => $name, 'geometry' => $geometry, 'metadata' => $metadata];
        }

        return $references;
    }
}
