<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use App\Support\FarmerPicker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * The beneficiary picker shared by the assistance and harvest forms.
 *
 * Both forms used to serialise every farmer the account could see into a select —
 * 724 KB and 299 KB for Ramos's 1,546 beneficiaries, on every load. The registry is
 * searched on the server now.
 *
 * What matters here is that moving the search to an endpoint did not move the
 * municipality boundary with it. A search endpoint that returns a name is a way to
 * ask "is this person in the registry", so it has to answer only for the registry
 * the account may read.
 */
class FarmerPickerTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private Municipality $sibling;

    private User $staff;

    private Farmer $local;

    private Farmer $foreign;

    private string $ffrs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = $this->makeMunicipality();
        $this->sibling = $this->makeMunicipality();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->staff = User::create([
            'name' => 'Picker Clerk',
            'email' => 'picker-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);

        $this->ffrs = '99-99-99-999-'.substr($suffix, -6);

        $this->local = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Testfirst',
            'middle_name' => 'Testmiddle',
            'last_name' => 'Testsurname',
            'ffrs' => $this->ffrs,
            'farm_location' => 'Barangay Uno',
            'farm_municipality' => 'Ramos',
            'farm_province' => 'Tarlac',
            'farm_area_ha' => 1.25,
            'contact_number' => '09170000001',
            'is_arb' => true,
            'is_4ps' => true,
        ]);

        $this->foreign = Farmer::create([
            'municipality_id' => $this->sibling->id,
            'first_name' => 'Another',
            'last_name' => 'Testsurname',
            'ffrs' => $this->ffrs.'-X',
            'farm_location' => 'Barangay Dos',
        ]);
    }

    public function test_a_search_never_reaches_another_municipality(): void
    {
        // The shared surname is the point: both farmers match the term, and only one
        // may be returned.
        $response = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', ['q' => 'Testsurname']))
            ->assertOk();

        $values = collect($response->json('farmers'))->pluck('value')->all();

        $this->assertContains((string) $this->local->id, $values);
        $this->assertNotContains((string) $this->foreign->id, $values);
        $this->assertSame(1, $response->json('total'));
    }

    public function test_a_submitted_municipality_cannot_widen_the_search(): void
    {
        // A municipal account posting the neighbour's id gets its own scope, not the
        // neighbour's — the parameter only ever narrows what scope already allows.
        $response = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', [
                'q' => 'Testsurname',
                'municipality_id' => $this->sibling->id,
            ]))
            ->assertOk();

        $this->assertNotContains(
            (string) $this->foreign->id,
            collect($response->json('farmers'))->pluck('value')->all()
        );
    }

    public function test_a_one_character_term_returns_nothing_and_says_why(): void
    {
        // One character matches most of a registry. The caller is asked to be
        // specific rather than handed an arbitrary slice of everyone.
        $response = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', ['q' => 'Z']))
            ->assertOk();

        $this->assertSame([], $response->json('farmers'));
        $this->assertTrue($response->json('needs_more_input'));
    }

    public function test_the_profile_block_is_opt_in(): void
    {
        $without = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', ['q' => 'Testsurname']))
            ->assertOk()
            ->json('farmers.0.dataset');

        // The harvest form shows a name. Sending it a contact number would be
        // handing out personal data for a field that never appears.
        $this->assertArrayNotHasKey('contact', $without);
        $this->assertArrayNotHasKey('tags', $without);
        $this->assertSame('Testsurname, Testfirst Testmiddle', $without['name']);

        $with = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', ['q' => 'Testsurname', 'profile' => 1]))
            ->assertOk()
            ->json('farmers.0.dataset');

        $this->assertSame('09170000001', $with['contact']);
        $this->assertSame('ARB, 4Ps', $with['tags']);
        $this->assertSame('1.25 ha', $with['area']);
    }

    public function test_a_term_with_wildcard_characters_searches_for_those_characters(): void
    {
        Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Percent',
            'last_name' => 'Literal%Name',
            'farm_location' => 'Barangay Tres',
        ]);

        $response = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', ['q' => 'Literal%N']))
            ->assertOk();

        // Unescaped, "%" would act as a wildcard and match far more than was typed.
        $this->assertSame(1, $response->json('total'));
    }

    public function test_the_limit_is_capped_however_large_a_caller_asks_for(): void
    {
        $picker = app(FarmerPicker::class);

        for ($i = 0; $i < 4; $i++) {
            Farmer::create([
                'municipality_id' => $this->municipality->id,
                'first_name' => 'Bulk'.$i,
                'last_name' => 'Cappable',
                'farm_location' => 'Barangay Uno',
            ]);
        }

        $result = $picker->search($this->staff, 'Cappable', null, 2);

        $this->assertSame(2, $result['returned']);
        $this->assertSame(4, $result['total']);
        $this->assertTrue($result['truncated']);
        $this->assertLessThanOrEqual(FarmerPicker::MAXIMUM_LIMIT, $result['returned']);
    }

    public function test_a_form_carries_only_the_farmer_already_chosen(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Farmer::create([
                'municipality_id' => $this->municipality->id,
                'first_name' => 'Unlisted'.$i,
                'last_name' => 'Bystander',
                'farm_location' => 'Barangay Uno',
            ]);
        }

        $picker = app(FarmerPicker::class);

        $this->assertCount(0, $picker->initialOptions($this->staff));
        $this->assertCount(1, $picker->initialOptions($this->staff, $this->local->id));

        // The escape hatch behind the form's "browse the whole registry" link.
        $this->assertGreaterThan(5, $picker->initialOptions($this->staff, null, true)->count());
    }

    public function test_the_picker_and_the_endpoint_describe_a_farmer_identically(): void
    {
        // If these ever drifted, an option rendered on page load would describe a
        // beneficiary differently from the same option fetched a second later.
        $picker = app(FarmerPicker::class);

        $rendered = $picker->initialOptions($this->staff, $this->local->id, false, true)->first();

        $fetched = $this->actingAs($this->staff)
            ->getJson(route('farmers.picker', ['q' => 'Testsurname', 'profile' => 1]))
            ->assertOk()
            ->json('farmers.0');

        $this->assertSame($rendered['label'], $fetched['label']);
        $this->assertSame($rendered['dataset'], $fetched['dataset']);
    }

    public function test_a_name_is_shown_surname_first_with_the_identifier_that_separates_namesakes(): void
    {
        $picker = app(FarmerPicker::class);

        $option = $picker->option($this->local->fresh());

        $this->assertSame('Testsurname, Testfirst Testmiddle — '.$this->ffrs, $option['label']);

        $noIdentifier = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Juan',
            'last_name' => 'Nameless',
            'farm_location' => 'Barangay Uno',
        ]);

        // A farmer with no identifier is still pickable and says so rather than
        // trailing an empty dash.
        $this->assertSame('Nameless, Juan', $picker->option($noIdentifier)['label']);
        $this->assertSame('Not assigned', $picker->option($noIdentifier)['dataset']['ffrs']);
    }

    public function test_a_signed_out_visitor_cannot_search_the_registry(): void
    {
        $this->getJson(route('farmers.picker', ['q' => 'Testsurname']))
            ->assertUnauthorized();
    }

    private function makeMunicipality(): Municipality
    {
        $suffix = str_replace('.', '', uniqid('', true));

        return Municipality::create([
            'name' => 'Picker Town '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'PK'.substr($suffix, -8),
            'is_active' => true,
        ]);
    }
}
