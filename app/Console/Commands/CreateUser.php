<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('users:create {email} {name} {--role=admin : admin, analyst or viewer}')]
#[Description('Create a user (public registration is disabled)')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->option('role'));
        $password = (string) $this->secret('Password');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password, 'role' => $role],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::defaults()], 'role' => ['required']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
            'role' => $role,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Created {$role->value} {$user->email}. They must set up two-factor authentication at first login.");

        return self::SUCCESS;
    }
}
