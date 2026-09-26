<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

/**
 * Everyone in a recipe's household can read and change it (Recipe::scopeAccessibleTo()). A
 * household that saved someone else's published recipe can read it, never change it — making
 * their own version is how they get one they can.
 */
class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipe $recipe): bool
    {
        return $recipe->isEditableBy($user) || $recipe->isSavedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $recipe->isEditableBy($user);
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $recipe->isEditableBy($user);
    }

    /** Per-record `delete` is checked by the bulk action; this only gates showing it. */
    public function deleteAny(User $user): bool
    {
        return true;
    }
}
