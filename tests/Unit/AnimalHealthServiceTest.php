<?php

namespace Tests\Unit;

use App\Models\AntiRabiesVaccination;
use PHPUnit\Framework\TestCase;

class AnimalHealthServiceTest extends TestCase
{
    public function test_animal_health_service_types_and_farm_animals_are_supported(): void
    {
        $this->assertSame(
            ['vaccination', 'deworming', 'vitamins', 'treatment'],
            array_keys(AntiRabiesVaccination::SERVICE_TYPE_LABELS)
        );

        foreach (['Cattle', 'Carabao', 'Goat', 'Sheep', 'Swine', 'Chicken', 'Duck'] as $species) {
            $this->assertArrayHasKey($species, AntiRabiesVaccination::ANIMAL_TYPE_LABELS);
        }
    }

    public function test_service_and_animal_labels_support_group_records(): void
    {
        $service = new AntiRabiesVaccination([
            'service_type' => 'deworming',
            'service_name' => 'Ivermectin',
            'pet_type' => 'Goat',
            'animal_count' => 24,
        ]);

        $this->assertSame('Deworming', $service->serviceTypeLabel());
        $this->assertSame('Goat', $service->animalTypeLabel());
        $this->assertSame(24, $service->animalsServed());
    }

    public function test_legacy_rows_default_to_vaccination_and_one_animal(): void
    {
        $legacy = new AntiRabiesVaccination([
            'pet_type' => 'Dog',
        ]);

        $this->assertSame('Vaccination', $legacy->serviceTypeLabel());
        $this->assertSame(1, $legacy->animalsServed());
    }
}
