<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsViewFixtures;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class AssistanceDirectoryPresentationTest extends TestCase
{
    use PresentationProvinceSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $this->createPresentationScope();
        $this->withViewErrors([]);
        $this->actingAs(OperationsViewFixtures::user());
    }

    public function test_filter_removal_and_reset_preserve_the_selected_workspace(): void
    {
        request()->query->replace([
            'municipality_id' => '1',
            'assistance_sector' => 'fisheries',
            'q' => '<img src=x onerror=alert(1)>',
            'input_category' => 'fish_feed',
            'received_from' => '2026-09-01',
            'page' => '4',
        ]);
        $data = OperationsViewFixtures::data('rice_seed_distributions.index');
        $data['canChooseMunicipality'] = true;
        $xpath = $this->renderDirectory($data);

        $removeSearch = $xpath->query('//a[starts-with(@aria-label, "Remove Search filter:")]')->item(0);
        parse_str(parse_url($removeSearch->getAttribute('href'), PHP_URL_QUERY), $remaining);
        $this->assertSame('1', $remaining['municipality_id']);
        $this->assertSame('fisheries', $remaining['assistance_sector']);
        $this->assertSame('fish_feed', $remaining['input_category']);
        $this->assertSame('2026-09-01', $remaining['received_from']);
        $this->assertArrayNotHasKey('q', $remaining);
        $this->assertArrayNotHasKey('page', $remaining);

        $reset = $xpath->query('//a[normalize-space(.)="Clear filters"]')->item(0);
        parse_str(parse_url($reset->getAttribute('href'), PHP_URL_QUERY), $resetQuery);
        $this->assertSame(['municipality_id' => '1', 'assistance_sector' => 'fisheries'], $resetQuery);
        $this->assertSame(0, $xpath->query('//*[@onerror]')->length);
        $this->assertSame(1, $xpath->query('//input[@type="hidden"][@name="assistance_sector"][@value="fisheries"]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="assistance_sector"]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="assistanceMoreFilters"][@open]')->length);
        $this->assertSame(1, $xpath->query('//nav[@aria-label="Assistance sector"]/a[@aria-current="page"][normalize-space(.)="Fisheries assistance"]')->length);
    }

    public function test_empty_workspace_keeps_the_municipality_and_sector_in_the_record_action(): void
    {
        request()->query->replace(['municipality_id' => '1', 'assistance_sector' => 'fisheries']);
        $data = OperationsViewFixtures::data('rice_seed_distributions.index');
        $data['canChooseMunicipality'] = true;
        $data['records'] = new LengthAwarePaginator([], 0, 10);
        $xpath = $this->renderDirectory($data);

        $emptyAction = $xpath->query('//div[contains(@class,"module-empty")]/a')->item(0);
        parse_str(parse_url($emptyAction->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['municipality_id' => '1', 'assistance_sector' => 'fisheries'], $query);
        $this->assertStringContainsString('Record release', $emptyAction->textContent);
        $this->assertSame(0, $xpath->query('//a[normalize-space(.)="Clear filters"]')->length);
    }

    public function test_release_details_retain_the_delete_form_and_quantity_units(): void
    {
        $data = OperationsViewFixtures::data('rice_seed_distributions.index');
        $data['records']->first()->quantity_unit = 'piece';
        $xpath = $this->renderDirectory($data);

        $this->assertSame(0, $xpath->query('//tr[contains(@class,"assistance-record")]//form')->length);
        $this->assertSame(1, $xpath->query('//tr[@id="rice-detail-41"][@hidden]//form/input[@name="_method"][@value="DELETE"]')->length);
        $this->assertSame(1, $xpath->query('//tr[@id="rice-detail-41"]//form/input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//button[@data-row-detail="rice-detail-41"][@aria-controls="rice-detail-41"][@aria-expanded="false"]')->length);
        $this->assertStringContainsString('15.50 pieces', $xpath->query('//td[@data-label="Release"]')->item(0)->textContent);
        $this->assertSame(4, $xpath->query('//section[@aria-label="Agriculture and fisheries assistance summary"]/article')->length);
    }

    private function renderDirectory(array $data): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML((string) $this->view('rice_seed_distributions.index', $data));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }
}
