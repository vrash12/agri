<?php

namespace Tests\Unit;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Support\FarmerIdentifier;
use PHPUnit\Framework\TestCase;

class FarmerIdentifierTest extends TestCase
{
    public function test_identifiers_preserve_the_numeric_key_without_truncating_large_values(): void
    {
        foreach ([1 => 'AGRI-F-000001', 123 => 'AGRI-F-000123', 1234567 => 'AGRI-F-1234567', PHP_INT_MAX => 'AGRI-F-'.PHP_INT_MAX] as $id => $expected) {
            $this->assertSame($expected, FarmerIdentifier::format($id));
            $this->assertSame($expected, FarmerPortalAccount::canonicalLoginId($id));
            $this->assertSame($id, FarmerIdentifier::parse($expected));
        }
    }

    public function test_search_accepts_full_current_and_legacy_identifiers_case_insensitively(): void
    {
        foreach (['AGRI-F-000123', 'agri-f-000123', '  AGRI-F-000123  ', 'PAIS-FRM-000123', 'pais-frm-000123'] as $identifier) {
            $this->assertSame(123, FarmerIdentifier::parse($identifier));
        }
    }

    public function test_malformed_zero_and_overflow_identifiers_do_not_resolve_to_another_farmer(): void
    {
        foreach (['', '123', 'AGRI-F-', 'AGRI-F-000000', 'PAIS-FRM-0', 'AGRI-F--123', 'AGRI-F-12.3', 'AGRI-F-1e3', 'AGRI-F-000123tail', 'prefixAGRI-F-000123', 'AGRI-F-12 3', 'AGRI-F-'.PHP_INT_MAX.'0'] as $identifier) {
            $this->assertNull(FarmerIdentifier::parse($identifier), $identifier);
        }
    }

    public function test_unsaved_farmer_has_no_id_and_existing_legacy_identifier_is_preserved(): void
    {
        $farmer = new Farmer;
        $this->assertNull($farmer->agri_gov_id);

        $farmer->id = 123;
        $this->assertSame('AGRI-F-000123', $farmer->agri_gov_id);
        $this->assertSame('PAIS-FRM-000123', $farmer->registry_id);
        $this->assertSame(123, $farmer->getKey());
        $this->assertSame('id', $farmer->getKeyName());
        $this->assertTrue($farmer->getIncrementing());
    }
}
