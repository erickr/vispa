<?php

namespace App\Filament\Resources\RecipeRevisions;

use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionForm;
use App\Models\RecipeRevision;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RecipeRevisionResource extends Resource
{
    protected static ?string $model = RecipeRevision::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('revision.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('revision.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return RecipeRevisionForm::configure($schema);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Revisions of any recipe in the user's households, not only their own — plus the
        // published revisions of recipes they saved, which RecipeRevisionPolicy keeps read-only.
        return parent::getEloquentQuery()->where(fn (Builder $query) => $query
            ->whereHas('recipe', fn (Builder $recipes) => $recipes->accessibleTo(Auth::user()))
            ->orWhere(fn (Builder $query) => $query
                ->where('status', 'published')
                ->whereHas('recipe', fn (Builder $recipes) => $recipes->savedBy(Auth::user()))));
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewRecipeRevision::route('/{record}'),
            'edit' => EditRecipeRevision::route('/{record}/edit'),
        ];
    }
}
