<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Method names are Jetstream's: its actions authorize against them.
 */
class HouseholdPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Household $household): bool
    {
        return $user->belongsToTeam($household);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Household $household): bool
    {
        return $user->ownsTeam($household);
    }

    /**
     * Determine whether the user can add household members.
     */
    public function addTeamMember(User $user, Household $household): bool
    {
        return $user->ownsTeam($household);
    }

    /**
     * Determine whether the user can update household member permissions.
     */
    public function updateTeamMember(User $user, Household $household): bool
    {
        return $user->ownsTeam($household);
    }

    /**
     * Determine whether the user can remove household members.
     */
    public function removeTeamMember(User $user, Household $household): bool
    {
        return $user->ownsTeam($household);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Household $household): bool
    {
        return $user->ownsTeam($household);
    }
}
