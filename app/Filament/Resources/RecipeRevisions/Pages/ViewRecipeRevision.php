<?php

namespace App\Filament\Resources\RecipeRevisions\Pages;

use App\Actions\Recipes\ForkRecipeRevision;
use App\Filament\Actions\ShareRecipeAction;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\RecipeRevisions\Schemas\RecipeRevisionInfolist;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewRecipeRevision extends ViewRecord
{
    protected static string $resource = RecipeRevisionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return RecipeRevisionInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('revision.actions.edit_content'))
                ->visible(fn (): bool => $this->canChange())
                ->url(fn (): string => RecipeRevisionResource::getUrl('edit', ['record' => $this->record])),
            ShareRecipeAction::make()
                ->visible(fn (): bool => $this->canChange()),
            // The recipe's own settings — visibility, source link, slugs — live a layer up.
            Action::make('settings')
                ->label(__('recipe.actions.settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->visible(fn (): bool => $this->canChange())
                ->url(fn (): string => RecipeResource::getUrl('edit', ['record' => $this->record->recipe_id])),

            // A saved recipe is read-only; changing it means a recipe of your own that starts
            // as a copy of this version and remembers where it came from.
            Action::make('fork')
                ->label(__('recipe.saved.fork'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->visible(fn (): bool => ! $this->canChange())
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
                ->modalHeading(__('recipe.saved.fork_heading'))
                ->modalDescription(__('recipe.saved.fork_description'))
                ->modalSubmitActionLabel(__('recipe.saved.fork_submit'))
                ->action(function (ForkRecipeRevision $fork): void {
                    $draft = $fork->handle($this->record, Auth::user());

                    Notification::make()
                        ->title(__('recipe.saved.forked.title'))
                        ->body(__('recipe.saved.forked.body'))
                        ->success()
                        ->send();

                    $this->redirect(RecipeRevisionResource::getUrl('edit', ['record' => $draft]));
                }),
            Action::make('unsave')
                ->label(__('recipe.saved.remove'))
                ->icon(Heroicon::OutlinedBookmarkSlash)
                ->color('gray')
                ->visible(fn (): bool => ! $this->canChange())
                ->requiresConfirmation()
                ->modalHeading(__('recipe.saved.remove_heading'))
                ->modalDescription(__('recipe.saved.remove_description'))
                ->modalSubmitActionLabel(__('recipe.saved.remove_submit'))
                ->action(function (): void {
                    // From every household of the reader's that kept it: they all list it alike.
                    $this->record->recipe->savedByHouseholds()->detach(Auth::user()->allTeams()->modelKeys());

                    Notification::make()->title(__('recipe.saved.removed'))->success()->send();

                    $this->redirect(RecipeResource::getUrl('index'));
                }),
        ];
    }

    private function canChange(): bool
    {
        return Auth::user()?->can('update', $this->record) ?? false;
    }

    public function getBreadcrumb(): string
    {
        return __('revision.breadcrumb', [
            'locale' => $this->record->locale,
            'version' => $this->record->version_number,
        ]);
    }

    /**
     * This sub-resource has no index page; anchor navigation on the parent recipe instead — or,
     * for a saved recipe, whose settings are not the reader's, on the recipes list.
     */
    public function getBreadcrumbs(): array
    {
        return [
            ($this->canChange()
                ? RecipeResource::getUrl('edit', ['record' => $this->record->recipe_id])
                : RecipeResource::getUrl('index')) => __('recipe.breadcrumb'),
            $this->getBreadcrumb(),
        ];
    }
}
