<?php

namespace App\Filament\Resources\RecipeRevisions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecipeRevisionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('title')->columnSpanFull(),
                TextEntry::make('status')->badge(),
                TextEntry::make('locale')->badge(),
                TextEntry::make('version_number')->label('Version')->prefix('v'),
                TextEntry::make('servings'),
                TextEntry::make('prep_time_minutes')->label('Prep')->suffix(' min'),
                TextEntry::make('cook_time_minutes')->label('Cook')->suffix(' min'),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('notes')->columnSpanFull(),
            ])->columns(3),

            Section::make('Ingredients')->schema([
                RepeatableEntry::make('ingredientGroups')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('title')
                            ->hiddenLabel()
                            ->formatStateUsing(fn (?string $state): string => $state ?: 'Ungrouped'),
                        RepeatableEntry::make('ingredients')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('quantity')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn ($state): ?string => $state === null
                                        ? null
                                        : rtrim(rtrim((string) $state, '0'), '.')),
                                TextEntry::make('unit.code')->hiddenLabel(),
                                TextEntry::make('ingredient.canonical_name')->hiddenLabel(),
                                IconEntry::make('optional')
                                    ->label('Optional')
                                    ->boolean(),
                                TextEntry::make('preparation_note')->hiddenLabel(),
                            ])
                            ->columns(5),
                    ]),
            ]),

            Section::make('Instructions')->schema([
                RepeatableEntry::make('instructionSections')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('title')
                            ->hiddenLabel()
                            ->formatStateUsing(fn (?string $state): string => $state ?: 'Steps'),
                        RepeatableEntry::make('steps')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('instruction_text')->hiddenLabel()->columnSpan(3),
                                TextEntry::make('timer_seconds')->label('Timer')->suffix(' s'),
                            ])
                            ->columns(4),
                    ]),
            ]),
        ]);
    }
}
