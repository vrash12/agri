<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsViewFixtures;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class AnimalHealthDirectoryPresentationTest extends TestCase
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

    public function test_removing_a_filter_keeps_the_selected_municipality_and_other_filters(): void
    {
        $data = OperationsViewFixtures::data('anti_rabies_vaccinations.index');
        $data['canChooseMunicipality'] = true;
        $data['q'] = '<script>alert("sample")</script>';
        $data['petType'] = 'Dog';
        $data['serviceType'] = 'vaccination';
        $data['year'] = '2026';
        $html = (string) $this->view('anti_rabies_vaccinations.index', $data);
        $xpath = $this->xpath($html);
        $link = $xpath->query('//a[starts-with(@aria-label, "Remove Search filter:")]')->item(0);

        $this->assertNotNull($link);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['municipality_id' => '1', 'service_type' => 'vaccination', 'pet_type' => 'Dog', 'year' => '2026'], $query);
        $this->assertStringNotContainsString($data['q'], $html);
        $this->assertStringContainsString(e($data['q']), $html);

        $clear = $xpath->query('//a[normalize-space(.)="Clear filters"]')->item(0);
        parse_str(parse_url($clear->getAttribute('href'), PHP_URL_QUERY), $clearQuery);
        $this->assertSame(['municipality_id' => '1'], $clearQuery);
    }

    public function test_municipal_assignment_does_not_turn_an_empty_register_into_a_filtered_result(): void
    {
        $data = OperationsViewFixtures::data('anti_rabies_vaccinations.index');
        $data['records'] = new LengthAwarePaginator([], 0, 20);
        $html = (string) $this->view('anti_rabies_vaccinations.index', $data);
        $xpath = $this->xpath($html);

        $this->assertStringContainsString('No animal-health services recorded yet', $html);
        $this->assertStringNotContainsString('No services match these filters', $html);
        $this->assertSame(0, $xpath->query('//select[@name="municipality_id"]')->length);
        $this->assertSame(0, $xpath->query('//a[normalize-space(.)="Clear filters"]')->length);
        $this->assertSame(1, $xpath->query('//div[@class="module-empty"]/a[normalize-space(.)="Record service"]')->length);
    }

    public function test_responsive_register_retains_one_authorized_set_of_record_actions(): void
    {
        $data = OperationsViewFixtures::data('anti_rabies_vaccinations.index');
        $xpath = $this->xpath((string) $this->view('anti_rabies_vaccinations.index', $data));
        $this->assertSame(1, $xpath->query('//table[@aria-labelledby="animalRegisterTitle"]//form/input[@name="_method" and @value="DELETE"]')->length);
        $this->assertSame(1, $xpath->query('//table[@aria-labelledby="animalRegisterTitle"]//a[@aria-label="Edit service for Sample Raiser"]')->length);

        $this->actingAs(OperationsViewFixtures::user(User::ROLE_SUPER_ADMIN));
        $html = (string) $this->view('anti_rabies_vaccinations.index', $data);
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//table[@aria-labelledby="animalRegisterTitle"]//form')->length);
        $this->assertSame(0, $xpath->query('//table[@aria-labelledby="animalRegisterTitle"]//a')->length);
        $this->assertStringContainsString('Read-only oversight', $html);
        $this->assertStringContainsString('Sample Raiser', $html);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }
}
