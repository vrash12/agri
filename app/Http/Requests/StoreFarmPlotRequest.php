<?php

namespace App\Http\Requests;

use App\Models\Farmer;
use App\Models\FarmPlot;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for drawing or reshaping a farm parcel.
 *
 * Creating a parcel is authorized against the farmer it belongs to, and reshaping
 * one against the parcel itself, which is the pairing the controller used before
 * these rules moved out of it. Whether the shape sits inside the municipality
 * geofence is not decided here: that is `MunicipalityBoundaryGuard`'s job, and it
 * needs the normalized polygon.
 */
class StoreFarmPlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plot = $this->route('plot');
        if ($plot instanceof FarmPlot) {
            return (bool) $this->user()?->can('update', $plot);
        }

        $farmer = $this->route('farmer');

        return (bool) ($farmer instanceof Farmer && $this->user()?->can('update', $farmer));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'color' => [
                'nullable',
                'string',
                'max:16',
                'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/',
            ],
            // Three points is the minimum that encloses any area at all.
            'polygon' => ['required', 'array', 'min:3'],
            'polygon.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'polygon.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
