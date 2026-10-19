<?php

namespace App\Http\Requests;

use App\Models\FarmPlot;
use App\Models\ParcelFieldAssessment;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveParcelFieldAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plot = $this->route('plot');

        return $plot instanceof FarmPlot && $this->user()?->can('update', $plot);
    }

    public function rules(): array
    {
        $date = ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.LocalTime::now()->toDateString(), 'before_or_equal:observed_on'];
        $soil = $this->filled('soil_ph') || $this->filled('soil_texture') || $this->filled('drainage');
        $terrain = $this->filled('slope_percent');
        $climate = $this->filled('growing_temperature_c') || $this->filled('growing_rainfall_mm');
        $water = $this->filled('water_availability') && $this->input('water_availability') !== 'unknown';

        return [
            '_record_version' => ['required', 'string', 'regex:/^(new|[a-f0-9]{64})$/'],
            '_plot_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            '_context_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'crop' => ['required', Rule::in(array_keys(ParcelFieldAssessment::CROPS))],
            'crop_year' => ['required', 'integer', 'between:1990,'.(LocalTime::now()->year + 1)],
            'season' => ['required', Rule::in(['dry', 'wet'])],
            'observed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.LocalTime::now()->toDateString()],
            'soil_ph' => ['nullable', 'numeric', 'between:0,14'],
            'soil_texture' => ['nullable', Rule::in(['sandy', 'loamy', 'clayey'])],
            'drainage' => ['nullable', Rule::in(['good', 'moderate', 'poor'])],
            'slope_percent' => ['nullable', 'numeric', 'between:0,100'],
            'growing_temperature_c' => ['nullable', 'numeric', 'between:-10,60'],
            'growing_rainfall_mm' => ['nullable', 'numeric', 'between:0,10000'],
            'soil_source' => [Rule::requiredIf($soil), 'nullable', 'string', 'max:180'],
            'soil_observed_on' => [Rule::requiredIf($soil), ...$date],
            'terrain_source' => [Rule::requiredIf($terrain), 'nullable', 'string', 'max:180'],
            'terrain_observed_on' => [Rule::requiredIf($terrain), ...$date],
            'climate_source' => [Rule::requiredIf($climate), 'nullable', 'string', 'max:180'],
            'climate_period_start' => [Rule::requiredIf($climate || $this->filled('climate_period_end')), ...$date],
            'climate_period_end' => [Rule::requiredIf($climate || $this->filled('climate_period_start')), ...$date, 'after_or_equal:climate_period_start'],
            'water_availability' => ['required', Rule::in(array_keys(ParcelFieldAssessment::WATER))],
            'water_source' => [Rule::requiredIf($water), 'nullable', 'string', 'max:180'],
            'water_observed_on' => [Rule::requiredIf($water), ...$date],
            'crop_stage' => ['required', Rule::in(array_keys(ParcelFieldAssessment::STAGES))],
            'planted_on' => $date,
            'field_condition' => ['required', Rule::in(array_keys(ParcelFieldAssessment::CONDITIONS))],
            'next_inspection_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:observed_on'],
            'follow_up_status' => ['required', Rule::in(['open', 'completed'])],
            'follow_up_notes' => ['required_if:follow_up_status,completed', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.before_or_equal' => 'Use a date on or before the field-check date and today.',
            'soil_source.required' => 'Add where the soil information came from.',
            'soil_observed_on.required' => 'Add the soil test or observation date.',
            'terrain_source.required' => 'Add where the slope measurement came from.',
            'terrain_observed_on.required' => 'Add the slope measurement date.',
            'climate_source.required' => 'Add the source for this growing-period climate information.',
            'water_source.required' => 'Describe how the water supply was verified.',
            'water_observed_on.required' => 'Add the date the water supply was checked.',
            'follow_up_notes.required_if' => 'Describe the field visit or action before marking this follow-up completed.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((! $this->filled('growing_temperature_c') && ! $this->filled('growing_rainfall_mm'))
                || $validator->errors()->hasAny(['climate_period_start', 'climate_period_end'])) {
                return;
            }
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $this->input('climate_period_start'));
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $this->input('climate_period_end'));
            $days = $start->diffInDays($end) + 1;
            if ($days < 30 || $days > 366) {
                $validator->errors()->add('climate_period_end', 'Use a completed growing-period reference of 30 to 366 days. Short-term forecasts are shown separately.');
            }
        });
    }
}
