<?php

namespace App\Actions\Recipes;

use App\Models\Household;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Keeps another household's published recipe in the user's current household, to read and cook
 * from. It is saved, not copied: the owners keep it, and it changes when they publish again.
 * Safe to call twice.
 */
class SaveRecipeToHousehold
{
    /**
     * @return Household|null the household it was saved to; null when it is already the user's
     *                        own recipe, which needs no saving
     *
     * @throws AuthorizationException when there is nothing published to save
     */
    public function handle(Recipe $recipe, User $user): ?Household
    {
        if ($recipe->isEditableBy($user)) {
            return null;
        }

        if (! self::canBeSaved($recipe)) {
            throw new AuthorizationException;
        }

        $household = $user->currentTeam ?? $user->personalTeam();

        if ($household === null) {
            throw new AuthorizationException;
        }

        if (! $household->savedRecipes()->whereKey($recipe->getKey())->exists()) {
            $household->savedRecipes()->attach($recipe->getKey(), ['saved_by_user_id' => $user->getKey()]);
        }

        return $household;
    }

    /**
     * Only what its owners have published and shared: a private recipe has nothing to show, and
     * a draft is their work in progress.
     */
    public static function canBeSaved(Recipe $recipe): bool
    {
        return $recipe->isShareable()
            && $recipe->revisions()->where('status', 'published')->exists();
    }
}
