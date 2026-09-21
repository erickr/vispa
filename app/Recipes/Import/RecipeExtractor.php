<?php

namespace App\Recipes\Import;

interface RecipeExtractor
{
    /**
     * @param  array<int, string>  $unitCodes  the catalog's unit codes; the extractor maps onto these or leaves the unit empty
     *
     * @throws RecipeImportException when no recipe could be extracted
     */
    public function extract(RecipeSource $source, array $unitCodes): ExtractedRecipe;
}
