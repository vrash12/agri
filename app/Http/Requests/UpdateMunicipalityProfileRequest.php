<?php

namespace App\Http\Requests;

use App\Models\Municipality;
use App\Models\MunicipalityProfile;
use App\Support\MunicipalityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMunicipalityProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scoped = app(MunicipalityAccess::class)->scopeMunicipalities(Municipality::query()->active(), $this->user())
            ->whereKey($this->route('municipality')->getKey())->whereHas('supervisingProvince', fn ($query) => $query->active())->exists();
        abort_unless($scoped, 404);
        $profile = new MunicipalityProfile;
        $profile->municipality_id = $this->route('municipality')->getKey();

        return $this->user()->can('update', $profile);
    }

    public function rules(): array
    {
        return [
            '_record_version' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'office_name' => ['required', 'string', 'max:100'],
            // Never fetch this URL or use a remote URL as a logo source.
            'website_url' => ['bail', 'nullable', 'string', 'url', 'max:200', 'regex:~^https?://[^\s]+$~i', function ($attribute, $value, $fail) {
                $parts = parse_url($value);
                if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
                    $fail('Enter a public office website without embedded credentials.');
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'office_address' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'signatory_name' => ['nullable', 'required_with:signatory_title', 'string', 'max:100'],
            'signatory_title' => ['nullable', 'required_with:signatory_name', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=2400,max_height=2400', Rule::prohibitedIf($this->boolean('remove_logo'))],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['_record_version' => 'settings version', 'website_url' => 'official website', 'logo' => 'LGU logo'];
    }
}
