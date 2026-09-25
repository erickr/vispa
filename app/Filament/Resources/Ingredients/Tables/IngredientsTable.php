<?php

namespace App\Filament\Resources\Ingredients\Tables;

use App\Models\Ingredient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class IngredientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner'))
            ->columns([
                TextColumn::make('canonical_name')
                    ->label(__('ingredient.fields.canonical_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner_user_id')
                    ->label(__('ingredient.fields.visibility'))
                    ->badge()
                    ->state(fn (Ingredient $record): string => match (true) {
                        $record->isShared() => 'shared',
                        $record->owner_user_id === Auth::id() => 'private',
                        default => 'household',
                    })
                    // A household member's ingredient says whose it is.
                    ->description(fn (Ingredient $record): ?string => $record->isShared() || $record->owner_user_id === Auth::id()
                        ? null
                        : $record->owner?->name)
                    ->formatStateUsing(fn (string $state): string => __('ingredient.visibility.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'shared' => 'gray',
                        'household' => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('translations_count')
                    ->counts('translations')
                    ->label(__('ingredient.translations.count'))
                    ->alignRight(),
                TextColumn::make('created_at')
                    ->label(__('ingredient.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('canonical_name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Shared rows sit in everyone's list; only delete the ones this user may.
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
