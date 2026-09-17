<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsViewFixtures;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class CooperativeDirectoryPresentationTest extends TestCase
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

    public function test_display_options_stay_submittable_and_reveal_non_default_values(): void
    {
        $data = OperationsViewFixtures::data('farmers_cooperatives.index');
        $xpath = $this->render($data);
        $this->assertSame(1, $xpath->query('//form[@method="GET"]//details[not(@open)]//select[@name="sort"]')->length);
        $this->assertSame(1, $xpath->query('//form[@method="GET"]//details[not(@open)]//select[@name="per_page"]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="municipality_id"]')->length);

        $xpath = $this->render(array_replace($data, ['sort' => 'members', 'perPage' => 50]));
        $this->assertSame(1, $xpath->query('//details[@open]//select[@name="sort"]/option[@value="members" and @selected]')->length);
        $this->assertSame(1, $xpath->query('//details[@open]//select[@name="per_page"]/option[@value="50" and @selected]')->length);
    }

    public function test_removing_filters_keeps_the_municipality_and_other_selections(): void
    {
        $data = array_replace(OperationsViewFixtures::data('farmers_cooperatives.index'), [
            'canChooseMunicipality' => true,
            'selectedMunicipalityId' => 1,
            'q' => 'Growers & partners',
            'status' => 'empty',
            'sort' => 'members',
            'perPage' => 50,
        ]);
        $xpath = $this->render($data);
        $link = $xpath->query('//a[starts-with(@aria-label,"Remove search filter")]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['municipality_id' => '1', 'status' => 'empty', 'sort' => 'members', 'per_page' => '50'], $query);

        $clear = $xpath->query('//a[normalize-space(.)="Clear filters"]')->item(0);
        $this->assertSame(route('farmers-cooperatives.index', ['municipality_id' => 1]), $clear->getAttribute('href'));
        $create = $xpath->query('//a[contains(@href,"farmers-cooperatives/create")]')->item(0);
        $this->assertSame(route('farmers-cooperatives.create', ['municipality_id' => 1]), $create->getAttribute('href'));
    }

    public function test_empty_state_distinguishes_filters_from_office_scope_and_read_only_access(): void
    {
        $data = array_replace(OperationsViewFixtures::data('farmers_cooperatives.index'), [
            'records' => new LengthAwarePaginator([], 0, 10),
        ]);
        $xpath = $this->render($data);
        $this->assertSame('No cooperatives recorded yet', $xpath->query('//div[@class="module-empty"]/strong')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//div[@class="module-empty"]//a[contains(@href,"/create")]')->length);

        $xpath = $this->render(array_replace($data, ['q' => 'No matching profile']));
        $this->assertSame('No cooperatives match these filters', $xpath->query('//div[@class="module-empty"]/strong')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//div[@class="module-empty"]//a[normalize-space(.)="Clear filters"]')->length);

        $this->actingAs(OperationsViewFixtures::user(User::ROLE_SUPER_ADMIN));
        $xpath = $this->render($data);
        $this->assertStringContainsString('when staff record them', $xpath->query('//div[@class="module-empty"]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//a[contains(@href,"farmers-cooperatives/create")]')->length);
    }

    public function test_record_actions_remain_explicit_and_read_only_roles_only_get_exports(): void
    {
        $data = OperationsViewFixtures::data('farmers_cooperatives.index');
        $data['record']->name = '<img src=x onerror=alert(1)> Growers';
        $xpath = $this->render($data);
        $this->assertSame(0, $xpath->query('//table//img')->length);
        $this->assertSame(1, $xpath->query('//a[starts-with(@aria-label,"Manage members of")]')->length);
        $this->assertSame(1, $xpath->query('//a[starts-with(@aria-label,"Edit profile of")]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-cooperative-name]//input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-cooperative-name]//input[@name="_method" and @value="DELETE"]')->length);

        $this->actingAs(OperationsViewFixtures::user(User::ROLE_SUPER_ADMIN));
        $xpath = $this->render($data);
        $this->assertSame(0, $xpath->query('//a[starts-with(@aria-label,"Manage members of") or starts-with(@aria-label,"Edit profile of")]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="_method" and @value="DELETE"]')->length);
        $this->assertSame(1, $xpath->query('//a[starts-with(@aria-label,"Export member list for")]')->length);
    }

    private function render(array $data): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML((string) $this->view('farmers_cooperatives.index', $data));

        return new \DOMXPath($document);
    }
}
