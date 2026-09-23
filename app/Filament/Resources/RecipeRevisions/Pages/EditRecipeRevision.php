<?php

namespace App\Filament\Resources\RecipeRevisions\Pages;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\RecipeRevision;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditRecipeRevision extends EditRecord
{
    protected static string $resource = RecipeRevisionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // A published revision is a snapshot people may already be reading, so what to do with it
        // is the cook's call, not ours: a new revision, this one as it stands, or neither. Asked
        // here rather than on the button that led here, so every way in — the recipe page, the
        // revisions repeater, a bookmarked URL — goes through the same question.
        if ($this->record->status === 'published') {
            $this->mountAction('revisionChoice');
        }
    }

    public function revisionChoiceAction(): Action
    {
        return Action::make('revisionChoice')
            ->modalHeading(__('revision.published_choice.heading'))
            ->modalDescription(__('revision.published_choice.description'))
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalIconColor('warning')
            // No way out but a deliberate one: closing this modal would leave the published
            // revision open in the form, which is the one thing nobody asked for.
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCancelAction(false)
            ->modalSubmitActionLabel(__('revision.published_choice.new_revision'))
            ->extraModalFooterActions([
                Action::make('editInPlace')
                    ->label(__('revision.published_choice.edit_in_place'))
                    ->color('gray')
                    ->cancelParentActions()
                    ->action(fn () => null),
                Action::make('leaveItAlone')
                    ->label(__('revision.published_choice.cancel'))
                    ->color('gray')
                    ->link()
                    ->action(fn () => $this->redirect(
                        static::getResource()::getUrl('view', ['record' => $this->record]),
                    )),
            ])
            ->action(function (): void {
                // An open draft for this locale is the new revision — a second one would only
                // split the work in two.
                $draft = RecipeRevision::query()
                    ->where('recipe_id', $this->record->recipe_id)
                    ->where('locale', $this->record->locale)
                    ->where('status', 'draft')
                    ->orderByDesc('version_number')
                    ->first()
                    ?? $this->record->draftFork(Auth::id());

                Notification::make()
                    ->title(__('revision.notifications.draft_copy.title'))
                    ->body(__('revision.notifications.draft_copy.body', ['version' => $draft->version_number]))
                    ->info()
                    ->send();

                $this->redirect(static::getResource()::getUrl('edit', ['record' => $draft]));
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getBreadcrumb(): string
    {
        return __('revision.breadcrumb', [
            'locale' => $this->record->locale,
            'version' => $this->record->version_number,
        ]);
    }

    /**
     * This sub-resource has no index page; anchor navigation on the parent recipe instead.
     */
    public function getBreadcrumbs(): array
    {
        return [
            RecipeResource::getUrl('edit', ['record' => $this->record->recipe_id]) => __('recipe.breadcrumb'),
            $this->getBreadcrumb(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return RecipeResource::getUrl('edit', ['record' => $this->record->recipe_id]);
    }
}
