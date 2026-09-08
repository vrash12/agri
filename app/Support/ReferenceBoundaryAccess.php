<?php

namespace App\Support;

use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class ReferenceBoundaryAccess
{
    public static function actor(string $province): User
    {
        $actor = User::query()->active()->where(function (Builder $query) use ($province): void {
            $query->where('role', User::ROLE_SYSTEM_OWNER)
                ->orWhere(fn (Builder $query) => $query->where('role', User::ROLE_SUPER_ADMIN)
                    ->whereHas('province', fn (Builder $query) => $query->active()->where('name', $province)));
        })->orderByRaw('CASE WHEN role = ? THEN 0 ELSE 1 END', [User::ROLE_SYSTEM_OWNER])
            ->orderBy('id')->lockForUpdate()->first();

        if (! $actor) {
            throw new RuntimeException('An active System Owner or assigned Super Admin account is required for this province import.');
        }

        return $actor;
    }

    public static function provinceId(string $name): int
    {
        $province = Province::query()->where('name', $name)->lockForUpdate()->first();
        if (! $province) {
            if (! self::actor($name)->isSystemOwner()) {
                throw new RuntimeException('The System Owner must configure this province first.');
            }
            $province = Province::query()->create(['name' => $name, 'is_active' => true]);
        }
        if (! $province->is_active) {
            throw new RuntimeException('This supervising province is inactive.');
        }

        return (int) $province->id;
    }
}
