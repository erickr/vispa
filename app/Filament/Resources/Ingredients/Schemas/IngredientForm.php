<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('canonical_name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),

            Section::make('Translations')
                ->description('Localized display names for this ingredient.')
                ->schema([
                    Repeater::make('translations')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('locale')
                                ->required()
                                ->maxLength(10)
                                ->placeholder('en, sv, ...'),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['locale'] ?? null)
                        ->addActionLabel('Add translation'),
                ]),
        ]);
    }
}
