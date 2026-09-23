<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\Region;
use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Explicit, insert-only demonstration import. Never invoked by normal seeding. */
final class RegionOneSampleData
{
    private const VERSION = 'SAMPLE-R1-V1';

    private const PROVINCES = ['Ilocos Norte', 'Ilocos Sur', 'La Union', 'Pangasinan'];

    private const NOTICE = 'SYNTHETIC SAMPLE ONLY. No real farmer, surveyed land, consent, entitlement or seed delivery is represented.';

    public function __construct(private HypotheticalFarmPlots $plots, private ConcurrentWrite $writes)
    {
    }

    /**
     * @param  array<int, int>  $municipalityIds Three existing municipalities in distinct Region I provinces.
     * @return array<string, mixed> Safe counts and scope labels only; never credentials or public-map tokens.
     */
    public function populate(User $owner, array $municipalityIds, bool $apply = false): array
    {
        if (count($municipalityIds) !== 3 || count(array_unique($municipalityIds, SORT_REGULAR)) !== 3
            || collect($municipalityIds)->contains(fn ($id) => ! is_int($id) || $id < 1)) {
            throw new DomainException('Select exactly three different municipality IDs, one per Region I province.');
        }
        sort($municipalityIds, SORT_NUMERIC);

        // Match the web plotting/boundary scope locks and their lexical ordering.
        $resources = array_map(fn ($id) => MunicipalityBoundary::class.':municipality:'.$id, $municipalityIds);
        sort($resources, SORT_STRING);
        $run = fn () => $this->writes->transaction(fn () => $this->execute($owner, $municipalityIds, $apply));
        foreach (array_reverse($resources) as $resource) {
            $next = $run;
            $run = fn () => Cache::lock('mutating-record:v2:'.hash('sha256', $resource), 120)->block(5, $next);
        }

        return Cache::lock('region-one-sample-data:v1', 120)->block(5, $run);
    }

    private function execute(User $owner, array $ids, bool $apply): array
    {
        $owner = User::query()->lockForUpdate()->find($owner->getKey());
        if (! $owner || ! $owner->is_active || $owner->role !== User::ROLE_SYSTEM_OWNER) {
            throw new DomainException('An existing active System Owner must authorize this sample import.');
        }
        if (! Schema::hasTable('audit_logs')) {
            throw new DomainException('A working audit trail is required before importing sample records.');
        }

        $scopes = $this->scopes($ids);
        $graph = $this->graph();
        $receipts = AuditLog::query()->where('module', 'region1_samples')->where('event', 'imported')
            ->where('auditable_type', self::class)->where('auditable_id', self::VERSION)
            ->lockForUpdate()->limit(2)->get();
        if ($receipts->isNotEmpty() || array_sum(array_map('count', $graph)) > 0) {
            if ($receipts->count() !== 1 || array_map('count', $graph) !== $this->counts()
                || ($receipts->first()->metadata['municipality_ids'] ?? null) !== $ids
                || ! hash_equals((string) ($receipts->first()->metadata['fingerprint'] ?? ''), $this->fingerprint($graph, $scopes))) {
                throw new DomainException('Sample records are incomplete, changed, or belong to another selection. Nothing was overwritten.');
            }

            return $this->summary('unchanged', $scopes);
        }

        // Generate every plot before the first insert, using the locked live boundaries.
        $plans = [];
        foreach ($scopes as $scope) {
            $query = FarmPlot::query()->whereHas('farmer', fn ($q) => $q->where('municipality_id', $scope['municipality']->id));
            if ((clone $query)->whereRaw('LENGTH(polygon_json) > ?', [131072])->exists()) {
                throw new DomainException('Existing parcel geometry exceeds this bounded sample import limit.');
            }
            $existing = $query->select(['id', 'polygon_json'])->orderBy('id')->lockForUpdate()->limit(129)->get();
            try {
                $plans[] = $this->plots->generate($scope['boundary']->geojson, 12, $existing->pluck('polygon_json')->all());
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                throw new DomainException('Cannot safely place all hypothetical plots in '.$scope['municipality']->name.'. '.$exception->getMessage());
            }
        }
        if (! $apply) {
            return $this->summary('preview', $scopes);
        }

        // Attribute model-observer events to the authorizing owner without creating a login/session.
        $guard = Auth::guard('web');
        $previous = $guard->user();
        $guard->setUser($owner);
        try {
            foreach ($scopes as $index => $scope) {
                $this->createMunicipalitySamples($owner, $scope, $plans[$index]);
            }
            $graph = $this->graph();
            if (array_map('count', $graph) !== $this->counts()) {
                throw new DomainException('The sample import did not produce the complete expected record set.');
            }
            $receipt = AuditTrail::record('imported', 'region1_samples', 'Created explicitly authorized synthetic Region I sample records.', [
                'actor' => $owner,
                'owner_only' => true,
                'auditable_type' => self::class,
                'auditable_id' => self::VERSION,
                'metadata' => [
                    'dataset_version' => self::VERSION,
                    'municipality_ids' => $ids,
                    'fingerprint' => $this->fingerprint($graph, $scopes),
                    'counts' => $this->counts(),
                    'classification' => 'synthetic_demonstration',
                ],
            ]);
            if ($receipt === null) {
                throw new DomainException('The required import receipt could not be saved. All sample inserts were rolled back.');
            }
        } finally {
            $previous ? $guard->setUser($previous) : $guard->forgetUser();
        }

        return $this->summary('created', $scopes);
    }

