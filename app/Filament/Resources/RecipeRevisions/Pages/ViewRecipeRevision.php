<?php

namespace App\Filament\Resources\RecipeRevisions\Pages;

use App\Filament\Actions\ShareRecipeAction;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionInfolist;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

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
            ShareRecipeAction::make(),
            Action::make('edit')
                ->label(__('revision.actions.edit_content'))
                ->url(fn (): string => RecipeRevisionResource::getUrl('edit', ['record' => $this->record])),
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
