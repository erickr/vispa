<?php

namespace App\Actions\Jetstream;

use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new household member to the given household.
     */
    public function invite(User $user, Household $household, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $household);

        $this->validate($household, $email, $role);

        InvitingTeamMember::dispatch($household, $email, $role);

        $invitation = $household->teamInvitations()->create([
            'email' => $email,
            'role' => $role,
        ]);

        Mail::to($email)->send(new HouseholdInvitationMail($invitation));
    }

    /**
     * Validate the invite member operation.
     */
    protected function validate(Household $household, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($household), [
            'email.unique' => __('household.validation.already_invited'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($household, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a household member.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function rules(Household $household): array
    {
        return array_filter([
            'email' => [
                'required', 'email',
                Rule::unique(Jetstream::teamInvitationModel())->where(function (Builder $query) use ($household) {
                    $query->where('household_id', $household->id);
                }),
            ],
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
