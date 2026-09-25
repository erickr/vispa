<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use App\Support\SupportedLocales;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
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
            'locale' => SupportedLocales::DEFAULT,
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
     * Give the user the household they would have got on sign-up. Jetstream's own tests call it
     * by this name.
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        return $this->has(
            Household::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => Household::defaultNameFor($user->name),
                    'user_id' => $user->id,
                    'personal_household' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}
