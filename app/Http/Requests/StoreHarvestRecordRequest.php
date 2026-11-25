<?php

namespace App\Http\Requests;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Modules\Agriculture\Application\HarvestFieldRules;
use App\Support\MunicipalityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

/**
 * Validation for recording or editing a harvest.
 *
 * The harvest fields themselves (commodity, season, year, quantity, unit and the
 * date-within-year rule) come from `HarvestFieldRules`, which the Excel import uses
 * too, so a single form and a bulk upload accept exactly the same harvest.
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
        $sectionReference = Schema::hasTable('parcel_crop_seasons')
            ? ['exists:parcel_crop_seasons,id']
            : [];

        // Ownership and linkage fields are specific to this form; the harvest fields
        // themselves are shared with the Excel import so the two cannot drift.
        return array_merge([
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'farm_plot_id' => ['nullable', 'integer', 'exists:farm_plots,id'],
            'parcel_crop_season_id' => array_merge(['nullable', 'integer'], $sectionReference),
            'planted_area_key' => ['nullable', 'string', 'max:64', 'regex:/^[a-f0-9]{8}-[a-f0-9-]{27,36}$/i'],
        ], HarvestFieldRules::rules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return HarvestFieldRules::messages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectOutOfScopeFarmer($validator);
            $this->rejectMismatchedParcel($validator);
            $this->rejectMismatchedCropSection($validator);
            HarvestFieldRules::rejectDateOutsideReportedYear(
                $validator,
                $this->input('date_harvested'),
                $this->input('harvest_year')
            );
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
     * A section reference is meaningful only when it belongs to the selected parcel,
     * farmer and reporting period. The area key is opaque and is never trusted on its
     * own; it is resolved inside the authorized season JSON.
     */
    private function rejectMismatchedCropSection(Validator $validator): void
    {
        $seasonId = $this->input('parcel_crop_season_id');
        $areaKey = $this->input('planted_area_key');

        if ($seasonId === null && $areaKey === null) {
            return;
        }
        if ($seasonId === null || $areaKey === null) {
            $validator->errors()->add('parcel_crop_season_id', 'Choose one saved planted section, or clear the section selection.');

            return;
        }
        if (! Schema::hasColumn('harvest_records', 'parcel_crop_season_id')
            || ! Schema::hasColumn('harvest_records', 'planted_area_key')
            || ! Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
            $validator->errors()->add('parcel_crop_season_id', 'Planted-section links are not available in this installation yet.');

            return;
        }
        if ($validator->errors()->hasAny(['farmer_id', 'farm_plot_id', 'harvest_year', 'season', 'commodity'])) {
            return;
        }

        $farmerId = $this->input('farmer_id');
        $plotId = $this->input('farm_plot_id');
        $record = ParcelCropSeason::query()->with('plot.farmer')->find($seasonId);
        if (! $record || $plotId === null || (int) $record->farm_plot_id !== (int) $plotId
            || ! $record->plot?->farmer || (int) $record->plot->farmer_id !== (int) $farmerId
            || ! app(MunicipalityAccess::class)->scope(Farmer::query(), $this->user())->whereKey($farmerId)->exists()
            || (int) $record->municipality_id !== (int) $record->plot->farmer->municipality_id) {
            $validator->errors()->add('parcel_crop_season_id', 'Choose a planted section from the selected farmer’s parcel.');

            return;
        }

        $area = collect($record->planted_areas ?: [])->firstWhere('area_key', $areaKey);
        if (! is_array($area)) {
            $validator->errors()->add('planted_area_key', 'That planted section no longer exists. Reload the harvest form and choose a current section.');

            return;
        }

        if ((int) $record->crop_year !== (int) $this->input('harvest_year')
            || $this->input('season') !== $record->season) {
            $validator->errors()->add('parcel_crop_season_id', 'The planted section and harvest must use the same year and season.');

            return;
        }

        $expectedCommodity = [
            'rice' => 'rice', 'corn' => 'corn', 'vegetables' => 'vegetable',
            'root_crops' => 'rootcrop', 'fruit' => 'fruit', 'legumes' => 'other',
        ][$area['crop'] ?? ''] ?? null;
        if ($expectedCommodity !== null && $expectedCommodity !== $this->input('commodity')) {
            $validator->errors()->add('parcel_crop_season_id', 'Choose a harvest commodity that matches the planted section.');
        }
    }

    private function nullIfBlank(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
