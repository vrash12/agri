<?php

namespace App\Support;

use App\Models\AntiRabiesVaccination;
use Illuminate\Validation\Rule;

final class AnimalHealthServiceRules
{
    public static function owner(): array
    {
        return [
            'municipality_id' => ['nullable', 'integer'],
            'owner_name' => ['required', 'string', 'max:120'],
            'barangay' => ['required', 'string', 'max:120'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public static function animal(string $prefix = ''): array
    {
        $rules = [
            'pet_type' => ['required', Rule::in(array_keys(AntiRabiesVaccination::ANIMAL_TYPE_LABELS))],
            'pet_breed' => ['nullable', 'string', 'max:120'],
            'pet_name' => ['nullable', 'string', 'max:120'],
            'pet_color' => ['nullable', 'string', 'max:80'],
            'service_type' => ['required', Rule::in(array_keys(AntiRabiesVaccination::SERVICE_TYPE_LABELS))],
            'service_name' => ['required', 'string', 'max:150'],
            'animal_count' => ['required', 'integer', 'min:1', 'max:1000000'],
            'dosage' => ['nullable', 'string', 'max:120'],
            'administration_route' => ['nullable', 'string', 'max:60'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'treatment_notes' => ['nullable', 'string', 'max:3000'],
            'administered_by' => ['nullable', 'string', 'max:120'],
            'vaccination_date' => ['required', 'date', 'before_or_equal:today'],
            'next_service_date' => ['nullable', 'date', 'after_or_equal:'.$prefix.'vaccination_date'],
        ];

        return collect($rules)->mapWithKeys(fn ($rule, $field) => [$prefix.$field => $rule])->all();
    }
}
