<?php

namespace Tests\Feature;

use App\Support\RiceSeedImportValidation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RiceSeedImportValidationTest extends TestCase
{
    /** @dataProvider textLimits */
    public function test_snapshot_text_limits_reject_overflow_without_disclosing_values(string $field, string $label, int $limit): void
    {
        $validation = new RiceSeedImportValidation;
        $validation->validate([$field => str_repeat('é', $limit)], 5);
        try {
            $validation->validate([$field => str_repeat('é', $limit + 1)], 5);
            $this->fail('Oversized text must be rejected before persistence.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'file' => ["Excel row 5: {$label} exceeds {$limit} characters. Correct this cell and upload the workbook again. No rows were imported or updated."],
            ], $exception->errors());
        }
    }

    public static function textLimits(): array
    {
        return [
            ['seed_variety_claimed', 'Seed Variety Claimed', 120],
            ['contact_number', 'Contact Number', 50],
            ['farm_location', 'Farm Address', 255],
        ];
    }
}
