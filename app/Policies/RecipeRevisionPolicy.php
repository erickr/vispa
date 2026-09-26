<?php

namespace App\Policies;

use App\Models\RecipeRevision;
use App\Models\User;

/**
 * A revision goes with its recipe (see RecipePolicy), except that a household which only saved
 * the recipe reads its published revisions and nothing else: the drafts are the owners' work
 * in progress.
 */
class RecipeRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RecipeRevision $revision): bool
    {
        return $revision->recipe->isEditableBy($user)
            || ($revision->status === 'published' && $revision->recipe->isSavedBy($user));
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RecipeRevision $revision): bool
    {
        return $revision->recipe->isEditableBy($user);
    }

    public function delete(User $user, RecipeRevision $revision): bool
    {
        return $revision->recipe->isEditableBy($user);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
