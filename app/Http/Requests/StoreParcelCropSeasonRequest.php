<?php

namespace App\Http\Requests;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            '_plot_version' => [Rule::requiredIf($this->exists('planted_areas')), 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'planted_areas' => ['sometimes', 'array', 'max:8'],
            'planted_areas.*' => ['array:name,crop,variety,polygon,area_ha'],
            'planted_areas.*.name' => ['nullable', 'string', 'max:80'],
            'planted_areas.*.variety' => ['nullable', 'string', 'max:80'],
            'planted_areas.*.crop' => ['required', Rule::in(array_diff(array_keys(ParcelCropSeason::CROPS), ['not_recorded']))],
            'planted_areas.*.polygon' => ['required', 'array', 'min:3', 'max:50'],
            'planted_areas.*.polygon.*' => ['required', 'array:lat,lng'],
            'planted_areas.*.polygon.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'planted_areas.*.polygon.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $areas = $this->input('planted_areas');
        if (is_string($areas)) {
            if (strlen($areas) > 100000) {
                throw ValidationException::withMessages(['planted_areas' => 'The planted-area drawing is too large. Use at most 8 areas with 50 points each.']);
            }
            try {
                $areas = json_decode($areas, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ValidationException::withMessages(['planted_areas' => 'The planted-area drawing could not be read. Redraw the boundary and try again.']);
            }
            $this->merge(['planted_areas' => $areas]);
        }
    }
}
