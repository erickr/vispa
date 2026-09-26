<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Support\SupportedLocales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('code')
                    ->label(__('unit.fields.code'))
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                Select::make('type')
                    ->label(__('unit.fields.type'))
                    ->options([
                        'mass' => __('unit.types.mass'),
                        'volume' => __('unit.types.volume'),
                        'count' => __('unit.types.count'),
                    ])
                    ->required()
                    ->native(false),

                Select::make('base_unit_id')
                    ->label(__('unit.fields.base_unit'))
                    ->relationship(
                        name: 'baseUnit',
                        titleAttribute: 'code',
                        modifyQueryUsing: fn ($query, $record) => $record
                            ? $query->where('id', '!=', $record->id)
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('factor_to_base')
                    ->label(__('unit.fields.factor_to_base'))
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->step('0.000001'),
            ])->columns(2),

            // One per supported locale, all required: a unit shows up on every recipe line.
            Section::make(__('unit.translations.heading'))
                ->description(__('unit.translations.description'))
                ->schema([
                    Repeater::make('translations')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('locale')
                                ->label(__('unit.fields.locale'))
                                ->options(fn (?Model $record): array => SupportedLocales::optionsIncluding($record?->locale))
                                ->required()
                                ->distinct()
                                ->selectablePlaceholder(false)
                                ->native(false),
                            TextInput::make('name')
                                ->label(__('unit.fields.name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('abbreviation')
                                ->label(__('unit.fields.abbreviation'))
                                ->helperText(__('unit.fields.abbreviation_help'))
                                ->required()
                                ->maxLength(50),
                        ])
                        ->columns(3)
                        ->default(fn (): array => array_map(fn (string $locale): array => ['locale' => $locale], SupportedLocales::codes()))
                        ->minItems(count(SupportedLocales::codes()))
                        ->itemLabel(fn (array $state): ?string => SupportedLocales::options()[$state['locale'] ?? ''] ?? $state['locale'] ?? null)
                        ->addActionLabel(__('unit.translations.add')),
                ]),
        ]);
    }
}
