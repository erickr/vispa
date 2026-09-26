<?php

namespace App\Filament\Resources\RecipeRevisions\Schemas;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\RecipeRevision;
use App\Models\RecipeRevisionIngredient;
use App\Models\RecipeRevisionInstructionStep;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RecipeRevisionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // Saved from another household: theirs to change, the reader's to cook from.
            Callout::make(__('recipe.saved.callout_heading'))
                ->description(__('recipe.saved.callout_description'))
                ->icon(Heroicon::OutlinedBookmark)
                ->color('info')
                ->visible(fn (RecipeRevision $record): bool => ! self::canChange($record))
                ->columnSpanFull(),

            // A version of someone else's recipe: the way back to what it started from, while
            // the reader can still open that.
            Callout::make(fn (RecipeRevision $record): string => __('recipe.saved.based_on', [
                'title' => $record->recipe->forkedFromRevision?->title ?? '',
            ]))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->visible(fn (RecipeRevision $record): bool => self::originalFor($record) !== null)
                ->footerActions([
                    Action::make('openOriginal')
                        ->label(__('recipe.saved.open_original'))
                        ->link()
                        ->url(fn (RecipeRevision $record): ?string => ($original = self::originalFor($record))
                            ? RecipeRevisionResource::getUrl('view', ['record' => $original])
                            : null),
                ])
                ->columnSpanFull(),

            // Where it came from, first — for a saved link this is the recipe.
            Callout::make(fn (RecipeRevision $record): string => $record->recipe?->sourceHost() ?? __('revision.source.saved_link'))
                ->description(fn (RecipeRevision $record): string => $record->source_credit
                    ?: __('revision.source.description_short'))
                ->icon(Heroicon::OutlinedLink)
                ->color('info')
                ->visible(fn (RecipeRevision $record): bool => filled($record->recipe?->source_url))
                ->footerActions([
                    Action::make('openSource')
                        ->label(__('revision.source.open'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(fn (RecipeRevision $record): ?string => $record->recipe?->source_url)
                        ->openUrlInNewTab(),
                ])
                ->columnSpanFull(),

            // The cover leads, with the description beside it — a recipe page, not a field dump.
            Section::make()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        ImageEntry::make('cover')
                            ->hiddenLabel()
                            ->disk('public')
                            ->height(190)
                            ->extraImgAttributes(['class' => 'rounded-xl object-cover w-full'])
                            ->state(fn (RecipeRevision $record): ?string => $record->coverImage()?->path)
                            ->visible(fn (RecipeRevision $record): bool => $record->coverImage() !== null),

                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->placeholder(__('revision.infolist.no_description'))
                            ->columnSpan(fn (RecipeRevision $record): int => $record->coverImage() ? 2 : 3),
                    ]),

                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('status')
                            ->label(__('revision.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => RecipeRevision::statusWord($state))
                            ->color(fn (string $state): string => match ($state) {
                                'published' => 'success',
                                'draft' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('servings')->label(__('revision.fields.servings'))->placeholder('—'),
                        TextEntry::make('prep_time_minutes')->label(__('revision.infolist.hands_on'))->suffix(' min')->placeholder('—'),
                        TextEntry::make('cook_time_minutes')->label(__('revision.infolist.cooking'))->suffix(' min')->placeholder('—'),
                    ]),

                    TextEntry::make('notes')
                        ->label(__('revision.fields.notes'))
                        ->placeholder('—')
                        ->visible(fn (RecipeRevision $record): bool => filled($record->notes))
                        ->columnSpanFull(),
                ]),

            // Nothing written down at all: say so once rather than showing two empty sections.
            EmptyState::make(__('revision.infolist.link_only_heading'))
                ->description(__('revision.infolist.link_only_description'))
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (RecipeRevision $record): bool => $record->isLinkOnly()),

            Section::make(__('revision.infolist.ingredients_heading'))
                ->visible(fn (RecipeRevision $record): bool => $record->ingredients()->exists())
                ->schema([
                    RepeatableEntry::make('ingredientGroups')
                        ->hiddenLabel()
                        ->contained(false)
                        ->schema([
                            TextEntry::make('title')
                                ->hiddenLabel()
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->formatStateUsing(fn (?string $state): string => $state ?: __('revision.items.ungrouped')),

                            // One line per ingredient, read as prose rather than laid out in columns.
                            RepeatableEntry::make('ingredients')
                                ->hiddenLabel()
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('line')
                                        ->hiddenLabel()
                                        ->state(fn (RecipeRevisionIngredient $record): string => $record->line()),
                                ]),
                        ]),
                ]),

            Section::make(__('revision.infolist.instructions_heading'))
                ->visible(fn (RecipeRevision $record): bool => $record->instructionSteps()->exists())
                ->schema([
                    RepeatableEntry::make('instructionSections')
                        ->hiddenLabel()
                        ->contained(false)
                        ->schema([
                            TextEntry::make('title')
                                ->hiddenLabel()
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->formatStateUsing(fn (?string $state): string => $state ?: __('revision.items.steps')),

                            RepeatableEntry::make('steps')
                                ->hiddenLabel()
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('instruction_text')
                                        // Filament writes sort_order 1-based and rewrites it on
                                        // reorder, so it is the step number the cook reads.
                                        ->label(fn (RecipeRevisionInstructionStep $record): string => __('revision.infolist.step_number', ['number' => $record->sort_order]))
                                        ->size(TextSize::Medium),

                                    TextEntry::make('timer_seconds')
                                        ->label(__('revision.infolist.timer'))
                                        ->suffix(' s')
                                        ->visible(fn (RecipeRevisionInstructionStep $record): bool => filled($record->timer_seconds)),
                                ]),
                        ]),
                ]),

            Section::make(__('revision.infolist.photos_heading'))
                ->visible(fn (RecipeRevision $record): bool => $record->images()->exists())
                ->schema([
                    RepeatableEntry::make('images')
                        ->hiddenLabel()
                        ->contained(false)
                        ->grid(['default' => 2, 'md' => 4])
                        ->schema([
                            ImageEntry::make('path')
                                ->hiddenLabel()
                                ->disk('public')
                                ->height(120)
                                ->extraImgAttributes(['class' => 'rounded-lg object-cover w-full']),
                            TextEntry::make('alt_text')
                                ->hiddenLabel()
                                ->size(TextSize::ExtraSmall)
                                ->color('gray')
                                ->placeholder(__('revision.infolist.no_photo_description')),
                        ]),
                ]),

            Section::make(__('revision.infolist.revision_heading'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('locale')->label(__('revision.fields.locale'))->badge(),
                        TextEntry::make('version_number')->label(__('revision.fields.version'))->prefix('v'),
                        TextEntry::make('published_at')->label(__('revision.fields.published_at'))->dateTime()->placeholder(__('revision.infolist.not_published')),
                    ]),

                    // Every other version of this recipe, in every language it is written in —
                    // the way back to the published one after reading a draft, and the way across
                    // to the Swedish version of an English page.
                    TextEntry::make('siblings')
                        ->label(__('revision.infolist.other_versions'))
                        ->state(fn (RecipeRevision $record): HtmlString => new HtmlString(view('filament.revisions.links', [
                            'current' => $record,
                            'revisions' => $record->recipe
                                ->revisions()
                                // Someone who only saved it sees what its owners published.
                                ->when(! self::canChange($record), fn ($query) => $query->where('status', 'published'))
                                ->orderByRaw('(locale = ?) desc', [$record->locale])
                                ->orderBy('locale')
                                ->orderByDesc('version_number')
                                ->get(),
                        ])->render()))
                        ->columnSpanFull(),
                ]),
        ])->columns(1);
    }

    private static function canChange(RecipeRevision $record): bool
    {
        return Auth::user()?->can('update', $record) ?? false;
    }

    /**
     * The revision this recipe was forked from, if the reader may open it: newer published
     * versions are not followed — the fork started from this one.
     */
    private static function originalFor(RecipeRevision $record): ?RecipeRevision
    {
        $original = $record->recipe->forkedFromRevision;

        return $original && Auth::user()?->can('view', $original) ? $original : null;
    }
}
