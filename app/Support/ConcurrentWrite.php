<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConcurrentWrite
{
    /**
     * Create a non-reversible version token from the current persisted state.
     * Hashing all attributes also detects two updates in the same DB timestamp
     * second on legacy tables without fractional timestamp precision.
     */
    public static function version(Model $record): string
    {
        if (! $record->exists || $record->getKey() === null) {
            return '';
        }

        $attributes = $record->getAttributes();
        ksort($attributes);

        $payload = json_encode([
            'model' => get_class($record),
            'key' => (string) $record->getKey(),
            'attributes' => $attributes,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);

        return hash_hmac(
            'sha256',
            is_string($payload) ? $payload : serialize([
                get_class($record),
                (string) $record->getKey(),
                $attributes,
            ]),
            (string) config('app.key')
        );
    }

    /**
     * Lock, freshness-check, and mutate one record in a retried transaction.
     *
     * @template TReturn
     *
     * @param  Closure(Model): TReturn  $callback
     * @return TReturn
     */
    public function execute(
        Model $record,
        ?string $expectedVersion,
        Closure $callback
    ) {
        $this->validateToken($expectedVersion);

        return $record->getConnection()->transaction(
            function () use ($record, $expectedVersion, $callback) {
                $current = $record->newModelQuery()
                    ->lockForUpdate()
                    ->findOrFail($record->getKey());

                if (! hash_equals(
                    self::version($current),
                    (string) $expectedVersion
                )) {
                    throw ValidationException::withMessages([
                        '_record_version' => 'This record was changed by another user after you opened it. Review the latest values, then submit your changes again.',
                    ]);
                }

                return $callback($current);
            },
            max(1, (int) config('concurrency.transaction_attempts', 3))
        );
    }

    /**
     * Lock and mutate a current record when no browser edit version exists,
     * such as an immediate delete action from an index table.
     *
     * @template TReturn
     *
     * @param  Closure(Model): TReturn  $callback
     * @return TReturn
     */
    public function locked(Model $record, Closure $callback)
    {
        return $record->getConnection()->transaction(
            function () use ($record, $callback) {
                $current = $record->newModelQuery()
                    ->lockForUpdate()
                    ->findOrFail($record->getKey());

                return $callback($current);
            },
            max(1, (int) config('concurrency.transaction_attempts', 3))
        );
    }

    /**
     * Run a multi-record write in a transaction with deadlock retries.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback)
    {
        return DB::transaction(
            $callback,
            max(1, (int) config('concurrency.transaction_attempts', 3))
        );
    }

    private function validateToken(?string $token): void
    {
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw ValidationException::withMessages([
                '_record_version' => 'The edit session is missing or expired. Reload the page before saving.',
            ]);
        }
    }
}
