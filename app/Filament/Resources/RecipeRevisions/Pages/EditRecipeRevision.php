<?php

namespace App\Filament\Resources\RecipeRevisions\Pages;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\RecipeRevision;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditRecipeRevision extends EditRecord
{
    protected static string $resource = RecipeRevisionResource::class;

    public function mount(int|string $record): void
    {
        $revision = RecipeRevision::findOrFail($record);

        // Append-only: never edit a published revision in place. Redirect to a draft fork —
        // reusing an existing open draft for this locale if one exists, otherwise cloning.
        if ($revision->status === 'published') {
            $draft = RecipeRevision::query()
                ->where('recipe_id', $revision->recipe_id)
                ->where('locale', $revision->locale)
                ->where('status', 'draft')
                ->orderByDesc('version_number')
                ->first()
                ?? $revision->draftFork(Auth::id());

            Notification::make()
                ->title(__('revision.notifications.draft_copy.title'))
                ->body(__('revision.notifications.draft_copy.body', ['version' => $draft->version_number]))
                ->info()
                ->send();

            $this->redirect(static::getResource()::getUrl('edit', ['record' => $draft]));

            return;
        }

        parent::mount($record);
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
