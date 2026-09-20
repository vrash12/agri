<?php

namespace App\Http\Requests;

use App\Models\RiceSeedDistribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssistanceCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', RiceSeedDistribution::class);
    }

    public function rules(): array
    {
        return [
            'province_id' => ['nullable', 'integer', 'min:1'],
            'municipality_id' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', Rule::in(array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'season' => ['nullable', Rule::in(['all', 'dry', 'wet', 'unrecorded'])],
            'year' => ['exclude_if:season,unrecorded', 'required_if:season,dry,wet', 'nullable', 'integer', 'between:1990,'.(now()->year + 1)],
        ];
    }

    public function messages(): array
    {
        return ['year.required_if' => 'Choose the planting year for this dry or wet season.'];
    }
}
