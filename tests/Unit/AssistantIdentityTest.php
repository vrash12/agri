<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
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
            $this->assertStringContainsString('eAgriLGU Assistant', $answer);
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
            $this->assertStringContainsString($language === 'fil' ? 'eAgriLGU lamang' : 'eAgriLGU features only', $answer);
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
