<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Actions\ShareRecipeAction;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Filament\Resources\Recipes\Schemas\RecipeForm;
use App\Jobs\ImportRecipe;
use App\Models\RecipeImport;
use App\Models\RecipeRevision;
use App\Support\SupportedLocales;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    /** Set by the form's "import from this link" checkbox, for afterSave(). */
    protected bool $importFromSource = false;

    /** The import afterSave() started, whose status page the save lands on. */
    protected ?RecipeImport $import = null;

    protected function getHeaderActions(): array
    {
        return [
            ShareRecipeAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Checked against the saved recipe, not only the form: the box could have been ticked
        // while it showed and the link changed back since.
        $this->importFromSource = ($data['import_from_source'] ?? false)
            && RecipeForm::offersImport($this->record, trim((string) ($data['source_url'] ?? '')));
        unset($data['import_from_source']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->importFromSource) {
            $this->import = ImportRecipe::start(Auth::user(), $this->record->source_url, $this->revisionToFill());
        }
    }

    /**
     * Where the page goes: the draft the cook is working on, else — the link-only revision
     * being published, which people may already be reading — a new draft of it, as editing a
     * published revision would ask for. A recipe with no revision at all gets its first.
     */
    private function revisionToFill(): RecipeRevision
    {
        $revision = $this->record->displayRevision();

        if ($revision === null) {
            $locale = $this->record->default_locale ?: SupportedLocales::preferred();

            return $this->record->revisions()->create([
                'locale' => $locale,
                'version_number' => 1,
                'status' => 'draft',
                'title' => __('recipe.untitled', locale: $locale),
                'created_by_user_id' => Auth::id(),
            ]);
        }

        return $revision->status === 'draft' ? $revision : $revision->draftFork(Auth::id());
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->import
            ? RecipeResource::getUrl('import', ['import' => $this->import])
            : parent::getRedirectUrl();
    }
}
