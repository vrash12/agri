<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Policies\MunicipalityBoundaryPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The policy asks `hasUsableScope()`, which resolves the account's province, and a
 * municipal account's municipality and its supervising province, from the database.
 * An isolated in-memory schema gives those lookups something deterministic to find.
 */
class MunicipalityBoundaryPolicyTest extends TestCase
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
        $this->municipality = Municipality::withoutEvents(fn (): Municipality => Municipality::query()->create([
            'name' => 'Policy Municipality', 'code' => 'POLICY', 'province' => 'Tarlac',
            'province_id' => $this->province->id, 'is_active' => true,
        ]));
    }

    public function test_only_active_super_admin_can_modify_boundaries(): void
    {
        $policy = new MunicipalityBoundaryPolicy();
        $boundary = new MunicipalityBoundary(['municipality_id' => $this->municipality->id]);

        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $provincial = $this->user(User::ROLE_PROVINCIAL_STAFF);
        $municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->municipality->id);

        $this->assertTrue($policy->create($superAdmin));
        $this->assertTrue($policy->update($superAdmin, $boundary));
        $this->assertTrue($policy->activate($superAdmin, $boundary));
        $this->assertFalse($policy->create($provincial));
        $this->assertFalse($policy->update($municipal, $boundary));
    }

    public function test_municipal_users_can_view_only_their_boundary(): void
    {
        $policy = new MunicipalityBoundaryPolicy();
        $own = new MunicipalityBoundary(['municipality_id' => $this->municipality->id]);
        $foreign = new MunicipalityBoundary(['municipality_id' => $this->municipality->id + 1]);
        $municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->municipality->id);

        $this->assertTrue($policy->view($municipal, $own));
        $this->assertFalse($policy->view($municipal, $foreign));
    }

    private function user(string $role, ?int $municipalityId = null): User
    {
        return new User([
            'role' => $role,
            'municipality_id' => $municipalityId,
            // Provincial roles resolve their scope through the province.
            'province_id' => $municipalityId === null ? $this->province->id : null,
            'is_active' => true,
        ]);
    }
}
