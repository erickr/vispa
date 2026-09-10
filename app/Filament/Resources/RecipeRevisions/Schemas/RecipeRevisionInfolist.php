<?php

namespace App\Filament\Resources\RecipeRevisions\Schemas;

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

class RecipeRevisionInfolist
{
    private const STATUS_WORDS = [
        'published' => 'Ready',
        'draft' => 'Still testing',
        'archived' => 'Put away',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // Where it came from, first — for a saved link this is the recipe.
            Callout::make(fn (RecipeRevision $record): string => $record->recipe?->sourceHost() ?? 'Saved link')
                ->description(fn (RecipeRevision $record): string => $record->source_credit
                    ?: 'The original lives on the web.')
                ->icon(Heroicon::OutlinedLink)
                ->color('info')
                ->visible(fn (RecipeRevision $record): bool => filled($record->recipe?->source_url))
                ->footerActions([
                    Action::make('openSource')
                        ->label('Open the original')
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
                            ->placeholder('No description yet.')
                            ->columnSpan(fn (RecipeRevision $record): int => $record->coverImage() ? 2 : 3),
                    ]),

                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => self::STATUS_WORDS[$state] ?? $state)
                            ->color(fn (string $state): string => match ($state) {
                                'published' => 'success',
                                'draft' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('servings')->placeholder('—'),
                        TextEntry::make('prep_time_minutes')->label('Hands on')->suffix(' min')->placeholder('—'),
                        TextEntry::make('cook_time_minutes')->label('Cooking')->suffix(' min')->placeholder('—'),
                    ]),

                    TextEntry::make('notes')
                        ->placeholder('—')
                        ->visible(fn (RecipeRevision $record): bool => filled($record->notes))
                        ->columnSpanFull(),
                ]),

            // Nothing written down at all: say so once rather than showing two empty sections.
            EmptyState::make('Saved as a link, and that is plenty')
                ->description('No ingredients or steps have been written down for this one.')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (RecipeRevision $record): bool => $record->isLinkOnly()),

            Section::make('What you need')
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
                                ->formatStateUsing(fn (?string $state): string => $state ?: 'Ungrouped'),

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

            Section::make('How to make it')
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
                                ->formatStateUsing(fn (?string $state): string => $state ?: 'Steps'),

                            RepeatableEntry::make('steps')
                                ->hiddenLabel()
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('instruction_text')
                                        // Filament writes sort_order 1-based and rewrites it on
                                        // reorder, so it is the step number the cook reads.
                                        ->label(fn (RecipeRevisionInstructionStep $record): string => 'Step '.$record->sort_order)
                                        ->size(TextSize::Medium),

                                    TextEntry::make('timer_seconds')
                                        ->label('Timer')
                                        ->suffix(' s')
                                        ->visible(fn (RecipeRevisionInstructionStep $record): bool => filled($record->timer_seconds)),
                                ]),
                        ]),
                ]),

            Section::make('Photos')
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
                                ->placeholder('No description'),
                        ]),
                ]),

            Section::make('Revision')
                ->collapsed()
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('locale')->badge(),
                        TextEntry::make('version_number')->label('Version')->prefix('v'),
                        TextEntry::make('published_at')->dateTime()->placeholder('Not published'),
                    ]),
                ]),
        ])->columns(1);
    }
}
