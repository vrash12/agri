<?php

namespace Tests\Unit;

use App\Http\Controllers\FarmPlotController;
use App\Models\Farmer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FarmPlotImportMatchingTest extends TestCase
{
    public function test_ambiguous_surname_fails_closed_instead_of_selecting_the_first_farmer(): void
    {
        $farmers = collect([
            $this->farmer(10, 'Ana', 'Santos', 'Pance'),
            $this->farmer(11, 'Bea', 'Santos', 'Guiteb'),
        ]);

        $match = $this->match($farmers, null, 'Santos', []);

        $this->assertNull($match['farmer']);
        $this->assertSame('ambiguous', $match['strategy']);
    }

    public function test_unique_surname_and_exact_identifier_still_resolve(): void
    {
        $farmers = collect([
            $this->farmer(10, 'Ana', 'Santos', 'Pance', 'FFRS-10', 'RSBSA-10'),
            $this->farmer(11, 'Bea', 'Reyes', 'Guiteb', 'FFRS-11', 'RSBSA-11'),
        ]);

        $bySurname = $this->match($farmers, null, 'Santos', []);
        $byCode = $this->match($farmers, 'RSBSA-11', 'Unrelated name', []);

        $this->assertSame(10, $bySurname['farmer']?->id);
        $this->assertSame('surname_unique', $bySurname['strategy']);
        $this->assertSame(11, $byCode['farmer']?->id);
        $this->assertSame('parcel_code', $byCode['strategy']);
    }

    private function match(Collection $farmers, ?string $parcelCode, ?string $ownerName, array $extended): array
    {
        $reflection = new ReflectionClass(FarmPlotController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('matchFarmerDetailed');
        $method->setAccessible(true);

        return $method->invoke($controller, $farmers, $parcelCode, $ownerName, $extended);
    }

    private function farmer(
        int $id,
        string $firstName,
        string $lastName,
        string $barangay,
        ?string $ffrs = null,
        ?string $rsbsa = null
    ): Farmer {
        return (new Farmer([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'farm_location' => $barangay,
            'farm_municipality' => 'Ramos',
            'farm_province' => 'Tarlac',
            'ffrs' => $ffrs,
            'rsbsa_no' => $rsbsa,
        ]))->forceFill(['id' => $id]);
    }
}
