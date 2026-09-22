<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Actions\ShareRecipeAction;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ShareRecipeAction::make(),
            DeleteAction::make(),
        ];
    }
}
