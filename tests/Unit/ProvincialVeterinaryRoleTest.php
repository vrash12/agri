<?php

namespace Tests\Unit;

use App\Models\AntiRabiesVaccination;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Policies\AntiRabiesVaccinationPolicy;
use App\Policies\FarmerPolicy;
use App\Policies\RiceSeedDistributionPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Since province supervision was added, the policies ask `hasUsableScope()`, which
 * looks the account's province up in the database. These cases therefore need a
 * province to exist; an isolated in-memory schema keeps them independent of
 * whatever the working database happens to contain.
 */
class ProvincialVeterinaryRoleTest extends TestCase
{
    private Province $province;

    private Municipality $municipality;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code')->unique();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->province = Province::query()->create(['name' => 'Tarlac', 'is_active' => true]);
        // The animal-health policy confirms the record's municipality sits inside
        // the veterinary account's province.
        $this->municipality = Municipality::withoutEvents(fn (): Municipality => Municipality::query()->create([
            'name' => 'Vet Municipality', 'code' => 'VETMUNI', 'province' => 'Tarlac',
            'province_id' => $this->province->id, 'is_active' => true,
        ]));
    }

    public function test_role_has_province_wide_animal_health_write_access(): void
    {
        $user = new User([
            'role' => User::ROLE_PROVINCIAL_VET,
            'province_id' => $this->province->id,
            'is_active' => true,
        ]);
        $record = new AntiRabiesVaccination([
            'municipality_id' => $this->municipality->id,
        ]);
        $policy = new AntiRabiesVaccinationPolicy();

        $this->assertTrue($user->isProvincialVeterinaryOffice());
        $this->assertTrue($user->canAccessAllMunicipalities());
        $this->assertFalse($user->requiresMunicipality());
        $this->assertFalse($user->canManageOperationalData());
        $this->assertNull($policy->before($user));
        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->view($user, $record));
        $this->assertTrue($policy->update($user, $record));
        $this->assertTrue($policy->delete($user, $record));
    }

    public function test_an_inactive_province_removes_that_access(): void
    {
        $this->province->forceFill(['is_active' => false])->save();
        $user = new User([
            'role' => User::ROLE_PROVINCIAL_VET,
            'province_id' => $this->province->id,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasUsableScope());
        $this->assertFalse((new AntiRabiesVaccinationPolicy())->viewAny($user));
    }

    public function test_role_is_denied_by_other_operational_policies(): void
    {
        $user = new User([
            'role' => User::ROLE_PROVINCIAL_VET,
            'province_id' => $this->province->id,
            'is_active' => true,
        ]);

        $this->assertFalse((new FarmerPolicy())->before($user));
        $this->assertFalse(
            (new RiceSeedDistributionPolicy())->before($user)
        );
    }
}
