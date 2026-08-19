<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\FarmPlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditTrail
{
    private static bool $recording = false;

    private static bool $tableAvailable = false;

    /**
     * Store an audit event without ever interrupting the user's main action.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(
        string $event,
        string $module,
        string $description,
        array $context = []
    ): ?AuditLog {
        if (self::$recording) {
            return null;
        }

        try {
            if (! self::$tableAvailable) {
                if (! Schema::hasTable('audit_logs')) {
                    return null;
                }

                self::$tableAvailable = true;
            }

            self::$recording = true;

            $actor = $context['actor'] ?? Auth::user();
            $auditable = $context['auditable'] ?? null;
            $request = app()->bound('request') ? app(Request::class) : null;
            $metadata = self::cleanValues($context['metadata'] ?? null) ?? [];

            if ($request?->route()?->getName()) {
                $metadata['route_name'] = $request->route()->getName();
            }

            return AuditLog::query()->create([
                'user_id' => $actor instanceof User ? $actor->getKey() : null,
                'municipality_id' => self::municipalityId(
                    $context['municipality_id'] ?? null,
                    $actor,
                    $auditable
                ),
                'actor_name' => $context['actor_name']
                    ?? ($actor instanceof User ? $actor->name : null),
                'actor_email' => $context['actor_email']
                    ?? ($actor instanceof User ? $actor->email : null),
                'actor_role' => $context['actor_role']
                    ?? ($actor instanceof User ? $actor->role : null),
                'event' => $event,
                'module' => $module,
                'auditable_type' => $auditable instanceof Model
                    ? $auditable->getMorphClass()
                    : ($context['auditable_type'] ?? null),
                'auditable_id' => $auditable instanceof Model
                    ? (string) $auditable->getKey()
                    : ($context['auditable_id'] ?? null),
                'description' => $description,
                'old_values' => self::cleanValues($context['old_values'] ?? null),
                'new_values' => self::cleanValues($context['new_values'] ?? null),
                'metadata' => $metadata ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request
                    ? mb_substr((string) $request->userAgent(), 0, 500)
                    : null,
                'request_method' => $request?->method(),
                'request_url' => $request?->fullUrl(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        } finally {
            self::$recording = false;
        }
    }

    /**
     * Remove secrets and normalize values before they reach persistent logs.
     *
     * @param  mixed  $values
     * @return array<string, mixed>|null
     */
    public static function cleanValues($values): ?array
    {
        if (! is_array($values) || $values === []) {
            return null;
        }

        $blocked = [
            'password',
            'password_confirmation',
            'remember_token',
            'public_map_token',
            'profile_photo_path',
            'last_login_at',
            'created_at',
            'updated_at',
        ];

        $clean = [];

        foreach ($values as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);
            if (
                in_array($normalizedKey, $blocked, true)
                || str_contains($normalizedKey, 'password')
                || str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'secret')
            ) {
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $value = self::cleanNestedArray($value);
            }

            $clean[$key] = $value;
        }

        return $clean ?: null;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function cleanNestedArray(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if (
                str_contains($normalizedKey, 'password')
                || str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'secret')
            ) {
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                $value = $value->toArray();
            }

            $clean[$key] = is_array($value)
                ? self::cleanNestedArray($value)
                : $value;
        }

        return $clean;
    }

    /**
     * @param  mixed  $explicitId
     * @param  mixed  $actor
     * @param  mixed  $auditable
     */
    private static function municipalityId($explicitId, $actor, $auditable): ?int
    {
        if (filled($explicitId)) {
            return (int) $explicitId;
        }

        if ($auditable instanceof FarmPlot) {
            return $auditable->farmer?->municipality_id
                ? (int) $auditable->farmer->municipality_id
                : null;
        }

        if ($auditable instanceof Model && filled($auditable->municipality_id)) {
            return (int) $auditable->municipality_id;
        }

        if ($actor instanceof User && filled($actor->municipality_id)) {
            return (int) $actor->municipality_id;
        }

        return null;
    }
}
