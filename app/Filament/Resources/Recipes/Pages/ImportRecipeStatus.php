<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Jobs\ImportRecipe;
use App\Models\RecipeImport;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Shown while a recipe import runs on the queue. Polls the import row and opens the new draft
 * in the editor as soon as it's written.
 */
class ImportRecipeStatus extends Page
{
    protected static string $resource = RecipeResource::class;

    protected string $view = 'filament.resources.recipes.pages.import-recipe-status';

    public RecipeImport $import;

    public function mount(RecipeImport $import): void
    {
        abort_unless($import->user_id === Auth::id(), 403);

        $this->import = $import;
        $this->openWhenDone();
    }

    public function getTitle(): string|Htmlable
    {
        return __('recipe.import.heading');
    }

    /** Called by wire:poll until the import finishes. */
    public function check(): void
    {
        $this->import->refresh();
        $this->openWhenDone();
    }

    public function retry(): void
    {
        abort_unless($this->import->status === RecipeImport::STATUS_FAILED, 400);

        $this->redirect(RecipeResource::getUrl('import', [
            'import' => ImportRecipe::start(Auth::user(), $this->import->source_url),
        ]));
    }

    private function openWhenDone(): void
    {
        if ($this->import->status === RecipeImport::STATUS_DONE && $this->import->recipe_revision_id) {
            $this->redirect(RecipeRevisionResource::getUrl('edit', ['record' => $this->import->recipe_revision_id]));
        }
    }
}
