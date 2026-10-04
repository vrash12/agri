<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class RiceSeedImportValidation
{
    /** Database text limits, including legacy identity snapshot columns. */
    private const TEXT_FIELDS = [
        'lot_series' => ['Lot Series', 120],
        'seed_variety_claimed' => ['Seed Variety Claimed', 120],
        'seed_variety_planted' => ['Seed Variety Planted', 120],
        'date_of_sowing_label' => ['Date of Sowing', 120],
        'last_name' => ['Farmer Last Name', 255],
        'first_name' => ['Farmer First Name', 255],
        'middle_name' => ['Farmer Middle Name', 255],
        'ext_name' => ['Farmer Ext Name', 50],
        'ffrs' => ['FFRS RSBSA Number', 255],
        'contact_number' => ['Contact Number', 50],
        'farm_location' => ['Farm Address', 255],
        'farm_province' => ['Farm Address (Province)', 255],
        'farm_municipality' => ['Farm Address (Municipality)', 255],
        'ecosystem' => ['Eco-System', 255],
        'ecosystem_source' => ['Eco-System Source', 255],
    ];

    /** @param  array<string, mixed>  $data */
    public function validate(array $data, int $excelRow): void
    {
        foreach (self::TEXT_FIELDS as $field => [$label, $limit]) {
            if (mb_strlen((string) ($data[$field] ?? ''), 'UTF-8') > $limit) {
                // Never echo a cell value: workbook rows contain protected identity data.
                // Throw within the import transaction so earlier rows roll back as well.
                throw ValidationException::withMessages([
                    'file' => "Excel row {$excelRow}: {$label} exceeds {$limit} characters. Correct this cell and upload the workbook again. No rows were imported or updated.",
                ]);
            }
        }
    }
}
