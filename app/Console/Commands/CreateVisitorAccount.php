<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class CreateVisitorAccount extends Command
{
    protected $signature = 'visitor:create
        {email : Sign-in email for the visitor}
        {--name=System Visitor : Display name}
        {--password= : Initial password; omit to enter it securely at the prompt}';

    protected $description = 'Create an independent read-only visitor account without a geographic assignment';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $password = (string) ($this->option('password') ?: $this->secret('Initial password'));

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:72', Password::uncompromised()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::query()->where('visitor_mode', true)->where('is_active', true)->exists()) {
            throw new RuntimeException('An active visitor account already exists. Disable it before creating another.');
        }

        $account = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'visitor_mode' => true,
            'region_id' => null,
            'province_id' => null,
            'municipality_id' => null,
            'is_active' => true,
        ]);
        $account->forceFill(['visitor_mode' => true])->save();

        $this->info('Visitor account created. Share its credentials through a private channel.');

        return self::SUCCESS;
    }
}
