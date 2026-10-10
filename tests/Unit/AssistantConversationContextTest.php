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

    public function test_signed_invalid_help_metadata_is_rejected(): void
    {
        $user = $this->user();
        $contexts = new AssistantConversationContext;
        $token = $contexts->issue($user, 'synthetic-session', ['task' => 'help', 'year' => 2026]);
        $value = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        foreach ([['help_topic' => 'private-records'], ['help_topic' => ['crop-drawing']],
            ['help_topic' => 'crop-drawing', 'help_style' => 'raw-history'],
            ['help_style' => 'brief'], ['task' => 'farmer', 'help_topic' => 'crop-drawing']] as $invalid) {
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

    private function user(): User
    {
        $user = new User(['role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => 3, 'is_active' => true, 'visitor_mode' => false]);
        $user->id = 71;
        $user->setRelation('municipality', new Municipality(['province_id' => 9]));

        return $user;
    }
}
