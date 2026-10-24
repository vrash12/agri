<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BootstrapSystemOwner extends Command
{
    protected $signature = 'system:bootstrap-owner {--name= : First owner name} {--email= : First owner email}';

    protected $description = 'Interactively create the first System Owner on a new, migrated installation only';

    public function handle(ConcurrentWrite $writes): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Use an interactive terminal. Passwords are never accepted as command arguments or defaults.');

            return self::FAILURE;
        }
        if (! config('app.key') || ! Schema::hasTable('application_installation_baselines')
            || ! Schema::hasTable('audit_logs') || ! Schema::hasTable('municipality_websites')
            || ! Schema::hasTable('parcel_field_assessments') || ! Schema::hasColumn('users', 'visitor_mode')
            || ! Schema::hasColumn('users', 'region_id')) {
            $this->error('Run the complete fresh installation and generate its application key first. This command cannot recover an existing installation.');

            return self::FAILURE;
        }
        if (DB::table('application_installation_baselines')->value('name') !== 'legacy_application_v1') {
            $this->error('Unknown installation baseline. Bootstrap refused.');

            return self::FAILURE;
        }
        if (User::query()->exists()) {
            $this->error('An office account already exists. Use the existing account-management or recovery process.');

            return self::FAILURE;
        }
        $name = trim((string) ($this->option('name') ?: $this->ask('Owner name')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Owner email'))));
        $password = (string) $this->secret('Password (15–72 characters; at most 72 bytes)');
        $confirmation = (string) $this->secret('Confirm password');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:15', 'max:72', 'confirmed'],
        ]);
        if ($validator->fails() || strlen($password) > 72) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            if (strlen($password) > 72) {
                $this->error('The password must not exceed 72 bytes.');
            }

            return self::FAILURE;
        }
        $hash = Hash::make($password);
        unset($password, $confirmation);
        $lock = Cache::lock('installation-owner:'.hash('sha256', DB::connection()->getDatabaseName()), 30);
        try {
            $lock->block(5, function () use ($writes, $name, $email, $hash): void {
                $writes->transaction(function () use ($name, $email, $hash): void {
                    if (User::query()->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['owner' => 'Another office account was created. Bootstrap refused.']);
                    }
                    $owner = new User;
                    $owner->forceFill(['name' => $name, 'email' => $email, 'password' => $hash,
                        'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true, 'municipality_id' => null,
                        'province_id' => null, 'region_id' => null, 'visitor_mode' => false])->saveOrFail();
                    if (! AuditTrail::record('installation_owner_created', 'Installation', 'Created the first System Owner through interactive fresh-installation bootstrap.', [
                        'actor' => $owner, 'auditable' => $owner, 'owner_only' => true,
                        'metadata' => ['source' => 'interactive_cli_bootstrap'],
                    ])) {
                        throw new RuntimeException('The required installation audit could not be saved. No owner account was committed.');
                    }
                });
            });
        } catch (LockTimeoutException) {
            $this->error('Another bootstrap is running. Retry after it completes.');

            return self::FAILURE;
        }
        $this->info('The first System Owner was created. Sign in using the credentials you entered; configure authorized geography and accounts explicitly.');

        return self::SUCCESS;
    }
}
