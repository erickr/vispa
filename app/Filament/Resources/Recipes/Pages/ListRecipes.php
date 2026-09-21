<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Jobs\ImportRecipe;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Reads the page with Claude on the queue, then opens the new draft.
            Action::make('importFromLink')
                ->label(__('recipe.import.action'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => filled(config('services.anthropic.key')))
                ->modalHeading(__('recipe.import.modal_heading'))
                ->modalDescription(__('recipe.import.modal_description'))
                ->modalSubmitActionLabel(__('recipe.import.submit'))
                ->schema([
                    TextInput::make('url')
                        ->label(__('recipe.fields.source_url'))
                        ->placeholder(__('recipe.fields.source_url_placeholder'))
                        ->url()
                        ->required()
                        ->maxLength(500),
                ])
                ->action(fn (array $data) => $this->redirect(RecipeResource::getUrl('import', [
                    'import' => ImportRecipe::start(Auth::user(), trim($data['url'])),
                ]))),
            CreateAction::make(),
        ];
    }
}
