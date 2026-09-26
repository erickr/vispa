<?php

namespace App\Actions\Recipes;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * "Make my own version" of a recipe the user can read but not change, typically one their
 * household saved. The result is a new private recipe in the household that saved it, owned by
 * them, whose first draft is a copy of $revision — and which remembers where it came from
 * (forked_from_recipe_id / forked_from_revision_id).
 */
class ForkRecipeRevision
{
    public function handle(RecipeRevision $revision, User $user): RecipeRevision
    {
        Gate::forUser($user)->authorize('view', $revision);

        return DB::transaction(function () use ($revision, $user): RecipeRevision {
            $original = $revision->recipe;

            $recipe = Recipe::create([
                'owner_user_id' => $user->getKey(),
                'household_id' => $this->household($original, $user),
                'forked_from_recipe_id' => $original->getKey(),
                'forked_from_revision_id' => $revision->getKey(),
                'default_locale' => $revision->locale,
                'visibility' => 'private',
                // Where the dish came from is still where it came from.
                'source_url' => $original->source_url,
            ]);

            $draft = $revision->forkInto($recipe, $user->getKey());

            $this->adoptIngredients($draft, $user);

            return $draft;
        });
    }

    /**
     * The household the user found it in: the one of theirs that saved it — their current one if
     * that did. Null leaves it to Recipe's creating hook (their current household).
     */
    private function household(Recipe $original, User $user): ?int
    {
        $saving = $original->savedByHouseholds()
            ->whereIn('households.id', $user->allTeams()->modelKeys())
            ->pluck('households.id')
            ->map(fn ($id): int => (int) $id);

        return $saving->contains((int) $user->current_household_id)
            ? (int) $user->current_household_id
            : $saving->first();
    }

    /**
     * The original's private ingredients belong to another household: the copy would lean on
     * them (and stop their owners deleting them), and the forker could not pick them again. Each
     * is swapped for one the forker can see with the same name, else a private copy of their own.
     */
    private function adoptIngredients(RecipeRevision $draft, User $user): void
    {
        $visible = $user->householdMemberIds();
        $replacements = [];

        foreach ($draft->ingredients()->with('ingredient.translations')->get() as $line) {
            $ingredient = $line->ingredient;

            if ($ingredient === null || $ingredient->isShared() || in_array($ingredient->owner_user_id, $visible, true)) {
                continue;
            }

            $replacements[$ingredient->getKey()] ??= $this->ownIngredient($ingredient, $user)->getKey();

            $line->update(['ingredient_id' => $replacements[$ingredient->getKey()]]);
        }
    }

    private function ownIngredient(Ingredient $theirs, User $user): Ingredient
    {
        $match = Ingredient::query()
            ->visibleTo($user)
            ->whereRaw('LOWER(canonical_name) = ?', [mb_strtolower($theirs->canonical_name)])
            // Prefer the shared catalog row over a private duplicate.
            ->orderByRaw('owner_user_id IS NOT NULL')
            ->first();

        if ($match) {
            return $match;
        }

        // Owned explicitly rather than by the creating hook, which only knows the signed-in user.
        $copy = new Ingredient(['canonical_name' => $theirs->canonical_name]);
        $copy->owner_user_id = $user->isCatalogAdmin() ? null : $user->getKey();
        $copy->save();

        foreach ($theirs->translations as $translation) {
            $copy->translations()->create(['locale' => $translation->locale, 'name' => $translation->name]);
        }

        return $copy;
    }
}
