<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SetupProvinceAccess extends Command
{
    protected $signature = 'province-access:setup {owner : Existing active Super Admin or System Owner ID}
        {--staff-province= : Explicit province for existing unassigned provincial staff and veterinary accounts}
        {--admin-province=* : Create an inactive Super Admin for this province when none exists}';

    protected $description = 'Explicitly initialize System Owner and province supervision without changing operational records';

    public function handle(): int
    {
        $accounts = Cache::lock('province-access:setup', 120)->block(15, fn () => DB::transaction(function (): array {
            $owner = User::query()->lockForUpdate()->findOrFail((int) $this->argument('owner'));
            if (! $owner->isActive() || ! $owner->hasAnyRole([User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_OWNER])) {
                throw new RuntimeException('Select an existing active Super Admin or System Owner.');
            }
            if (User::query()->where('role', User::ROLE_SYSTEM_OWNER)->whereKeyNot($owner->id)->exists()) {
                throw new RuntimeException('A different System Owner already exists. Manage province accounts through that owner.');
            }
            $unassigned = User::query()->whereIn('role', [User::ROLE_PROVINCIAL_STAFF, User::ROLE_PROVINCIAL_VET])
                ->whereNull('province_id')->lockForUpdate()->get();
            $staffProvince = null;
            if ($unassigned->isNotEmpty()) {
                $staffProvince = Province::query()->active()->where('name', $this->option('staff-province'))->sole();
            }
            $adminProvinces = [];
            foreach (array_unique($this->option('admin-province')) as $name) {
                $adminProvinces[] = Province::query()->active()->where('name', $name)->sole();
            }

            if (! $owner->isSystemOwner()) {
                $before = $owner->only(['role', 'province_id', 'municipality_id']);
                User::withoutEvents(fn () => $owner->forceFill([
                    'role' => User::ROLE_SYSTEM_OWNER, 'province_id' => null, 'municipality_id' => null,
                ])->save());
                AuditTrail::record('updated', 'User management', 'System Owner access was initialized.', [
                    'actor' => $owner, 'auditable' => $owner, 'old_values' => $before,
                    'new_values' => $owner->only(['role', 'province_id', 'municipality_id']),
                    'metadata' => ['setup_command' => true],
                ]);
            }
            foreach ($unassigned as $account) {
                User::withoutEvents(fn () => $account->forceFill(['province_id' => $staffProvince->id, 'municipality_id' => null])->save());
                AuditTrail::record('updated', 'User management', 'The provincial account received its province assignment.', [
                    'actor' => $owner, 'auditable' => $account,
                    'old_values' => ['province_id' => null], 'new_values' => ['province_id' => $staffProvince->id],
                    'metadata' => ['setup_command' => true],
                ]);
            }
            $created = [];
            foreach ($adminProvinces as $province) {
                if (User::query()->where('role', User::ROLE_SUPER_ADMIN)->where('province_id', $province->id)->exists()) {
                    continue;
                }
                // Placeholder sign-in address; no email is sent. Owner chooses a password before activation.
                $email = 'superadmin.'.Str::slug($province->name).'@agri-ms.test';
                if (User::query()->where('email', $email)->exists()) {
                    throw new RuntimeException('The proposed province administrator address is already in use. Resolve the account identity first.');
                }
                $account = User::withoutEvents(fn () => User::query()->create([
                    'name' => $province->name.' Super Administrator', 'email' => $email,
                    'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id,
                    'municipality_id' => null, 'is_active' => false,
                    'password' => Hash::make(Str::random(64)),
                ]));
                AuditTrail::record('created', 'User management', 'An inactive province Super Admin account was prepared.', [
                    'actor' => $owner, 'auditable' => $account,
                    'new_values' => $account->only(['name', 'role', 'province_id', 'is_active']),
                    'metadata' => ['setup_command' => true, 'password_required_before_activation' => true],
                ]);
                $created[] = [$province->name, $account->email, 'Inactive — set a password and activate in User Management'];
            }

            return $created;
        }, 3));
        $this->info('System Owner and province assignments are ready. Existing sign-in passwords and operational records were preserved.');
        if ($accounts !== []) {
            $this->table(['Province', 'Sign-in email', 'Next step'], $accounts);
        }

        return self::SUCCESS;
    }
}
