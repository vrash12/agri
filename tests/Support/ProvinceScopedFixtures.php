<?php

namespace Tests\Support;

use App\Models\Province;

/**
 * Province assignment for fixtures in database-backed suites.
 *
 * Since province supervision was added, `User::hasUsableScope()` refuses any
 * provincial account without an active `province_id`, and refuses a municipal
 * account whose municipality is not supervised by an active province. Fixtures
 * that predate that change create accounts which can no longer sign in, so every
 * municipality and provincial account in a test needs a province behind it.
 *
 * `firstOrCreate` reuses a province the target database already has and creates
 * one otherwise, so these suites work against both a populated working database
 * and an empty one. Suites using `DatabaseTransactions` roll any creation back.
 */
trait ProvinceScopedFixtures
{
    private function supervisingProvince(string $name = 'Tarlac'): Province
    {
        return Province::query()->firstOrCreate(
            ['name' => $name],
            ['is_active' => true]
        );
    }

    private function supervisingProvinceId(string $name = 'Tarlac'): int
    {
        return (int) $this->supervisingProvince($name)->id;
    }
}
