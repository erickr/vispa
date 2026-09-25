<?php

namespace App\Actions\Jetstream;

use App\Models\Household;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Events\TeamMemberRemoved;

class RemoveTeamMember implements RemovesTeamMembers
{
    /**
     * Remove the household member from the given household.
     */
    public function remove(User $user, Household $household, User $member): void
    {
        $this->authorize($user, $household, $member);

        $this->ensureUserDoesNotOwnTeam($member, $household);

        $household->removeUser($member);

        TeamMemberRemoved::dispatch($household, $member);
    }

    /**
     * Authorize that the user can remove the household member.
     */
    protected function authorize(User $user, Household $household, User $member): void
    {
        if (! Gate::forUser($user)->check('removeTeamMember', $household) &&
            $user->id !== $member->id) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the currently authenticated user does not own the household.
     */
    protected function ensureUserDoesNotOwnTeam(User $member, Household $household): void
    {
        if ($member->id === $household->owner->id) {
            throw ValidationException::withMessages([
                'household' => [__('household.validation.owner_cannot_leave')],
            ])->errorBag('removeTeamMember');
        }
    }
}
