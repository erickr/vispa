<?php

namespace App\Actions\Jetstream;

use App\Models\Household;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class AddTeamMember implements AddsTeamMembers
{
    /**
     * Add a new household member to the given household.
     */
    public function add(User $user, Household $household, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $household);

        $this->validate($household, $email, $role);

        $newMember = Jetstream::findUserByEmailOrFail($email);

        AddingTeamMember::dispatch($household, $newMember);

        $household->users()->attach(
            $newMember, ['role' => $role]
        );

        TeamMemberAdded::dispatch($household, $newMember);
    }

    /**
     * Validate the add member operation.
     */
    protected function validate(Household $household, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules(), [
            'email.exists' => __('household.validation.no_such_user'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($household, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for adding a household member.
     *
     * @return array<string, Rule|array|string>
     */
    protected function rules(): array
    {
        return array_filter([
            'email' => ['required', 'email', 'exists:users'],
            'role' => Jetstream::hasRoles()
                            ? ['required', 'string', new Role]
                            : null,
        ]);
    }

    /**
     * Ensure that the user is not already on the household.
     */
    protected function ensureUserIsNotAlreadyOnTeam(Household $household, string $email): Closure
    {
        return function ($validator) use ($household, $email) {
            $validator->errors()->addIf(
                $household->hasUserWithEmail($email),
                'email',
                __('household.validation.already_member')
            );
        };
    }
}
