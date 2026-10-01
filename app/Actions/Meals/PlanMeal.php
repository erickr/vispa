<?php

namespace App\Actions\Meals;

use App\Models\Household;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use App\Support\SupportedLocales;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Puts a dish on the user's current household's plan: a recipe they can open (their own or a
 * saved one), or a new dish by name, which becomes a regular private recipe holding only a
 * draft revision with that title — to be filled in, or imported into, later.
 */
class PlanMeal
{
    /**
     * @throws AuthorizationException when the recipe is not one the user can open, or they have
     *                                no household to plan for
     */
    public function recipe(User $user, Recipe $recipe, ?Carbon $plannedFor = null): PlannedMeal
    {
        if (! Recipe::query()->viewableBy($user)->whereKey($recipe->getKey())->exists()) {
            throw new AuthorizationException;
        }

        return $this->plan($user, $recipe, $plannedFor);
    }

    /**
     * @throws AuthorizationException when the user has no household to plan for
     */
    public function newDish(User $user, string $name, ?Carbon $plannedFor = null): PlannedMeal
    {
        $this->household($user);

        return DB::transaction(function () use ($user, $name, $plannedFor): PlannedMeal {
            $locale = SupportedLocales::sanitize($user->locale);

            $recipe = Recipe::create([
                'owner_user_id' => $user->getKey(),
                'default_locale' => $locale,
                'visibility' => 'private',
            ]);

            $recipe->revisions()->create([
                'locale' => $locale,
                'version_number' => 1,
                'status' => 'draft',
                'title' => trim($name),
                'created_by_user_id' => $user->getKey(),
            ]);

            return $this->plan($user, $recipe, $plannedFor);
        });
    }

    private function plan(User $user, Recipe $recipe, ?Carbon $plannedFor): PlannedMeal
    {
        return PlannedMeal::create([
            'household_id' => $this->household($user)->getKey(),
            'recipe_id' => $recipe->getKey(),
            'added_by_user_id' => $user->getKey(),
            'planned_for' => $plannedFor?->toDateString(),
        ]);
    }

    private function household(User $user): Household
    {
        return $user->currentTeam ?? throw new AuthorizationException;
    }
}
