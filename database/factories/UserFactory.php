<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\User\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Domain\User\Models\User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has completed app-based multi-factor
     * authentication setup.
     *
     * The panel enables MFA with isRequired: true (see AppPanelProvider), so
     * a user WITHOUT a secret is redirected to the set-up page rather than
     * reaching the panel. Any request expecting to land on a panel page needs
     * this state.
     */
    public function withAppAuthentication(): static
    {
        return $this->state(fn (array $attributes) => [
            // Filament only checks the secret is filled; a valid base32 TOTP
            // secret is used here so the value is realistic.
            'app_authentication_secret' => 'ABCDEFGHIJKLMNOP',
        ]);
    }
}
