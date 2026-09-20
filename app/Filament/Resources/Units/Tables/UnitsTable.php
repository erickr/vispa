<?php

namespace App\Filament\Resources\Units\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('unit.fields.code'))->searchable()->sortable(),
                TextColumn::make('type')
                    ->label(__('unit.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('unit.types.'.$state))
                    ->sortable(),
                TextColumn::make('baseUnit.code')
                    ->label(__('unit.fields.base_unit'))
                    ->placeholder('—'),
                TextColumn::make('factor_to_base')
                    ->label(__('unit.fields.factor_to_base'))
                    ->numeric(decimalPlaces: 6)
                    ->alignRight(),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
