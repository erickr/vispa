<?php

namespace App\Filament\Resources\Recipes\Tables;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Models\Recipe;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('uuid')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('default_locale')->label('Locale')->badge(),
                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('revisions_count')
                    ->counts('revisions')
                    ->label('Revisions')
                    ->alignRight(),
                TextColumn::make('updated_at')->dateTime()->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('visibility')->options([
                    'private' => 'Private',
                    'unlisted' => 'Unlisted',
                    'public' => 'Public',
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('editContent')
                    ->label('Edit content')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(function (Recipe $record): ?string {
                        $revision = $record->revisions()->orderByDesc('version_number')->first();

                        return $revision
                            ? RecipeRevisionResource::getUrl('edit', ['record' => $revision])
                            : null;
                    })
                    ->visible(fn (Recipe $record): bool => $record->revisions()->exists()),
                EditAction::make()->label('Settings'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
