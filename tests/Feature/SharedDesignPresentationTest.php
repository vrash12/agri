<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedDesignPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
    }

    /** @dataProvider navigationRoles */
    public function test_simplified_navigation_preserves_role_destinations(string $role, bool $backups, bool $accounts, bool $audit): void
    {
        $user = new User(['name' => 'Sample Staff', 'role' => $role, 'municipality_id' => 1, 'is_active' => true]);
        $user->id = 1;
        $user->setRelation('municipality', new Municipality(['name' => 'Sample Municipality']));
        $this->actingAs($user);

        $html = (string) $this->view('layouts.app');
        foreach (['backups.index' => $backups, 'admins.index' => $accounts, 'audit-logs.index' => $audit] as $route => $visible) {
            $this->assertSame($visible, str_contains($html, 'href="'.route($route).'"'), $route);
        }
        $this->assertStringContainsString('Skip to content', $html);
        $this->assertStringContainsString('Unsaved changes may be lost', $html);
        $this->assertStringNotContainsString('<span class="nav-description">', $html);
        $this->assertStringContainsString('href="'.route('anti-rabies-vaccinations.index').'"', $html);

        if ($role === User::ROLE_PROVINCIAL_VET) {
            foreach (['dashboard', 'farmers.index', 'rice-seed-distributions.index', 'municipality-boundaries.index'] as $route) {
                $this->assertStringNotContainsString('href="'.route($route).'"', $html);
            }
        }
    }

    public static function navigationRoles(): array
    {
        return [
            'municipal staff' => [User::ROLE_MUNICIPAL_STAFF, true, false, false],
            'municipal head' => [User::ROLE_MUNICIPAL_HEAD, true, true, false],
            'provincial staff' => [User::ROLE_PROVINCIAL_STAFF, true, false, false],
            'provincial vet' => [User::ROLE_PROVINCIAL_VET, false, false, false],
            'super admin' => [User::ROLE_SUPER_ADMIN, false, true, true],
        ];
    }

    public function test_municipal_office_label_uses_its_assigned_province(): void
    {
        $user = new User(['name' => 'Sample Staff', 'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => 1, 'is_active' => true]);
        $user->id = 1;
        $user->setRelation('municipality', new Municipality(['name' => 'Baguio City', 'province' => 'Benguet']));
        $this->actingAs($user);

        $this->view('layouts.app')
            ->assertSee('Baguio City, Benguet')
            ->assertDontSee('Baguio City, Tarlac');
    }

    public function test_login_keeps_native_credentials_and_error_recovery(): void
    {
        $view = $this->withViewErrors(['email' => 'These credentials do not match our records.'])->view('auth.login');

        $view->assertSee('These credentials do not match our records.')
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('action="'.route('login.attempt').'"', false)
            ->assertSee('Go to the first field to check')
            ->assertDontSee('name="password" value=', false);
    }
}
