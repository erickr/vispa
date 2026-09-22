<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Support\PublicLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A recipe as a guest sees it: no panel, no account, just the recipe. Reached by its uuid, which
 * is what makes an unlisted link worth handing out — it is unguessable, and nothing lists it.
 */
class SharedRecipeController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe): View
    {
        // A private recipe has no public page. It is a 404 rather than a 403 so the link gives
        // away nothing about whether the recipe exists.
        if (! $recipe->isShareable()) {
            throw new NotFoundHttpException;
        }

        $locales = $recipe->sharedLocales();

        if ($locales === []) {
            throw new NotFoundHttpException;
        }

        // The page is read in whichever language it was actually written in, so ingredient
        // lines and the revision's own prose agree with the wording around them.
        $locale = PublicLocale::resolve($request, $locales);
        $revision = $recipe->sharedRevision($locale);

        App::setLocale($revision->locale);

        $revision->load([
            'ingredientGroups',
            'ingredients.ingredient.translations',
            'ingredients.unit',
            'instructionSections',
            'instructionSteps',
            'images',
        ]);

        return view('recipes.share', [
            'recipe' => $recipe,
            'revision' => $revision,
            'languages' => PublicLocale::links('recipes.share', ['recipe' => $recipe->uuid], $locales, $revision->locale),
        ]);
    }
}
