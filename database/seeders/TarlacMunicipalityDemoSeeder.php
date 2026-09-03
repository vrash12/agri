<?php

namespace Database\Seeders;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\GeoGeometry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TarlacMunicipalityDemoSeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/tarlac_reference_boundaries.geojson';

    private const SOURCE_SHA256 = 'a782a110527abd49b8ca91f6fd0636dcb1943989f0a3c12e78a9aa877755e815';

    private const DATA_MARKER = 'SYNTHETIC-DEMO-V1';

    /**
     * PSGC areas are used as a sanity check, not as geometry.
     *
     * @var array<string, array<string, mixed>>
     */
    private const MUNICIPALITIES = [
        'ANAO' => [
            'name' => 'Anao',
            'psgc' => '0306901000',
            'shape_id' => '30758251B97244135669664',
            'psa_area_ha' => 2544.0,
            'color' => '#2563EB',
            'barangays' => [
                'Baguindoc (Baguinloc)', 'Bantog', 'Campos', 'Carmen', 'Casili',
                'Don Ramon', 'Hernando', 'Poblacion', 'Rizal', 'San Francisco East',
            ],
        ],
        'CAMILING' => [
            'name' => 'Camiling',
            'psgc' => '0306903000',
            'shape_id' => '30758251B23459833682053',
            'psa_area_ha' => 13139.0,
            'color' => '#7C3AED',
            'barangays' => [
                'Anoling 1st', 'Anoling 2nd', 'Anoling 3rd', 'Bacabac', 'Bacsay',
                'Bancay 1st', 'San Isidro', 'Bilad', 'Birbira', 'Bobon Caarosipan',
            ],
        ],
        'PANIQUI' => [
            'name' => 'Paniqui',
            'psgc' => '0306910000',
            'shape_id' => '30758251B69585850409571',
            'psa_area_ha' => 10664.0,
            'color' => '#D97706',
            'barangays' => [
                'Abogado', 'Acocolao', 'Aduas', 'Apulid', 'Balaoang',
                'Barang (Borang)', 'Brillante', 'Burgos', 'Cabayaoasan', 'Canan',
            ],
        ],
        'RAMOS' => [
            'name' => 'Ramos',
            'psgc' => '0306912000',
            'shape_id' => '30758251B37101241671575',
            'psa_area_ha' => 2574.0,
            'color' => '#15803D',
            'barangays' => [
                'Coral-Iloco', 'Guiteb', 'Pance', 'Poblacion Center', 'Poblacion North',
                'Poblacion South', 'San Juan', 'San Raymundo', 'Toledo', 'Poblacion Center',
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const ASSISTANCE_TEMPLATES = [
        [
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'item' => 'NSIC Rc 222 certified rice seed',
            'quantity' => 40,
            'seed_class' => 'Certified',
            'crop_establishment' => 'Transplanted',
        ],
        [
            'input_category' => 'fish_fingerlings',
            'quantity_unit' => 'piece',
            'item' => 'Tilapia fingerlings',
            'quantity' => 750,
        ],
        [
            'input_category' => 'fertilizer',
            'quantity_unit' => 'sack',
            'item' => 'Complete fertilizer 14-14-14',
            'quantity' => 2,
        ],
        [
            'input_category' => 'fish_fingerlings',
            'quantity_unit' => 'piece',
            'item' => 'Hito fingerlings',
            'quantity' => 500,
        ],
        [
            'input_category' => 'vegetable_seed',
            'quantity_unit' => 'pack',
            'item' => 'Eggplant and pechay seed packs',
            'quantity' => 5,
            'seed_class' => 'Registered',
        ],
        [
            'input_category' => 'fish_feed',
            'quantity_unit' => 'kg',
            'item' => 'Grower fish feed',
            'quantity' => 25,
        ],
        [
            'input_category' => 'corn_seed',
            'quantity_unit' => 'kg',
            'item' => 'Hybrid yellow corn seed',
            'quantity' => 18,
            'seed_class' => 'Certified',
            'crop_establishment' => 'Direct seeded',
        ],
        [
            'input_category' => 'fishing_gear',
            'quantity_unit' => 'set',
            'item' => 'Gill net and handline set',
            'quantity' => 1,
        ],
        [
            'input_category' => 'soil_amendment',
            'quantity_unit' => 'sack',
            'item' => 'Agricultural lime',
            'quantity' => 3,
        ],
        [
            'input_category' => 'aquaculture_input',
            'quantity_unit' => 'set',
            'item' => 'Fish cage maintenance kit',
            'quantity' => 1,
        ],
    ];

    public function run(): void
    {
        $this->assertRequiredSchema();

        $geometry = app(GeoGeometry::class);
        $actor = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $actor) {
            throw new RuntimeException('An active Super Admin account is required to attribute the boundary import.');
        }

        $municipalities = Municipality::query()
            ->active()
            ->whereIn('code', array_keys(self::MUNICIPALITIES))
            ->get()
            ->keyBy(fn (Municipality $municipality): string => strtoupper((string) $municipality->code));

        $missing = collect(array_keys(self::MUNICIPALITIES))->diff($municipalities->keys());
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Missing active municipalities: '.$missing->implode(', ').'.');
        }

        $boundaries = $this->loadAndValidateBoundaries($geometry);
        $auditEvents = [];
        $farmerCount = 0;
        $assistanceCount = 0;

        Cache::lock('municipality-boundaries:activation', 120)->block(15, function () use (
            $actor,
            $boundaries,
            $geometry,
            $municipalities,
            &$auditEvents,
            &$farmerCount,
            &$assistanceCount
        ): void {
            DB::transaction(function () use (
                $actor,
                $boundaries,
                $geometry,
                $municipalities,
                &$auditEvents,
                &$farmerCount,
                &$assistanceCount
            ): void {
                $auditEvents = $this->seedBoundaries(
                    $actor,
                    $municipalities,
                    $boundaries,
                    $geometry
                );

                [$farmerCount, $assistanceCount] = $this->seedOperationalDemoData($municipalities);
            }, 3);
        });

        $this->recordBoundaryAudits($actor, $auditEvents);

        $this->command?->info(sprintf(
            'Ready: 4 active reference geofences, %d synthetic farmers, and %d synthetic assistance records.',
            $farmerCount,
            $assistanceCount
        ));
        $this->command?->warn('Reference geofences are approximate and require LGU/NAMRIA verification before official use.');
    }

    private function assertRequiredSchema(): void
    {
        foreach (['municipalities', 'municipality_boundaries', 'farmers', 'rice_seed_distributions', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table {$table} is missing. Apply the approved project schema first.");
            }
        }

        if (! Schema::hasColumns('rice_seed_distributions', ['input_category', 'quantity_unit', 'input_notes'])) {
            throw new RuntimeException(
                'The flexible agriculture/fisheries distribution migration is not applied. Run the specific 2026_08_20_000100 migration first.'
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadAndValidateBoundaries(GeoGeometry $geometry): array
    {
        $path = database_path(self::SOURCE_FILE);
        if (! is_file($path)) {
            throw new RuntimeException('The pinned municipality boundary source file is missing.');
        }

        $contents = (string) file_get_contents($path);
        $normalizedChecksum = hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
        if ($normalizedChecksum !== self::SOURCE_SHA256) {
            throw new RuntimeException('The pinned municipality boundary source checksum changed.');
        }

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (($document['type'] ?? null) !== 'FeatureCollection') {
            throw new RuntimeException('The pinned boundary source is not a GeoJSON FeatureCollection.');
        }

        if (
            ($document['source']['commit'] ?? null) !== '9469f09'
            || ($document['source']['license'] ?? null) !== 'CC BY 3.0 IGO'
        ) {
            throw new RuntimeException('The boundary attribution metadata does not match the approved pinned source.');
        }

        $features = collect($document['features'] ?? [])->keyBy(
            fn (array $feature): string => strtoupper((string) ($feature['properties']['shapeName'] ?? ''))
        );
        $prepared = [];

        foreach (self::MUNICIPALITIES as $code => $definition) {
            $feature = $features->get($code);
            if (! is_array($feature)) {
                throw new RuntimeException("The {$definition['name']} boundary is missing from the pinned source.");
            }

            if (($feature['properties']['shapeID'] ?? null) !== $definition['shape_id']) {
                throw new RuntimeException("The {$definition['name']} source feature ID does not match the approved reference.");
            }

            $preparedGeometry = $geometry->prepare($feature['geometry'] ?? []);
            $metadata = $geometry->metadata($preparedGeometry);
            $areaDifference = abs($metadata['area_ha'] - $definition['psa_area_ha']) / $definition['psa_area_ha'];
            if ($areaDifference > 0.03) {
                throw new RuntimeException("The {$definition['name']} boundary area differs from the PSA reference by more than 3%.");
            }

            $prepared[$code] = [
                'definition' => $definition,
                'geometry' => $preparedGeometry,
                'metadata' => $metadata,
            ];
        }

        $codes = array_keys($prepared);
        foreach ($codes as $leftIndex => $leftCode) {
            foreach (array_slice($codes, $leftIndex + 1) as $rightCode) {
                if ($geometry->overlaps($prepared[$leftCode]['geometry'], $prepared[$rightCode]['geometry'])) {
                    throw new RuntimeException("Pinned boundaries for {$leftCode} and {$rightCode} overlap.");
                }
            }
        }

        return $prepared;
    }

    /**
     * @param  Collection<string, Municipality>  $municipalities
     * @param  array<string, array<string, mixed>>  $boundaries
     * @return array<int, array<string, mixed>>
     */
    private function seedBoundaries(
        User $actor,
        Collection $municipalities,
        array $boundaries,
        GeoGeometry $geometry
    ): array {
        $events = [];
        $targetIds = $municipalities->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $allBoundaries = MunicipalityBoundary::query()->lockForUpdate()->get();

        foreach ($allBoundaries->where('status', MunicipalityBoundary::STATUS_ACTIVE) as $existing) {
            if (in_array((int) $existing->municipality_id, $targetIds, true)) {
                continue;
            }

            $conflictingCodes = collect($boundaries)
                ->filter(fn (array $candidate): bool => $geometry->overlaps($candidate['geometry'], $existing->geojson))
                ->keys();

            if ($conflictingCodes->isEmpty()) {
                continue;
            }

            if (! $this->isKnownInvalidMoncadaBoundary($existing, $municipalities)) {
                throw new RuntimeException(
                    "The requested reference boundary conflicts with active boundary #{$existing->id} ({$existing->name}). Review it before importing."
                );
            }

            $before = $this->boundarySnapshot($existing);
            $existing->forceFill([
                'status' => MunicipalityBoundary::STATUS_ARCHIVED,
                'archived_at' => now(),
                'updated_by' => $actor->id,
            ])->save();
            $events[] = $this->boundaryEvent('archived', $existing, $before, $this->boundarySnapshot($existing), [
                'reason' => 'Known four-point legacy Moncada polygon was located over Ramos/Paniqui and blocked verified reference boundaries.',
                'conflicting_reference_municipalities' => $conflictingCodes->values()->all(),
            ]);
        }

        foreach ($boundaries as $code => $candidate) {
            /** @var Municipality $municipality */
            $municipality = $municipalities->get($code);
            $definition = $candidate['definition'];
            $name = $definition['name'].' Planning Reference · geoBoundaries 2020';

            foreach ($allBoundaries->where('municipality_id', $municipality->id) as $other) {
                if ($other->status !== MunicipalityBoundary::STATUS_ACTIVE || $other->name === $name) {
                    continue;
                }

                $before = $this->boundarySnapshot($other);
                $other->forceFill([
                    'status' => MunicipalityBoundary::STATUS_ARCHIVED,
                    'archived_at' => now(),
                    'updated_by' => $actor->id,
                ])->save();
                $events[] = $this->boundaryEvent('archived', $other, $before, $this->boundarySnapshot($other), [
                    'reason' => 'Replaced by the requested pinned municipality reference boundary.',
                ]);
            }

            $record = $allBoundaries
                ->first(fn (MunicipalityBoundary $boundary): bool => (int) $boundary->municipality_id === (int) $municipality->id
                    && $boundary->name === $name
                ) ?? new MunicipalityBoundary();
            $before = $record->exists ? $this->boundarySnapshot($record) : null;
            $metadata = $candidate['metadata'];

            $record->fill([
                'municipality_id' => $municipality->id,
                'name' => $name,
                'geojson' => $candidate['geometry'],
                'color' => $definition['color'],
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

            $wasNew = ! $record->exists;
            $changed = $wasNew || $record->isDirty();
            if ($changed) {
                $record->save();
                $events[] = $this->boundaryEvent(
                    $wasNew ? 'imported' : 'updated',
                    $record,
                    $before,
                    $this->boundarySnapshot($record),
                    $this->sourceMetadata($definition)
                );

                if ($wasNew || ($before['status'] ?? null) !== MunicipalityBoundary::STATUS_ACTIVE) {
                    $events[] = $this->boundaryEvent(
                        'activated',
                        $record,
                        $before,
                        $this->boundarySnapshot($record),
                        $this->sourceMetadata($definition)
                    );
                }
            }

            Cache::forget('municipality-boundary:active:v1:'.$municipality->id);
        }

        $active = MunicipalityBoundary::query()->active()->lockForUpdate()->get();
        foreach ($boundaries as $code => $candidate) {
            $municipality = $municipalities->get($code);
            foreach ($active->where('municipality_id', '!=', $municipality->id) as $other) {
                if ($geometry->overlaps($candidate['geometry'], $other->geojson)) {
                    throw new RuntimeException(
                        "The activated {$code} reference boundary overlaps active boundary #{$other->id} ({$other->name})."
                    );
                }
            }
        }

        return $events;
    }

    /** @param  Collection<string, Municipality>  $municipalities */
    private function isKnownInvalidMoncadaBoundary(
        MunicipalityBoundary $boundary,
        Collection $municipalities
    ): bool {
        $moncada = Municipality::query()->where('code', 'MONCADA')->first();

        return $moncada
            && (int) $boundary->municipality_id === (int) $moncada->id
            && (int) $boundary->vertex_count <= 4
            && (float) $boundary->area_ha < 2000
            && $boundary->name === 'Moncada Official Boundary'
            && ! $municipalities->contains(fn (Municipality $item): bool => $item->is($moncada));
    }

    /**
     * @param  Collection<string, Municipality>  $municipalities
     * @return array{0:int,1:int}
     */
    private function seedOperationalDemoData(Collection $municipalities): array
    {
        $farmersSeeded = 0;
        $assistanceSeeded = 0;

        foreach (self::MUNICIPALITIES as $code => $definition) {
            /** @var Municipality $municipality */
            $municipality = $municipalities->get($code);

            foreach (range(1, 10) as $number) {
                $index = $number - 1;
                $ffrs = sprintf('DEMO-%s-FFRS-%03d', $code, $number);
                $rsbsa = sprintf('DEMO-%s-RSBSA-%03d', $code, $number);
                $firstName = sprintf('Demo%02d', $number);
                $lastName = $definition['name'].' Sample Farmer';

                $farmer = Farmer::query()->updateOrCreate(
                    ['ffrs' => $ffrs],
                    [
                        'municipality_id' => $municipality->id,
                        'rsbsa_no' => $rsbsa,
                        'first_name' => $firstName,
                        'middle_name' => 'Synthetic',
                        'last_name' => $lastName,
                        'owner_name' => "{$firstName} Synthetic {$lastName}",
                        'date_of_birth' => sprintf('%04d-%02d-%02d', 1968 + $number, ($number % 12) + 1, ($number % 25) + 1),
                        'contact_number' => null,
                        'gender' => $number % 2 === 0 ? 'Female' : 'Male',
                        'farm_location' => $definition['barangays'][$index],
                        'farm_province' => 'Tarlac',
                        'farm_municipality' => $definition['name'],
                        'ecosystem' => $number % 3 === 0 ? 'Rainfed' : 'Irrigated',
                        'ecosystem_source' => self::DATA_MARKER,
                        'is_arb' => $number % 5 === 0,
                        'is_4ps' => $number % 4 === 0,
                        'is_ip' => false,
                        'is_pwd' => false,
                        'is_sc' => $number >= 8,
                        'is_ofw' => false,
                        'farm_area_ha' => number_format(0.65 + ($number * 0.17), 2, '.', ''),
                    ]
                );
                $farmersSeeded++;

                $template = self::ASSISTANCE_TEMPLATES[$index];
                $lotSeries = sprintf('DEMO-%s-2026-%03d', $code, $number);
                RiceSeedDistribution::query()->updateOrCreate(
                    [
                        'municipality_id' => $municipality->id,
                        'lot_series' => $lotSeries,
                    ],
                    array_merge(
                        $this->farmerSnapshot($farmer),
                        [
                            'farmer_id' => $farmer->id,
                            'input_category' => $template['input_category'],
                            'quantity_unit' => $template['quantity_unit'],
                            'seed_variety_claimed' => $template['item'],
                            'kgs_received' => $template['quantity'],
                            'date_received' => sprintf('2026-%02d-%02d', 6 + intdiv($index, 4), 4 + ($number * 2)),
                            'claimed_area_ha' => str_ends_with($template['input_category'], '_seed')
                                ? $farmer->farm_area_ha
                                : null,
                            'claimed_seeds_kg' => str_ends_with($template['input_category'], '_seed')
                                && $template['quantity_unit'] === 'kg'
                                    ? $template['quantity']
                                    : null,
                            'crop_establishment' => $template['crop_establishment'] ?? null,
                            'seed_class' => $template['seed_class'] ?? null,
                            'input_notes' => self::DATA_MARKER.' · Demonstration record only; no real assistance was issued.',
                        ]
                    )
                );
                $assistanceSeeded++;
            }
        }

        return [$farmersSeeded, $assistanceSeeded];
    }

    /** @return array<string, mixed> */
    private function farmerSnapshot(Farmer $farmer): array
    {
        return [
            'municipality_id' => $farmer->municipality_id,
            'last_name' => $farmer->last_name,
            'first_name' => $farmer->first_name,
            'middle_name' => $farmer->middle_name,
            'ext_name' => $farmer->ext_name,
            'ffrs' => $farmer->ffrs ?: $farmer->rsbsa_no,
            'date_of_birth' => optional($farmer->date_of_birth)->format('Y-m-d'),
            'gender' => $farmer->gender,
            'contact_number' => $farmer->contact_number,
            'farm_location' => $farmer->farm_location,
            'farm_province' => $farmer->farm_province,
            'farm_municipality' => $farmer->farm_municipality,
            'farm_area_ha' => $farmer->farm_area_ha,
            'ecosystem' => $farmer->ecosystem,
            'ecosystem_source' => $farmer->ecosystem_source,
            'is_arb' => $farmer->is_arb,
            'is_4ps' => $farmer->is_4ps,
            'is_ip' => $farmer->is_ip,
            'is_pwd' => $farmer->is_pwd,
            'is_sc' => $farmer->is_sc,
            'is_ofw' => $farmer->is_ofw,
        ];
    }

    /** @return array<string, mixed> */
    private function boundarySnapshot(MunicipalityBoundary $boundary): array
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
            'archived_at' => optional($boundary->archived_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function sourceMetadata(array $definition): array
    {
        return [
            'data_classification' => 'planning_reference',
            'psgc_code' => $definition['psgc'],
            'source_dataset' => 'geoBoundaries gbOpen PHL ADM3',
            'source_revision' => '9469f09',
            'source_feature_id' => $definition['shape_id'],
            'source_organizations' => ['NAMRIA', 'PSA', 'OCHA Philippines'],
            'source_license' => 'CC BY 3.0 IGO',
            'boundary_year' => 2020,
            'source_checksum' => self::SOURCE_SHA256,
            'notice' => 'Approximate planning/reference boundary; not legal, cadastral, or survey-grade.',
            'demo_seed' => self::DATA_MARKER,
        ];
    }

    /** @return array<string, mixed> */
    private function boundaryEvent(
        string $event,
        MunicipalityBoundary $boundary,
        ?array $before,
        array $after,
        array $metadata
    ): array {
        return compact('event', 'boundary', 'before', 'after', 'metadata');
    }

    /** @param  array<int, array<string, mixed>>  $events */
    private function recordBoundaryAudits(User $actor, array $events): void
    {
        foreach ($events as $event) {
            /** @var MunicipalityBoundary $boundary */
            $boundary = $event['boundary'];
            $boundary->loadMissing('municipality:id,name');

            AuditTrail::record(
                $event['event'],
                'Municipality geofences',
                sprintf(
                    '%s %s the “%s” boundary for %s.',
                    $actor->name,
                    $event['event'],
                    $boundary->name,
                    $boundary->municipality?->name ?? 'a municipality'
                ),
                [
                    'actor' => $actor,
                    'auditable' => $boundary,
                    'municipality_id' => $boundary->municipality_id,
                    'old_values' => $event['before'],
                    'new_values' => $event['after'],
                    'metadata' => $event['metadata'],
                ]
            );
        }
    }
}
