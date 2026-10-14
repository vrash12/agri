<?php

namespace App\Http\Requests;

use App\Models\ClimateRiskAssessment;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveClimateRiskAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');

        return $assessment instanceof ClimateRiskAssessment
            ? $this->user()?->can('update', $assessment) === true
            : $this->user()?->can('create', ClimateRiskAssessment::class) === true;
    }

    public function rules(): array
    {
        return [
            'municipality_id' => ['required', 'integer', 'min:1'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'risk_level' => ['required', Rule::in(array_keys(ClimateRiskAssessment::LEVELS))],
            'drought_status' => ['required', Rule::in(array_keys(ClimateRiskAssessment::DROUGHT))],
            'rainfall_outlook' => ['required', Rule::in(array_keys(ClimateRiskAssessment::RAINFALL))],
            'observed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.LocalTime::now()->toDateString()],
            'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:observed_on'],
            'source_type' => ['required', Rule::in(['field', 'pagasa'])],
            'source_label' => ['required', 'string', 'max:180'],
            'source_url' => ['nullable', 'required_if:source_type,pagasa', 'url:https', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'plot_ids' => ['sometimes', 'array', 'max:100'],
            'plot_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'verified' => ['accepted'],
            '_record_version' => [$this->route('assessment') ? 'required' : 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('source_url') && $this->filled('source_url')) {
                $parts = parse_url((string) $this->input('source_url'));
                $host = strtolower($parts['host'] ?? '');
                if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
                    || ($this->input('source_type') === 'pagasa' && $host !== 'pagasa.dost.gov.ph' && ! str_ends_with($host, '.pagasa.dost.gov.ph'))) {
                    $validator->errors()->add('source_url', 'Use a direct HTTPS source link without credentials or fragments. PAGASA references must use a PAGASA website.');
                }
            }
            if (! $validator->errors()->has('observed_on') && ! $validator->errors()->has('valid_until')
                && CarbonImmutable::parse($this->input('observed_on'))->diffInDays(CarbonImmutable::parse($this->input('valid_until'))) > 31) {
                $validator->errors()->add('valid_until', 'Review an assessment within 31 days of its observation date.');
            }
            if ($this->input('risk_level') === 'normal' && (count((array) $this->input('plot_ids', [])) > 0
                || in_array($this->input('drought_status'), ['dry_condition', 'dry_spell', 'drought'], true))) {
                $validator->errors()->add('risk_level', 'Choose Watch, Alert or Critical when dry conditions or affected parcels are reported.');
            }
        }];
    }
}
