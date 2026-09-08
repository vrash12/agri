<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsViewFixtures;
use Tests\TestCase;

class OperationsPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $this->withViewErrors([]);
        $this->actingAs(OperationsViewFixtures::user());
    }

    /** @dataProvider views */
    public function test_views_preserve_forms_and_render_without_database_access(string $view): void
    {
        $html = (string) $this->view($view, OperationsViewFixtures::data($view));
        $this->assertStringContainsString('Preview Municipality', $html);
        if (str_ends_with($view, '.edit') || str_ends_with($view, '.assign_farmers')) {
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('name="_record_version"', $html);
        }
        $this->assertStringNotContainsString('<script src="https://cdn.jsdelivr.net/npm/chart.js', $html);
    }

    public static function views(): array
    {
        return array_map(fn ($view) => [$view], [
            'rice_seed_distributions.index', 'rice_seed_distributions.create', 'rice_seed_distributions.edit', 'rice_seed_distributions.import',
            'anti_rabies_vaccinations.index', 'anti_rabies_vaccinations.create', 'anti_rabies_vaccinations.edit',
            'farmers_cooperatives.index', 'farmers_cooperatives.create', 'farmers_cooperatives.edit', 'farmers_cooperatives.assign_farmers',
            'agricultural_machineries.index', 'agricultural_machineries.create', 'agricultural_machineries.edit',
        ]);
    }

    public function test_legacy_non_seed_details_and_concurrency_token_remain_submittable(): void
    {
        $html = (string) $this->view('rice_seed_distributions.edit', OperationsViewFixtures::data('rice_seed_distributions.edit'));
        $this->assertMatchesRegularExpression('/<details[^>]+id="riceSeedSpecificFields"[^>]+open/', $html);
        $this->assertMatchesRegularExpression('/<details[^>]+id="riceProductionMonitoring"[^>]+open/', $html);
        $this->assertStringContainsString('value="2.50"', $html);
        $this->assertStringContainsString('name="date_received" value="2026-09-01"', $html);
        $this->assertStringContainsString('value="4"', $html);
        $this->assertStringContainsString('BATCH-PREVIEW', $html);
        $this->assertStringNotContainsString('field.disabled = !showSeedFields', $html);
    }

    public function test_create_forms_do_not_collapse_required_fields(): void
    {
        foreach (['rice_seed_distributions', 'anti_rabies_vaccinations', 'farmers_cooperatives', 'agricultural_machineries'] as $module) {
            $html = (string) $this->view($module.'.create', OperationsViewFixtures::data($module.'.create'));
            $document = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($document);
            $this->assertSame(0, $xpath->query('//details[not(@open)]//*[@required]')->length, $module);
            $this->assertGreaterThan(0, $xpath->query('//form//*[@required]')->length, $module);
        }
    }

    public function test_reports_have_readable_figures_and_are_closed_initially(): void
    {
        foreach (['rice_seed_distributions' => 'assistanceReports', 'anti_rabies_vaccinations' => 'animalHealthReports', 'agricultural_machineries' => 'machineryReports'] as $module => $id) {
            $html = (string) $this->view($module.'.index', OperationsViewFixtures::data($module.'.index'));
            $this->assertStringContainsString('<details class="module-more" id="'.$id.'">', $html);
            $this->assertStringContainsString('<caption', $html);
            $this->assertStringContainsString('data-report-status role="status"', $html);
        }
    }

    public function test_super_admin_gets_read_only_operations_and_vet_has_service_entry(): void
    {
        $this->actingAs(OperationsViewFixtures::user(User::ROLE_SUPER_ADMIN));
        foreach (['rice_seed_distributions', 'farmers_cooperatives', 'agricultural_machineries'] as $module) {
            $html = (string) $this->view($module.'.index', OperationsViewFixtures::data($module.'.index'));
            $this->assertStringNotContainsString('name="_method" value="DELETE"', $html);
        }
        $this->actingAs(OperationsViewFixtures::user(User::ROLE_PROVINCIAL_VET));
        $this->view('anti_rabies_vaccinations.index', OperationsViewFixtures::data('anti_rabies_vaccinations.index'))->assertSee('Record service');
    }
}
