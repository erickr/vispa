<?php

namespace App\Policies;

use App\Models\PlannedMeal;
use App\Models\User;

/**
 * A household's plan is shared: everyone in it can add to it, change a day or take a dish off.
 */
class PlannedMealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, PlannedMeal $meal): bool
    {
        return $meal->isManageableBy($user);
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, PlannedMeal $meal): bool
    {
        return $meal->isManageableBy($user);
    }

    public function delete(User $user, PlannedMeal $meal): bool
    {
        return $meal->isManageableBy($user);
    }

    /** Per-record `delete` is checked by the bulk action; this only gates showing it. */
    public function deleteAny(User $user): bool
    {
        return true;
    }
}
