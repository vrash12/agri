<?php

namespace App\Http\Requests;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for recording or editing a harvest.
 *
 * The commodity, season and unit lists come from the model constants rather than a
 * copy kept beside the rules, so what is accepted here cannot drift from what the
 * production report understands.
 *
 * A harvest is deliberately allowed to be thin. The farmer and the parcel are both
 * optional, because a municipality reporting its corn tonnage for a season has a real
 * figure and no single farmer to attach it to, and refusing that would push the
 * number into a spreadsheet the system never sees. What is required is the minimum
 * the figure needs to mean anything: a commodity, a quantity with the unit it was
 * counted in, and a year to report it in.
 */
class StoreHarvestRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('harvest_record');

        return (bool) ($record instanceof HarvestRecord
            ? $this->user()?->can('update', $record)
            : $this->user()?->can('create', HarvestRecord::class));
    }

    protected function prepareForValidation(): void
    {
        // Empty selects arrive as "", which is not null and would fail an integer or
        // an "in" rule on a field the operator deliberately left blank.
        $this->merge([
            'farmer_id' => $this->nullIfBlank('farmer_id'),
            'farm_plot_id' => $this->nullIfBlank('farm_plot_id'),
            'season' => $this->nullIfBlank('season'),
            'variety' => $this->nullIfBlank('variety'),
            'date_harvested' => $this->nullIfBlank('date_harvested'),
            'area_harvested_ha' => $this->nullIfBlank('area_harvested_ha'),
            'notes' => $this->nullIfBlank('notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'farm_plot_id' => ['nullable', 'integer', 'exists:farm_plots,id'],

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
    public function messages(): array
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectOutOfScopeFarmer($validator);
            $this->rejectMismatchedParcel($validator);
            $this->rejectDateOutsideReportedYear($validator);
        });
    }

    /**
     * The farmer must be one this account can already see.
     *
     * `exists:farmers,id` only proves the row is real. Without this an operator could
     * attach a harvest to a farmer in another municipality by posting the id, and the
     * record would then be owned by one municipality while naming another's farmer.
     */
    private function rejectOutOfScopeFarmer(Validator $validator): void
    {
        $farmerId = $this->input('farmer_id');

        if ($farmerId === null || $validator->errors()->has('farmer_id')) {
            return;
        }

        $visible = app(MunicipalityAccess::class)
            ->scope(Farmer::query(), $this->user())
            ->whereKey($farmerId)
            ->exists();

        if (! $visible) {
            $validator->errors()->add('farmer_id', 'Choose a farmer from your own municipality.');
        }
    }

    /**
     * The parcel must belong to the farmer the harvest names.
     *
     * A parcel with no farmer on the form, or a parcel belonging to someone else,
     * would put an area against land the harvest has no claim to.
     */
    private function rejectMismatchedParcel(Validator $validator): void
    {
        $plotId = $this->input('farm_plot_id');

        if ($plotId === null || $validator->errors()->has('farm_plot_id')) {
            return;
        }

        $farmerId = $this->input('farmer_id');

        if ($farmerId === null) {
            $validator->errors()->add('farm_plot_id', 'Choose the farmer first, then one of their parcels.');

            return;
        }

        $belongs = FarmPlot::query()
            ->whereKey($plotId)
            ->where('farmer_id', $farmerId)
            ->exists();

        if (! $belongs) {
            $validator->errors()->add('farm_plot_id', 'That parcel belongs to a different farmer.');
        }
    }

    /**
     * A harvest date has to fall in the year the harvest is reported in.
     *
     * Not a formality: the year is what the production report groups by, so a date in
     * one year reported under another produces a chart nobody can reconcile against
     * the paper record. One year either side is allowed, because a dry-season harvest
     * genuinely straddles the turn of the year.
     */
    private function rejectDateOutsideReportedYear(Validator $validator): void
    {
        $date = $this->input('date_harvested');
        $year = $this->input('harvest_year');

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

    private function nullIfBlank(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
