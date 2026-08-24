<?php

namespace Tests\Unit;

use App\Models\RiceSeedDistribution;
use PHPUnit\Framework\TestCase;

class FisheriesAssistanceTest extends TestCase
{
    public function test_fisheries_categories_are_first_class_assistance_types(): void
    {
        $this->assertSame(
            [
                'fish_fingerlings',
                'fish_feed',
                'fishing_gear',
                'aquaculture_input',
                'other_fisheries',
            ],
            RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES
        );

        $release = new RiceSeedDistribution([
            'input_category' => 'fish_fingerlings',
            'quantity_unit' => 'piece',
        ]);

        $this->assertTrue($release->isFisheriesInput());
        $this->assertFalse($release->isSeedInput());
        $this->assertSame('Fish fingerlings', $release->inputCategoryLabel());
        $this->assertSame('pieces', $release->quantityUnitLabel());
        $this->assertSame('Fisheries assistance', $release->assistanceSectorLabel());
    }

    public function test_crop_inputs_remain_in_the_agriculture_sector(): void
    {
        $release = new RiceSeedDistribution([
            'input_category' => 'fertilizer',
            'quantity_unit' => 'sack',
        ]);

        $this->assertFalse($release->isFisheriesInput());
        $this->assertSame('Crops & farm inputs', $release->assistanceSectorLabel());
    }
}
