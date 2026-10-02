<?php

namespace App\Actions\Meals;

use App\Models\PlannedMeal;
use App\Models\RecipeRating;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Takes a dish off the plan, with an optional 1–5 rating of how it turned out. The rating is
 * optional because a dish may come off the plan without having been cooked. The recipe itself is
 * never touched.
 */
class RemovePlannedMeal
{
    /**
     * @throws AuthorizationException when the meal is not on one of the user's households' plans
     * @throws InvalidArgumentException when the rating is outside 1–5
     */
    public function handle(User $user, PlannedMeal $meal, ?int $rating = null): ?RecipeRating
    {
        if (! $meal->isManageableBy($user)) {
            throw new AuthorizationException;
        }

        if ($rating !== null && ($rating < RecipeRating::MIN || $rating > RecipeRating::MAX)) {
            throw new InvalidArgumentException("A rating is between 1 and 5, got {$rating}.");
        }

        return DB::transaction(function () use ($user, $meal, $rating): ?RecipeRating {
            $recorded = $rating === null ? null : RecipeRating::create([
                'recipe_id' => $meal->recipe_id,
                'recipe_revision_id' => $meal->recipe?->revisionFor($user)?->getKey(),
                'household_id' => $meal->household_id,
                'rated_by_user_id' => $user->getKey(),
                'rating' => $rating,
            ]);

            $meal->delete();

            return $recorded;
        });
    }
}
