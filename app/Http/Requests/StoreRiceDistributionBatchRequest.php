<?php

namespace App\Http\Requests;

use App\Models\RiceDistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating or editing a Rice Seed Distribution Sheet batch.
 *
 * `authorize()` runs the policy before the rules, so an account that may not
 * manage operational data - a Super Administrator or the System Owner - is
 * refused instead of being handed a description of the form.
 *
 * `municipality_id` is accepted but never trusted: the controller resolves real
 * ownership through `MunicipalityAccess::resolveForWrite()`.
 */
class StoreRiceDistributionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('riceDistributionBatch');

        return (bool) ($batch instanceof RiceDistributionBatch
            ? $this->user()?->can('update', $batch)
            : $this->user()?->can('create', RiceDistributionBatch::class));
    }

    /**
     * Accept "DS", "dry" or "Dry season" from the form and store one key, so the
     * sheet heading is built from a value the application understands.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'planting_season' => RiceDistributionBatch::normalizeSeason($this->input('planting_season')),
            'harvest_season' => RiceDistributionBatch::normalizeSeason($this->input('harvest_season')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $latestYear = (int) now()->year + 1;

        return [
            'municipality_id' => ['nullable', 'integer'],
            'reference' => ['required', 'string', 'max:120'],
            'planting_season' => ['required', Rule::in(array_keys(RiceDistributionBatch::SEASONS))],
            'planting_year' => ['required', 'integer', 'between:1990,'.$latestYear],
            'harvest_season' => ['nullable', Rule::in(array_keys(RiceDistributionBatch::SEASONS))],
            // The harvest season is recorded explicitly; it is never inferred from
            // the planting season, so it may legitimately stay empty for a sheet
            // that has not been harvested yet.
            'harvest_year' => ['nullable', 'integer', 'between:1990,'.$latestYear],
            'default_seed_bag_kg' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'planting_season.required' => 'Choose the planting season this sheet covers.',
            'planting_season.in' => 'Choose the planting season this sheet covers.',
            'harvest_season.in' => 'Choose a valid harvest season, or leave it blank until harvest is recorded.',
        ];
    }
}
