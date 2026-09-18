<?php

namespace App\Support;

use App\Models\HarvestRecord;
use App\Models\RiceSeedDistribution;

/**
 * Turns the production section of a Rice Seed Distribution Sheet into a harvest record.
 *
 * The sheet already has a production monitoring section and staff already fill it in
 * on paper, so asking them to enter a harvest a second time would be the surest way
 * to have it never entered at all. Saving a release with those fields writes the
 * matching harvest record instead.
 *
 * This is a projection, not a copy with a life of its own. The release stays the
 * place rice production is entered and edited; the harvest record is what the
 * production figures are read from, alongside harvests for commodities no seed sheet
 * covers. Re-saving a release updates its one record, and clearing the production
 * fields removes it.
 *
 * What counts as "production was recorded" is deliberately narrow: a year and a bag
 * count. A release with a planted variety but no harvest is a plan, not a harvest,
 * and projecting it would put a zero into a production chart for a field nobody has
 * cut yet.
 */
class HarvestFromRelease
{
    /**
     * Create, update or remove the harvest record a release projects to.
     *
     * Returns the record when one exists afterwards, and null when the release
     * carries no harvest to report.
     */
    public function sync(RiceSeedDistribution $release): ?HarvestRecord
    {
        if (! $this->hasHarvest($release)) {
            HarvestRecord::query()
                ->where('rice_seed_distribution_id', $release->getKey())
                ->delete();

            return null;
        }

        $record = HarvestRecord::query()
            ->firstOrNew(['rice_seed_distribution_id' => $release->getKey()]);

        $record->fill([
            'municipality_id' => $release->municipality_id,
            'farmer_id' => $release->farmer_id,
            'commodity' => 'rice',
            'variety' => $release->seed_variety_planted,
            'season' => $release->harvest_season,
            'harvest_year' => $release->harvest_year,
            'area_harvested_ha' => $release->avg_area_harvested_ha,

            /*
             * Bags, because bags are what the sheet counts.
             *
             * `avg_weight_per_bag_kg` sits beside it and multiplying the two would
             * give kilograms, but that figure is an average the office wrote down
             * rather than a weighed total. Storing the counted number keeps the chart
             * reporting what was actually observed; the bag weight is kept in the
             * note below so a kilogram estimate can still be worked out by someone
             * who wants one, and is seen to be an estimate when they do.
             */
            'quantity' => $release->total_production_bags,
            'quantity_unit' => 'bag',

            'notes' => $this->note($release),
        ]);

        $record->save();

        return $record;
    }

    /**
     * Whether the release actually reports a harvest.
     *
     * A year alone is a plan; a bag count alone has no period to report it in. Both
     * are needed before this becomes a figure the production chart can stand on.
     */
    public function hasHarvest(RiceSeedDistribution $release): bool
    {
        return $release->harvest_year !== null
            && $release->total_production_bags !== null;
    }

    private function note(RiceSeedDistribution $release): string
    {
        $parts = ['Projected from assistance release #'.$release->getKey().'.'];

        if ($release->avg_weight_per_bag_kg !== null) {
            $parts[] = 'Average bag weight recorded as '
                .number_format((float) $release->avg_weight_per_bag_kg, 2)
                .' kg, so the kilogram equivalent is an estimate rather than a weighed total.';
        }

        return implode(' ', $parts);
    }
}
