<?php

namespace App\Support;

use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Assembles the Rice Seed Distribution Sheet: its titles, its grouped column
 * headings and one row per grouped assistance release.
 *
 * This is deliberately separate from the controller and from the spreadsheet
 * writer. The controller stays HTTP-thin, the writer only knows how to draw a
 * worksheet, and the sheet's meaning - which columns belong to which season, what
 * each column is called and where its value comes from - lives here.
 *
 * Season headings are always built from the stored season and year. Nothing is
 * inferred from the current date, so reprinting a 2024 sheet in 2026 still prints
 * the 2024 season.
 */
class RiceSeedDistributionSheet
{
    /**
     * Releases are read in bounded chunks: a municipal sheet can legitimately run
     * to thousands of recipients and must not be loaded into memory at once.
     */
    public const CHUNK_SIZE = 500;

    /**
     * Sheet titles, in print order.
     *
     * @return array<int, string>
     */
    public function titles(RiceDistributionBatch $batch): array
    {
        $season = $batch->plantingSeasonHeading();

        return array_values(array_filter([
            'RICE SEED DISTRIBUTION SHEET',
            $batch->municipality?->name
                ? mb_strtoupper($batch->municipality->name)
                : null,
            trim(implode(' - ', array_filter([
                $batch->reference,
                $season !== '' ? $season.' planting' : null,
            ]))) ?: null,
            $batch->notes ?: null,
        ]));
    }

    /**
     * Column groups drawn as one merged heading above their own columns.
     *
     * @return array<int, array{heading: string, columns: array<int, array<string, mixed>>}>
     */
    public function groups(RiceDistributionBatch $batch): array
    {
        $plantingHeading = $batch->plantingSeasonHeading();
        $harvestHeading = $batch->harvestSeasonHeading();

        return [
            [
                'heading' => 'FARMER PROFILE',
                'columns' => [
                    ['key' => 'sequence', 'label' => 'No.', 'width' => 6],
                    ['key' => 'last_name', 'label' => 'Last name', 'width' => 18],
                    ['key' => 'first_name', 'label' => 'First name', 'width' => 18],
                    ['key' => 'middle_name', 'label' => 'Middle name', 'width' => 16],
                    ['key' => 'ext_name', 'label' => 'Ext.', 'width' => 7],
                    ['key' => 'ffrs', 'label' => 'FFRS / RSBSA no.', 'width' => 22],
                    ['key' => 'gender', 'label' => 'Sex', 'width' => 8],
                    ['key' => 'farm_location', 'label' => 'Farm location', 'width' => 26],
                    ['key' => 'farm_area_ha', 'label' => 'Total farm area (ha)', 'width' => 14, 'decimals' => 2, 'total' => true],
                    ['key' => 'registered_rice_area_ha', 'label' => 'Registered rice area (ha)', 'width' => 15, 'decimals' => 2, 'total' => true],
                ],
            ],
            [
                'heading' => trim($plantingHeading.' SEED DISTRIBUTION'),
                'columns' => [
                    ['key' => 'date_received', 'label' => 'Date received', 'width' => 13],
                    ['key' => 'seed_variety_claimed', 'label' => 'Variety received', 'width' => 20],
                    ['key' => 'seed_class', 'label' => 'Seed class', 'width' => 14],
                    ['key' => 'seed_bags', 'label' => 'Bags', 'width' => 8, 'decimals' => 0, 'total' => true],
                    ['key' => 'seed_bag_kg', 'label' => 'Bag weight (kg)', 'width' => 12, 'decimals' => 2],
                    ['key' => 'kgs_received', 'label' => 'Total seed (kg)', 'width' => 13, 'decimals' => 2, 'total' => true],
                    ['key' => 'claimed_area_ha', 'label' => 'Area to be planted (ha)', 'width' => 14, 'decimals' => 2, 'total' => true],
                    ['key' => 'crop_establishment', 'label' => 'Crop establishment', 'width' => 17],
                    ['key' => 'date_of_sowing_label', 'label' => 'Sowing schedule', 'width' => 17],
                ],
            ],
            [
                'heading' => trim($harvestHeading.' PRODUCTION MONITORING'),
                'columns' => [
                    ['key' => 'harvest_season', 'label' => 'Harvest season', 'width' => 13],
                    ['key' => 'seed_variety_planted', 'label' => 'Variety planted', 'width' => 20],
                    ['key' => 'avg_area_harvested_ha', 'label' => 'Harvested area (ha)', 'width' => 14, 'decimals' => 2, 'total' => true],
                    ['key' => 'total_production_bags', 'label' => 'Production bags', 'width' => 13, 'decimals' => 0, 'total' => true],
                    ['key' => 'avg_weight_per_bag_kg', 'label' => 'Average bag weight (kg)', 'width' => 14, 'decimals' => 2],
                ],
            ],
            [
                'heading' => 'OTHER RECORDS',
                'columns' => [
                    ['key' => 'kp_kits_received', 'label' => 'KP kits received', 'width' => 12, 'decimals' => 0, 'total' => true],
                    ['key' => 'consent_status', 'label' => 'Data privacy consent', 'width' => 15],
                    ['key' => 'representative_name', 'label' => 'Representative', 'width' => 22],
                ],
            ],
            [
                'heading' => 'ACKNOWLEDGEMENT',
                'columns' => [
                    // Deliberately left empty for a handwritten signature at release.
                    ['key' => 'signature', 'label' => 'Signature of recipient', 'width' => 28, 'blank' => true],
                ],
            ],
        ];
    }

