<?php

namespace App\Modules\Agriculture\Application;

use App\Models\HarvestRecord;
use App\Support\LocalTime;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The field rules every harvest write applies, whichever way it arrives.
 *
 * The single-record form and the Excel import accept the same harvest, so they share
 * one definition. Two copies would drift: a limit tightened on the form and not the
 * import would let a bulk upload store what the form refuses.
 *
 * The commodity, season and unit lists come from the model constants rather than a
 * copy kept beside the rules, so what is accepted here cannot drift from what the
 * production report understands.
 *
 * Ownership, farmer, parcel and planted-section checks are deliberately not here.
 * Each entry point resolves those against its own context (a route-bound record, a
 * chosen municipality) before anything is written.
 */
final class HarvestFieldRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'commodity' => ['required', Rule::in(array_keys(HarvestRecord::COMMODITY_LABELS))],
            'variety' => ['nullable', 'string', 'max:120'],
            'season' => ['nullable', Rule::in(array_keys(HarvestRecord::SEASON_LABELS))],

            // A harvest cannot be reported before records began or into a year that has
            // not started. The upper bound allows next year, because a dry-season
            // harvest is planned and recorded across a year boundary.
            'harvest_year' => ['required', 'integer', 'min:1990', 'max:'.(LocalTime::now()->year + 1)],
            'date_harvested' => ['nullable', 'date', 'before_or_equal:today'],

            'area_harvested_ha' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity_unit' => ['required', Rule::in(array_keys(HarvestRecord::QUANTITY_UNIT_LABELS))],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'commodity.required' => 'Choose what was harvested.',
            'commodity.in' => 'Choose one of the listed commodities.',
            'harvest_year.required' => 'Enter the year this harvest is reported in.',
            'harvest_year.max' => 'A harvest cannot be recorded for a year that has not started yet.',
            'quantity.required' => 'Enter how much was harvested.',
            'quantity_unit.required' => 'Choose the unit the harvest was counted in.',
            'quantity_unit.in' => 'Choose one of the listed units.',
            'date_harvested.before_or_equal' => 'A harvest date cannot be in the future.',
        ];
    }

    /**
     * A harvest date has to fall in the year the harvest is reported in.
     *
     * Not a formality: the year is what the production report groups by, so a date in
     * one year reported under another produces a chart nobody can reconcile against
     * the paper record. One year either side is allowed, because a dry-season harvest
     * genuinely straddles the turn of the year.
     *
     * Call from an `after` hook so the field rules have already run; a date or year
     * that failed its own rule is not checked again here.
     */
    public static function rejectDateOutsideReportedYear(Validator $validator, mixed $date, mixed $year): void
    {
        if ($date === null || $year === null) {
            return;
        }

        if ($validator->errors()->has('date_harvested') || $validator->errors()->has('harvest_year')) {
            return;
        }

        $dateYear = (int) date('Y', strtotime((string) $date));

        if (abs($dateYear - (int) $year) > 1) {
            $validator->errors()->add(
                'date_harvested',
                'The harvest date is in '.$dateYear.' but this harvest is reported under '.$year.'.'
            );
        }
    }
}
