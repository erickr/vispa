<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

/**
 * Everyone may add ingredients; what they add is theirs alone. Shared ingredients belong to
 * the catalog admin.
 */
class IngredientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->isShared() || $this->owns($user, $ingredient);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->isShared() ? $user->isCatalogAdmin() : $this->owns($user, $ingredient);
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

    private function owns(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->owner_user_id === $user->getKey();
    }
}
