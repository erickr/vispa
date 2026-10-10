<?php

namespace App\Filament\Resources\RecipeRevisions\Pages;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Jobs\ImportRecipe;
use App\Models\RecipeRevision;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
            $this->addLinkAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * A dish planned by name opens here, with nowhere to say where the recipe is. Adding the link
     * while the revision is still empty offers to read the page into it, as the recipe's settings
     * page does for a new link.
     */
    protected function addLinkAction(): Action
    {
        return Action::make('addLink')
            ->label(__('revision.source.add'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn (): bool => blank($this->record->recipe->source_url)
                && Auth::user()->can('update', $this->record->recipe))
            ->modalHeading(__('revision.source.add_heading'))
            ->modalSubmitActionLabel(__('revision.source.add_submit'))
            ->schema([
                TextInput::make('source_url')
                    ->label(__('recipe.fields.source_url'))
                    ->required()
                    ->url()
                    // The plain url rule also takes javascript:, data:, ftp: and more.
                    ->rule('url:http,https')
                    ->validationMessages(['url' => __('recipe.fields.source_url_invalid')])
                    ->maxLength(500)
                    ->placeholder(__('recipe.fields.source_url_placeholder')),

                Checkbox::make('import_from_source')
                    ->label(__('recipe.fields.import_from_source'))
                    ->helperText(__('recipe.fields.import_from_source_helper'))
                    ->default(true)
                    ->visible(fn (): bool => $this->offersImport()),
            ])
            ->action(function (array $data): void {
                $recipe = $this->record->recipe;
                Gate::authorize('update', $recipe);

                // Checked again on submit: the box may have shown before someone filled the recipe in.
                $import = ($data['import_from_source'] ?? false) && $this->offersImport();

                // What was typed in the editor goes with the link rather than being lost to the
                // status page.
                if ($import) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                }

                $recipe->update(['source_url' => trim($data['source_url'])]);

                if (! $import) {
                    Notification::make()->title(__('revision.notifications.link_added'))->success()->send();

                    return;
                }

                // A closure, as a published revision is forked first: over the import limit, the
                // link is saved and nothing else changes.
                $started = ImportRecipe::startOrNotify(Auth::user(), $recipe->source_url, fn (): RecipeRevision => $this->record->status === 'draft'
                    ? $this->record
                    : $this->record->draftFork(Auth::id()));

                if ($started) {
                    $this->redirect(RecipeResource::getUrl('import', ['import' => $started]));
                }
            });
    }

    /** Whether there is a model to read with and nothing written yet for the page to land on. */
    private function offersImport(): bool
    {
        return filled(config('services.anthropic.key')) && $this->record->isLinkOnly();
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
