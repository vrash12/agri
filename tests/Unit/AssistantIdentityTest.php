<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AssistantIdentity;
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
        foreach (['identity', 'greeting', 'thanks', 'capabilities', 'technology'] as $kind) {
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
            $this->assertStringContainsString($language === 'fil' ? 'AgriLGU lamang' : 'AgriLGU features only', $answer);
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
