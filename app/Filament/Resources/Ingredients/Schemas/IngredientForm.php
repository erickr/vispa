<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use App\Support\SupportedLocales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
                            Select::make('locale')
                                ->label(__('ingredient.fields.locale'))
                                ->options(fn (?Model $record): array => SupportedLocales::optionsIncluding($record?->locale))
                                ->default(fn (): string => SupportedLocales::preferred())
                                ->required()
                                ->selectablePlaceholder(false)
                                ->native(false),
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
