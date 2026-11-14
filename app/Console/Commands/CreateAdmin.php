<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Studio\Auth\RegisterController;
use App\Models\User;
use App\Studio\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the single Content Admin account. This is the ONLY way an admin
 * can be created: registration always produces Content Creators.
 */
class CreateAdmin extends Command
{
    protected $signature = 'studio:create-admin
        {--name= : Full name}
        {--email= : Email address used to sign in}
        {--password= : Password (omit to be asked without it being shown)}';

    protected $description = 'Create the Content Admin account for Content Studio (only one can exist)';

    public function handle(): int
    {
        if ($existing = User::admin()) {
            $this->error("A Content Admin already exists ({$existing->email}). Only one is allowed.");
            $this->line('To change its password, use "Forgot your password?" on the sign-in page.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Full name');
        $email = strtolower((string) ($this->option('email') ?: $this->ask('Email address')));
        $password = $this->option('password') ?: $this->secret('Password (at least 10 characters, with upper and lower case letters and a number)');

        if (! $this->option('password') && $password !== $this->secret('Repeat the password')) {
            $this->error('The two passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', RegisterController::passwordRule()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $admin = new User(compact('name', 'email', 'password'));
        $admin->role = UserRole::Admin;
        $admin->status = UserStatus::Active;
        $admin->email_verified_at = now();
        $admin->save();

        Audit::log('user.admin_created', 'Content Admin account created from the command line', $admin, actor: $admin);

        $this->info('Content Admin created. Sign in at '.route('studio.login')." with $email.");

        return self::SUCCESS;
    }
}
