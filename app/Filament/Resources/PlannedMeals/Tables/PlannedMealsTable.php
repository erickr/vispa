<?php

namespace App\Filament\Resources\PlannedMeals\Tables;

use App\Actions\Meals\RemovePlannedMeal;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\PlannedMeal;
use App\Models\RecipeRating;
use App\Models\RecipeRevision;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PlannedMealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'recipe.revisions' => fn ($q) => $q->withCount(['ingredients', 'instructionSteps']),
                'addedBy',
            ]))
            ->columns([
                TextColumn::make('planned_for')
                    ->label(__('planned_meal.fields.planned_for'))
                    ->date('D j M')
                    ->placeholder(__('planned_meal.table.no_day'))
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('planned_meal.table.dish'))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->state(fn (PlannedMeal $record): string => self::revision($record)?->title ?? __('recipe.table.untitled'))
                    ->description(fn (PlannedMeal $record): ?string => self::isJustAName($record)
                        ? __('planned_meal.table.just_a_name')
                        : null),

                TextColumn::make('addedBy.name')
                    ->label(__('planned_meal.table.added_by'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            // Dated meals in day order, then the undated ones in the order they were added.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('planned_for is null')
                ->orderBy('planned_for')
                ->orderBy('id'))
            ->filters([
                Filter::make('upcoming')
                    ->label(__('planned_meal.table.only_upcoming'))
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                        ->whereNull('planned_for')
                        ->orWhereDate('planned_for', '>=', today()))),
            ])
            ->recordUrl(fn (PlannedMeal $record): ?string => self::recipeUrl($record))
            ->recordActions([
                Action::make('changeDay')
                    ->label(__('planned_meal.actions.change_day'))
                    ->icon(Heroicon::OutlinedCalendar)
                    ->color('gray')
                    ->authorize('update')
                    ->fillForm(fn (PlannedMeal $record): array => ['planned_for' => $record->planned_for])
                    ->schema([
                        DatePicker::make('planned_for')
                            ->label(__('planned_meal.fields.planned_for'))
                            ->native(false)
                            ->firstDayOfWeek(1),
                    ])
                    ->action(fn (PlannedMeal $record, array $data) => $record->update(['planned_for' => $data['planned_for'] ?? null])),

                Action::make('remove')
                    ->label(__('planned_meal.actions.remove'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->authorize('delete')
                    ->modalHeading(__('planned_meal.actions.remove_heading'))
                    ->modalDescription(__('planned_meal.actions.remove_description'))
                    ->modalSubmitActionLabel(__('planned_meal.actions.remove'))
                    ->schema([
                        ToggleButtons::make('rating')
                            ->label(__('planned_meal.actions.rating'))
                            ->helperText(__('planned_meal.actions.rating_helper'))
                            ->options(collect(range(RecipeRating::MIN, RecipeRating::MAX))
                                ->mapWithKeys(fn (int $rating): array => [
                                    $rating => $rating.' · '.__("planned_meal.actions.rating_labels.{$rating}"),
                                ])
                                ->all())
                            ->icons(array_fill_keys(range(RecipeRating::MIN, RecipeRating::MAX), Heroicon::OutlinedStar))
                            ->inline()
                            ->nullable()
                            ->in(range(RecipeRating::MIN, RecipeRating::MAX)),
                    ])
                    ->action(function (PlannedMeal $record, array $data): void {
                        $dish = self::revision($record)?->title ?? __('recipe.table.untitled');
                        $rating = filled($data['rating'] ?? null) ? (int) $data['rating'] : null;

                        app(RemovePlannedMeal::class)->handle(Auth::user(), $record, $rating);

                        Notification::make()
                            ->title($rating === null
                                ? __('planned_meal.notifications.removed', ['dish' => $dish])
                                : __('planned_meal.notifications.removed_rated', ['dish' => $dish, 'rating' => $rating]))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('planned_meal.actions.remove'))
                        ->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading(__('planned_meal.table.empty_heading'))
            ->emptyStateDescription(__('planned_meal.table.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }

    private static function revision(PlannedMeal $record): ?RecipeRevision
    {
        return $record->recipe?->revisionFor(Auth::user());
    }

    /** A dish planned by name only, nothing written or linked yet. */
    private static function isJustAName(PlannedMeal $record): bool
    {
        $revision = self::revision($record);

        return $revision !== null
            && $record->recipe->source_url === null
            && (int) $revision->ingredients_count === 0
            && (int) $revision->instruction_steps_count === 0;
    }

    /**
     * Opens the recipe to cook from; a new dish has nothing to read yet, so the household opens
     * it where the writing starts.
     */
    private static function recipeUrl(PlannedMeal $record): ?string
    {
        $revision = self::revision($record);

        if (! $revision) {
            return null;
        }

        return self::isJustAName($record) && $record->recipe->isEditableBy(Auth::user())
            ? RecipeRevisionResource::getUrl('edit', ['record' => $revision])
            : RecipeRevisionResource::getUrl('view', ['record' => $revision]);
    }
}
