<?php

namespace App\Http\Requests;

use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use App\Support\SeedReleaseQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for recording or editing an agriculture or fisheries assistance release.
 *
 * The category and unit lists come from the model constants rather than a copy kept
 * beside the rules, so the values accepted here cannot drift from the values the
 * rest of the system understands.
 *
 * `authorize()` runs the policy before the rules, matching the order the controller
 * used when it called `authorize()` itself.
 */
class StoreRiceSeedDistributionRequest extends FormRequest
{
    /**
     * Establishment methods offered on the form.
     */
    public const CROP_ESTABLISHMENT = ['Direct', 'Transplanted'];

    /**
     * Seed classes offered on the form.
     *
     * "Registered" is a real seed class that imported records already use. It was
     * missing here, so editing one of those releases failed validation on a value
     * the operator never touched.
     */
    public const SEED_CLASSES = ['Certified', 'Registered', 'Not Specified'];

    public function authorize(): bool
    {
        $release = $this->routeRelease();

        return (bool) ($release instanceof RiceSeedDistribution
            ? $this->user()?->can('update', $release)
            : $this->user()?->can('create', RiceSeedDistribution::class));
    }

    /**
     * Apply the defaults the form relies on before anything is checked.
     */
    protected function prepareForValidation(): void
    {
        $merged = [
            'input_category' => $this->input('input_category', 'rice_seed'),
            'quantity_unit' => $this->input('quantity_unit', 'kg'),
            'consent_status' => $this->normalizedConsentStatus(),
            'harvest_season' => RiceDistributionBatch::normalizeSeason($this->input('harvest_season')),
        ];

        // Bags x bag weight is the recorded release, so it overrides whatever the
        // total field carried. `kgs_received` stays the only stored total.
        //
        // The derivation only holds when the release is recorded in kilograms. Bag
        // weight is stated in kilograms, so deriving it for a release counted in
        // pieces or sets would write a kilogram figure under a unit that is not
        // kilograms, and every later total would read it as weight.
        if (SeedReleaseQuantity::isKilogramUnit($merged['quantity_unit'])) {
            $derivedKilograms = SeedReleaseQuantity::derivedKilograms(
                $this->input('seed_bags'),
                $this->input('seed_bag_kg')
            );

            if ($derivedKilograms !== null) {
                $merged['kgs_received'] = $derivedKilograms;
            }
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $latestYear = (int) now()->year + 1;

        return [
            'municipality_id' => ['nullable', 'integer'],
            'farmer_id' => ['required', 'exists:farmers,id'],
            // Ownership of the chosen sheet is confirmed against the resolved
            // municipality in the controller; `exists` alone would leak nothing but
            // would not keep a release out of another municipality's sheet.
            'batch_id' => ['nullable', 'integer', 'exists:rice_distribution_batches,id'],

            'input_category' => ['required', Rule::in(array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))],
            'seed_variety_claimed' => ['required', 'string', 'max:200'],
            'quantity_unit' => ['required', Rule::in(array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS))],
            'input_notes' => ['nullable', 'string', 'max:1000'],
            'claimed_area_ha' => ['nullable', 'numeric', 'min:0'],
            'claimed_seeds_kg' => ['nullable', 'numeric', 'min:0'],
            'lot_series' => ['nullable', 'string'],
            'crop_establishment' => ['nullable', Rule::in($this->allowedWithStoredValue(self::CROP_ESTABLISHMENT, 'crop_establishment'))],
            'date_of_sowing_label' => ['nullable', 'string', 'max:60'],
            'registered_rice_area_ha' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            'avg_weight_per_bag_kg' => ['nullable', 'integer', 'min:0'],
            'total_production_bags' => ['nullable', 'integer', 'min:0'],
            'avg_area_harvested_ha' => ['nullable', 'numeric', 'min:0'],
            'seed_variety_planted' => ['nullable', 'string', 'max:200'],
            'seed_class' => ['nullable', Rule::in($this->allowedWithStoredValue(self::SEED_CLASSES, 'seed_class'))],
            'harvest_season' => ['nullable', Rule::in(array_keys(RiceDistributionBatch::SEASONS))],
            'harvest_year' => ['nullable', 'integer', 'between:1990,'.$latestYear],

            'seed_bags' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'seed_bag_kg' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'kgs_received' => ['required', 'numeric', 'min:0'],
            'date_received' => ['required', 'date'],

            'consent_status' => ['required', Rule::in(array_keys(RiceSeedDistribution::CONSENT_STATUS_LABELS))],
            'kp_kits_received' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'representative_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'harvest_season.in' => 'Choose a valid harvest season, or leave it blank until harvest is recorded.',
            'consent_status.in' => 'Record the data-privacy consent as yes or no, or leave it unrecorded.',
        ];
    }

    /**
     * Keep a legacy value the record already carries acceptable.
     *
     * Imported releases hold establishment methods and seed classes this form has
     * never offered. Rejecting them would make those records impossible to edit at
     * all, so the value already stored on the record being edited is allowed
     * through untouched while new records still get the current list.
     *
     * @param  array<int, string>  $offered
     * @return array<int, string>
     */
    private function allowedWithStoredValue(array $offered, string $attribute): array
    {
        $release = $this->routeRelease();

        if (! $release instanceof RiceSeedDistribution) {
            return $offered;
        }

        $stored = $release->getAttribute($attribute);

        if (! is_string($stored) || trim($stored) === '' || in_array($stored, $offered, true)) {
            return $offered;
        }

        return [...$offered, $stored];
    }

    /**
     * The release being edited, if any.
     *
     * The resource route names this parameter `riceSeedDistribution`; reading any
     * other key silently returns null, which would authorize an update as if it
     * were a create.
     */
    private function routeRelease(): ?RiceSeedDistribution
    {
        $release = $this->route('riceSeedDistribution');

        return $release instanceof RiceSeedDistribution ? $release : null;
    }

    /**
     * An unanswered consent question stays unanswered. A value that is not one of
     * the three recognised states is left alone so the rules reject it instead of
     * quietly recording something the farmer never said.
     */
    private function normalizedConsentStatus(): mixed
    {
        $status = $this->input('consent_status');

        if (is_string($status)) {
            $status = mb_strtolower(trim($status));
        }

        if ($status === null || $status === '') {
            return RiceSeedDistribution::CONSENT_UNRECORDED;
        }

        return $status;
    }

    /**
     * Fingerlings are counted, not weighed, so the dashboard can report a real
     * fingerling total rather than a mix of kilograms and pieces.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('input_category') === 'fish_fingerlings'
                && $this->input('quantity_unit') !== 'piece') {
                $validator->errors()->add(
                    'quantity_unit',
                    'Fish fingerlings must be recorded by piece so the dashboard can report an accurate fingerling count.'
                );
            }
        });
    }
}
