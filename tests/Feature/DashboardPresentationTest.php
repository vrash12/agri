<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Presentation checks use unsaved models and cannot touch operational data.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    /** @dataProvider dashboardRoles */
    public function test_dashboard_preserves_role_specific_actions(string $role, bool $canWrite, bool $canManageStaff): void
    {
        $this->signInForView($role);

        $view = $this->view('dashboard');
        $view->assertSee('Operations dashboard');

        if ($canWrite) {
            $view->assertSee('Add farmer')->assertSee('Record assistance')->assertSee('Manage backups');
        } else {
            $view->assertDontSee('Add farmer')->assertDontSee('Record assistance')->assertDontSee('Manage backups');
        }

        if ($canManageStaff) {
            $view->assertSee('Manage staff');
        } else {
            $view->assertDontSee('Manage staff');
        }

        if ($role === User::ROLE_SUPER_ADMIN) {
            $view->assertSee('Municipality performance')->assertSee('Review audit trail');
        } else {
            $view->assertDontSee('Municipality performance')->assertDontSee('Review audit trail');
        }
    }

    public static function dashboardRoles(): array
    {
        return [
            'municipal staff' => [User::ROLE_MUNICIPAL_STAFF, true, false],
            'municipal head' => [User::ROLE_MUNICIPAL_HEAD, true, true],
            'provincial staff' => [User::ROLE_PROVINCIAL_STAFF, true, false],
            'super admin' => [User::ROLE_SUPER_ADMIN, false, true],
        ];
    }

    public function test_attention_links_and_monthly_figures_are_available_without_a_chart(): void
    {
        $this->signInForView(User::ROLE_MUNICIPAL_STAFF);

        $view = $this->view('dashboard', [
            'stats' => ['unmapped_farmers' => 4, 'farmers_missing_ffrs' => 2],
            'charts' => ['months' => ['Jan', 'Feb'], 'rice_monthly' => [125.5, 0]],
        ]);

        $view->assertSee('2 areas')->assertSee('Monthly figures')->assertSee('125.50 kg');
        foreach (['mapping=unmapped', 'quality=missing_ffrs', 'quality=missing_location', 'maintenance=attention'] as $filter) {
            $view->assertSee($filter, false);
        }
        $view->assertSee('aria-label="Farmers with mapped parcels"', false);
        $view->assertDontSee('No outstanding items in these four checks.');
    }

    public function test_empty_dashboard_and_all_time_office_totals_are_labeled_separately(): void
    {
        $this->signInForView(User::ROLE_MUNICIPAL_STAFF);

        $html = (string) $this->view('dashboard', [
            'stats' => ['total_cooperatives' => 12, 'total_backup_files' => 37],
        ]);

        $this->assertStringContainsString('No outstanding items in these four checks.', $html);
        $this->assertStringContainsString('No distribution activity', $html);
        $this->assertStringContainsString('Registered cooperatives', $html);
        $this->assertStringContainsString('Stored backup files', $html);
        preg_match('/<section class="ops-month-strip".*?<\/section>/s', $html, $monthlySection);
        $this->assertArrayHasKey(0, $monthlySection);
        $this->assertStringNotContainsString('cooperatives', $monthlySection[0]);
        $this->assertStringNotContainsString('backup', $monthlySection[0]);
    }

    public function test_default_dashboard_has_four_metrics_and_three_actions_with_reports_closed(): void
    {
        $this->signInForView(User::ROLE_MUNICIPAL_STAFF);
        $html = (string) $this->view('dashboard');
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(4, $xpath->query('//section[contains(@class,"ops-kpi-grid")]/article')->length);
        $this->assertSame(3, $xpath->query('//div[contains(@class,"ops-actions")]/a')->length);
        $this->assertSame(1, $xpath->query('//details[@id="dashboardReports" and not(@open)]')->length);
        $this->assertSame(0, $xpath->query('//script[contains(@src,"chart.js")]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="dashboardReports"]//canvas')->length);
        $this->assertStringContainsString('availability_status=available', $html);
    }

    private function signInForView(string $role): void
    {
        $user = new User(['name' => 'Preview Staff', 'role' => $role, 'municipality_id' => 1, 'is_active' => true]);
        $user->id = 1;
        $user->setRelation('municipality', new Municipality(['name' => 'Sample Municipality']));
        $this->actingAs($user);
    }
}
