<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                Select::make('type')
                    ->options([
                        'mass' => 'Mass',
                        'volume' => 'Volume',
                        'count' => 'Count',
                    ])
                    ->required()
                    ->native(false),

                Select::make('base_unit_id')
                    ->label('Base unit')
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
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->step('0.000001'),
            ])->columns(2),
        ]);
    }
}
