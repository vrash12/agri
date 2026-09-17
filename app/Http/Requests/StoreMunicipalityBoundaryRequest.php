<?php

namespace App\Http\Requests;

use App\Models\MunicipalityBoundary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for drawing a municipality geofence or editing an existing one.
 *
 * Editing accepts a partial submission: a name or colour change arrives without
 * geometry, and the controller keeps the stored shape and its measurements in that
 * case. Only a create fixes the owning municipality and the initial status, and
 * only an edit carries the record version that guards against overwriting someone
 * else's newer save.
 *
 * The geometry itself is checked by `App\Support\GeoGeometry` after decoding, which
 * is the one place that understands rings, self-intersection, and overlap.
 */
class StoreMunicipalityBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $boundary = $this->existingBoundary();

        return (bool) ($boundary
            ? $this->user()?->can('update', $boundary)
            : $this->user()?->can('create', MunicipalityBoundary::class));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->existingBoundary() === null;

        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'geojson' => [$creating ? 'required' : 'sometimes'],
            'replace_confirmed' => ['nullable', 'boolean'],
        ];

        if ($creating) {
            $rules['municipality_id'] = ['required', 'integer', Rule::exists('municipalities', 'id')->where('is_active', true)];
            $rules['status'] = ['required', Rule::in([MunicipalityBoundary::STATUS_DRAFT, MunicipalityBoundary::STATUS_ACTIVE])];
        } else {
            $rules['_record_version'] = ['required', 'string'];
        }

        return $rules;
    }

    public function existingBoundary(): ?MunicipalityBoundary
    {
        $boundary = $this->route('boundary');

        return $boundary instanceof MunicipalityBoundary ? $boundary : null;
    }
}