    /** @return array<int, array{municipality:Municipality, province:Province, boundary:MunicipalityBoundary}> */
    private function scopes(array $ids): array
    {
        $region = Region::query()->where('code', 'region1')->where('is_active', true)->lockForUpdate()->first();
        if (! $region) {
            throw new DomainException('Region I must already exist and be active.');
        }
        $scopes = [];
        $provinces = [];
        foreach ($ids as $id) {
            $municipality = Municipality::query()->lockForUpdate()->find($id);
            $province = $municipality ? Province::query()->lockForUpdate()->find($municipality->province_id) : null;
            if (! $municipality || ! $municipality->is_active || ! $province || ! $province->is_active
                || (int) $province->region_id !== (int) $region->id || ! in_array($province->name, self::PROVINCES, true)
                || in_array((int) $province->id, $provinces, true)) {
                throw new DomainException('Each selected municipality must be active and belong to a different active Region I province.');
            }
            $query = MunicipalityBoundary::query()->where('municipality_id', $id)->active();
            if ((clone $query)->whereRaw('LENGTH(geojson) > ?', [262144])->exists()) {
                throw new DomainException('The active boundary exceeds this bounded sample import limit.');
            }
            $boundaries = $query->lockForUpdate()->limit(2)->get();
            if ($boundaries->count() !== 1) {
                throw new DomainException('Exactly one active geofence is required for each selected municipality.');
            }
            $provinces[] = (int) $province->id;
            $scopes[] = ['municipality' => $municipality, 'province' => $province, 'boundary' => $boundaries->first()];
        }

        return $scopes;
    }

