<?php

namespace App\Filament\Resources\PlannedMeals;

use App\Filament\Resources\PlannedMeals\Pages\ListPlannedMeals;
use App\Filament\Resources\PlannedMeals\Tables\PlannedMealsTable;
use App\Models\PlannedMeal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The current household's planned meals. There is no create or edit page: a dish is planned
 * from the list's "Plan a meal" modal (Schemas\PlanMealForm), and the row opens its recipe.
 */
class PlannedMealResource extends Resource
{
    protected static ?string $model = PlannedMeal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'planned-meals';

    public static function getModelLabel(): string
    {
        return __('planned_meal.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('planned_meal.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('planned_meal.menu');
    }

    public static function table(Table $table): Table
    {
        return PlannedMealsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forCurrentHouseholdOf(Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlannedMeals::route('/'),
        ];
    }
}
