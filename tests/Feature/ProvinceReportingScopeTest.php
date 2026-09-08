<?php

namespace Tests\Feature;

use App\Http\Controllers\FarmerController;
use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProvinceReportingScopeTest extends TestCase
{
    private Province $benguet;

    private Province $tarlac;

    private Municipality $own;

    private Municipality $foreign;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
        Model::withoutEvents(function (): void {
            $this->benguet = Province::create(['name' => 'Benguet', 'is_active' => true]);
            $this->tarlac = Province::create(['name' => 'Tarlac', 'is_active' => true]);
            $this->own = Municipality::create(['name' => 'La Trinidad', 'province' => 'Benguet', 'province_id' => $this->benguet->id, 'code' => 'LAT', 'is_active' => true]);
            $this->foreign = Municipality::create(['name' => 'Capas', 'province' => 'Tarlac', 'province_id' => $this->tarlac->id, 'code' => 'CAP', 'is_active' => true]);
            $this->admin = $this->user(User::ROLE_SUPER_ADMIN, $this->benguet->id, 'Benguet Admin');
            $this->owner = $this->user(User::ROLE_SYSTEM_OWNER, null, 'System Owner');
        });
    }

    public function test_audit_listing_filters_counts_and_exports_stay_in_assigned_province(): void
    {
        $own = $this->log($this->benguet->id, $this->own->id, 'Benguet event', 'Own module');
        $foreign = $this->log($this->tarlac->id, $this->foreign->id, 'Hidden Tarlac event', 'Hidden module');
        $global = $this->log(null, null, 'Global owner event', 'Global module');

        $this->actingAs($this->admin)->get(route('audit-logs.index'))->assertOk()
            ->assertSee('Benguet event')->assertDontSee('Hidden Tarlac event')->assertDontSee('Global owner event')
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1)
            ->assertViewHas('modules', fn ($modules) => $modules->all() === ['Own module'])
            ->assertViewHas('municipalities', fn ($items) => $items->pluck('id')->all() === [$this->own->id])
            ->assertViewHas('users', fn ($items) => $items->pluck('name')->all() === ['Own module actor']);
        $this->get(route('audit-logs.show', $own))->assertOk();
        $this->get(route('audit-logs.show', $foreign))->assertNotFound();
        $this->get(route('audit-logs.show', $global))->assertNotFound();

        $csv = $this->get(route('audit-logs.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Benguet event', $csv);
        $this->assertStringNotContainsString('Hidden Tarlac event', $csv);
        $this->assertStringNotContainsString('Global owner event', $csv);
        $exportLog = AuditLog::where('event', 'exported')->firstOrFail();
        $this->assertSame($this->benguet->id, $exportLog->province_id);

        $this->get(route('audit-logs.index', ['municipality_id' => $this->foreign->id, 'q' => 'Hidden']))
            ->assertOk()->assertViewHas('stats', fn ($stats) => $stats['total'] === 0);
        $csv = $this->get(route('audit-logs.export', ['municipality_id' => $this->foreign->id, 'user_id' => 999]))
            ->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Hidden Tarlac event', $csv);
        $this->assertStringNotContainsString('Benguet event', $csv);
    }

    public function test_owner_can_review_global_and_both_provincial_audit_histories(): void
    {
        $this->log($this->benguet->id, $this->own->id, 'Benguet event');
        $this->log($this->tarlac->id, $this->foreign->id, 'Tarlac event');
        $global = $this->log(null, null, 'Global owner event');
        $this->actingAs($this->owner)->get(route('audit-logs.index'))->assertOk()
            ->assertSee('Benguet event')->assertSee('Tarlac event')->assertSee('Global owner event')
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 3);
        $this->get(route('audit-logs.show', $global))->assertOk();
    }

    public function test_non_oversight_accounts_cannot_read_audits(): void
    {
        $record = $this->log($this->benguet->id, $this->own->id, 'Benguet event');
        $staff = Model::withoutEvents(fn () => $this->user(User::ROLE_PROVINCIAL_STAFF, $this->benguet->id, 'Staff'));
        $this->actingAs($staff)->get(route('audit-logs.index'))->assertForbidden();
        $this->get(route('audit-logs.show', $record))->assertForbidden();
        $this->get(route('audit-logs.export'))->assertForbidden();
    }

    public function test_audit_snapshots_follow_target_ownership_and_survive_record_and_actor_changes(): void
    {
        $this->actingAs($this->owner);
        $farmer = Farmer::create(['first_name' => 'Local', 'last_name' => 'Farmer', 'municipality_id' => $this->own->id]);
        $created = AuditLog::where('auditable_type', Farmer::class)->where('event', 'created')->firstOrFail();
        $this->assertSame($this->benguet->id, $created->province_id);
        $farmer->delete();
        $this->assertSame($this->benguet->id, AuditLog::where('auditable_type', Farmer::class)->where('event', 'deleted')->firstOrFail()->province_id);

        $this->actingAs($this->admin);
        $event = AuditTrail::record('login', 'Authentication', 'Original assignment');
        Model::withoutEvents(fn () => $this->admin->update(['province_id' => $this->tarlac->id, 'name' => 'Current foreign account name']));
        $this->assertSame($this->benguet->id, $event->fresh()->province_id);
        $this->get(route('audit-logs.show', $event))->assertNotFound();
        $benguetAdmin = Model::withoutEvents(fn () => $this->user(User::ROLE_SUPER_ADMIN, $this->benguet->id, 'Replacement Admin'));
        $this->actingAs($benguetAdmin)->get(route('audit-logs.show', $event))->assertOk()->assertDontSee('Current foreign account name');
        $this->get(route('audit-logs.index'))->assertOk()->assertDontSee('Current foreign account name');
    }

    public function test_cross_province_reassignment_is_an_owner_only_event(): void
    {
        $this->actingAs($this->owner);
        $this->admin->update(['province_id' => $this->tarlac->id]);
        $event = AuditLog::where('auditable_type', User::class)->where('event', 'updated')->firstOrFail();
        $this->assertNull($event->province_id);
        $this->get(route('audit-logs.show', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('audit-logs.show', $event))->assertNotFound();
    }

    public function test_dashboard_totals_and_municipality_overview_are_scoped_before_aggregation(): void
    {
        Model::withoutEvents(function (): void {
            Farmer::create(['first_name' => 'Own', 'last_name' => 'Farmer', 'municipality_id' => $this->own->id]);
            Farmer::create(['first_name' => 'Foreign', 'last_name' => 'Farmer', 'municipality_id' => $this->foreign->id]);
            Farmer::create(['first_name' => 'Unassigned', 'last_name' => 'Farmer']);
        });
        DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn ($date) => (int) date('n', strtotime($date)), 1);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Benguet Provincial Agriculture Office')->assertDontSee('Tarlac Provincial Agriculture Office')
            ->assertDontSee('Records without a municipality')
            ->assertViewHas('stats', fn ($stats) => $stats['total_farmers'] === 1 && $stats['total_backup_files'] === 0)
            ->assertViewHas('municipalityStats', fn ($items) => $items->pluck('id')->all() === [$this->own->id])
            ->assertViewHas('provinceOverview', fn ($summary) => $summary['unassigned_records'] === 0);
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
            ->assertSee('System administration')->assertSee('Records without a municipality')->assertDontSee('Manage backups')
            ->assertViewHas('stats', fn ($stats) => $stats['total_farmers'] === 3)
            ->assertViewHas('municipalityStats', fn ($items) => $items->count() === 2)
            ->assertViewHas('provinceOverview', fn ($summary) => $summary['unassigned_records'] === 1);
    }

    public function test_farmer_helpers_scope_reads_and_reject_cross_province_write_ownership(): void
    {
        Model::withoutEvents(function (): void {
            Farmer::create(['first_name' => 'Own', 'last_name' => 'Farmer', 'municipality_id' => $this->own->id]);
            Farmer::create(['first_name' => 'Foreign', 'last_name' => 'Farmer', 'municipality_id' => $this->foreign->id]);
        });
        $controller = app(FarmerController::class);
        $method = new \ReflectionMethod($controller, 'applyMunicipalityScope');
        $query = $method->invoke($controller, Farmer::query(), $this->admin);
        $this->assertSame(['Own'], $query->pluck('first_name')->all());
        $write = new \ReflectionMethod($controller, 'resolveMunicipalityForWrite');
        $staff = Model::withoutEvents(fn () => $this->user(User::ROLE_PROVINCIAL_STAFF, $this->benguet->id, 'Staff'));
        $request = Request::create('/farmers', 'POST', ['municipality_id' => $this->own->id]);
        $this->assertSame($this->own->id, $write->invoke($controller, $request, $staff)->id);
        $this->expectException(ValidationException::class);
        $write->invoke($controller, Request::create('/farmers', 'POST', ['municipality_id' => $this->foreign->id]), $staff);
    }

    private function user(string $role, ?int $provinceId, string $name): User
    {
        return User::create(['name' => $name, 'email' => str_replace(' ', '-', strtolower($name)).'@example.test', 'password' => 'test-disabled', 'role' => $role, 'province_id' => $provinceId, 'is_active' => true]);
    }

    private function log(?int $provinceId, ?int $municipalityId, string $description, string $module = 'Farmers'): AuditLog
    {
        return AuditLog::create(['province_id' => $provinceId, 'municipality_id' => $municipalityId, 'event' => 'created', 'module' => $module, 'description' => $description, 'user_id' => $provinceId === $this->benguet->id ? $this->admin->id : 999, 'actor_name' => $module.' actor']);
    }

    private function schema(): void
    {
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province')->nullable();
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        (require database_path('migrations/2026_09_08_000100_add_province_supervision.php'))->up();
        foreach ([
            'farmers' => ['first_name', 'last_name', 'ffrs', 'farm_location', 'public_map_token'],
            'rice_seed_distributions' => ['ffrs', 'last_name', 'first_name', 'input_category', 'seed_variety_claimed', 'quantity_unit', 'date_received'],
            'anti_rabies_vaccinations' => ['owner_name', 'pet_name', 'pet_type', 'service_type', 'service_name', 'barangay', 'vaccination_date'],
            'farmers_cooperatives' => ['name'],
            'agricultural_machineries' => ['name', 'availability_status', 'condition_status', 'next_maintenance_date'],
            'backup_files' => ['name'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($name, $columns): void {
                $table->id();
                $table->unsignedBigInteger('municipality_id')->nullable();
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
                if ($name === 'rice_seed_distributions') {
                    $table->double('kgs_received')->default(0);
                }
                if ($name === 'anti_rabies_vaccinations') {
                    $table->integer('animal_count')->default(1);
                }
                $table->timestamps();
            });
        }
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->double('area_ha')->default(0);
            $table->timestamps();
        });
    }
}
