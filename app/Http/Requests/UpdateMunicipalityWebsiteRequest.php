<?php

namespace App\Http\Requests;

use App\Models\Municipality;
use App\Models\MunicipalityWebsite;
use App\Modules\LocalGovernment\Application\LguWebsiteAddress;
use App\Support\MunicipalityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateMunicipalityWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $municipality = $this->route('municipality');
        abort_unless(app(MunicipalityAccess::class)->scopeMunicipalities(Municipality::query()->active(), $this->user())
            ->whereKey($municipality->getKey())->whereHas('supervisingProvince', fn ($query) => $query->active())->exists(), 404);
        $website = new MunicipalityWebsite;
        $website->municipality_id = $municipality->getKey();

        return $this->user()->can('update', $website);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => strtolower(trim($this->input('slug')))]);
        }
        // Fixed editor rows are optional; keep only rows containing user input.
        foreach (['services', 'announcements'] as $field) {
            if (is_array($this->input($field))) {
                $this->merge([$field => array_values(array_filter($this->input($field), fn ($row) => ! is_array($row)
                    || count(array_filter($row, fn ($value) => $value !== null && $value !== '')) > 0))]);
            }
        }
    }

    public function rules(): array
    {
        abort_unless(Schema::hasTable('municipality_websites') && Schema::hasTable('municipality_profiles'), 503, 'LGU websites are awaiting the database update.');

        return [
            '_record_version' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'slug' => ['bail', 'required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::notIn(LguWebsiteAddress::reservedSlugs()),
                Rule::unique('municipality_websites', 'slug')->ignore($this->route('municipality')->getKey(), 'municipality_id')],
            'is_published' => ['required', 'boolean'],
            'headline' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'welcome_text' => ['nullable', 'string', 'max:4000'],
            'office_hours' => ['nullable', 'string', 'max:200'],
            'services' => ['sometimes', 'array', 'max:6'],
            'services.*' => ['array:title,description,url'],
            'services.*.title' => ['required', 'string', 'max:80'],
            'services.*.description' => ['required', 'string', 'max:600'],
            'services.*.url' => $this->linkRules(),
            'announcements' => ['sometimes', 'array', 'max:3'],
            'announcements.*' => ['array:title,body,date'],
            'announcements.*.title' => ['required', 'string', 'max:100'],
            'announcements.*.body' => ['required', 'string', 'max:1200'],
            'announcements.*.date' => ['nullable', 'date_format:Y-m-d'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096', 'dimensions:min_width=320,min_height=180,max_width=4000,max_height=4000', Rule::prohibitedIf($this->boolean('remove_cover'))],
            'remove_cover' => ['sometimes', 'boolean'],
        ];
    }

    private function linkRules(): array
    {
        return ['bail', 'nullable', 'string', 'url', 'max:500', 'regex:~^https?://[^\s]+$~i', function ($attribute, $value, $fail) {
            $parts = parse_url($value);
            if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
                $fail('Use an HTTP(S) service link without embedded credentials.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['_record_version' => 'website version', 'slug' => 'website address', 'is_published' => 'publication status'];
    }
}
