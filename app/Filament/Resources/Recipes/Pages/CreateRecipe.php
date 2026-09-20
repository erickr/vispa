<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Support\SupportedLocales;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_user_id'] = Auth::id();

        return $data;
    }

    /**
     * A recipe's content lives on a revision, so a new recipe needs a first draft revision to edit.
     * If the user didn't add one on the Revisions tab, seed a draft in the recipe's default locale.
     */
    protected function afterCreate(): void
    {
        if (! $this->record->revisions()->exists()) {
            $locale = $this->record->default_locale ?: SupportedLocales::preferred();

            $this->record->revisions()->create([
                'locale' => $locale,
                'version_number' => 1,
                'status' => 'draft',
                // Content, not chrome: written in the revision's locale rather than the
                // reader's, so a Swedish revision starts with a Swedish placeholder title.
                'title' => __('recipe.untitled', locale: $locale),
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }

    /**
     * Land in the full content editor for the recipe's newest revision rather than the bare
     * recipe metadata page.
     */
    protected function getRedirectUrl(): string
    {
        $revision = $this->record->revisions()->orderByDesc('version_number')->first();

        return $revision
            ? RecipeRevisionResource::getUrl('edit', ['record' => $revision])
            : RecipeResource::getUrl('edit', ['record' => $this->record]);
    }
}
