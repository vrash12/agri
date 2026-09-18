<?php

namespace App\Support;

use App\Models\RiceSeedDistribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Has this farmer already been given this input?
 *
 * A subsidy programme's central control is that one entitlement is issued once. The
 * register had nothing to enforce that: two staff working the same queue, or the same
 * person on two days, could record the same farmer receiving rice seed twice for the
 * same planting with nothing to notice.
 *
 * This warns. It does not refuse, and it is important that it does not: a second
 * release is sometimes exactly right — a replanting after a typhoon, a delivery split
 * across two days because the truck was short, or two genuinely different cropping
 * seasons. Refusing those would teach staff to work around the system, which is worse
 * than not checking. The officer sees what the earlier release was and decides, and
 * the decision is recorded.
 *
 * What counts as "the same period" depends on what was actually written down:
 *
 * - When both releases state a harvest season and year, those are compared. A stated
 *   season is a fact the office recorded.
 * - Otherwise a window of days around the receipt date is used. The season is never
 *   guessed from a date — `RiceSeedDistribution` is explicit that harvest season is
 *   stated and never inferred — and a window is not a guess about which season a
 *   release belongs to, only a statement that two hand-overs were close together.
 */
class DuplicateClaimCheck
{
    public function __construct(private MunicipalityAccess $municipalityAccess)
    {
    }

    /**
     * Earlier releases of the same input to the same farmer that an officer should
     * see before recording another.
     *
     * Ordered most recent first, because the last release is the one that decides
     * whether this one looks like a repeat.
     *
     * @return Collection<int, RiceSeedDistribution>
     */
    public function priorClaims(
        int $farmerId,
        ?string $inputCategory,
        ?string $dateReceived,
        ?string $harvestSeason = null,
        ?int $harvestYear = null,
        ?int $ignoreReleaseId = null
    ): Collection {
        if ($farmerId <= 0 || blank($inputCategory)) {
            return collect();
        }

        $query = RiceSeedDistribution::query()
            ->where('farmer_id', $farmerId)
            ->where('input_category', $inputCategory);

        if ($ignoreReleaseId !== null) {
            $query->whereKeyNot($ignoreReleaseId);
        }

        // A stated season is the office's own answer about which planting this
        // belongs to, so it beats any window.
        if (filled($harvestSeason) && $harvestYear !== null) {
            $query->where('harvest_season', $harvestSeason)->where('harvest_year', $harvestYear);

            return $query->orderByDesc('date_received')->orderByDesc('id')->get();
        }

        $received = $this->parseDate($dateReceived);

        if (! $received) {
            // With no date and no season there is no period to compare within, so
            // every earlier release of this input is worth seeing.
            return $query->orderByDesc('date_received')->orderByDesc('id')->limit(5)->get();
        }

        $window = max(1, (int) config('assistance.duplicate_claim_window_days', 180));

        return $query
            ->whereNotNull('date_received')
            ->whereBetween('date_received', [
                $received->copy()->subDays($window)->toDateString(),
                $received->copy()->addDays($window)->toDateString(),
            ])
            ->orderByDesc('date_received')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * A sentence an officer can act on, or null when nothing was found.
     *
     * Names the date and quantity of the closest earlier release rather than saying
     * only that a duplicate exists: "already received rice seed" prompts a shrug,
     * "received 40 kg on 20 January, 12 days ago" prompts a check.
     */
    public function warningFor(
        int $farmerId,
        ?string $inputCategory,
        ?string $dateReceived,
        ?string $harvestSeason = null,
        ?int $harvestYear = null,
        ?int $ignoreReleaseId = null
    ): ?string {
        $prior = $this->priorClaims($farmerId, $inputCategory, $dateReceived, $harvestSeason, $harvestYear, $ignoreReleaseId);

        if ($prior->isEmpty()) {
            return null;
        }

        /** @var RiceSeedDistribution $latest */
        $latest = $prior->first();
        $label = RiceSeedDistribution::INPUT_CATEGORY_LABELS[$inputCategory] ?? $inputCategory;
        $name = trim(collect([$latest->first_name, $latest->last_name])->filter()->implode(' ')) ?: 'This farmer';

        $when = $latest->date_received
            ? 'on '.Carbon::parse($latest->date_received)->format('j F Y')
            : 'with no date recorded';

        $quantity = $latest->kgs_received !== null
            ? number_format((float) $latest->kgs_received, 2).' '.($latest->quantityUnitLabel() ?: '')
            : null;

        $count = $prior->count();
        $others = $count > 1 ? sprintf(' There are %d earlier releases of this input in the same period.', $count) : '';

        return trim(sprintf(
            '%s already received %s %s%s.%s Check this is a separate entitlement before saving.',
            $name,
            $label,
            $quantity ? '('.trim($quantity).') ' : '',
            $when,
            $others
        ));
    }

    /**
     * Repeat claims already sitting in the register, for review.
     *
     * Scoped to what the account may read. Returns one row per farmer and input with
     * more than one release in the same year of receipt — deliberately a calendar
     * year rather than the entry-time window, because a review list should be
     * generous about what it surfaces and let a person dismiss it.
     *
     * @return Collection<int, object>
     */
    public function existingRepeats(\App\Models\User $user): Collection
    {
        $scoped = $this->municipalityAccess->scope(RiceSeedDistribution::query(), $user);

        return $scoped
            ->whereNotNull('farmer_id')
            ->whereNotNull('input_category')
            ->where('input_category', '!=', '')
            ->whereNotNull('date_received')
            ->selectRaw('farmer_id, input_category, YEAR(date_received) as claim_year, COUNT(*) as releases')
            ->selectRaw('MIN(date_received) as first_received, MAX(date_received) as last_received')
            ->groupBy('farmer_id', 'input_category', 'claim_year')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('claim_year')
            ->orderByDesc('releases')
            ->get();
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
