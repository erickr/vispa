<?php

namespace App\Filament\Resources\RecipeRevisions\Pages;

use App\Filament\Actions\ShareRecipeAction;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionInfolist;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewRecipeRevision extends ViewRecord
{
    protected static string $resource = RecipeRevisionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return RecipeRevisionInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('revision.actions.edit_content'))
                ->url(fn (): string => RecipeRevisionResource::getUrl('edit', ['record' => $this->record])),
            ShareRecipeAction::make(),
            // The recipe's own settings — visibility, source link, slugs — live a layer up.
            Action::make('settings')
                ->label(__('recipe.actions.settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(fn (): string => RecipeResource::getUrl('edit', ['record' => $this->record->recipe_id])),
        ];
    }

    public function getBreadcrumb(): string
    {
        return __('revision.breadcrumb', [
            'locale' => $this->record->locale,
            'version' => $this->record->version_number,
        ]);
    }

    /**
     * This sub-resource has no index page; anchor navigation on the parent recipe instead.
     */
    public function getBreadcrumbs(): array
    {
        return [
            RecipeResource::getUrl('edit', ['record' => $this->record->recipe_id]) => __('recipe.breadcrumb'),
            $this->getBreadcrumb(),
        ];
    }
}
