<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * By default produces an ACTIVE, email-verified Content Creator, which is
 * what most tests want. Use the states for other situations.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password-123'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        // role and status are not mass assignable, so they are set here.
        // This runs first; the states below run after it and override it.
        return $this->afterMaking(function (User $user) {
            $user->role = UserRole::Creator;
            $user->status = UserStatus::Active;
        });
    }

    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = UserRole::Admin);
    }

    /** Registered and verified, but not yet activated by the admin. */
    public function pending(): static
    {
        return $this->afterMaking(fn (User $user) => $user->status = UserStatus::Pending);
    }

    public function suspended(): static
    {
        return $this->afterMaking(fn (User $user) => $user->status = UserStatus::Suspended);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
