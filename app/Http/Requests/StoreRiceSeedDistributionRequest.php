<?php

namespace App\Http\Requests;

use App\Models\RiceSeedDistribution;
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
     */
    public const SEED_CLASSES = ['Certified', 'Not Specified'];

    public function authorize(): bool
    {
        $release = $this->route('rice_seed_distribution');

        return (bool) ($release instanceof RiceSeedDistribution
            ? $this->user()?->can('update', $release)
            : $this->user()?->can('create', RiceSeedDistribution::class));
    }

    /**
     * Apply the defaults the form relies on before anything is checked.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'input_category' => $this->input('input_category', 'rice_seed'),
            'quantity_unit' => $this->input('quantity_unit', 'kg'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['nullable', 'integer'],
            'farmer_id' => ['required', 'exists:farmers,id'],

            'input_category' => ['required', Rule::in(array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))],
            'seed_variety_claimed' => ['required', 'string', 'max:200'],
            'quantity_unit' => ['required', Rule::in(array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS))],
            'input_notes' => ['nullable', 'string', 'max:1000'],
            'claimed_area_ha' => ['nullable', 'numeric', 'min:0'],
            'claimed_seeds_kg' => ['nullable', 'numeric', 'min:0'],
            'lot_series' => ['nullable', 'string'],
            'crop_establishment' => ['nullable', Rule::in(self::CROP_ESTABLISHMENT)],
            'date_of_sowing_label' => ['nullable', 'string', 'max:60'],

            'avg_weight_per_bag_kg' => ['nullable', 'integer', 'min:0'],
            'total_production_bags' => ['nullable', 'integer', 'min:0'],
            'avg_area_harvested_ha' => ['nullable', 'numeric', 'min:0'],
            'seed_variety_planted' => ['nullable', 'string', 'max:200'],
            'seed_class' => ['nullable', Rule::in(self::SEED_CLASSES)],

            'kgs_received' => ['required', 'numeric', 'min:0'],
            'date_received' => ['required', 'date'],
        ];
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
