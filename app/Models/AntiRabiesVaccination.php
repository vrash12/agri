<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntiRabiesVaccination extends Model
{
    public const SERVICE_TYPE_LABELS = [
        'vaccination' => 'Vaccination',
        'deworming' => 'Deworming',
        'vitamins' => 'Vitamins / supplementation',
        'treatment' => 'Treatment',
    ];

    public const ANIMAL_TYPE_LABELS = [
        'Dog' => 'Dog',
        'Cat' => 'Cat',
        'Cattle' => 'Cattle / Cow',
        'Carabao' => 'Carabao',
        'Goat' => 'Goat',
        'Sheep' => 'Sheep',
        'Swine' => 'Swine / Pig',
        'Chicken' => 'Chicken',
        'Duck' => 'Duck',
        'Turkey' => 'Turkey',
        'Horse' => 'Horse',
        'Rabbit' => 'Rabbit',
        'Other' => 'Other farm animal',
    ];

    protected $fillable = [
        'municipality_id',

        // Owner
        'owner_name',
        'barangay',
        'birthday',

        // Pet
        'pet_type',
        'pet_breed',
        'pet_name',
        'pet_color',

        // Animal-health service
        'service_type',
        'service_name',
        'animal_count',
        'dosage',
        'administration_route',
        'diagnosis',
        'treatment_notes',
        'administered_by',
        'next_service_date',

        // Historical service date columns
        'vaccination_year',
        'vaccination_date',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'birthday' => 'date',
        'vaccination_date' => 'date',
        'vaccination_year' => 'integer',
        'animal_count' => 'integer',
        'next_service_date' => 'date',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function serviceTypeLabel(): string
    {
        return self::SERVICE_TYPE_LABELS[$this->service_type ?: 'vaccination']
            ?? ucfirst((string) $this->service_type);
    }

    public function animalTypeLabel(): string
    {
        return self::ANIMAL_TYPE_LABELS[$this->pet_type]
            ?? ($this->pet_type ?: 'Animal not specified');
    }

    public function animalsServed(): int
    {
        return max(1, (int) ($this->animal_count ?: 1));
    }
}
