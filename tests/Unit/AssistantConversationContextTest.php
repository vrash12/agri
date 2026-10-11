<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Models\User;
use App\Support\AssistantConversationContext;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class AssistantConversationContextTest extends TestCase
{
    public function test_approved_help_references_round_trip_without_chat_or_record_text(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'help', 'year' => 2026, 'help_topic' => 'crop-drawing', 'help_style' => 'steps', 'message' => 'Do not retain me']);
        $references = $contexts->read($user, 'synthetic-session', $token);
        $this->assertSame(['task' => 'help', 'year' => 2026, 'farmer_id' => null, 'choices' => [], 'help_topic' => 'crop-drawing', 'help_style' => 'steps'], $references);
        $this->assertStringNotContainsString('Do not retain me', Crypt::decryptString($token));
    }

    public function test_legacy_version_one_tokens_remain_neutral_compatible_references(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'help', 'year' => 2026]);
        $this->assertSame(['task' => 'help', 'year' => 2026, 'farmer_id' => null, 'choices' => []], $contexts->read($user, 'synthetic-session', $token));
        $identity = $contexts->issue($user, 'synthetic-session', ['task' => 'identity', 'year' => 2026]);
        $this->assertSame('identity', $contexts->read($user, 'synthetic-session', $identity)['task']);
    }

    public function test_help_language_is_a_bounded_optional_preference_without_chat_text(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        foreach (['en', 'fil'] as $language) {
            $token = $contexts->issue($user, 'synthetic-session', ['task' => 'help', 'year' => 2026,
                'help_topic' => 'seed-import', 'help_style' => 'brief', 'help_language' => $language, 'answer' => 'Do not retain this reply']);
            $this->assertSame($language, $contexts->read($user, 'synthetic-session', $token)['help_language']);
            $this->assertStringNotContainsString('Do not retain this reply', Crypt::decryptString($token));
            $this->assertLessThanOrEqual(4096, strlen($token));
        }
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'farmer', 'year' => 2026, 'help_language' => 'fil']);
        $this->assertArrayNotHasKey('help_language', $contexts->read($user, 'synthetic-session', $token));
    }

    public function test_signed_invalid_help_metadata_is_rejected(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'help', 'year' => 2026]);
        $value = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        foreach ([['help_topic' => 'private-records'], ['help_topic' => ['crop-drawing']],
            ['help_topic' => 'crop-drawing', 'help_style' => 'raw-history'],
            ['help_style' => 'brief'], ['task' => 'farmer', 'help_topic' => 'crop-drawing'],
            ['help_topic' => 'seed-import', 'help_language' => 'private-translation'],
            ['help_topic' => 'seed-import', 'help_language' => ['fil']], ['help_language' => 'fil']] as $invalid) {
            $changed = Crypt::encryptString(json_encode(array_replace($value, $invalid), JSON_THROW_ON_ERROR));
            $this->assertNull($contexts->read($user, 'synthetic-session', $changed));
        }
    }

    public function test_help_metadata_is_bound_to_the_same_user_session_and_scope(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'help', 'year' => 2026, 'help_topic' => 'ndvi']);
        $this->assertNull($contexts->read($user, 'another-session', $token));
        $other = $this->user();
        $other->id = 72;
        $this->assertNull($contexts->read($other, 'synthetic-session', $token));
        $user->role = User::ROLE_PROVINCIAL_VET;
        $this->assertNull($contexts->read($user, 'synthetic-session', $token));
    }

    public function test_pending_scope_retains_only_a_bounded_report_and_selected_reference(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        foreach (['assistance', 'harvests'] as $report) {
            $token = $contexts->issue($user, 'synthetic-session', ['task' => 'farmer', 'year' => 2025,
                'farmer_id' => 17, 'pending_scope' => $report, 'message' => 'Private question must not survive']);
            $this->assertSame(['task' => 'farmer', 'year' => 2025, 'farmer_id' => 17, 'choices' => [], 'pending_scope' => $report],
                $contexts->read($user, 'synthetic-session', $token));
            $this->assertStringNotContainsString('Private question', Crypt::decryptString($token));
            $this->assertLessThanOrEqual(4096, strlen($token));
        }
    }

    public function test_signed_invalid_pending_scope_metadata_is_rejected(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'farmer', 'year' => 2025, 'farmer_id' => 17, 'pending_scope' => 'assistance']);
        $value = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        foreach ([['pending_scope' => 'private-fields'], ['pending_scope' => ['assistance']], ['farmer_id' => null],
            ['task' => 'assistance'], ['task' => 'help'], ['choices' => [17]], ['type' => 'choice']] as $invalid) {
            $changed = Crypt::encryptString(json_encode(array_replace($value, $invalid), JSON_THROW_ON_ERROR));
            $this->assertNull($contexts->read($user, 'synthetic-session', $changed, $invalid['type'] ?? 'context'));
        }
        $choice = $contexts->issue($user, 'synthetic-session', ['task' => 'farmer', 'year' => 2025, 'farmer_id' => 17, 'pending_scope' => 'assistance'], 'choice');
        $this->assertArrayNotHasKey('pending_scope', $contexts->read($user, 'synthetic-session', $choice, 'choice'));
    }

    private function user(): User
    {
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => 3, 'is_active' => true, 'visitor_mode' => false]);
        $user->id = 71;
        $user->setRelation('municipality', new Municipality(['province_id' => 9]));

        return $user;
    }
}
