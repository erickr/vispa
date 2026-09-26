<?php

namespace App\Http\Controllers;

use App\Actions\Recipes\SaveRecipeToHousehold;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\Recipe;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The "Save to my recipes" button on a shared recipe's page. Keeps the recipe in the visitor's
 * household and opens it in the panel — read-only there, with a way to make their own version.
 */
class SaveSharedRecipe extends Controller
{
    public function __invoke(Request $request, Recipe $recipe, SaveRecipeToHousehold $save): RedirectResponse
    {
        // Private or unpublished: the same 404 the page itself gives, rather than a 403 that
        // would confirm the recipe exists.
        abort_unless(SaveRecipeToHousehold::canBeSaved($recipe), 404);

        $user = $request->user();
        $household = $save->handle($recipe, $user);

        if ($household !== null) {
            Notification::make()
                ->title(__('recipe.saved.saved.title'))
                ->body(__('recipe.saved.saved.body', ['household' => $household->name]))
                ->success()
                ->send();
        }

        return redirect(RecipeRevisionResource::getUrl('view', [
            'record' => $recipe->revisionFor($user),
        ], panel: 'app'));
    }
}
