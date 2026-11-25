<?php

namespace App\Modules\Reporting\Application;

use App\Models\GeneratedReport;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * The in-app period report archive: who may see which report, on-demand
 * generation for the signed-in account, and the automatic monthly run.
 *
 * Scope always comes from MunicipalityAccess. A request can narrow a report to
 * one municipality the account may already see; it can never widen it. Reports
 * are stored as aggregate snapshots and are never emailed.
 */
final class PeriodReports
{
    public function __construct(
        private MunicipalityAccess $access,
        private PeriodReportBuilder $builder,
    ) {}

    /** Reports the account may list. The policy rechecks each report it opens. */
    public function visibleQuery(User $user): Builder
    {
        $query = GeneratedReport::query();
        if (! Gate::forUser($user)->allows('viewAny', GeneratedReport::class)) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isSystemOwner()) {
            return $query;
        }

        $municipalities = $this->access->scopeMunicipalities(Municipality::query(), $user)->select('municipalities.id');

        return $query->where(function (Builder $visible) use ($user, $municipalities): void {
            $visible->where(fn (Builder $q) => $q->where('scope_type', 'municipality')->whereIn('municipality_id', $municipalities));
            if ($user->requiresProvince() || $user->isRegionalHead()) {
                $visible->orWhere(fn (Builder $q) => $q->where('scope_type', 'province')
                    ->whereIn('province_id', $this->access->scopeProvinces(Province::query(), $user)->select('provinces.id')));
            }
            if ($user->isRegionalHead()) {
                $visible->orWhere(fn (Builder $q) => $q->where('scope_type', 'region')->where('region_id', $user->region_id));
            }
        });
    }

    /**
     * Builds and archives a report for the account's area or one municipality in it.
     */
    public function generate(User $user, string $periodType, int $year, ?int $part, ?int $municipalityId): GeneratedReport
    {
        Gate::forUser($user)->authorize('create', GeneratedReport::class);
        [$start, $end] = $this->period($periodType, $year, $part);

        if ($municipalityId !== null) {
            abort_unless($user->canAccessMunicipality($municipalityId), 403, 'Choose a municipality in your assigned area.');
            $scope = ['scope_type' => 'municipality', 'municipality_id' => $municipalityId];
            $ids = [$municipalityId];
        } else {
            $scope = $this->accountScope($user);
            $ids = $this->access->scopeMunicipalities(Municipality::query(), $user)
                ->active()->pluck('municipalities.id')->map(fn ($id) => (int) $id)->all();
        }

        $report = $this->store($scope, $ids, $periodType, $start, $end, GeneratedReport::SOURCE_ON_DEMAND, $user->id, null);

        AuditTrail::record('generated', 'Reports', 'Generated a period report.', [
            'auditable' => $report,
            'metadata' => [
                'period_type' => $periodType,
                'period_start' => $start->toDateString(),
                'scope_type' => $scope['scope_type'],
                'municipality_count' => count($ids),
            ],
        ]);

        return $report;
    }

    /**
     * The automatic monthly run: one report per active municipality and per active
     * province for the given month. Re-running the same month adds nothing.
     *
     * @return array{created:int, existing:int}
     */
    public function generateMonthly(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay();
        $counts = ['created' => 0, 'existing' => 0];

        $municipalities = Municipality::query()->active()
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('id')->get(['id', 'province_id']);
        foreach ($municipalities as $municipality) {
            $this->scheduled($counts, ['scope_type' => 'municipality', 'municipality_id' => $municipality->id], [(int) $municipality->id], $start, $end);
        }

        $byProvince = $municipalities->whereNotNull('province_id')->groupBy('province_id');
        $provinces = Province::query()->where('is_active', true)->whereIn('id', $byProvince->keys())->orderBy('id')->pluck('id');
        foreach ($provinces as $provinceId) {
            $ids = $byProvince->get($provinceId)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->scheduled($counts, ['scope_type' => 'province', 'province_id' => (int) $provinceId], $ids, $start, $end);
        }

        return $counts;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function period(string $periodType, int $year, ?int $part): array
    {
        if ($year < 1990 || $year > LocalTime::now()->year) {
            throw new InvalidArgumentException('Choose a reporting year that has already started.');
        }
        $start = match ($periodType) {
            'month' => CarbonImmutable::create($year, $this->bounded($part, 12), 1, 0, 0, 0, LocalTime::timezone()),
            'quarter' => CarbonImmutable::create($year, ($this->bounded($part, 4) - 1) * 3 + 1, 1, 0, 0, 0, LocalTime::timezone()),
            'year' => CarbonImmutable::create($year, 1, 1, 0, 0, 0, LocalTime::timezone()),
            default => throw new InvalidArgumentException('Choose a monthly, quarterly or annual report.'),
        };
        $end = match ($periodType) {
            'month' => $start->endOfMonth(),
            'quarter' => $start->addMonths(2)->endOfMonth(),
            default => $start->endOfYear(),
        };
        if ($start->greaterThan(LocalTime::now())) {
            throw new InvalidArgumentException('A report cannot cover a period that has not started yet.');
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    /** @param array{created:int, existing:int} $counts */
    private function scheduled(array &$counts, array $scope, array $ids, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $key = 'scheduled:month:'.$start->format('Y-m').':'.$scope['scope_type'].':'.($scope['municipality_id'] ?? $scope['province_id']);
        if (GeneratedReport::query()->where('dedupe_key', $key)->exists()) {
            $counts['existing']++;

            return;
        }
        try {
            $this->store($scope, $ids, 'month', $start, $end, GeneratedReport::SOURCE_SCHEDULED, null, $key);
            $counts['created']++;
        } catch (QueryException $exception) {
            // A concurrent run created the same report first; the unique key held.
            if (! GeneratedReport::query()->where('dedupe_key', $key)->exists()) {
                throw $exception;
            }
            $counts['existing']++;
        }
    }

    /** @param array<int, int> $ids */
    private function store(array $scope, array $ids, string $periodType, CarbonImmutable $start, CarbonImmutable $end, string $source, ?int $userId, ?string $dedupeKey): GeneratedReport
    {
        $summary = $this->builder->build($ids, $start, $end);
        $summary['partial'] = $end->endOfDay()->greaterThan(LocalTime::now());
        $summary['generated_at'] = now()->toIso8601String();

        return GeneratedReport::create($scope + [
            'period_type' => $periodType,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'municipality_ids' => array_values(array_unique($ids)),
            'title' => $this->title($scope, $periodType, $start),
            'summary' => $summary,
            'source' => $source,
            'dedupe_key' => $dedupeKey,
            'generated_by' => $userId,
        ]);
    }

    /** @return array<string, mixed> */
    private function accountScope(User $user): array
    {
        return match (true) {
            $user->isSystemOwner() => ['scope_type' => 'all'],
            $user->isRegionalHead() => ['scope_type' => 'region', 'region_id' => $user->region_id],
            $user->requiresProvince() => ['scope_type' => 'province', 'province_id' => $user->province_id],
            default => ['scope_type' => 'municipality', 'municipality_id' => $user->municipality_id],
        };
    }

    private function title(array $scope, string $periodType, CarbonImmutable $start): string
    {
        $area = match ($scope['scope_type']) {
            'municipality' => Municipality::query()->whereKey($scope['municipality_id'])->value('name') ?? 'Municipality',
            'province' => (Province::query()->whereKey($scope['province_id'])->value('name') ?? 'Province').' province',
            'region' => Region::query()->whereKey($scope['region_id'])->value('name') ?? 'Assigned region',
            default => 'All municipalities',
        };
        $period = match ($periodType) {
            'month' => $start->format('F Y'),
            'quarter' => 'Q'.$start->quarter.' '.$start->year,
            default => (string) $start->year,
        };

        return mb_substr($area.' · '.$period, 0, 200);
    }

    private function bounded(?int $value, int $max): int
    {
        if ($value === null || $value < 1 || $value > $max) {
            throw new InvalidArgumentException('Choose a valid month or quarter.');
        }

        return $value;
    }
}
