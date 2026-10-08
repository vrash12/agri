<?php

namespace App\Http\Requests;

use App\Models\AntiRabiesVaccination;
use App\Support\AnimalHealthServiceRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnimalHealthServicesRequest extends FormRequest
{
    public const MAX_ANIMAL_ROWS = 20;

    public function authorize(): bool
    {
        return $this->user()?->can('create', AntiRabiesVaccination::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Existing flat submissions still mean one animal service. New nested
        // rows must state their product explicitly; no dog vaccine is copied to cattle.
        if (! $this->exists('animals')) {
            $animal = $this->only(array_keys(AnimalHealthServiceRules::animal()));
            $animal += ['service_type' => 'vaccination', 'service_name' => 'Anti-rabies vaccine', 'animal_count' => 1];
            $this->merge(['animals' => [$animal]]);
        }
    }

    public function rules(): array
    {
        return AnimalHealthServiceRules::owner() + [
            'animals' => ['required', 'array', 'min:1', 'max:'.self::MAX_ANIMAL_ROWS, function ($attribute, $value, $fail): void {
                if (is_array($value) && ! array_is_list($value)) {
                    $fail('Animal rows must be a consecutive list. Reload the form and add the animals again.');
                }
            }],
            'animals.*' => ['required', 'array:'.implode(',', array_keys(AnimalHealthServiceRules::animal()))],
        ] + AnimalHealthServiceRules::animal('animals.*.');
    }

    public function messages(): array
    {
        return [
            'animals.required' => 'Add at least one animal or group.',
            'animals.max' => 'Save up to 20 animal or group rows at a time.',
            'animals.*.pet_type.required' => 'Choose a species for animal row #:position.',
            'animals.*.service_name.required' => 'Enter the product or treatment for animal row #:position.',
            'animals.*.next_service_date.after_or_equal' => 'The follow-up for animal row #:position must be on or after its service date.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [];
        $animals = $this->input('animals', []);
        if (! is_array($animals)) {
            return $attributes;
        }
        $labels = ['pet_type' => 'species', 'pet_breed' => 'breed', 'pet_name' => 'name or group ID', 'pet_color' => 'color', 'service_type' => 'service', 'service_name' => 'product or treatment', 'animal_count' => 'number served', 'vaccination_date' => 'service date', 'next_service_date' => 'follow-up date'];
        foreach (array_slice(array_keys($animals), 0, self::MAX_ANIMAL_ROWS) as $position => $key) {
            foreach (array_keys(AnimalHealthServiceRules::animal()) as $field) {
                $attributes['animals.'.$key.'.'.$field] = 'animal row '.($position + 1).' '.($labels[$field] ?? str_replace('_', ' ', $field));
            }
        }

        return $attributes;
    }
}
