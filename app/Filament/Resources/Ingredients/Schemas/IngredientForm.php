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
                    ->label(__('ingredient.fields.canonical_name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),

            Section::make(__('ingredient.translations.heading'))
                ->description(__('ingredient.translations.description'))
                ->schema([
                    Repeater::make('translations')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('locale')
                                ->label(__('ingredient.fields.locale'))
                                ->required()
                                ->maxLength(10)
                                ->placeholder(__('ingredient.fields.locale_placeholder')),
                            TextInput::make('name')
                                ->label(__('ingredient.fields.name'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['locale'] ?? null)
                        ->addActionLabel(__('ingredient.translations.add')),
                ]),
        ]);
    }
}
