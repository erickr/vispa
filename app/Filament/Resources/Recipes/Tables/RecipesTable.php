<?php

namespace App\Filament\Resources\Recipes\Tables;

use App\Filament\Actions\ShareRecipeAction;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // A recipe row is really a summary of its display revision: title, status, counts and
            // cover all come from there, so load the graph once instead of per column.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'revisions' => fn ($q) => $q->withCount(['ingredients', 'instructionSteps']),
                'revisions.images',
            ]))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->disk('public')
                    ->height(52)
                    ->width(52)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
                    ->state(fn (Recipe $record): ?string => $record->displayRevision()?->coverImage()?->path),

                TextColumn::make('title')
                    ->label(__('recipe.table.recipe'))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->state(fn (Recipe $record): string => $record->displayRevision()?->title ?? __('recipe.table.untitled'))
                    ->description(fn (Recipe $record): string => self::summary($record))
                    // The title lives on the revision, so search has to reach through the relation.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('revisions', fn (Builder $revisions) => $revisions->where('title', 'like', "%{$search}%"))
                        ->orWhere('source_url', 'like', "%{$search}%")),

                TextColumn::make('status')
                    ->label(__('revision.fields.status'))
                    ->badge()
                    ->state(fn (Recipe $record): ?string => RecipeRevision::statusWord($record->displayRevision()?->status))
                    ->color(fn (Recipe $record): string => match ($record->displayRevision()?->status) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder(__('recipe.table.not_started')),

                TextColumn::make('source_url')
                    ->label(__('recipe.table.from'))
                    ->formatStateUsing(fn (Recipe $record): string => $record->sourceHost() ?? '—')
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab()
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('visibility')
                    ->label(__('recipe.fields.visibility'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('recipe.visibility.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('updated_at')->label(__('recipe.table.touched'))->dateTime()->since()->sortable(),

                TextColumn::make('default_locale')->label(__('recipe.table.locale'))->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('revisions_count')->counts('revisions')->label(__('recipe.table.revisions'))->alignRight()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('uuid')->label(__('recipe.fields.uuid'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->label(__('recipe.fields.visibility'))
                    ->options([
                        'private' => __('recipe.visibility.private'),
                        'unlisted' => __('recipe.visibility.unlisted'),
                        'public' => __('recipe.visibility.public'),
                    ]),
                Filter::make('saved_links')
                    ->label(__('recipe.table.only_saved_links'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('source_url')),
            ])
            ->defaultSort('updated_at', 'desc')
            // Clicking the row opens the recipe, the way clicking its name does in the mockup.
            ->recordUrl(fn (Recipe $record): ?string => self::editContentUrl($record))
            ->emptyStateHeading(__('recipe.table.empty_heading'))
            ->emptyStateDescription(__('recipe.table.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedBookOpen)
            ->recordActions([
                Action::make('view')
                    ->label(__('recipe.actions.view'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(function (Recipe $record): ?string {
                        $revision = $record->displayRevision();

                        return $revision
                            ? RecipeRevisionResource::getUrl('view', ['record' => $revision])
                            : null;
                    })
                    ->visible(fn (Recipe $record): bool => $record->displayRevision() !== null),
                Action::make('editContent')
                    ->label(__('recipe.actions.edit_content'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Recipe $record): ?string => self::editContentUrl($record))
                    ->visible(fn (Recipe $record): bool => self::editContentUrl($record) !== null),
                ShareRecipeAction::make(),
                EditAction::make()->label(__('recipe.actions.settings')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The line under the name. A written-out recipe counts what it has; a saved link says so
     * plainly rather than reporting three zeroes.
     */
    private static function summary(Recipe $record): string
    {
        $revision = $record->displayRevision();

        if (! $revision) {
            return __('recipe.summary.nothing_yet');
        }

        $ingredients = (int) ($revision->ingredients_count ?? $revision->ingredients()->count());
        $steps = (int) ($revision->instruction_steps_count ?? $revision->instructionSteps()->count());
        $photos = $revision->images->count();

        if ($ingredients === 0 && $steps === 0) {
            return $record->sourceHost()
                ? __('recipe.summary.saved_from', ['host' => $record->sourceHost()])
                : __('recipe.summary.nothing_yet');
        }

        return implode(' · ', array_filter([
            $revision->servings ? trans_choice('recipe.summary.servings', $revision->servings) : null,
            trans_choice('recipe.summary.ingredients', $ingredients),
            trans_choice('recipe.summary.steps', $steps),
            $photos ? trans_choice('recipe.summary.photos', $photos) : __('recipe.summary.no_photos'),
        ]));
    }

    private static function editContentUrl(Recipe $record): ?string
    {
        $revision = $record->revisions->sortByDesc('version_number')->first();

        return $revision
            ? RecipeRevisionResource::getUrl('edit', ['record' => $revision])
            : null;
    }
}