    /**
     * Flattened column definitions in print order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(RiceDistributionBatch $batch): array
    {
        $columns = [];

        foreach ($this->groups($batch) as $group) {
            foreach ($group['columns'] as $column) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Stream the sheet's rows in bounded chunks.
     *
     * `lazy()` keeps the printed order - surname, then first name - while reading
     * a page at a time. `chunkById()` cannot be used here: it pages on the primary
     * key, which does not follow that order and would skip recipients.
     *
     * @param  Closure(array<string, string>, RiceSeedDistribution): void  $onRow
     * @return int the number of printed rows
     */
    public function stream(RiceDistributionBatch $batch, Closure $onRow): int
    {
        $columns = $this->columns($batch);
        $sequence = 0;

        foreach ($this->releases($batch)->lazy(self::CHUNK_SIZE) as $release) {
            $sequence++;
            $onRow($this->row($release, $sequence, $columns), $release);
        }

        return $sequence;
    }

    /**
     * Rows for an already-loaded page of releases, used by the on-screen sheet.
     *
     * The screen and the workbook share `releases()`, `columns()` and this
     * formatting, so a printed sheet cannot disagree with the sheet on screen.
     *
     * @param  iterable<int, RiceSeedDistribution>  $releases
     * @return array<int, array<string, string>>
     */
    public function presentRows(
        RiceDistributionBatch $batch,
        iterable $releases,
        int $firstSequence = 1
    ): array {
        $columns = $this->columns($batch);
        $sequence = max(1, $firstSequence);
        $rows = [];

        foreach ($releases as $release) {
            $rows[] = $this->row($release, $sequence, $columns);
            $sequence++;
        }

        return $rows;
    }

