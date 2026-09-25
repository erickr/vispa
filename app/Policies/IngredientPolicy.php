<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

/**
 * Everyone may add ingredients; what they add belongs to them and their household, who can all
 * see and edit it. Shared ingredients belong to the catalog admin.
 */
class IngredientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->isShared() || $this->inHousehold($user, $ingredient);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->isShared() ? $user->isCatalogAdmin() : $this->inHousehold($user, $ingredient);
    }

    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $this->update($user, $ingredient);
    }

    /** Per-record `delete` is checked by the bulk action; this only gates showing it. */
    public function deleteAny(User $user): bool
    {
        return true;
    }

    private function inHousehold(User $user, Ingredient $ingredient): bool
    {
        return in_array($ingredient->owner_user_id, $user->householdMemberIds(), true);
    }
}
