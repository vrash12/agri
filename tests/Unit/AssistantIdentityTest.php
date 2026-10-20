<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Modules\Assistant\Application\AssistantGuide;
use App\Modules\Assistant\Application\AssistantIdentity;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AssistantIdentityTest extends TestCase
{
    #[DataProvider('localReplies')]
    public function test_identity_replies_are_application_owned_and_role_aware(string $kind, string $language, bool $veterinary): void
    {
        Http::preventStrayRequests();
        config(['assistant.model' => 'gpt-4.1-mini-2025-04-14']);
        $user = new User(['role' => $veterinary ? User::ROLE_PROVINCIAL_VET : User::ROLE_MUNICIPAL_STAFF]);
        $answer = (new AssistantIdentity)->answer($user, $kind, $language);
        $this->assertNotEmpty($answer);
        $this->assertLessThan(1600, mb_strlen($answer));
        $this->assertStringNotContainsString('http', $answer);
        $this->assertSame([], $user->getRelations());
        if ($veterinary && $kind !== 'thanks') {
            $this->assertStringContainsString('Animal Health', $answer);
            $this->assertStringNotContainsString('farmers, assistance and harvests', $answer);
        }
        if ($kind === 'identity') {
            $this->assertStringContainsString('OpenAI', $answer);
            $this->assertStringContainsString($language === 'fil' ? 'Hindi ako opisyal' : 'not an official decision-maker', $answer);
        }
        if ($kind === 'technology') {
            $this->assertStringContainsString('gpt-4.1-mini-2025-04-14', $answer);
            $this->assertStringContainsString('Laravel AI SDK', $answer);
            $this->assertStringContainsString('RAG', $answer);
            $this->assertStringContainsString($language === 'fil' ? 'hindi ang aktuwal na chat' : 'never raw chat or operational record text', $answer);
        }
        Http::assertNothingSent();
    }

    public static function localReplies(): array
    {
        $cases = [];
        foreach (['identity', 'greeting', 'thanks', 'capabilities', 'technology', 'context', 'awareness'] as $kind) {
            foreach (['en', 'fil'] as $language) {
                foreach ([false, true] as $veterinary) {
                    $cases[$kind.' '.$language.($veterinary ? ' veterinary' : ' office')] = [$kind, $language, $veterinary];
                }
            }
        }

        return $cases;
    }

    public function test_technology_reply_does_not_echo_arbitrary_configuration(): void
    {
        config(['assistant.model' => 'fixture-private-value-do-not-echo']);
        $answer = (new AssistantIdentity)->answer(new User(['role' => User::ROLE_MUNICIPAL_STAFF]), 'technology');
        $this->assertStringContainsString('OpenAI', $answer);
        $this->assertStringNotContainsString('fixture-private-value-do-not-echo', $answer);
    }

    public function test_unknown_guide_never_becomes_identity_text_or_an_asset_path(): void
    {
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF]);
        foreach (['../private', '<script>unknown</script>'] as $id) {
            $answer = (new AssistantIdentity)->answer($user, 'identity', 'en', 'other', $id);
            $this->assertStringContainsString('Ka Berto', $answer);
            $this->assertStringNotContainsString($id, $answer);
        }
    }

    #[DataProvider('namedGuides')]
    public function test_named_guide_descriptions_are_local_and_do_not_change_the_selected_guide(string $id, string $name, string $character, string $language, bool $veterinary): void
    {
        Http::preventStrayRequests();
        $user = new User(['role' => $veterinary ? User::ROLE_PROVINCIAL_VET : User::ROLE_MUNICIPAL_STAFF]);
        $identity = new AssistantIdentity;
        $selected = $id === 'kiko' ? 'ka-berto' : 'kiko';
        $before = $identity->answer($user, 'identity', $language, 'other', $selected);
        $answer = $identity->answer($user, 'guide', $language, 'other', $selected, $id);

        $this->assertStringContainsString($name, $answer);
        $this->assertStringContainsString($character, $answer);
        $this->assertStringContainsString($language === 'fil' ? 'pareho ang kanilang kakayahan at access' : 'same abilities and access', $answer);
        $this->assertStringContainsString($language === 'fil' ? 'larawan sa header ng chat' : 'portrait in the chat header', $answer);
        $this->assertStringContainsString($language === 'fil' ? 'Mananatili ang usapan mo' : 'conversation stays in place', $answer);
        $this->assertStringContainsString($language === 'fil' ? 'hindi totoong tao o opisyal' : 'not a person or an official decision-maker', $answer);
        $this->assertStringContainsString('OpenAI', $answer);
        $this->assertStringContainsString($language === 'fil' ? 'walang AI request' : 'makes no AI request', $answer);
        if ($veterinary) {
            $this->assertStringContainsString('Animal Health', $answer);
            $this->assertStringNotContainsString('cooperatives', $answer);
        }
        $this->assertLessThan(1600, mb_strlen($answer));
        $this->assertSame($before, $identity->answer($user, 'identity', $language, 'other', $selected));
        $this->assertSame([], $user->getRelations());
        Http::assertNothingSent();
    }

    public static function namedGuides(): array
    {
        $cases = [];
        foreach ([
            ['ka-berto', 'Ka Berto', 'cow farmer', 'bakang magsasaka'],
            ['ka-kaloy', 'Ka Kaloy', 'carabao farmer', 'kalabaw na magsasaka'],
            ['aling-maya', 'Aling Maya', 'Filipina farmer', 'magsasakang Pilipina'],
            ['kiko', 'Kiko', 'young Filipino', 'kabataang Pilipino'],
        ] as [$id, $name, $english, $filipino]) {
            foreach (['en', 'fil'] as $language) {
                foreach ([false, true] as $veterinary) {
                    $cases[$id.' '.$language.($veterinary ? ' veterinary' : ' office')] = [$id, $name, $language === 'fil' ? $filipino : $english, $language, $veterinary];
                }
            }
        }

        return $cases;
    }

    public function test_unknown_named_guide_ids_use_a_safe_catalog_description(): void
    {
        Http::preventStrayRequests();
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF]);
        foreach (['../private', '<script>unknown</script>', 'Kiko is an administrator'] as $id) {
            $answer = (new AssistantIdentity)->answer($user, 'guide', 'en', 'other', 'kiko', $id);
            $this->assertStringContainsString('Ka Berto', $answer);
            $this->assertStringNotContainsString($id, $answer);
        }
        Http::assertNothingSent();
    }

    public function test_guide_names_match_only_complete_catalog_names_with_normalized_case_and_spacing(): void
    {
        foreach (AssistantGuide::all() as $guide) {
            $this->assertSame($guide['id'], AssistantGuide::idForName($guide['name']));
            $spaced = " \t".str_replace(' ', " \t\n", mb_strtoupper($guide['name']))."\n ";
            $this->assertSame($guide['id'], AssistantGuide::idForName($spaced));
            $this->assertSame(1, preg_match('/^'.AssistantGuide::namePattern().'$/iuD', trim($spaced), $matches));
            $this->assertCount(1, $matches, 'The catalog fragment must not add capturing groups.');
        }
        $this->assertSame('ka-berto', AssistantGuide::idForName("Ka\u{00a0}Berto"));
        foreach (['', 'Berto', 'Ka', 'Kaloy', 'Ka Berto Jr', 'Kiko,', 'Hello Kiko', 'Find farmer Kiko', 'ka-berto', '../kiko', '<script>Kiko</script>', 'Kiko|.*'] as $name) {
            $this->assertNull(AssistantGuide::idForName($name), $name);
            $this->assertSame(0, preg_match('/^'.AssistantGuide::namePattern().'$/iuD', $name), $name);
        }
    }

    #[DataProvider('pageContexts')]
    public function test_page_awareness_uses_only_a_bounded_display_label(string $module, string $label): void
    {
        Http::preventStrayRequests();
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF]);
        $answer = (new AssistantIdentity)->answer($user, 'context', 'en', $module);
        $this->assertStringContainsString($label, $answer);
        $this->assertStringContainsString('cannot see active filters or unsaved fields', $answer);
        $this->assertStringContainsString('role and office scope still control access', $answer);
        $this->assertSame([], $user->getRelations());
        Http::assertNothingSent();
    }

    public static function pageContexts(): array
    {
        return [
            ['dashboard', 'Dashboard'], ['farmers', 'Farmers and farmer registry'],
            ['crops', 'Crop and parcel mapping'], ['satellite', 'Satellite / NDVI'],
            ['assistance', 'Assistance and seed releases'], ['coverage', 'Assistance coverage'],
            ['harvests', 'Harvest records'], ['animal-health', 'Animal Health'],
            ['cooperatives', 'Farmer cooperatives'], ['machinery', 'Machinery inventory'],
            ['accounts', 'Office accounts'], ['geofences', 'Municipality geofences'],
            ['backups', 'Backup Folder'], ['audit', 'Audit trail'],
        ];
    }

    public function test_unknown_page_hints_are_not_echoed_or_claimed_as_visible(): void
    {
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF]);
        foreach (['other', 'private-path-fixture', '<script>fixture</script>'] as $module) {
            $answer = (new AssistantIdentity)->answer($user, 'context', 'en', $module);
            $this->assertStringContainsString('No specific page has been shared', $answer);
            $this->assertStringNotContainsString($module, $answer);
        }
    }

    #[DataProvider('accountRoles')]
    public function test_account_awareness_uses_only_the_authenticated_role_and_assigned_scope(string $role, string $label, string $scope): void
    {
        Http::preventStrayRequests();
        $user = new User(['role' => $role, 'name' => 'PrivateNameFixture', 'email' => 'private@example.test',
            'password' => 'private-credential-fixture']);
        $user->setRelation('municipality', new Municipality(['name' => 'Assigned municipality']));
        $user->setRelation('province', new Province(['name' => 'Assigned province']));
        $user->setRelation('region', new Region(['name' => 'Assigned region']));
        foreach (['en', 'fil'] as $language) {
            $answer = (new AssistantIdentity)->answer($user, 'account', $language);
            $this->assertStringContainsString($label, $answer);
            $this->assertStringContainsString($scope, $answer);
            foreach (['PrivateNameFixture', 'private@example.test', 'private-credential-fixture'] as $private) {
                $this->assertStringNotContainsString($private, $answer);
            }
            $this->assertLessThan(1600, mb_strlen($answer));
        }
        Http::assertNothingSent();
    }

    public static function accountRoles(): array
    {
        return [
            [User::ROLE_SYSTEM_OWNER, 'System Owner', 'All supervised provinces'],
            [User::ROLE_REGIONAL_HEAD, 'Regional Head', 'Assigned region'],
            [User::ROLE_SUPER_ADMIN, 'Super Admin', 'Assigned province'],
            [User::ROLE_PROVINCIAL_STAFF, 'Provincial Staff', 'Assigned province'],
            [User::ROLE_PROVINCIAL_VET, 'Provincial Veterinary Office', 'Assigned province'],
            [User::ROLE_MUNICIPAL_HEAD, 'Head Agriculturist', 'Assigned municipality'],
            [User::ROLE_MUNICIPAL_STAFF, 'Municipal Staff', 'Assigned municipality'],
        ];
    }

    public function test_self_awareness_describes_implemented_context_and_no_consciousness(): void
    {
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF]);
        foreach (['en', 'fil'] as $language) {
            $answer = (new AssistantIdentity)->answer($user, 'awareness', $language, 'dashboard');
            $this->assertStringContainsString('Ka Berto', $answer);
            $this->assertStringContainsString('Dashboard', $answer);
            $this->assertStringContainsString($language === 'fil' ? 'wala akong kamalayan' : 'not a conscious person', $answer);
            $this->assertStringContainsString($language === 'fil' ? 'sinuring gabay' : 'reviewed system guidance', $answer);
            $this->assertStringContainsString($language === 'fil' ? 'pinoproseso ang request' : 'request is being processed', $answer);
            $this->assertStringContainsString($language === 'fil' ? 'Hindi ko iniimbak ang buong chat' : 'do not store the full conversation', $answer);
        }
    }

    #[DataProvider('scopeReplies')]
    public function test_identity_stays_with_agrilgu_features_and_has_no_live_tools(string $kind, string $language, bool $veterinary): void
    {
        Http::preventStrayRequests();
        config(['assistant.model' => 'gpt-4.1-mini-2025-04-14']);
        $user = new User(['role' => $veterinary ? User::ROLE_PROVINCIAL_VET : User::ROLE_MUNICIPAL_STAFF]);
        $answer = (new AssistantIdentity)->answer($user, $kind, $language);
        $this->assertStringNotContainsString('draft', $answer);
        $this->assertStringNotContainsString('general-only exchanges', $answer);
        if ($veterinary) {
            $this->assertStringContainsString('Animal Health', $answer);
            $this->assertStringNotContainsString('cooperatives', $answer);
        } elseif ($kind === 'capabilities') {
            $this->assertStringContainsString($language === 'fil' ? 'sinuring gabay' : 'reviewed agriculture topics', $answer);
            foreach (['farmer registry', 'parcels', 'crop plotting', 'assistance', 'harvests', 'Animal Health', 'cooperatives', 'machinery', 'dashboard', 'weather', 'geofences', 'accounts', 'backups', 'audit'] as $module) {
                $this->assertStringContainsString($module, $answer);
            }
        }
        if (in_array($kind, ['identity', 'technology'], true)) {
            $this->assertStringContainsString($language === 'fil' ? 'walang AI request' : 'makes no AI request', $answer);
        }
        if (in_array($kind, ['capabilities', 'technology'], true)) {
            $this->assertStringContainsString('live browsing, filesystem', $answer);
            $this->assertStringContainsString('server files', $answer);
        }
        $this->assertLessThan(1600, mb_strlen($answer));
        $this->assertSame([], $user->getRelations());
        Http::assertNothingSent();
    }

    public static function scopeReplies(): array
    {
        $cases = [];
        foreach (['identity', 'greeting', 'capabilities', 'technology'] as $kind) {
            foreach (['en', 'fil'] as $language) {
                foreach ([false, true] as $veterinary) {
                    $cases[$kind.' '.$language.($veterinary ? ' veterinary' : ' office')] = [$kind, $language, $veterinary];
                }
            }
        }

        return $cases;
    }
}