    /**
     * Printed totals, aggregated by the database in one query over the same
     * filtered release query the rows come from.
     *
     * @return array<string, float>
     */
    public function totals(RiceDistributionBatch $batch): array
    {
        $keys = array_values(array_filter(
            array_column(
                array_filter(
                    $this->columns($batch),
                    static fn (array $column): bool => (bool) ($column['total'] ?? false)
                ),
                'key'
            ),
            // The keys come from this class's own column definitions, never from a
            // request; the guard keeps that true if a column is ever renamed.
            static fn (string $key): bool => (bool) preg_match('/^[a-z_]+$/', $key)
        ));

        if ($keys === []) {
            return [];
        }

        $expressions = array_map(
            // `kgs_received` holds a quantity, not a weight: its unit is in
            // `quantity_unit` and nothing converts between units. Only the rows
            // recorded in kilograms may reach a total printed under "(kg)" on a
            // sheet an officer signs. Every other totalled column here is a count or
            // an area and carries no unit, so it sums every row.
            static fn (string $key): string => $key === 'kgs_received'
                ? SeedReleaseQuantity::kilogramSumExpression($key).' as '.$key
                : 'COALESCE(SUM('.$key.'), 0) as '.$key,
            $keys
        );

        $aggregate = $this->releases($batch)
            ->reorder()
            ->selectRaw(implode(', ', $expressions))
            ->first();

        $totals = [];

        foreach ($keys as $key) {
            $totals[$key] = (float) ($aggregate?->getAttribute($key) ?? 0);
        }

        return $totals;
    }

    /**
     * The printed totals row, keyed the same way as a data row.
     *
     * @param  array<string, float>  $totals
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, string>
     */
    public function totalsRow(array $totals, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $key = (string) $column['key'];
            $row[$key] = array_key_exists($key, $totals)
                ? $this->number($totals[$key], (int) ($column['decimals'] ?? 2))
                : '';
        }

        $row['sequence'] = 'TOTAL';

        return $row;
    }

    /**
     * Releases grouped into this sheet.
     *
     * The municipality filter is redundant next to `batch_id` and is kept on
     * purpose: a release must never reach another municipality's sheet even if a
     * stray batch reference were ever written.
     */
    public function releases(RiceDistributionBatch $batch): Builder
    {
        return RiceSeedDistribution::query()
            ->where('batch_id', $batch->getKey())
            ->where('municipality_id', $batch->municipality_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, string>
     */
    private function row(RiceSeedDistribution $release, int $sequence, array $columns): array
    {
        $values = [
            'sequence' => (string) $sequence,
            'last_name' => (string) ($release->last_name ?? ''),
            'first_name' => (string) ($release->first_name ?? ''),
            'middle_name' => (string) ($release->middle_name ?? ''),
            'ext_name' => (string) ($release->ext_name ?? ''),
            'ffrs' => (string) ($release->ffrs ?? ''),
            'gender' => (string) ($release->gender ?? ''),
            'farm_location' => (string) ($release->farm_location ?? ''),
            'date_received' => optional($release->date_received)->format('Y-m-d') ?? '',
            'seed_variety_claimed' => (string) ($release->seed_variety_claimed ?? ''),
            'seed_class' => (string) ($release->seed_class ?? ''),
            'crop_establishment' => (string) ($release->crop_establishment ?? ''),
            'date_of_sowing_label' => (string) ($release->date_of_sowing_label ?? ''),
            'harvest_season' => $release->harvestSeasonHeading(),
            'seed_variety_planted' => (string) ($release->seed_variety_planted ?? ''),
            'consent_status' => $release->consentStatusLabel(),
            'representative_name' => (string) ($release->representative_name ?? ''),
            'signature' => '',
        ];

        foreach ($columns as $column) {
            $key = (string) $column['key'];

            if (array_key_exists($key, $values) || ($column['blank'] ?? false)) {
                continue;
            }

            // A release recorded in another unit states that unit on its own line.
            // The total underneath counts kilograms only, so printing a bare number
            // here would leave the column not adding up to its own total — the one
            // thing a signed form must never do.
            if ($key === 'kgs_received' && ! SeedReleaseQuantity::isKilogramUnit($release->quantity_unit)) {
                $values[$key] = trim(
                    $this->number($release->getAttribute($key), (int) ($column['decimals'] ?? 2))
                    .' '.$release->quantityUnitLabel()
                );

                continue;
            }

            $values[$key] = $this->number(
                $release->getAttribute($key),
                (int) ($column['decimals'] ?? 2)
            );
        }

        return $values;
    }

    private function number(mixed $value, int $decimals): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        return number_format((float) $value, $decimals, '.', '');
    }
}
