<?php

namespace App\Http\Requests;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParcelCropSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plot = $this->route('plot');

        return $plot instanceof FarmPlot && $this->user()?->can('update', $plot)
            && $this->user()?->can('create', ParcelCropSeason::class);
    }

    public function rules(): array
    {
        return [
            'crop_year' => ['required', 'integer', 'between:1990,'.(LocalTime::now()->year + 1)],
            'season' => ['required', Rule::in(array_keys(ParcelCropSeason::SEASONS))],
            'crop' => ['required', Rule::in(array_keys(ParcelCropSeason::CROPS))],
            'notes' => ['nullable', 'string', 'max:500'],
            '_record_version' => ['required', 'string', 'regex:/^(new|[a-f0-9]{64})$/'],
        ];
    }
}
