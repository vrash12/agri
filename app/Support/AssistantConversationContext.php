<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/** Encrypted references are hints. Every record is independently authorized. */
final class AssistantConversationContext
{
    private const TASKS = ['farmers', 'assistance', 'harvests', 'animal-health', 'summary', 'farmer-search', 'farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests', 'help'];

    /** @param array{task?:string,year?:int,farmer_id?:int|null,choices?:list<int>} $references */
    public function issue(User $user, string $sessionId, array $references, string $type = 'context'): string
    {
        $now = now('UTC')->timestamp;

        return Crypt::encryptString(json_encode([
            'version' => 1, 'type' => $type, 'user' => (int) $user->id,
            'session' => hash('sha256', $sessionId), 'scope' => $this->scope($user),
            'issued' => $now, 'expires' => $now + 900,
            'task' => $references['task'] ?? 'help', 'year' => $references['year'] ?? LocalTime::now()->year,
            'farmer_id' => $references['farmer_id'] ?? null,
            'choices' => array_slice($references['choices'] ?? [], 0, 6),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{task:string,year:int,farmer_id:int|null,choices:list<int>}|null */
    public function read(User $user, string $sessionId, string $token, string $type = 'context'): ?array
    {
        if ($sessionId === '' || strlen($token) > 4096) {
            return null;
        }
        try {
            $value = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            $now = now('UTC')->timestamp;
            if (! is_array($value) || ($value['version'] ?? null) !== 1 || ($value['type'] ?? null) !== $type
                || ($value['user'] ?? null) !== (int) $user->id
                || ! is_string($value['session'] ?? null) || ! hash_equals(hash('sha256', $sessionId), $value['session'])
                || ! is_string($value['scope'] ?? null) || ! hash_equals($this->scope($user), $value['scope'])
                || ! is_int($value['issued'] ?? null) || ! is_int($value['expires'] ?? null)
                || $value['issued'] > $now || $value['expires'] <= $now || $value['expires'] - $value['issued'] !== 900
                || ! in_array($value['task'] ?? null, self::TASKS, true)
                || ! is_int($value['year'] ?? null) || $value['year'] < 1900 || $value['year'] > 2100
                || (! is_null($value['farmer_id'] ?? null) && (! is_int($value['farmer_id']) || $value['farmer_id'] < 1))
                || ! is_array($value['choices'] ?? null) || ! array_is_list($value['choices']) || count($value['choices']) > 6) {
                return null;
            }
            foreach ($value['choices'] as $id) {
                if (! is_int($id) || $id < 1) {
                    return null;
                }
            }

            return array_intersect_key($value, array_flip(['task', 'year', 'farmer_id', 'choices']));
        } catch (Throwable) {
            // Never log tokens or decryption failures: an invalid hint is reset.
            return null;
        }
    }

    private function scope(User $user): string
    {
        return hash('sha256', json_encode([
            $user->role, $user->region_id, $user->province_id, $user->municipality_id,
            $user->municipality?->province_id, (bool) $user->is_active, (bool) $user->visitor_mode,
        ], JSON_THROW_ON_ERROR));
    }
}
