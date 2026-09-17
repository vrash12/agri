<?php

namespace App\Http\Requests;

use App\Models\MunicipalityBoundary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Validation for importing a municipality geofence from a boundary file.
 *
 * Only the shape of the upload is checked here. Parsing the file and validating
 * the geometry inside it belongs to `App\Support\MunicipalityBoundaryImporter`,
 * which reports a readable reason when a file cannot be used.
 */
class ImportMunicipalityBoundaryRequest extends FormRequest
{
    /**
     * Boundary formats the importer understands.
     */
    public const ACCEPTED_EXTENSIONS = ['kml', 'kmz', 'json', 'geojson', 'xml'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('import', MunicipalityBoundary::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in([MunicipalityBoundary::STATUS_DRAFT, MunicipalityBoundary::STATUS_ACTIVE])],
            'replace_confirmed' => ['nullable', 'boolean'],
            'file' => [
                // Stop at the first failure so a rejected upload is not also parsed.
                'bail',
                'required',
                'file',
                'max:10240',
                function (string $attribute, $value, $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($extension, self::ACCEPTED_EXTENSIONS, true)) {
                        $fail('Upload a KML, KMZ, GeoJSON, JSON, or XML boundary file.');
                    }
                },
            ],
        ];
    }
}
