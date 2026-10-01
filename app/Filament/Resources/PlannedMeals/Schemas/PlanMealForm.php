<?php

namespace App\Filament\Resources\PlannedMeals\Schemas;

use App\Models\Recipe;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

/**
 * The "Plan a meal" modal: pick one of the household's recipes, or type the name of a dish that
 * has no recipe yet — it becomes one holding only that title.
 */
class PlanMealForm
{
    public const EXISTING = 'existing';

    public const NEW_DISH = 'new';

    /**
     * @return array<int, Component|Field>
     */
    public static function components(): array
    {
        return [
            ToggleButtons::make('source')
                ->label(__('planned_meal.fields.source'))
                ->options([
                    self::EXISTING => __('planned_meal.fields.source_existing'),
                    self::NEW_DISH => __('planned_meal.fields.source_new'),
                ])
                ->icons([
                    self::EXISTING => 'heroicon-o-book-open',
                    self::NEW_DISH => 'heroicon-o-pencil-square',
                ])
                ->default(self::EXISTING)
                ->inline()
                ->grouped()
                ->required()
                ->live(),

            Select::make('recipe_id')
                ->label(__('planned_meal.fields.recipe'))
                ->options(fn (): array => self::recipeOptions())
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => $get('source') !== self::NEW_DISH)
                ->required(fn (Get $get): bool => $get('source') !== self::NEW_DISH),

            TextInput::make('name')
                ->label(__('planned_meal.fields.name'))
                ->placeholder(__('planned_meal.fields.name_placeholder'))
                ->helperText(__('planned_meal.fields.name_helper'))
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('source') === self::NEW_DISH)
                ->required(fn (Get $get): bool => $get('source') === self::NEW_DISH),

            DatePicker::make('planned_for')
                ->label(__('planned_meal.fields.planned_for'))
                ->helperText(__('planned_meal.fields.planned_for_helper'))
                ->native(false)
                ->firstDayOfWeek(1),
        ];
    }

    /**
     * Everything the user could cook from — their households' recipes and the ones they saved,
     * a saved recipe they made their own version of shown as that version only — by title.
     *
     * @return array<int, string>
     */
    public static function recipeOptions(): array
    {
        $user = Auth::user();

        return Recipe::query()
            ->viewableBy($user)
            ->withoutForkedSaves($user)
            ->with('revisions')
            ->get()
            ->mapWithKeys(fn (Recipe $recipe): array => [
                $recipe->getKey() => $recipe->revisionFor($user)?->title ?? __('recipe.table.untitled'),
            ])
            ->sort(fn (string $a, string $b): int => strcasecmp($a, $b))
            ->all();
    }
}
