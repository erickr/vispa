<?php

namespace App\Filament\Resources\Recipes\Tables;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
                'household',
            ]))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->disk('public')
                    ->height(52)
                    ->width(52)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
                    ->state(fn (Recipe $record): ?string => self::revision($record)?->coverImage()?->path),

                TextColumn::make('title')
                    ->label(__('recipe.table.recipe'))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->state(fn (Recipe $record): string => self::revision($record)?->title ?? __('recipe.table.untitled'))
                    ->description(fn (Recipe $record): string => self::summary($record))
                    // The title lives on the revision, so search has to reach through the relation
                    // — and, for a saved recipe, only as far as its published revisions: the
                    // owners' drafts are not the searcher's to find.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('revisions', fn (Builder $revisions) => $revisions
                            ->where('title', 'like', "%{$search}%")
                            ->where(fn (Builder $revisions) => $revisions
                                ->where('status', 'published')
                                ->orWhereHas('recipe', fn (Builder $recipes) => $recipes->accessibleTo(Auth::user()))))
                        ->orWhere('source_url', 'like', "%{$search}%")),

                TextColumn::make('status')
                    ->label(__('revision.fields.status'))
                    ->badge()
                    // A saved recipe is always a published one; what matters is that it is not
                    // yours to change.
                    ->state(fn (Recipe $record): ?string => self::isSaved($record)
                        ? __('recipe.saved.badge')
                        : RecipeRevision::statusWord($record->displayRevision()?->status))
                    ->color(fn (Recipe $record): string => self::isSaved($record) ? 'info' : match ($record->displayRevision()?->status) {
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

                // Everything listed is from one of the user's households; which one only matters
                // to someone in several.
                TextColumn::make('household.name')
                    ->label(__('household.fields.household'))
                    // A saved recipe's household is its owners', which is not the reader's business.
                    ->state(fn (Recipe $record): ?string => self::isSaved($record) ? null : $record->household?->name)
                    ->visible(fn (): bool => self::inSeveralHouseholds())
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
                SelectFilter::make('household_id')
                    ->label(__('household.fields.household'))
                    ->options(fn (): array => Auth::user()?->allTeams()->pluck('name', 'id')->all() ?? [])
                    // A household's recipes are the ones it has and the ones it saved.
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where(fn (Builder $query) => $query
                            ->where('household_id', $data['value'])
                            ->orWhereHas('savedByHouseholds', fn (Builder $households) => $households->whereKey($data['value']))),
                    ))
                    ->visible(fn (): bool => self::inSeveralHouseholds()),
                SelectFilter::make('visibility')
                    ->label(__('recipe.fields.visibility'))
                    ->options([
                        'private' => __('recipe.visibility.private'),
                        'unlisted' => __('recipe.visibility.unlisted'),
                        'public' => __('recipe.visibility.public'),
                    ]),
                Filter::make('saved_from_others')
                    ->label(__('recipe.table.only_saved_from_others'))
                    ->query(fn (Builder $query): Builder => $query->savedBy(Auth::user())),
                Filter::make('saved_links')
                    ->label(__('recipe.table.only_saved_links'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('source_url')),
            ])
            ->defaultSort('updated_at', 'desc')
            // The row is the only thing to click: it opens the recipe to read, and everything
            // else — editing, sharing, settings — is on that page. A recipe with no revision has
            // nothing to read yet, so it opens where the writing starts.
            ->recordUrl(fn (Recipe $record): ?string => self::viewUrl($record) ?? self::editContentUrl($record))
            ->emptyStateHeading(__('recipe.table.empty_heading'))
            ->emptyStateDescription(__('recipe.table.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedBookOpen)
            ->toolbarActions([
                BulkActionGroup::make([
                    // Saved recipes are someone else's; RecipePolicy turns those away one by one.
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    /**
     * The line under the name. A written-out recipe counts what it has; a saved link says so
     * plainly rather than reporting three zeroes.
     */
    private static function summary(Recipe $record): string
    {
        $revision = self::revision($record);

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

    private static function inSeveralHouseholds(): bool
    {
        return (Auth::user()?->allTeams()->count() ?? 0) > 1;
    }

    /**
     * The revision a row stands for: the household's newest work, or for a saved recipe the
     * published version in the reader's language.
     */
    private static function revision(Recipe $record): ?RecipeRevision
    {
        return $record->revisionFor(Auth::user());
    }

    private static function isSaved(Recipe $record): bool
    {
        return ! $record->isEditableBy(Auth::user());
    }

    private static function viewUrl(Recipe $record): ?string
    {
        $revision = self::revision($record);

        return $revision
            ? RecipeRevisionResource::getUrl('view', ['record' => $revision])
            : null;
    }

    private static function editContentUrl(Recipe $record): ?string
    {
        if (self::isSaved($record)) {
            return null;
        }

        $revision = $record->revisions->sortByDesc('version_number')->first();

        return $revision
            ? RecipeRevisionResource::getUrl('edit', ['record' => $revision])
            : null;
    }
}