    private function createMunicipalitySamples(User $owner, array $scope, array $plots): void
    {
        $municipality = $scope['municipality'];
        $prefix = self::VERSION.'-M'.$municipality->id;
        $batch = RiceDistributionBatch::query()->create([
            'municipality_id' => $municipality->id,
            'reference' => $prefix.'-WET2026',
            'planting_season' => RiceDistributionBatch::SEASON_WET,
            'planting_year' => 2026,
            'default_seed_bag_kg' => 20,
            'notes' => self::NOTICE,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        for ($number = 1; $number <= 4; $number++) {
            $farmerPlots = array_slice($plots, ($number - 1) * 3, 3);
            $attributes = [
                'municipality_id' => $municipality->id,
                'ffrs' => $prefix.'-F'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'rsbsa_no' => null,
                'first_name' => 'Sample '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'last_name' => 'Synthetic Farmer',
                'gender' => 'Unspecified',
                'date_of_birth' => null,
                'contact_number' => null,
                'farm_location' => 'Hypothetical sample land only - '.$municipality->name,
                'farm_province' => $scope['province']->name,
                'farm_municipality' => $municipality->name,
                'farm_area_ha' => round(array_sum(array_column($farmerPlots, 'area_ha')), 2),
                'ecosystem' => 'Irrigated',
                'ecosystem_source' => 'Synthetic sample assumption',
                'is_arb' => false, 'is_4ps' => false, 'is_ip' => false,
                'is_pwd' => false, 'is_sc' => false, 'is_ofw' => false,
            ];
            $farmer = Farmer::query()->create($attributes);
            foreach ($farmerPlots as $index => $plot) {
                FarmPlot::query()->create($plot + [
                    'farmer_id' => $farmer->id,
                    'name' => $attributes['ffrs'].'-P'.($index + 1).' Hypothetical',
                    'color' => ['#15803D', '#B45309', '#1D4ED8', '#7E22CE'][$number - 1],
                ]);
            }
            unset($attributes['rsbsa_no']);
            $bags = [1, 2, 2, 3][$number - 1];
            RiceSeedDistribution::query()->create($attributes + [
                'farmer_id' => $farmer->id,
                'batch_id' => $batch->id,
                'input_category' => 'rice_seed',
                'quantity_unit' => 'kg',
                'input_notes' => self::NOTICE,
                'lot_series' => $farmer->ffrs,
                'seed_variety_claimed' => 'NSIC Rc222',
                'seed_variety_planted' => null,
                'seed_class' => 'Certified',
                'crop_establishment' => $number % 2 === 0 ? 'Transplanted' : 'Direct',
                'claimed_area_ha' => $attributes['farm_area_ha'],
                'registered_rice_area_ha' => $attributes['farm_area_ha'],
                'claimed_seeds_kg' => SeedReleaseQuantity::derivedKilograms($bags, 20),
                'seed_bags' => $bags,
                'seed_bag_kg' => 20,
                'kgs_received' => SeedReleaseQuantity::derivedKilograms($bags, 20),
                'date_received' => '2026-09-24',
                'consent_status' => RiceSeedDistribution::CONSENT_UNRECORDED,
                'kp_kits_received' => 0,
            ]);
        }
    }

    /** Read only the bounded cohort, including unexpectedly attached plots/releases. */
    private function graph(): array
    {
        $farmers = Farmer::query()->where('ffrs', 'like', self::VERSION.'-%')->orderBy('id')->lockForUpdate()->limit(13)->get();
        $batches = RiceDistributionBatch::query()->where('reference', 'like', self::VERSION.'-%')->orderBy('id')->lockForUpdate()->limit(4)->get();
        $plots = FarmPlot::query()->where(fn ($q) => $q->where('name', 'like', self::VERSION.'-%')->orWhereIn('farmer_id', $farmers->modelKeys()))
            ->orderBy('id')->lockForUpdate()->limit(37)->get();
        $releases = RiceSeedDistribution::query()->where(fn ($q) => $q->where('lot_series', 'like', self::VERSION.'-%')
            ->orWhereIn('farmer_id', $farmers->modelKeys())->orWhereIn('batch_id', $batches->modelKeys()))
            ->orderBy('id')->lockForUpdate()->limit(13)->get();

        return [
            'farmers' => $farmers->map(fn ($row) => $row->getRawOriginal())->all(),
            'plots' => $plots->map(fn ($row) => $row->getRawOriginal())->all(),
            'seed_releases' => $releases->map(fn ($row) => $row->getRawOriginal())->all(),
            'batches' => $batches->map(fn ($row) => $row->getRawOriginal())->all(),
        ];
    }

    private function fingerprint(array $graph, array $scopes): string
    {
        $identities = array_map(fn ($scope) => [
            'municipality_id' => (int) $scope['municipality']->id,
            'municipality' => $scope['municipality']->name,
            'province_id' => (int) $scope['province']->id,
            'province' => $scope['province']->name,
            'boundary_id' => (int) $scope['boundary']->id,
            'geometry_hash' => hash('sha256', json_encode($scope['boundary']->geojson, JSON_THROW_ON_ERROR)),
        ], $scopes);

        // Persist only this digest; raw graph data (including hidden tokens) never leaves memory.
        return hash('sha256', json_encode([$graph, $identities], JSON_THROW_ON_ERROR));
    }

    private function counts(): array
    {
        return ['farmers' => 12, 'plots' => 36, 'seed_releases' => 12, 'batches' => 3];
    }

    private function summary(string $status, array $scopes): array
    {
        return ['status' => $status] + $this->counts() + [
            'seed_quantity_kg' => 480,
            'municipalities' => array_map(fn ($scope) => [
                'id' => (int) $scope['municipality']->id,
                'name' => $scope['municipality']->name,
                'province' => $scope['province']->name,
            ], $scopes),
            'notice' => self::NOTICE.' Samples are included in operational totals.',
        ];
    }
}
